<?php

namespace App\Services;

use App\Models\MovieCrawlerPage;
use App\Models\MovieModel;
use App\Models\SeriesMovie;
use App\Models\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SeriesFixerService — Manages series-level operations: fetch episodes from remote API,
 * fix individual episodes, repair broken series records, and provide data for the debug player.
 *
 * Usage:
 *   $fixer = new SeriesFixerService();
 *   $result = $fixer->getSeriesInfo($seriesId);           // Full series data for debug player
 *   $result = $fixer->fixSeries($seriesId);                // Fix series + all episodes
 *   $result = $fixer->fixEpisode($movieId);                // Fix a single episode (delegates to MovieFixerService)
 *   $result = $fixer->fetchRemoteEpisodes($seriesId);      // Fetch episode list from munowatch API
 *   $result = $fixer->syncEpisodesFromRemote($seriesId);   // Sync local DB with remote episodes
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
    //  PUBLIC API
    // ─────────────────────────────────────────────

    /**
     * Get comprehensive series info for the debug player.
     * Returns the series record + all episodes with their data.
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

        // Group episodes by season
        $seasons = [];
        foreach ($episodes as $ep) {
            $sn = $ep->season_number ?: '1';
            if (!isset($seasons[$sn])) {
                $seasons[$sn] = [];
            }
            $seasons[$sn][] = $this->episodeToArray($ep);
        }

        return [
            'success'  => true,
            'series'   => $this->seriesToArray($series),
            'episodes' => $episodes->map(fn($ep) => $this->episodeToArray($ep))->all(),
            'seasons'  => $seasons,
            'total_episodes' => $episodes->count(),
            'total_seasons'  => count($seasons),
        ];
    }

    /**
     * Fetch episode data from the remote munowatch API for a series.
     * Does NOT modify local DB — just returns what the API says.
     */
    public function fetchRemoteEpisodes(int $seriesId): array
    {
        $series = SeriesMovie::find($seriesId);
        if (!$series) {
            return ['success' => false, 'error' => "Series not found (ID: {$seriesId})"];
        }

        // Determine the munowatch video ID and series code
        $seriesCode = $series->series_code ?? $series->munowatch_id ?? null;
        $munowatchId = $series->munowatch_id ?? null;

        if (empty($seriesCode) && empty($munowatchId)) {
            // Try to get from an episode's API data
            $sampleEpisode = MovieModel::where('category_id', $seriesId)
                ->whereNotNull('munowatch_id')
                ->first();
            if ($sampleEpisode) {
                $munowatchId = $sampleEpisode->munowatch_id;
                // Fetch the preview for this episode to get series_code
                $previewResult = $this->fetchMunowatchPreview($munowatchId);
                if ($previewResult['success'] && !empty($previewResult['preview']['series_code'])) {
                    $seriesCode = $previewResult['preview']['series_code'];
                    // Store it back on the series for future use
                    $series->series_code = $seriesCode;
                    $series->save();
                }
            }
        }

        if (empty($seriesCode)) {
            return ['success' => false, 'error' => 'No series_code or munowatch_id found for this series. Cannot fetch remote episodes.'];
        }

        // Fetch the episode range from munowatch episodes API
        return $this->fetchEpisodesFromApi($seriesCode, $munowatchId);
    }

    /**
     * Fix a complete series: refresh series metadata + fix all episodes.
     */
    public function fixSeries(int $seriesId, int $maxEpisodes = 200): array
    {
        $series = SeriesMovie::find($seriesId);
        if (!$series) {
            return ['success' => false, 'error' => "Series not found (ID: {$seriesId})"];
        }

        set_time_limit(max(1800, $maxEpisodes * 15));
        ini_set('memory_limit', '512M');

        $results = [
            'series_id' => $seriesId,
            'series_title' => $series->title,
            'fixed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'episodes' => [],
        ];

        // Step 1: Try to sync episodes from remote
        $syncResult = $this->syncEpisodesFromRemote($seriesId);
        $results['sync'] = $syncResult;

        // Step 2: Fix each local episode using MovieFixerService
        $episodes = MovieModel::where('category_id', $seriesId)
            ->orderBy('id', 'asc')
            ->limit($maxEpisodes)
            ->get();

        foreach ($episodes as $ep) {
            try {
                $fixResult = $this->movieFixer->fix($ep->id);
                if ($fixResult['success'] ?? false) {
                    $results['fixed']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Ep #{$ep->id}: " . ($fixResult['message'] ?? 'Unknown error');
                }
                $results['episodes'][] = [
                    'id' => $ep->id,
                    'title' => $ep->title,
                    'success' => $fixResult['success'] ?? false,
                    'message' => $fixResult['message'] ?? '',
                ];
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = "Ep #{$ep->id}: Exception: " . $e->getMessage();
                $results['episodes'][] = [
                    'id' => $ep->id,
                    'title' => $ep->title,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        // Step 3: Update series metadata
        $this->refreshSeriesMetadata($series);

        $results['success'] = true;
        $results['message'] = "Series fix complete: {$results['fixed']} fixed, {$results['failed']} failed out of " . $episodes->count() . " episodes.";

        return $results;
    }

    /**
     * Sync episodes from the remote API into the local database.
     * Creates missing episodes, discovers new ones from the API.
     */
    public function syncEpisodesFromRemote(int $seriesId): array
    {
        $remoteResult = $this->fetchRemoteEpisodes($seriesId);
        if (!($remoteResult['success'] ?? false)) {
            return ['success' => false, 'error' => $remoteResult['error'] ?? 'Failed to fetch remote episodes'];
        }

        $series = SeriesMovie::find($seriesId);
        $remoteEpisodes = $remoteResult['episodes'] ?? [];
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($remoteEpisodes as $remoteEp) {
            try {
                $munowatchId = $remoteEp['id'] ?? null;
                if (empty($munowatchId)) continue;

                // Check if we already have this episode
                $existing = MovieModel::where('category_id', $seriesId)
                    ->where('munowatch_id', $munowatchId)
                    ->first();

                if ($existing) {
                    // Update URL if we got a fresh one
                    if (!empty($remoteEp['playingUrl']) && $remoteEp['playingUrl'] !== $existing->url) {
                        $existing->url = $remoteEp['playingUrl'];
                        $existing->save();
                        $updated++;
                    }
                    continue;
                }

                // Create new episode record
                $newEpisode = new MovieModel();
                $newEpisode->title = $remoteEp['title'] ?? ($series->title . ' - Episode');
                $newEpisode->url = $remoteEp['playingUrl'] ?? '';
                $newEpisode->external_url = self::MUNOWATCH_API_BASE . '/preview/v2/' . $munowatchId . '/' . self::MUNOWATCH_USER_ID;
                $newEpisode->munowatch_id = $munowatchId;
                $newEpisode->type = 'Series';
                $newEpisode->category_id = $seriesId;
                $newEpisode->category = $series->title;
                $newEpisode->status = 'Active';
                $newEpisode->is_muno = 'Yes';
                $newEpisode->muno_processed = 'Yes';
                $newEpisode->thumbnail_url = $remoteEp['thumbnail'] ?? $series->thumbnail;
                $newEpisode->vj = $remoteEp['vj'] ?? $series->vj;
                $newEpisode->duration = $remoteEp['duration'] ?? null;
                $newEpisode->genre = $remoteEp['genre'] ?? $series->genre;

                // Try to extract episode/season numbers from title
                $epNum = $this->extractEpisodeNumber($remoteEp['title'] ?? '');
                $snNum = $this->extractSeasonNumber($remoteEp['title'] ?? '');
                if ($epNum) $newEpisode->episode_number = $epNum;
                if ($snNum) $newEpisode->season_number = $snNum;
                $newEpisode->series_title = $series->title;

                $newEpisode->save();
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Episode ID {$munowatchId}: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'created' => $created,
            'updated' => $updated,
            'remote_total' => count($remoteEpisodes),
            'errors' => $errors,
        ];
    }

    /**
     * Fix a single episode (delegates to MovieFixerService).
     */
    public function fixEpisode(int $movieId): array
    {
        return $this->movieFixer->fix($movieId);
    }

    // ─────────────────────────────────────────────
    //  INTERNAL HELPERS
    // ─────────────────────────────────────────────

    /**
     * Fetch a preview from munowatch API for a given video ID.
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
            if (empty($raw)) {
                return ['success' => false, 'error' => 'Empty response from ' . $apiUrl];
            }

            $json = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'JSON parse error: ' . json_last_error_msg()];
            }

            $preview = $json['preview'] ?? $json['movie'] ?? $json['data'] ?? $json;

            return [
                'success' => true,
                'preview' => $preview,
                'raw'     => $raw,
                'api_url' => $apiUrl,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'API request failed: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch the episodes list from the munowatch episodes range API.
     */
    protected function fetchEpisodesFromApi(string $seriesCode, ?string $showId = null): array
    {
        // If showId not available, use series_code as the show ID
        $showId = $showId ?: $seriesCode;

        $apiUrl = self::MUNOWATCH_API_BASE . "/episodes/range/{$showId}/{$seriesCode}/1";

        try {
            $headers = [
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'User-Agent'    => 'okhttp/4.9.0',
                'Authorization' => 'Bearer ' . self::MUNOWATCH_JWT,
                'X-Api-Key'     => self::MUNOWATCH_JWT,
            ];

            $raw = Utils::get_url_with_auth($apiUrl, $headers);
            if (empty($raw)) {
                return ['success' => false, 'error' => 'Empty response from episodes API: ' . $apiUrl];
            }

            $json = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'Episodes JSON parse error: ' . json_last_error_msg()];
            }

            // Parse the eps_range format: can be "1-10", "EPISODE_1__EPISODE_10", "1,2,3", or list of items
            $episodes = $this->parseEpisodesResponse($json, $seriesCode);

            return [
                'success'  => true,
                'episodes' => $episodes,
                'api_url'  => $apiUrl,
                'raw'      => $raw,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Episodes API request failed: ' . $e->getMessage()];
        }
    }

    /**
     * Parse the munowatch episodes API response into a normalized array.
     * The API can return various formats: items array, eps_range string, etc.
     */
    protected function parseEpisodesResponse(array $json, string $seriesCode): array
    {
        $episodes = [];

        // Format 1: Direct items array (preferred — has full episode data)
        if (!empty($json['items']) && is_array($json['items'])) {
            foreach ($json['items'] as $idx => $item) {
                $episodes[] = [
                    'id'         => $item['id'] ?? null,
                    'title'      => $item['video_title'] ?? $item['title'] ?? "Episode " . ($idx + 1),
                    'playingUrl' => $item['playingUrl'] ?? $item['playingurl'] ?? '',
                    'thumbnail'  => $item['thumbnail'] ?? $item['image'] ?? '',
                    'duration'   => $item['duration'] ?? $item['secduration'] ?? '',
                    'vj'         => $item['vjname'] ?? $item['vj'] ?? '',
                    'genre'      => $item['genre'] ?? '',
                    'size'       => $item['size'] ?? '',
                    'episode_index' => $idx + 1,
                ];
            }
            return $episodes;
        }

        // Format 2: eps_range (e.g., "EPISODE_1__EPISODE_10" or "1-10" or "1,2,3")
        $epsRange = $json['eps_range'] ?? $json['epsRange'] ?? '';
        if (!empty($epsRange)) {
            $ids = $this->parseEpsRange($epsRange);
            foreach ($ids as $idx => $epId) {
                $episodes[] = [
                    'id'         => $epId,
                    'title'      => "Episode " . ($idx + 1),
                    'playingUrl' => '',
                    'thumbnail'  => '',
                    'duration'   => '',
                    'vj'         => '',
                    'genre'      => '',
                    'size'       => '',
                    'episode_index' => $idx + 1,
                ];
            }
            return $episodes;
        }

        // Format 3: Preview-level data with episode metadata
        if (!empty($json['preview']) && is_array($json['preview'])) {
            $preview = $json['preview'];
            // Single episode data
            $episodes[] = [
                'id'         => $preview['id'] ?? null,
                'title'      => $preview['video_title'] ?? 'Episode 1',
                'playingUrl' => $preview['playingUrl'] ?? '',
                'thumbnail'  => $preview['thumbnail'] ?? '',
                'duration'   => $preview['duration'] ?? '',
                'vj'         => $preview['vjname'] ?? '',
                'genre'      => $preview['genre'] ?? '',
                'size'       => $preview['size'] ?? '',
                'episode_index' => 1,
            ];
        }

        return $episodes;
    }

    /**
     * Parse munowatch eps_range string into an array of episode IDs.
     * Handles formats: "EPISODE_1__EPISODE_10", "1-10", "1,2,3,4,5"
     */
    protected function parseEpsRange(string $range): array
    {
        $range = trim($range);

        // Format: EPISODE_1__EPISODE_10
        if (str_contains($range, '__')) {
            $parts = explode('__', $range);
            $start = (int) preg_replace('/\D/', '', $parts[0] ?? '1');
            $end   = (int) preg_replace('/\D/', '', end($parts));
            if ($start > 0 && $end >= $start) {
                return range($start, $end);
            }
        }

        // Format: 1-10
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $range, $m)) {
            return range((int)$m[1], (int)$m[2]);
        }

        // Format: 1,2,3,4,5
        if (str_contains($range, ',')) {
            return array_map('intval', array_filter(explode(',', $range)));
        }

        // Single number
        if (is_numeric($range)) {
            return [(int)$range];
        }

        return [];
    }

    /**
     * Refresh the parent series metadata: episode counts, status, etc.
     */
    protected function refreshSeriesMetadata(SeriesMovie $series): void
    {
        $totalEpisodes = MovieModel::where('category_id', $series->id)->count();
        $activeEpisodes = MovieModel::where('category_id', $series->id)
            ->where('status', 'Active')
            ->count();

        $series->total_episodes = $totalEpisodes;
        if ($totalEpisodes > 0 && $activeEpisodes > 0) {
            $series->is_active = 'Yes';
        }
        $series->save();
    }

    /**
     * Extract episode number from a title string.
     */
    protected function extractEpisodeNumber(string $title): ?int
    {
        // S01E03, S1E3
        if (preg_match('/S\d+E(\d+)/i', $title, $m)) return (int)$m[1];
        // EP 3, EPS 3, Episode 3
        if (preg_match('/(?:EP|EPS|Episode)\s*(\d+)/i', $title, $m)) return (int)$m[1];
        // Part 3
        if (preg_match('/Part\s*(\d+)/i', $title, $m)) return (int)$m[1];
        return null;
    }

    /**
     * Extract season number from a title string.
     */
    protected function extractSeasonNumber(string $title): ?int
    {
        if (preg_match('/S(\d+)E\d+/i', $title, $m)) return (int)$m[1];
        if (preg_match('/Season\s*(\d+)/i', $title, $m)) return (int)$m[1];
        return null;
    }

    // ─────────────────────────────────────────────
    //  DATA FORMATTERS
    // ─────────────────────────────────────────────

    /**
     * Convert a SeriesMovie model to an array for API/UI consumption.
     */
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

    /**
     * Convert a MovieModel (episode) to an array for API/UI consumption.
     */
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
}
