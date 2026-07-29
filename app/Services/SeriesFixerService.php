<?php

namespace App\Services;

use App\Models\MovieCrawlerPage;
use App\Models\MovieModel;
use App\Models\SeriesMovie;
use App\Models\Utils;
use App\Services\MunowatchAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SeriesFixerService v2.0 — Complete rewrite with proper pagination support.
 *
 * The munowatch episodes/range API returns ranges like:
 *   [{"eps":"  -  20","eps_range":"39531__39550"}, {"eps":"  21-  40","eps_range":"39551__39570"}, ...]
 *
 * Each range has:
 *   - eps: "  {startEp}-  {endEp}" — human-readable episode numbers
 *   - eps_range: "{startVideoId}__{endVideoId}" — munowatch video ID boundaries
 *
 * CRITICAL: Video IDs within a range are NOT always contiguous. Gaps exist because
 * other series' videos may occupy IDs in between. We use the nxt_eps_id chain from
 * preview API to traverse non-contiguous ranges.
 *
 * Usage:
 *   $fixer = new SeriesFixerService();
 *   $fixer->getSeriesInfo($seriesId)              // Series data for debug player
 *   $fixer->fetchRemoteRanges($seriesId)           // Get pagination ranges from API
 *   $fixer->fetchEpisodesForRange($seriesId, $idx) // Fetch episodes for a specific range
 *   $fixer->syncAllEpisodes($seriesId)             // Full sync: all ranges, all episodes
 *   $fixer->fixSeries($seriesId)                   // Sync + fix all episodes
 *   $fixer->fixEpisode($movieId)                   // Fix single episode
 */
class SeriesFixerService
{
    protected const MUNOWATCH_USER_ID = 169464;
    protected const MUNOWATCH_API_BASE = 'https://munowatch.org/api';
    protected const MUNOWATCH_JWT = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';

    protected MovieFixerService $movieFixer;

    public function __construct()
    {
        $this->movieFixer = new MovieFixerService();
    }

    // ─────────────────────────────────────────────
    //  PUBLIC: Debug Player Data
    // ─────────────────────────────────────────────

    /**
     * Get comprehensive series info for the debug player.
     */
    public function getSeriesInfo(int $seriesId): array
    {
        $series = SeriesMovie::find($seriesId);
        if (!$series) {
            return ['success' => false, 'error' => "Series not found (ID: {$seriesId})"];
        }

        $episodes = MovieModel::where('category_id', $seriesId)
            ->orderByRaw("CAST(COALESCE(season_number, '1') AS UNSIGNED) ASC")
            ->orderByRaw("CAST(COALESCE(episode_number, '0') AS UNSIGNED) ASC")
            ->orderBy('id', 'asc')
            ->get();

        // Deduplicate by munowatch_id (keep first occurrence)
        $seen = [];
        $uniqueEps = $episodes->filter(function ($ep) use (&$seen) {
            if ($ep->munowatch_id && isset($seen[$ep->munowatch_id])) {
                return false;
            }
            if ($ep->munowatch_id) $seen[$ep->munowatch_id] = true;
            return true;
        });

        // Group by season
        $seasons = [];
        foreach ($uniqueEps as $ep) {
            $sn = $ep->season_number ?: '1';
            $seasons[$sn][] = $this->episodeToArray($ep);
        }

        return [
            'success'  => true,
            'series'   => $this->seriesToArray($series),
            'episodes' => $uniqueEps->values()->map(fn($ep) => $this->episodeToArray($ep))->all(),
            'seasons'  => $seasons,
            'total_episodes' => $uniqueEps->count(),
            'total_seasons'  => count($seasons),
        ];
    }

    // ─────────────────────────────────────────────
    //  PUBLIC: Pagination-Aware Fetching
    // ─────────────────────────────────────────────

    /**
     * Fetch the episode RANGES from munowatch (pagination metadata).
     * Returns the range objects so the UI can show "Fetch Range 1-20", "21-40", etc.
     */
    public function fetchRemoteRanges(int $seriesId): array
    {
        $series = SeriesMovie::find($seriesId);
        if (!$series) {
            return ['success' => false, 'error' => "Series not found"];
        }

        $resolved = $this->resolveSeriesCode($series);
        if (!$resolved['success']) {
            return $resolved;
        }

        $seriesCode = $resolved['series_code'];
        $showId = $resolved['show_id'];
        $totalRemote = $resolved['total_episodes'] ?? null;

        // Fetch ranges
        $rangesResult = $this->callEpisodesRangeApi($showId, $seriesCode);
        if (!$rangesResult['success']) {
            return $rangesResult;
        }

        $ranges = $rangesResult['ranges'];

        // Annotate each range with local sync status
        $localEpisodes = MovieModel::where('category_id', $seriesId)
            ->whereNotNull('munowatch_id')
            ->pluck('munowatch_id')
            ->toArray();
        $localCount = count($localEpisodes);

        $annotatedRanges = [];
        $totalRemoteEps = 0;
        foreach ($ranges as $idx => $range) {
            $parsed = $this->parseRangeObject($range);
            $parsed['range_index'] = $idx;
            $totalRemoteEps += $parsed['episode_count'];

            // Count how many local episodes fall in this video ID range
            $localInRange = 0;
            foreach ($localEpisodes as $mId) {
                if ((int)$mId >= $parsed['start_video_id'] && (int)$mId <= $parsed['end_video_id']) {
                    $localInRange++;
                }
            }
            $parsed['local_count'] = $localInRange;
            $parsed['is_complete'] = ($localInRange >= $parsed['episode_count']);

            $annotatedRanges[] = $parsed;
        }

        return [
            'success' => true,
            'ranges' => $annotatedRanges,
            'total_ranges' => count($annotatedRanges),
            'total_remote_episodes' => $totalRemoteEps,
            'total_from_preview' => $totalRemote,
            'local_episode_count' => $localCount,
            'series_code' => $seriesCode,
            'show_id' => $showId,
            'api_url' => $rangesResult['api_url'] ?? '',
        ];
    }

    /**
     * Fetch and sync episodes for a specific range index.
     * Supports batching to avoid MAMP FastCGI 30s idle timeout.
     *
     * When continue_from is provided, the expensive resolve+range API calls are skipped
     * and we jump directly to the chain traversal — cutting ~5-10s of overhead per batch.
     *
     * @param int $seriesId       Local series ID
     * @param int $rangeIndex     Which range (0-based)
     * @param int $season          Season number
     * @param int $batchSize       Max episodes per request (0 = all)
     * @param string|null $continueFrom  Video ID to resume chain from
     * @param int|null $continueEp       Episode number to resume from
     * @param int|null $rangeEndEp       End episode of the range (passed by JS for continuation)
     */
    public function fetchEpisodesForRange(
        int $seriesId,
        int $rangeIndex,
        int $season = 1,
        int $batchSize = 0,
        ?string $continueFrom = null,
        ?int $continueEp = null,
        ?int $rangeEndEp = null
    ): array {
        $series = SeriesMovie::find($seriesId);
        if (!$series) {
            return ['success' => false, 'error' => "Series not found"];
        }

        set_time_limit(300);
        ini_set('memory_limit', '256M');

        // FAST PATH: If continuing, skip the expensive resolve+range API calls
        if ($continueFrom !== null && $continueEp !== null && $rangeEndEp !== null) {
            $remaining = $rangeEndEp - $continueEp + 1;
            $limit = ($batchSize > 0) ? min($batchSize, $remaining) : $remaining;

            Log::info("[SeriesFixer] FAST continue range #{$rangeIndex}: eps {$continueEp}-" . ($continueEp + $limit - 1) . " (batch={$limit}), VID={$continueFrom}");

            $episodes = $this->traverseEpisodeChain($continueFrom, $limit, $series, $continueEp);

            $fetchedCount = count($episodes['created'] ?? []) + count($episodes['updated'] ?? []) + count($episodes['skipped'] ?? []);
            $chainEnded = $episodes['chainEnded'] ?? false;
            $nextEp = $continueEp + $fetchedCount;
            $hasMore = !$chainEnded && ($nextEp <= $rangeEndEp);
            $nextVideoId = $episodes['nextVideoId'] ?? null;

            return [
                'success' => true,
                'range' => ['start_ep' => $continueEp, 'end_ep' => $rangeEndEp, 'episode_count' => $rangeEndEp - $continueEp + 1],
                'episodes_fetched' => count($episodes['created'] ?? []) + count($episodes['updated'] ?? []),
                'created' => count($episodes['created'] ?? []),
                'updated' => count($episodes['updated'] ?? []),
                'skipped' => count($episodes['skipped'] ?? []),
                'errors' => $episodes['errors'] ?? [],
                'has_more' => $hasMore,
                'chain_ended' => $chainEnded,
                'next_video_id' => $nextVideoId,
                'next_ep_num' => $hasMore ? $nextEp : null,
                'range_end_ep' => $rangeEndEp,
                'message' => "Eps {$continueEp}-" . ($continueEp + $fetchedCount - 1) . ": " .
                    count($episodes['created'] ?? []) . " created, " .
                    count($episodes['updated'] ?? []) . " updated, " .
                    count($episodes['skipped'] ?? []) . " skipped." .
                    ($chainEnded ? ' [END OF CHAIN]' : ''),
            ];
        }

        // NORMAL PATH: First batch for a range — need to resolve and fetch ranges
        $resolved = $this->resolveSeriesCode($series);
        if (!$resolved['success']) return $resolved;

        $rangesResult = $this->callEpisodesRangeApi($resolved['show_id'], $resolved['series_code'], $season);
        if (!$rangesResult['success']) return $rangesResult;

        $ranges = $rangesResult['ranges'];
        if ($rangeIndex < 0 || $rangeIndex >= count($ranges)) {
            return ['success' => false, 'error' => "Range index {$rangeIndex} out of bounds (0-" . (count($ranges) - 1) . ")"];
        }

        $range = $this->parseRangeObject($ranges[$rangeIndex]);

        // Determine start point
        $startVid = $continueFrom ?? $range['start_video_id'];
        $startEp  = $continueEp  ?? $range['start_ep'];
        $remaining = ($range['start_ep'] + $range['episode_count']) - $startEp;
        $limit = ($batchSize > 0) ? min($batchSize, $remaining) : $remaining;

        Log::info("[SeriesFixer] Fetching range #{$rangeIndex}: eps {$startEp}-" . ($startEp + $limit - 1) . " (batch={$limit}), start VID={$startVid}");

        // Traverse episodes using nxt_eps_id chain
        $episodes = $this->traverseEpisodeChain(
            $startVid,
            $limit,
            $series,
            $startEp
        );

        // Determine if there are more episodes in this range
        $fetchedCount = count($episodes['created'] ?? []) + count($episodes['updated'] ?? []) + count($episodes['skipped'] ?? []);
        $chainEnded = $episodes['chainEnded'] ?? false;
        $nextEp = $startEp + $fetchedCount;
        $hasMore = !$chainEnded && ($nextEp < ($range['start_ep'] + $range['episode_count']));
        $nextVideoId = $episodes['nextVideoId'] ?? null;

        return [
            'success' => true,
            'range' => $range,
            'episodes_fetched' => count($episodes['created'] ?? []) + count($episodes['updated'] ?? []),
            'created' => count($episodes['created'] ?? []),
            'updated' => count($episodes['updated'] ?? []),
            'skipped' => count($episodes['skipped'] ?? []),
            'errors' => $episodes['errors'] ?? [],
            'has_more' => $hasMore,
            'chain_ended' => $chainEnded,
            'next_video_id' => $nextVideoId,
            'next_ep_num' => $hasMore ? $nextEp : null,
            'range_end_ep' => $range['end_ep'],
            'message' => "Eps {$startEp}-" . ($startEp + $fetchedCount - 1) . ": " .
                count($episodes['created'] ?? []) . " created, " .
                count($episodes['updated'] ?? []) . " updated, " .
                count($episodes['skipped'] ?? []) . " skipped." .
                ($chainEnded ? ' [END OF CHAIN]' : ''),
        ];
    }

    /**
     * Full sync: fetch ALL ranges and traverse ALL episodes.
     */
    public function syncAllEpisodes(int $seriesId, int $season = 1): array
    {
        $series = SeriesMovie::find($seriesId);
        if (!$series) return ['success' => false, 'error' => "Series not found"];

        set_time_limit(3600);
        ini_set('memory_limit', '512M');

        $resolved = $this->resolveSeriesCode($series);
        if (!$resolved['success']) return $resolved;

        $rangesResult = $this->callEpisodesRangeApi($resolved['show_id'], $resolved['series_code'], $season);
        if (!$rangesResult['success']) return $rangesResult;

        $ranges = $rangesResult['ranges'];
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $allErrors = [];

        foreach ($ranges as $idx => $rawRange) {
            $range = $this->parseRangeObject($rawRange);
            Log::info("[SeriesFixer] SyncAll range #{$idx}: eps {$range['start_ep']}-{$range['end_ep']}");

            $result = $this->traverseEpisodeChain(
                $range['start_video_id'],
                $range['episode_count'],
                $series,
                $range['start_ep']
            );

            $totalCreated += count($result['created'] ?? []);
            $totalUpdated += count($result['updated'] ?? []);
            $totalSkipped += count($result['skipped'] ?? []);
            if (!empty($result['errors'])) {
                $allErrors = array_merge($allErrors, $result['errors']);
            }

            usleep(300000); // 300ms between ranges
        }

        // Refresh series metadata
        $this->refreshSeriesMetadata($series);

        return [
            'success' => true,
            'total_ranges' => count($ranges),
            'created' => $totalCreated,
            'updated' => $totalUpdated,
            'skipped' => $totalSkipped,
            'errors' => $allErrors,
            'message' => "Full sync: {$totalCreated} created, {$totalUpdated} updated, {$totalSkipped} existed across " . count($ranges) . " ranges.",
        ];
    }

    /**
     * Backwards-compatible: Fetch remote episodes (now returns ranges + basic episode list).
     */
    public function fetchRemoteEpisodes(int $seriesId): array
    {
        $rangesResult = $this->fetchRemoteRanges($seriesId);
        if (!$rangesResult['success']) return $rangesResult;

        // Return ranges as "episodes" with range metadata for backward compat
        $episodes = [];
        foreach ($rangesResult['ranges'] as $range) {
            for ($ep = $range['start_ep']; $ep <= $range['end_ep']; $ep++) {
                $episodes[] = [
                    'episode_number' => $ep,
                    'range_index' => $range['range_index'],
                    'start_video_id' => $range['start_video_id'],
                    'end_video_id' => $range['end_video_id'],
                ];
            }
        }

        return [
            'success' => true,
            'episodes' => $episodes,
            'ranges' => $rangesResult['ranges'],
            'total_remote' => $rangesResult['total_remote_episodes'],
            'api_url' => $rangesResult['api_url'] ?? '',
        ];
    }

    // ─────────────────────────────────────────────
    //  PUBLIC: Fix Operations
    // ─────────────────────────────────────────────

    /**
     * Fix a complete series: sync all episodes + fix each one.
     */
    public function fixSeries(int $seriesId, int $maxEpisodes = 200): array
    {
        $series = SeriesMovie::find($seriesId);
        if (!$series) return ['success' => false, 'error' => "Series not found"];

        set_time_limit(max(1800, $maxEpisodes * 15));
        ini_set('memory_limit', '512M');

        $results = [
            'series_id' => $seriesId,
            'series_title' => $series->title,
            'episodes_fixed' => 0,
            'episodes_failed' => 0,
            'errors' => [],
        ];

        // Step 1: Sync episodes from remote
        $syncResult = $this->syncAllEpisodes($seriesId);
        $results['sync'] = $syncResult;

        // Step 2: Fix each local episode
        $episodes = MovieModel::where('category_id', $seriesId)
            ->orderByRaw("CAST(COALESCE(episode_number, '0') AS UNSIGNED) ASC")
            ->limit($maxEpisodes)
            ->get();

        foreach ($episodes as $ep) {
            try {
                $fixResult = $this->movieFixer->fix($ep->id);
                if ($fixResult['success'] ?? false) {
                    $results['episodes_fixed']++;
                } else {
                    $results['episodes_failed']++;
                    $results['errors'][] = "Ep #{$ep->id}: " . ($fixResult['message'] ?? 'Unknown');
                }
            } catch (\Throwable $e) {
                $results['episodes_failed']++;
                $results['errors'][] = "Ep #{$ep->id}: " . $e->getMessage();
            }
        }

        $this->refreshSeriesMetadata($series);
        $results['success'] = true;
        $results['message'] = "Fix complete: {$results['episodes_fixed']} fixed, {$results['episodes_failed']} failed of " . $episodes->count();
        return $results;
    }

    /**
     * Fix a single episode.
     */
    public function fixEpisode(int $movieId): array
    {
        return $this->movieFixer->fix($movieId);
    }

    /**
     * Backwards-compatible sync method.
     */
    public function syncEpisodesFromRemote(int $seriesId): array
    {
        return $this->syncAllEpisodes($seriesId);
    }

    // ─────────────────────────────────────────────
    //  INTERNAL: API Communication
    // ─────────────────────────────────────────────

    /**
     * Resolve the series_code and show_id for a series.
     * If not stored, fetches preview of first episode to discover them.
     */
    protected function resolveSeriesCode(SeriesMovie $series): array
    {
        $seriesCode = $series->series_code;
        $showId = null;

        // Try to find a show ID from existing episodes
        $sampleEp = MovieModel::where('category_id', $series->id)
            ->whereNotNull('munowatch_id')
            ->where('munowatch_id', '!=', '')
            ->first();

        if ($sampleEp) {
            $showId = $sampleEp->munowatch_id;
        }

        // If no series_code yet, get it from preview API
        if (empty($seriesCode)) {
            if (empty($showId)) {
                // Try external_url of series itself
                if (preg_match('/preview\/v2\/(\d+)\//', $series->external_url ?? '', $m)) {
                    $showId = $m[1];
                }
            }
            if (empty($showId)) {
                return ['success' => false, 'error' => 'No munowatch_id on any episode and no external_url for this series.'];
            }

            $preview = $this->fetchMunowatchPreview($showId);
            if (!$preview['success']) {
                return ['success' => false, 'error' => 'Cannot fetch preview to discover series_code: ' . ($preview['error'] ?? '')];
            }

            $seriesCode = $preview['preview']['series_code'] ?? null;
            if (empty($seriesCode)) {
                return ['success' => false, 'error' => 'Preview did not return a series_code for video ID ' . $showId];
            }

            // Persist for future use
            $series->series_code = $seriesCode;
            if (!$series->munowatch_id) {
                $series->munowatch_id = $seriesCode;
            }
            $series->save();

            // Also grab total episodes
            $totalRemote = $preview['preview']['episodes'] ?? null;
        } else {
            // We have series_code; use it or sample episode for showId
            if (empty($showId)) {
                $showId = $seriesCode; // fallback
            }
            $totalRemote = null;
        }

        // Build a quick preview to get total_episodes if we don't have it
        if (empty($totalRemote) && $showId) {
            $preview = $this->fetchMunowatchPreview($showId);
            $totalRemote = $preview['preview']['episodes'] ?? null;
        }

        return [
            'success' => true,
            'series_code' => $seriesCode,
            'show_id' => $showId,
            'total_episodes' => $totalRemote,
        ];
    }

    /**
     * Call the episodes/range API endpoint.
     * Returns: [{eps: "  -  20", eps_range: "39531__39550"}, ...]
     */
    protected function callEpisodesRangeApi(string $showId, string $seriesCode, int $season = 1): array
    {
        $apiUrl = self::MUNOWATCH_API_BASE . "/episodes/range/{$showId}/{$seriesCode}/{$season}";

        try {
            $session = MunowatchAuthService::getActiveSession();
            $headers = [
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'User-Agent'    => 'okhttp/4.9.0',
                'Authorization' => 'Bearer ' . $session['app_jwt'],
                'X-Api-Key'     => $session['app_jwt'],
                'X-Session-Id'  => $session['session_id'],
            ];

            $raw = Utils::get_url_with_auth($apiUrl, $headers);

            // Retry once on auth failure
            if (!empty($raw) && MunowatchAuthService::isAuthFailure($raw)) {
                MunowatchAuthService::invalidateSession();
                $session = MunowatchAuthService::refreshSession();
                $headers['Authorization'] = 'Bearer ' . $session['app_jwt'];
                $headers['X-Api-Key']     = $session['app_jwt'];
                $headers['X-Session-Id']  = $session['session_id'];
                $raw = Utils::get_url_with_auth($apiUrl, $headers);
            }
            if (empty($raw)) {
                return ['success' => false, 'error' => 'Empty response from: ' . $apiUrl];
            }

            // Trim whitespace — the API often returns leading whitespace
            $raw = trim($raw);
            $json = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'JSON parse error: ' . json_last_error_msg() . ' | Raw: ' . substr($raw, 0, 200)];
            }

            // The response is either:
            //   - A JSON array of {eps, eps_range} objects (success)
            //   - An object with {error: true, msg: "..."} (no episodes)
            if (isset($json['error'])) {
                return ['success' => false, 'error' => 'API: ' . ($json['msg'] ?? 'Unknown error')];
            }

            // Must be an array of range objects
            if (!is_array($json) || empty($json)) {
                return ['success' => false, 'error' => 'Unexpected response format'];
            }

            // Validate first element has expected keys
            if (!isset($json[0]['eps']) || !isset($json[0]['eps_range'])) {
                return ['success' => false, 'error' => 'Response items missing eps/eps_range keys'];
            }

            return ['success' => true, 'ranges' => $json, 'api_url' => $apiUrl];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'API request failed: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch preview data from munowatch for a single video ID.
     */
    protected function fetchMunowatchPreview(string $videoId): array
    {
        try {
            $session = MunowatchAuthService::getActiveSession();
            $apiUrl  = self::MUNOWATCH_API_BASE . '/preview/v2/' . $videoId . '/' . $session['user_id'];

            $headers = [
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'User-Agent'    => 'okhttp/4.9.0',
                'Authorization' => 'Bearer ' . $session['app_jwt'],
                'X-Api-Key'     => $session['app_jwt'],
                'X-Session-Id'  => $session['session_id'],
            ];

            $raw = Utils::get_url_with_auth($apiUrl, $headers);
            if (empty($raw)) return ['success' => false, 'error' => 'Empty response'];

            // Retry once on auth failure
            if (MunowatchAuthService::isAuthFailure($raw)) {
                MunowatchAuthService::invalidateSession();
                $session = MunowatchAuthService::refreshSession();
                $apiUrl  = self::MUNOWATCH_API_BASE . '/preview/v2/' . $videoId . '/' . $session['user_id'];
                $headers['Authorization'] = 'Bearer ' . $session['app_jwt'];
                $headers['X-Api-Key']     = $session['app_jwt'];
                $headers['X-Session-Id']  = $session['session_id'];
                $raw = Utils::get_url_with_auth($apiUrl, $headers);
                if (empty($raw)) return ['success' => false, 'error' => 'Empty response after re-auth'];
            }

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

    // ─────────────────────────────────────────────
    //  INTERNAL: Episode Chain Traversal
    // ─────────────────────────────────────────────

    /**
     * Traverse episodes using nxt_eps_id chain starting from a given video ID.
     * For each episode found: create or update the local database record.
     *
     * This is the core pagination handler that correctly follows the munowatch
     * episode chain even when video IDs are non-contiguous.
     *
     * @param string $startVideoId  First video ID in this range
     * @param int    $expectedCount How many episodes to expect in this range
     * @param SeriesMovie $series   The parent series record
     * @param int    $startEpNum    Episode number of the first episode in this range
     * @return array  {created: [], updated: [], skipped: [], errors: []}
     */
    protected function traverseEpisodeChain(string $startVideoId, int $expectedCount, SeriesMovie $series, int $startEpNum = 1): array
    {
        $created = [];
        $updated = [];
        $skipped = [];
        $errors = [];
        $nextVideoId = null;
        $chainEnded = false;

        $currentVideoId = $startVideoId;
        $epNum = $startEpNum;
        $visited = [];
        $maxIterations = $expectedCount + 5; // safety margin
        $fetched = 0;

        for ($i = 0; $i < $maxIterations && $fetched < $expectedCount; $i++) {
            // Prevent infinite loops
            if (isset($visited[$currentVideoId])) {
                Log::info("[SeriesFixer] Already visited VID {$currentVideoId}, ending chain");
                break;
            }
            $visited[$currentVideoId] = true;

            // Fetch preview for this episode
            $preview = $this->fetchMunowatchPreview($currentVideoId);
            if (!$preview['success']) {
                $errors[] = "VID {$currentVideoId} (ep#{$epNum}): " . ($preview['error'] ?? 'Preview failed');
                // Try incrementing video ID as fallback (might be contiguous)
                $currentVideoId = (string)((int)$currentVideoId + 1);
                $epNum++;
                $fetched++;
                continue;
            }

            $p = $preview['preview'];
            $videoTitle = $p['video_title'] ?? ($series->title . ' ' . $epNum);
            $playingUrl = $p['playingUrl'] ?? '';
            $thumbnail = $p['thumbnail'] ?? '';
            $duration = $p['duration'] ?? '';
            $vjName = $p['vjname'] ?? '';
            $genre = $p['genre'] ?? '';
            $nxtEpsId = $p['nxt_eps_id'] ?? 0;

            // Upsert this episode in the local DB
            $upsertResult = $this->upsertEpisode($series, [
                'munowatch_id' => (string)$currentVideoId,
                'title' => $videoTitle,
                'url' => $playingUrl,
                'thumbnail' => $thumbnail,
                'duration' => $duration,
                'vj' => $vjName,
                'genre' => $genre,
                'episode_number' => $epNum,
                'season_number' => 1,
            ]);

            switch ($upsertResult['action']) {
                case 'created': $created[] = $currentVideoId; break;
                case 'updated': $updated[] = $currentVideoId; break;
                case 'skipped': $skipped[] = $currentVideoId; break;
            }

            // Move to next episode
            $epNum++;
            $fetched++;

            if ($nxtEpsId == $currentVideoId || $nxtEpsId == 0) {
                // End of chain (self-referencing or zero) — this means we've reached the LAST episode
                Log::info("[SeriesFixer] End of chain at VID {$currentVideoId} (nxt={$nxtEpsId})");
                $chainEnded = true;
                break;
            }
            $currentVideoId = (string)$nxtEpsId;
            $nextVideoId = (string)$nxtEpsId; // Store for continuation

            // Small delay to avoid hammering the API
            usleep(150000); // 150ms
        }

        return compact('created', 'updated', 'skipped', 'errors', 'nextVideoId', 'chainEnded');
    }

    /**
     * Create or update a local episode record.
     */
    protected function upsertEpisode(SeriesMovie $series, array $data): array
    {
        try {
            $munowatchId = $data['munowatch_id'];

            // Check existing by munowatch_id
            $existing = MovieModel::where('category_id', $series->id)
                ->where('munowatch_id', $munowatchId)
                ->first();

            if ($existing) {
                // Update only if we have better data
                $changed = false;
                if (!empty($data['url']) && $data['url'] !== $existing->url) {
                    $existing->url = $data['url'];
                    $changed = true;
                }
                if (!empty($data['title']) && strlen($data['title']) > strlen($existing->title ?? '')) {
                    $existing->title = $data['title'];
                    $changed = true;
                }
                if (!empty($data['thumbnail']) && empty($existing->thumbnail_url)) {
                    $existing->thumbnail_url = $data['thumbnail'];
                    $changed = true;
                }
                if (!empty($data['duration']) && empty($existing->duration)) {
                    $existing->duration = $data['duration'];
                    $changed = true;
                }
                if (!empty($data['episode_number']) && empty($existing->episode_number)) {
                    $existing->episode_number = $data['episode_number'];
                    $changed = true;
                }
                if (!empty($data['vj']) && empty($existing->vj)) {
                    $existing->vj = $data['vj'];
                    $changed = true;
                }

                // Activate episode if it has a healthy URL but is still Inactive
                if ($existing->status !== 'Active' && self::isHealthyEpisodeUrl($existing->url)) {
                    $existing->status = 'Active';
                    $changed = true;
                }

                if ($changed) {
                    $existing->save();
                    return ['action' => 'updated'];
                }
                return ['action' => 'skipped'];
            }

            // Create new episode
            $ep = new MovieModel();
            $ep->title = $data['title'];
            $ep->url = $data['url'] ?? '';
            $ep->external_url = self::MUNOWATCH_API_BASE . '/preview/v2/' . $munowatchId . '/' . self::MUNOWATCH_USER_ID;
            $ep->munowatch_id = $munowatchId;
            $ep->type = 'Series';
            $ep->category_id = $series->id;
            $ep->category = $series->title;
            $ep->status = !empty($data['url']) ? 'Active' : 'Inactive';
            $ep->is_muno = 'Yes';
            $ep->muno_processed = 'Yes';
            $ep->thumbnail_url = $data['thumbnail'] ?? $series->thumbnail;
            $ep->vj = $data['vj'] ?? $series->vj;
            $ep->duration = $data['duration'] ?? null;
            $ep->genre = $data['genre'] ?? $series->genre ?? 'Series';
            $ep->episode_number = $data['episode_number'] ?? null;
            $ep->season_number = $data['season_number'] ?? 1;
            $ep->series_title = $series->title;
            $ep->save();

            return ['action' => 'created'];
        } catch (\Throwable $e) {
            Log::error("[SeriesFixer] upsertEpisode error: " . $e->getMessage());
            return ['action' => 'error', 'error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────
    //  INTERNAL: Range Parsing
    // ─────────────────────────────────────────────

    /**
     * Parse a single range object from the episodes/range API.
     *
     * Input:  {"eps": "  21-  40", "eps_range": "39551__39570"}
     * Output: [start_ep => 21, end_ep => 40, episode_count => 20,
     *          start_video_id => "39551", end_video_id => "39570",
     *          is_contiguous => true, label => "Episodes 21-40"]
     */
    protected function parseRangeObject(array $range): array
    {
        $epsStr = trim($range['eps'] ?? '');
        $epsRangeStr = trim($range['eps_range'] ?? '');

        // Parse "eps" field: "  21-  40" or "  -  20"
        $startEp = 1;
        $endEp = 1;
        if (str_contains($epsStr, '-')) {
            $parts = explode('-', $epsStr);
            $startEp = (int)trim($parts[0] ?? '') ?: 1;
            $endEp = (int)trim($parts[1] ?? '');
        } elseif (is_numeric(trim($epsStr))) {
            $startEp = $endEp = (int)trim($epsStr);
        }
        $episodeCount = max(1, $endEp - $startEp + 1);

        // Parse "eps_range" field: "39531__39550"
        $startVid = '';
        $endVid = '';
        if (str_contains($epsRangeStr, '__')) {
            $parts = explode('__', $epsRangeStr);
            $startVid = trim($parts[0] ?? '');
            $endVid = trim($parts[1] ?? '');
        }

        // Check if contiguous (video ID span == episode count)
        $vidSpan = ($startVid && $endVid) ? ((int)$endVid - (int)$startVid + 1) : 0;
        $isContiguous = ($vidSpan === $episodeCount);

        return [
            'start_ep' => $startEp,
            'end_ep' => $endEp,
            'episode_count' => $episodeCount,
            'start_video_id' => $startVid,
            'end_video_id' => $endVid,
            'is_contiguous' => $isContiguous,
            'video_id_span' => $vidSpan,
            'label' => "Episodes {$startEp}-{$endEp}",
            'raw_eps' => $epsStr,
            'raw_eps_range' => $epsRangeStr,
        ];
    }

    // ─────────────────────────────────────────────
    //  INTERNAL: Metadata & Helpers
    // ─────────────────────────────────────────────

    protected function refreshSeriesMetadata(SeriesMovie $series): void
    {
        $totalEps = MovieModel::where('category_id', $series->id)->count();
        $activeEps = MovieModel::where('category_id', $series->id)->where('status', 'Active')->count();

        $series->total_episodes = $totalEps;
        if ($totalEps > 0 && $activeEps > 0) {
            $series->is_active = 'Yes';
        }

        // Clean the series title (remove trailing episode numbers)
        $cleanTitle = $this->cleanSeriesTitle($series->title);
        if ($cleanTitle !== $series->title) {
            Log::info("[SeriesFixer] Cleaned title: [{$series->title}] → [{$cleanTitle}]");
            $series->title = $cleanTitle;
        }

        $series->save();
    }

    /**
     * Check if ALL remote episodes have been fetched for this series.
     * If so, mark the series as active and update metadata.
     *
     * Called after completing all ranges or when chain_ended is detected on the last range.
     */
    public function checkAndActivateSeries(int $seriesId): array
    {
        $series = SeriesMovie::find($seriesId);
        if (!$series) return ['activated' => false, 'reason' => 'Series not found'];

        $localCount = MovieModel::where('category_id', $seriesId)->count();
        $activeCount = MovieModel::where('category_id', $seriesId)->where('status', 'Active')->count();

        // Try to get remote episode count (from ranges if we can without an API call)
        $remoteCount = $series->total_episodes;

        $result = [
            'local_count' => $localCount,
            'active_count' => $activeCount,
            'remote_count' => $remoteCount,
            'activated' => false,
            'title_cleaned' => false,
        ];

        // Clean the series title
        $cleanTitle = $this->cleanSeriesTitle($series->title);
        if ($cleanTitle !== $series->title) {
            $series->title = $cleanTitle;
            $result['title_cleaned'] = true;
            $result['new_title'] = $cleanTitle;
        }

        // Determine if series should be activated:
        // If we have local episodes with active status, mark series active
        if ($activeCount > 0 && $localCount > 0) {
            $series->is_active = 'Yes';
            $series->total_episodes = $localCount;
            $series->muno_processed = 'Yes';
            $result['activated'] = true;
            $result['reason'] = "Activated: {$activeCount}/{$localCount} episodes active";
            Log::info("[SeriesFixer] Activated series #{$seriesId} '{$series->title}': {$activeCount}/{$localCount} eps active");
        }

        $series->save();
        return $result;
    }

    /**
     * Clean a series title by removing trailing episode/part numbers.
     *
     * Examples:
     *   "Winds of Love 139"          → "Winds of Love"
     *   "House of The Dragon 10"      → "House of The Dragon"
     *   "A Woman In A Veil 80"        → "A Woman In A Veil"
     *   "Echo 3"                      → "Echo 3"  (kept — "3" is part of the actual title)
     *   "Pearl Harbor 1"              → "Pearl Harbor"
     *   "Heroes 14"                   → "Heroes"
     *
     * Logic: removes trailing number if number > 1 OR if it matches common patterns.
     * Short titles (≤2 words) keep their numbers to avoid breaking real titles like "Echo 3".
     */
    public function cleanSeriesTitle(string $title): string
    {
        $title = trim($title);

        // Patterns ordered from most specific to least specific.
        // We loop so compound suffixes are stripped layer by layer.
        $patterns = [
            // Season + Episode / Part combos (most specific first)
            '/\s*Season\s+[IVXLC]+\s*:\s*(?:Part|Episode)\s*\d+\s*$/i',  // "Season II: Part 1"
            '/\s*Season\s*\d+\s*:\s*Episode\s*\d+\s*$/i',                 // "Season 2: Episode 1"
            '/\s*-\s*Episode\s*\d+\s*$/i',                                // "- Episode 5"
            '/\s*:\s*Episode\s*\d+\s*$/i',                                // ": Episode 5"
            '/\s*Episode\s*\d+\s*$/i',                                    // "Episode 5"
            '/\s*EPS?\s*\d+\s*$/i',                                       // "EP 5" / "EPS 5"
            '/\s*:\s*Part\s*\d+\s*$/i',                                   // ": Part 5"
            '/\s*-\s*Part\s*\d+\s*$/i',                                   // "- Part 5"
            '/\s*Part\s*\d+\s*$/i',                                       // "Part 5"
            '/\s*Season\s+[IVXLC]+\s*$/i',                                // "Season II"
            '/\s*Season\s*\d+\s*$/i',                                     // "Season 2"
            '/\s+\d+\s*-\s*\d+\s*$/i',                                    // "1 - 2" range
        ];

        // Keep looping until no pattern matches (handles stacked suffixes)
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($patterns as $pat) {
                $cleaned = preg_replace($pat, '', $title);
                if ($cleaned !== $title && strlen(trim($cleaned)) > 2) {
                    $title = trim($cleaned);
                    $changed = true;
                    break; // restart from top after each strip
                }
            }
        }

        // Clean any trailing punctuation left behind (orphan colon, dash, comma)
        $title = preg_replace('/[\s:,\-]+$/', '', $title);

        // Smart trailing-number removal
        //   >=5  → always remove  (very likely episode number)
        //   3-4  → remove only if base has 2+ words  (protect "Echo 3")
        //   1-2  → never remove   (protect "Ludik 2", "Pearl Harbor 1")
        if (preg_match('/^(.+?)\s+(\d+)\s*$/', $title, $m)) {
            $basePart   = trim($m[1]);
            $trailingNum = (int) $m[2];
            $wordCount   = str_word_count($basePart);

            if ($trailingNum >= 5 || ($trailingNum >= 3 && $wordCount >= 2)) {
                $title = $basePart;
            }
        }

        return trim($title);
    }

    protected function extractEpisodeNumber(string $title): ?int
    {
        if (preg_match('/S\d+E(\d+)/i', $title, $m)) return (int)$m[1];
        if (preg_match('/(?:EP|EPS|Episode)\s*(\d+)/i', $title, $m)) return (int)$m[1];
        if (preg_match('/Part\s*(\d+)/i', $title, $m)) return (int)$m[1];
        // Try trailing number: "A Woman in a Veil 42"
        if (preg_match('/\b(\d+)\s*$/', $title, $m)) return (int)$m[1];
        return null;
    }

    protected function extractSeasonNumber(string $title): ?int
    {
        if (preg_match('/S(\d+)E\d+/i', $title, $m)) return (int)$m[1];
        if (preg_match('/Season\s*(\d+)/i', $title, $m)) return (int)$m[1];
        return null;
    }

    // ─────────────────────────────────────────────
    //  DATA FORMATTERS
    // ─────────────────────────────────────────────

    protected function seriesToArray(SeriesMovie $series): array
    {
        return [
            'id'              => $series->id,
            'title'           => $series->title,
            'category'        => $series->Category,
            'description'     => $series->description,
            'thumbnail'       => $series->thumbnail,
            'total_seasons'   => $series->total_seasons,
            'total_episodes'  => $series->total_episodes,
            'total_views'     => $series->total_views,
            'is_active'       => $series->is_active,
            'external_url'    => $series->external_url,
            'vj'              => $series->vj,
            'genre'           => $series->genre,
            'language'        => $series->language,
            'year'            => $series->year,
            'series_code'     => $series->series_code,
            'munowatch_id'    => $series->munowatch_id,
            'is_muno'         => $series->is_muno ?? 'No',
            'created_at'      => $series->created_at?->toDateTimeString(),
        ];
    }

    protected function episodeToArray(MovieModel $ep): array
    {
        return [
            'id'              => $ep->id,
            'title'           => $ep->title,
            'url'             => $ep->url,
            'external_url'    => $ep->external_url,
            'type'            => $ep->type,
            'status'          => $ep->status,
            'thumbnail_url'   => $ep->thumbnail_url,
            'category_id'     => $ep->category_id,
            'category'        => $ep->category,
            'episode_number'  => $ep->episode_number,
            'season_number'   => $ep->season_number ?? '1',
            'series_title'    => $ep->series_title,
            'episode_title'   => $ep->episode_title,
            'duration'        => $ep->duration,
            'vj'              => $ep->vj,
            'genre'           => $ep->genre,
            'munowatch_id'    => $ep->munowatch_id,
            'views_count'     => $ep->views_count,
            'is_muno'         => $ep->is_muno,
        ];
    }

    /**
     * True if the URL is a real playable URL (not empty, not a dummy, not a broken GCS/Firebase link).
     */
    protected static function isHealthyEpisodeUrl(?string $url): bool
    {
        if (empty($url) || strlen(trim($url)) < 10) return false;
        if (stripos($url, 'eli.mp4')         !== false) return false;
        if (stripos($url, 'googleapis')      !== false) return false;
        if (stripos($url, 'firebasestorage') !== false) return false;
        return true;
    }
}
