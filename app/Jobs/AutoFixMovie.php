<?php

namespace App\Jobs;

use App\Models\MovieModel;
use App\Models\VideoPlaybackFailure;
use App\Services\MovieFixerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AutoFixMovie — Attempts to auto-fix a movie when a playback failure is reported.
 *
 * This is NOT a queued Job (project uses sync queue). Instead it's invoked via
 * app()->terminating() so the HTTP response is sent to the client first,
 * then the fix runs in the same PHP process after output is flushed.
 *
 * Safety measures:
 *  - Cooldown: skips if the same movie was already attempted within the last 5 minutes
 *  - Re-entry guard: static flag prevents recursive triggers
 *  - Try/catch: never breaks failure creation regardless of fix outcome
 *  - Only fires for movies sourced from munowatch (has page_source_url or munowatch_id)
 */
class AutoFixMovie
{
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
     * Schedule an auto-fix to run after the HTTP response is sent.
     *
     * Call this from the model's created event or from the API controller.
     * It registers an app()->terminating() callback that runs after the
     * response is delivered, so the client is not blocked.
     */
    public static function scheduleAfterResponse(int $movieId, int $failureId): void
    {
        // Guard: don't schedule if we're already inside a fix operation
        if (static::$inProgress) {
            return;
        }

        // Guard: skip if no movie ID
        if ($movieId <= 0) {
            return;
        }

        // Guard: cooldown — skip if this movie was already attempted recently
        $cacheKey = static::COOLDOWN_PREFIX . $movieId;
        if (Cache::has($cacheKey)) {
            Log::debug("[AutoFixMovie] Skipping movie #{$movieId} — cooldown active (attempted recently).");
            return;
        }

        // Set cooldown immediately so other concurrent requests skip
        Cache::put($cacheKey, true, static::COOLDOWN_SECONDS);

        Log::info("[AutoFixMovie] Scheduled auto-fix for movie #{$movieId} (triggered by failure #{$failureId}).");

        // Register to run AFTER the HTTP response is sent
        app()->terminating(function () use ($movieId, $failureId) {
            static::execute($movieId, $failureId);
        });
    }

    /**
     * Execute the auto-fix immediately (called from terminating callback).
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
}
