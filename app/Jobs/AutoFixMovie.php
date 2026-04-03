<?php

namespace App\Jobs;

use App\Models\MovieModel;
use App\Models\VideoPlaybackFailure;
use App\Services\MovieFixerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AutoFixMovie — Attempts to auto-fix a movie when a playback failure is reported.
 *
 * Implements ShouldQueue so the fix is handled by the queue worker instead of
 * running in the HTTP process. The static scheduleAfterResponse() helper
 * dispatches the job to the queue immediately (no longer uses app()->terminating()).
 *
 * Safety measures:
 *  - Cooldown: skips if the same movie was already attempted within the last 5 minutes
 *  - Re-entry guard: static flag prevents recursive triggers in the same worker process
 *  - Try/catch: never breaks failure creation regardless of fix outcome
 *  - Only fires for movies sourced from munowatch (has page_source_url or munowatch_id)
 */
class AutoFixMovie implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before giving up.
     */
    public int $tries = 2;

    /**
     * Seconds to wait before retrying after a failure.
     */
    public int $backoff = 60;
    /**
     * Prevents re-entry when the fixer updates failure records
     * (which could trigger another model event if not guarded).
     */
    protected static bool $inProgress = false;

    /**
     * Cache key prefix for cooldown locks.
     */
    protected const COOLDOWN_PREFIX = 'auto_fix_movie_';

    /**
     * Cooldown in seconds — skip if same movie was attempted recently.
     */
    protected const COOLDOWN_SECONDS = 300; // 5 minutes

    /**
     * Create a new queued auto-fix job instance.
     */
    public function __construct(
        public readonly int $movieId,
        public readonly int $failureId,
    ) {}

    /**
     * Execute the queued job.
     */
    public function handle(): void
    {
        static::execute($this->movieId, $this->failureId);
    }

    /**
     * Dispatch the auto-fix to the queue (backward-compatible helper).
     *
     * Applies the cooldown guard before dispatching so concurrent API requests
     * for the same movie only enqueue one fix job.
     */
    public static function scheduleAfterResponse(int $movieId, int $failureId): void
    {
        if (static::$inProgress || $movieId <= 0) {
            return;
        }

        $cacheKey = static::COOLDOWN_PREFIX . $movieId;
        if (Cache::has($cacheKey)) {
            Log::debug("[AutoFixMovie] Skipping movie #{$movieId} — cooldown active (attempted recently).");
            return;
        }

        Cache::put($cacheKey, true, static::COOLDOWN_SECONDS);
        Log::info("[AutoFixMovie] Dispatching queued auto-fix for movie #{$movieId} (triggered by failure #{$failureId}).");

        static::dispatch($movieId, $failureId);
    }

    /**
     * Execute the auto-fix immediately (called from the queued handle() method).
     */
    public static function execute(int $movieId, int $failureId): void
    {
        // Re-entry guard
        if (static::$inProgress) {
            return;
        }

        static::$inProgress = true;

        try {
            // Quick check: does the movie exist and is it fixable?
            $movie = MovieModel::find($movieId);
            if (!$movie) {
                Log::warning("[AutoFixMovie] Movie #{$movieId} not found, skipping auto-fix.");
                return;
            }

            // Only auto-fix munowatch movies (they have API endpoint to re-fetch from)
            $hasMunowatchSource = !empty($movie->munowatch_id)
                || (!empty($movie->page_source_url) && str_contains($movie->page_source_url, 'munowatch.org/api/'))
                || (!empty($movie->external_url) && str_contains($movie->external_url, 'munowatch.org/api/'))
                || $movie->is_muno === 'Yes';

            if (!$hasMunowatchSource) {
                Log::debug("[AutoFixMovie] Movie #{$movieId} is not from munowatch, skipping auto-fix.");
                // Deactivate non-munowatch movies since we can't auto-fix them
                $movie->status = 'Inactive';
                $movie->muno_success = 'Auto-deactivated: no auto-fix source available on ' . now()->format('Y-m-d H:i:s');
                $movie->save();
                static::invalidateManifestCache($movieId, 'no auto-fix source');
                return;
            }

            Log::info("[AutoFixMovie] Executing auto-fix for movie #{$movieId}...");

            set_time_limit(120); // Allow up to 2 minutes for the external API call

            $fixer = new MovieFixerService();
            $result = $fixer->fix($movieId);

            if ($result['success']) {
                Log::info("[AutoFixMovie] Movie #{$movieId} auto-fixed successfully. " . ($result['message'] ?? ''));
            } else {
                Log::warning("[AutoFixMovie] Movie #{$movieId} auto-fix failed: " . ($result['message'] ?? 'Unknown error'));
                // Only deactivate the movie AFTER auto-fix has failed
                $movie->status = 'Inactive';
                $movie->muno_success = 'Auto-deactivated: auto-fix failed on ' . now()->format('Y-m-d H:i:s') . ' - ' . ($result['message'] ?? 'Unknown');
                $movie->save();
                Log::info("[AutoFixMovie] Movie #{$movieId} set to Inactive after failed auto-fix.");
                static::invalidateManifestCache($movieId, $result['message'] ?? 'auto-fix failed');
            }

        } catch (\Throwable $e) {
            Log::error("[AutoFixMovie] Exception during auto-fix of movie #{$movieId}: " . $e->getMessage());
        } finally {
            static::$inProgress = false;
        }
    }

    /**
     * Check if auto-fix is currently in progress (for re-entry guard).
     */
    public static function isInProgress(): bool
    {
        return static::$inProgress;
    }

    /**
     * Invalidate all manifest cache keys that may contain this movie.
     *
     * The V2 manifest uses slot-based cache keys where the slot rotates every 6 hours:
     *   v2_manifest_featured_{slot}  — the single featured/trending movie
     *   v2_manifest_sections_{slot}  — all movie list sections (trending, popular, etc.)
     *   v2_total_movies              — total movie count shown in stats
     *
     * We clear the current slot AND the previous slot to handle edge cases where a
     * request was cached just before a 6-hour boundary flip.
     */
    protected static function invalidateManifestCache(int $movieId, string $reason = ''): void
    {
        try {
            $slotNow  = (int) floor(now()->timestamp / 21600);
            $slotPrev = $slotNow - 1;

            $keys = [
                "v2_manifest_featured_{$slotNow}",
                "v2_manifest_sections_{$slotNow}",
                "v2_manifest_featured_{$slotPrev}",
                "v2_manifest_sections_{$slotPrev}",
                'v2_total_movies',
            ];

            foreach ($keys as $key) {
                Cache::forget($key);
            }

            Log::info("[AutoFixMovie] Manifest cache cleared for deactivated movie #{$movieId}. Reason: {$reason}. Keys cleared: " . implode(', ', $keys));
        } catch (\Throwable $e) {
            // Never let cache errors break the main flow
            Log::warning("[AutoFixMovie] Failed to clear manifest cache for movie #{$movieId}: " . $e->getMessage());
        }
    }
}
