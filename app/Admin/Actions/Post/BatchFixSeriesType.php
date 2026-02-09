<?php

namespace App\Admin\Actions\Post;

use App\Models\SeriesMovie;
use App\Models\MovieModel;
use App\Models\Utils;
use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Batch Fix Series Type — Detects SeriesMovie records that are actually
 * standalone Movies on munowatch (not TV series) and deletes them.
 *
 * Multi-signal analysis for each selected series:
 *  1. Counts local episodes (movie_models WHERE category_id = series.id)
 *     → If episodes > 0, SKIP (too risky — might be a real series)
 *  2. Resolves a munowatch video ID from external_url / munowatch_id / series_code
 *  3. Fetches munowatch preview API to inspect series signals (genre, episodes count,
 *     series_code vs videoId, nxt_eps_id, episode_state)
 *  4. Tries the episodes/range API — if it fails or returns 0 ranges, no episodic structure
 *  5. Scores all signals; if score < 3 (same threshold as MovieFixerService) → confirmed Movie
 *
 * If confirmed as Movie:
 *  - Converts any orphan episode records (category_id → this series) back to type='Movie'
 *  - Deletes the series_movies record
 *
 * Safety: Max 50 series per batch. Only deletes 0-episode series with munowatch confirmation.
 */
class BatchFixSeriesType extends BatchAction
{
    public $name = 'Fix Series Type (Detect Movies)';

    protected $selector = '.batch-fix-series-type';

    protected const MUNOWATCH_API_BASE = 'https://munowatch.org/api';
    protected const MUNOWATCH_USER_ID  = 169464;
    protected const MUNOWATCH_JWT      = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';

    // ──────────────────────────────────
    //  MODEL RESOLUTION (override findOrFail → find)
    // ──────────────────────────────────

    /**
     * Override parent's retrieveModel to use lenient find() instead of findOrFail().
     * This prevents 404 errors when some selected IDs no longer exist in the DB
     * (e.g. already deleted by a previous batch run, or stale page cache).
     */
    public function retrieveModel(Request $request)
    {
        if (!$key = $request->get('_key')) {
            return false;
        }

        if (is_string($key)) {
            $key = explode(',', $key);
        }

        // Use find() — returns only records that exist, silently skips missing IDs
        return SeriesMovie::find($key);
    }

    // ──────────────────────────────────
    //  ENTRY POINT
    // ──────────────────────────────────

    public function handle(Collection $collection, Request $request)
    {
        set_time_limit(3600);
        ini_set('memory_limit', '512M');

        $maxBatch = 50;
        $total    = $collection->count();

        if ($total > $maxBatch) {
            return $this->response()
                ->error("Too many series selected ({$total}). Please select at most {$maxBatch} at a time.")
                ->refresh();
        }

        if ($total === 0) {
            return $this->response()->error('No series selected.')->refresh();
        }

        Log::info("[BatchFixSeriesType] Starting analysis for {$total} series.");

        $deleted    = 0;
        $skipped    = 0;
        $hasEpisodes = 0;
        $noMunoInfo = 0;
        $confirmedSeries = 0;
        $errors     = [];
        $details    = [];

        foreach ($collection as $series) {
            try {
                $result = $this->analyzeAndFix($series);

                switch ($result['action']) {
                    case 'deleted':
                        $deleted++;
                        $details[] = "✅ #{$series->id} \"{$series->title}\" → DELETED (movie, score={$result['score']}, signals: {$result['signals']})";
                        break;
                    case 'skipped_has_episodes':
                        $hasEpisodes++;
                        $details[] = "⏭ #{$series->id} \"{$series->title}\" → SKIPPED (has {$result['episode_count']} episode(s))";
                        break;
                    case 'skipped_no_muno_info':
                        $noMunoInfo++;
                        $details[] = "⚠ #{$series->id} \"{$series->title}\" → SKIPPED (no munowatch info to verify)";
                        break;
                    case 'kept_is_series':
                        $confirmedSeries++;
                        $details[] = "📺 #{$series->id} \"{$series->title}\" → KEPT (confirmed series, score={$result['score']}, signals: {$result['signals']})";
                        break;
                    case 'error':
                        $skipped++;
                        $details[] = "❌ #{$series->id} \"{$series->title}\" → ERROR: {$result['error']}";
                        break;
                }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "#{$series->id}: " . $e->getMessage();
                Log::error("[BatchFixSeriesType] Exception for series #{$series->id}: " . $e->getMessage());
            }

            // Throttle between API calls
            if ($total > 5) {
                usleep(300_000); // 300ms
            }
        }

        Log::info("[BatchFixSeriesType] Complete: {$deleted} deleted, {$hasEpisodes} have episodes, {$confirmedSeries} confirmed series, {$noMunoInfo} no muno info, {$skipped} errors.");

        $msg = "Series Type Fix: {$deleted} deleted (confirmed movies), "
             . "{$confirmedSeries} confirmed series, "
             . "{$hasEpisodes} skipped (have episodes), "
             . "{$noMunoInfo} skipped (no munowatch info), "
             . "{$skipped} errors.";

        if (!empty($details)) {
            $msg .= "\n\n" . implode("\n", array_slice($details, 0, 30));
        }

        if ($deleted > 0) {
            return $this->response()->success($msg)->refresh();
        }

        return $this->response()->warning($msg)->refresh();
    }

    // ──────────────────────────────────
    //  CORE ANALYSIS
    // ──────────────────────────────────

    /**
     * Analyze a single SeriesMovie to determine if it's actually a Movie.
     *
     * @return array ['action' => string, 'score' => int, 'signals' => string, ...]
     */
    protected function analyzeAndFix(SeriesMovie $series): array
    {
        $seriesId = $series->id;
        $localEpisodeCount = MovieModel::where('category_id', $seriesId)->count();

        // ── Safety gate: If it has real episodes, DO NOT touch it ──
        if ($localEpisodeCount > 0) {
            return [
                'action'        => 'skipped_has_episodes',
                'episode_count' => $localEpisodeCount,
            ];
        }

        // ── Resolve a munowatch video ID to query the API ──
        $videoId = $this->resolveVideoId($series);

        if (empty($videoId)) {
            // No munowatch info at all — we can still delete if it's a 0-episode
            // series with no external connection, but let's be conservative
            // and check if the episodes/range API works with the series_code
            if (!empty($series->series_code)) {
                $rangeResult = $this->checkEpisodesRangeApi($series->series_code, $series->series_code);
                if (!$rangeResult['has_episodes']) {
                    // No episodes on munowatch either — safe to delete
                    Log::info("[BatchFixSeriesType] #{$seriesId}: 0 local eps, no video ID, range API confirms no episodes. Deleting.");
                    $this->deleteSeries($series);
                    return [
                        'action'  => 'deleted',
                        'score'   => 0,
                        'signals' => 'no_video_id, range_api_no_episodes, 0_local_eps',
                    ];
                }
            }

            return ['action' => 'skipped_no_muno_info'];
        }

        // ── Fetch preview from munowatch ──
        $previewResult = $this->fetchMunowatchPreview($videoId);

        if (!$previewResult['success']) {
            // API call failed — the video might not exist on munowatch anymore
            // With 0 local episodes AND video not found on munowatch, safe to delete
            Log::info("[BatchFixSeriesType] #{$seriesId}: Preview API failed ({$previewResult['error']}). 0 local eps. Deleting as orphan.");
            $this->deleteSeries($series);
            return [
                'action'  => 'deleted',
                'score'   => 0,
                'signals' => "preview_api_failed({$previewResult['error']}), 0_local_eps",
            ];
        }

        $preview = $previewResult['preview'];

        // ── Multi-signal series scoring (matches MovieFixerService logic) ──
        $scoreResult = $this->calculateSeriesScore($videoId, $preview);
        $signalStrength = $scoreResult['score'];
        $signals = $scoreResult['signals'];

        // ── Also check episodes/range API for extra confidence ──
        $seriesCode = $preview['series_code'] ?? $series->series_code ?? '';
        if (!empty($seriesCode) && (string)$seriesCode !== (string)$videoId) {
            // series_code is different from videoId — check if episodes exist on munowatch
            $rangeResult = $this->checkEpisodesRangeApi($videoId, $seriesCode);
            if ($rangeResult['has_episodes']) {
                $signalStrength += 3;
                $signals[] = "range_api_has_episodes({$rangeResult['episode_count']})";
            } else {
                $signals[] = 'range_api_no_episodes';
            }
        }

        $signalStr = implode(', ', $signals);

        Log::info("[BatchFixSeriesType] #{$seriesId} \"{$series->title}\": score={$signalStrength}, signals=[{$signalStr}], local_eps=0");

        // ── Decision: score < 3 = it's a movie → delete ──
        if ($signalStrength < 3) {
            $this->deleteSeries($series);
            return [
                'action'  => 'deleted',
                'score'   => $signalStrength,
                'signals' => $signalStr,
            ];
        }

        return [
            'action'  => 'kept_is_series',
            'score'   => $signalStrength,
            'signals' => $signalStr,
        ];
    }

    // ──────────────────────────────────
    //  SIGNAL SCORING
    // ──────────────────────────────────

    /**
     * Calculate series signal score from munowatch preview data.
     * Same logic as MovieFixerService::shouldReverseToMovie().
     *
     * @return array ['score' => int, 'signals' => string[]]
     */
    protected function calculateSeriesScore(string $videoId, array $preview): array
    {
        $signalStrength = 0;
        $signals = [];

        $apiVideoId = $preview['id'] ?? $preview['vid'] ?? $videoId;
        $seriesCode = $preview['series_code'] ?? $preview['seriesCode'] ?? '';
        $genre      = strtolower($preview['genre'] ?? '');
        $episodes   = (int)($preview['episodes'] ?? 0);
        $epState    = strtoupper($preview['episode_state'] ?? '');
        $nxtEpsId   = (int)($preview['nxt_eps_id'] ?? 0);
        $contentType = (int)($preview['category_id'] ?? 0);

        // Signal 1: Genre contains "series" (weight 3)
        if (strpos($genre, 'series') !== false) {
            $signalStrength += 3;
            $signals[] = 'genre_series';
        }

        // Signal 2: Multiple episodes from API (weight 3)
        if ($episodes > 1) {
            $signalStrength += 3;
            $signals[] = "multi_episode({$episodes})";
        }

        // Signal 3: series_code differs from own video ID (weight 2)
        // If series_code == videoId, it's a self-reference = NOT a series indicator
        if (!empty($seriesCode) && (string)$seriesCode !== (string)$apiVideoId) {
            $signalStrength += 2;
            $signals[] = "has_series_code({$seriesCode}≠{$apiVideoId})";
        }

        // Signal 4: episode_state is NEXT/PREV (weight 2)
        if (in_array($epState, ['NEXT', 'PREV'])) {
            $signalStrength += 2;
            $signals[] = "episode_state({$epState})";
        }

        // Signal 5: nxt_eps_id > 0 and != own ID (weight 2)
        if ($nxtEpsId > 0 && $nxtEpsId != (int)($apiVideoId ?? 0)) {
            $signalStrength += 2;
            $signals[] = "has_nxt_eps_id({$nxtEpsId})";
        }

        // Signal 6: munowatch content category_id = 5 (TV Series) (weight 1)
        if ($contentType === 5) {
            $signalStrength += 1;
            $signals[] = 'content_type_tv_series';
        }

        return ['score' => $signalStrength, 'signals' => $signals];
    }

    // ──────────────────────────────────
    //  MUNOWATCH API HELPERS
    // ──────────────────────────────────

    /**
     * Resolve a munowatch video ID from series data.
     * Tries: external_url, munowatch_id, series_code, first sample episode.
     */
    protected function resolveVideoId(SeriesMovie $series): ?string
    {
        // Strategy 1: Parse from external_url
        if (!empty($series->external_url)) {
            if (preg_match('/preview\/v2\/(\d+)\//', $series->external_url, $m)) {
                return $m[1];
            }
        }

        // Strategy 2: munowatch_id on the series record (could be series_code or video ID)
        if (!empty($series->munowatch_id) && is_numeric($series->munowatch_id)) {
            return $series->munowatch_id;
        }

        // Strategy 3: series_code as video ID
        if (!empty($series->series_code) && is_numeric($series->series_code)) {
            return $series->series_code;
        }

        // Strategy 4: Check if any sample episode has a munowatch_id
        $sampleEp = MovieModel::where('category_id', $series->id)
            ->whereNotNull('munowatch_id')
            ->where('munowatch_id', '!=', '')
            ->first();

        if ($sampleEp) {
            return $sampleEp->munowatch_id;
        }

        return null;
    }

    /**
     * Fetch preview data from munowatch for a single video ID.
     */
    protected function fetchMunowatchPreview(string $videoId): array
    {
        $apiUrl = self::MUNOWATCH_API_BASE . '/preview/v2/' . $videoId . '/' . self::MUNOWATCH_USER_ID;

        try {
            $headers = [
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'User-Agent'    => 'okhttp/4.9.0',
                'Authorization' => 'Bearer ' . self::MUNOWATCH_JWT,
                'X-Api-Key'     => self::MUNOWATCH_JWT,
            ];

            $raw = Utils::get_url_with_auth($apiUrl, $headers);
            if (empty($raw)) return ['success' => false, 'error' => 'Empty response'];

            $json = json_decode(trim($raw), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'JSON parse error'];
            }

            $preview = $json['preview'] ?? $json['movie'] ?? $json['data'] ?? $json;
            return ['success' => true, 'preview' => $preview, 'api_url' => $apiUrl];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check the episodes/range API to see if munowatch has episodic content.
     *
     * @return array ['has_episodes' => bool, 'episode_count' => int]
     */
    protected function checkEpisodesRangeApi(string $showId, string $seriesCode, int $season = 1): array
    {
        $apiUrl = self::MUNOWATCH_API_BASE . "/episodes/range/{$showId}/{$seriesCode}/{$season}";

        try {
            $headers = [
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'User-Agent'    => 'okhttp/4.9.0',
                'Authorization' => 'Bearer ' . self::MUNOWATCH_JWT,
                'X-Api-Key'     => self::MUNOWATCH_JWT,
            ];

            $raw = Utils::get_url_with_auth($apiUrl, $headers);
            if (empty($raw)) return ['has_episodes' => false, 'episode_count' => 0];

            $json = json_decode(trim($raw), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['has_episodes' => false, 'episode_count' => 0];
            }

            // Error response = no episodes
            if (isset($json['error'])) {
                return ['has_episodes' => false, 'episode_count' => 0];
            }

            // Validate it's an array of range objects
            if (!is_array($json) || empty($json) || !isset($json[0]['eps'])) {
                return ['has_episodes' => false, 'episode_count' => 0];
            }

            // Count total episodes across all ranges
            $total = 0;
            foreach ($json as $range) {
                if (isset($range['eps'])) {
                    // eps is like "  -  20" meaning 20 episodes, or "21  -  40"
                    if (preg_match('/(\d+)\s*-\s*(\d+)/', $range['eps'], $m)) {
                        $total += ((int)$m[2] - (int)$m[1] + 1);
                    } elseif (preg_match('/\d+/', $range['eps'], $m)) {
                        $total += (int)$m[0];
                    }
                }
            }

            return ['has_episodes' => $total > 0, 'episode_count' => $total];
        } catch (\Throwable $e) {
            Log::warning("[BatchFixSeriesType] Range API error for {$showId}/{$seriesCode}: " . $e->getMessage());
            return ['has_episodes' => false, 'episode_count' => 0];
        }
    }

    // ──────────────────────────────────
    //  DELETION
    // ──────────────────────────────────

    /**
     * Safely delete a SeriesMovie record.
     *
     * Before deleting:
     *  1. Converts any orphan episode records to type='Movie' (clear series fields)
     *  2. Logs the deletion for audit trail
     *  3. Deletes the series_movies record
     */
    protected function deleteSeries(SeriesMovie $series): void
    {
        $seriesId = $series->id;
        $title    = $series->title;

        // Step 1: Clean up any orphan episodes (shouldn't exist if count was 0, but be safe)
        $orphanCount = MovieModel::where('category_id', $seriesId)->count();
        if ($orphanCount > 0) {
            Log::warning("[BatchFixSeriesType] #{$seriesId} has {$orphanCount} orphan episodes — converting to Movie type before deletion.");

            MovieModel::where('category_id', $seriesId)->update([
                'type'            => 'Movie',
                'category_id'     => null,
                'episode_number'  => null,
                'season_number'   => null,
                'series_title'    => null,
                'episode_title'   => null,
                'is_first_episode' => null,
            ]);
        }

        // Step 2: Log the deletion for audit
        Log::info("[BatchFixSeriesType] DELETING series #{$seriesId} \"{$title}\" — confirmed as Movie (0 episodes, munowatch analysis).");

        // Step 3: Delete the series record
        DB::table('series_movies')->where('id', $seriesId)->delete();
    }
}
