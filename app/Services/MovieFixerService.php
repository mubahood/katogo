<?php

namespace App\Services;

use App\Models\MovieCrawlerPage;
use App\Models\MovieCrawlerWebsite;
use App\Models\MovieModel;
use App\Models\SeriesMovie;
use App\Models\Utils;
use App\Models\VideoPlaybackFailure;
use Illuminate\Support\Facades\Log;

/**
 * MovieFixerService — Re-fetches movie data from its original source and repairs broken records.
 *
 * Designed for both single-movie fixes (debug player "Fix Movie" button) and batch processing.
 *
 * Usage:
 *   $fixer = new MovieFixerService();
 *
 *   // Fix a single movie
 *   $result = $fixer->fix($movieId);
 *
 *   // Batch fix
 *   $results = $fixer->fixBatch([101, 102, 103]);
 *
 *   // Fix all movies with pending playback failures
 *   $results = $fixer->fixAllPendingFailures($limit);
 *
 * @package App\Services
 */
class MovieFixerService
{
    /**
     * Munowatch user ID for preview API calls (matches mobile app).
     */
    protected const MUNOWATCH_USER_ID = 169464;

    /**
     * Munowatch website ID in movie_crawler_websites table.
     */
    protected const MUNOWATCH_WEBSITE_ID = 2;

    /**
     * Munowatch API base URL.
     */
    protected const MUNOWATCH_API_BASE = 'https://munowatch.org/api';

    /**
     * Hardcoded JWT token for munowatch API (matches MovieCrawlerPage).
     */
    protected const MUNOWATCH_JWT = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';

    /**
     * Valid video file extensions (matches MovieCrawlerPage logic).
     */
    protected const VALID_VIDEO_EXTENSIONS = [
        'mp4', 'mkv', 'avi', 'flv', 'wmv', 'mov', 'webm', 'mpeg',
        'mpg', 'm4v', '3gp', '3g2', 'f4v', 'f4p', 'ts', 'vob',
        'ogv', 'ogg', 'rm', 'rmvb', 'asf', 'divx', 'xvid',
    ];

    protected VideoUrlTester $urlTester;

    public function __construct()
    {
        $this->urlTester = new VideoUrlTester();
    }

    // ─────────────────────────────────────────────
    //  PUBLIC API
    // ─────────────────────────────────────────────

    /**
     * Fix a single movie by ID.
     *
     * Returns an associative array:
     *  - success   (bool)
     *  - message   (string)   Human-readable result
     *  - movie     (array)    Updated movie data (for UI refresh)
     *  - old_url   (string)   Previous video URL
     *  - new_url   (string)   New video URL (if changed)
     *  - changes   (array)    List of fields that were updated
     *
     * @param  int $movieId
     * @return array
     */
    public function fix(int $movieId): array
    {
        $movie = MovieModel::find($movieId);
        if (!$movie) {
            return $this->fail('Movie not found (ID: ' . $movieId . ')');
        }

        return $this->fixMovie($movie);
    }

    /**
     * Fix multiple movies by ID.
     *
     * @param  int[] $movieIds
     * @return array  ['total', 'fixed', 'failed', 'results' => [...]]
     */
    public function fixBatch(array $movieIds): array
    {
        $results = [];
        $fixed = 0;
        $failed = 0;

        foreach ($movieIds as $id) {
            $result = $this->fix((int) $id);
            $results[] = $result;
            if ($result['success']) {
                $fixed++;
            } else {
                $failed++;
            }
        }

        return [
            'total'   => count($movieIds),
            'fixed'   => $fixed,
            'failed'  => $failed,
            'results' => $results,
        ];
    }

    /**
     * Fix all movies that have pending (unresolved) playback failures.
     *
     * @param  int $limit  Max movies to process
     * @return array       Batch result
     */
    public function fixAllPendingFailures(int $limit = 50): array
    {
        $movieIds = VideoPlaybackFailure::where('status', 'pending')
            ->where(function ($q) {
                $q->where('fix_status', 'PENDING')
                  ->orWhereNull('fix_status');
            })
            ->whereNotNull('movie_id')
            ->distinct()
            ->limit($limit)
            ->pluck('movie_id')
            ->toArray();

        if (empty($movieIds)) {
            return [
                'total'   => 0,
                'fixed'   => 0,
                'failed'  => 0,
                'results' => [],
                'message' => 'No pending failures to fix',
            ];
        }

        return $this->fixBatch($movieIds);
    }

    // ─────────────────────────────────────────────
    //  CORE FIX LOGIC
    // ─────────────────────────────────────────────

    /**
     * Check if a movie is a series episode.
     */
    protected function isSeries(MovieModel $movie): bool
    {
        return $movie->type === 'Series';
    }

    /**
     * Core fix logic for a single movie.
     *
     * Fetches fresh data from the original server and saves it exactly as received.
     * The video URL (playingUrl) is stored as-is — no modification, no fallback construction.
     *
     * Series-aware: For episodes (type='Series'), protects category_id (FK to SeriesMovie),
     * episode_number, season_number, series_title, episode_title, is_first_episode, and type.
     *
     * Misclassification reversal: If a movie is currently type='Series' but fresh data shows
     * it's actually a standalone movie, reverses it back to type='Movie' and removes it from
     * its parent series.
     */
    protected function fixMovie(MovieModel $movie): array
    {
        $movieId  = $movie->id;
        $oldUrl   = $movie->getRawOriginal('url') ?? $movie->url;
        $isSeries = $this->isSeries($movie);

        Log::info("[MovieFixer] Starting fix for " . ($isSeries ? 'series episode' : 'movie') . " #{$movieId}: {$movie->title}");

        // Increment fix attempts on all related failures FIRST
        $this->incrementFixAttempts($movieId);

        try {
            // Step 1: Determine the source platform
            $platform = $this->detectPlatform($movie);

            if ($platform === 'unknown') {
                return $this->failMovie($movie, 'Cannot determine source platform. No munowatch_id, external_url, or page_source_url found.');
            }

            // Step 2: Fetch fresh data from the original server
            $freshData = $this->fetchFreshData($movie, $platform);

            if (!$freshData['success']) {
                return $this->failMovie($movie, $freshData['error']);
            }

            $preview = $freshData['preview'];

            // Step 2b: MISCLASSIFICATION CHECK — if currently Series but fresh data says Movie, reverse it
            if ($isSeries && $this->shouldReverseToMovie($movie, $preview)) {
                $reversalChanges = $this->reverseToMovie($movie, $preview);
                $isSeries = false; // No longer a series episode
                Log::info("[MovieFixer] #{$movieId}: REVERSED from Series to Movie. Changes: " . json_encode(array_keys($reversalChanges)));
            }

            // Step 3: Extract the video URL exactly as the server returned it
            $newUrl = $this->extractBestVideoUrl($preview);

            if (empty($newUrl)) {
                return $this->failMovie($movie, 'Remote server returned data but no valid video URL found in playingUrl, embedurl, or openload fields.');
            }

            Log::info("[MovieFixer] #{$movieId}: Got URL from server: {$newUrl}");

            // Step 4: Apply fresh data to the movie record (URL saved exactly as fetched)
            // Series-aware: protects series FK and episode fields
            $changes = $this->applyFreshData($movie, $preview, $newUrl, $isSeries);

            // Step 4b: For series episodes, sync series-level metadata to parent SeriesMovie
            if ($isSeries) {
                $this->syncSeriesMetadata($movie, $preview);
            }

            // Step 5: Mark movie as active and processed, save raw response from external server
            $movie->status         = 'Active';
            $movie->muno_processed = 'Yes';
            $movie->muno_success   = 'Yes';
            $movie->muno_message   = $freshData['raw_response'] ?? json_encode($preview);
            $movie->error_message  = null;

            // Ensure page_source_url and external_url store the API URL used
            if (!empty($freshData['api_url'])) {
                if (empty($movie->page_source_url) || !str_contains($movie->page_source_url, 'munowatch.org/api/')) {
                    $movie->page_source_url = $freshData['api_url'];
                }
                if (empty($movie->external_url) || !str_contains($movie->external_url, 'munowatch.org/api/')) {
                    $movie->external_url = $freshData['api_url'];
                }
            }

            $movie->save();

            // Step 6: Update the crawler page record if it exists
            $this->updateCrawlerPage($movie, $freshData['raw_response'] ?? null);

            // Step 7: Mark related playback failures as FIXED
            $this->markFailuresFixed($movieId, $changes, true);

            // Build success result
            $msg = 'Movie fixed successfully.';
            if (!empty($changes)) {
                $msg .= ' Updated: ' . implode(', ', array_keys($changes)) . '.';
            }

            Log::info("[MovieFixer] #{$movieId}: {$msg}");

            return [
                'success'   => true,
                'message'   => $msg,
                'movie'     => $this->movieToArray($movie),
                'old_url'   => $oldUrl,
                'new_url'   => $newUrl,
                'changes'   => $changes,
                'preview'   => $preview,
            ];

        } catch (\Throwable $e) {
            Log::error("[MovieFixer] #{$movieId}: Exception: " . $e->getMessage());
            return $this->failMovie($movie, 'Exception: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    //  PLATFORM DETECTION
    // ─────────────────────────────────────────────

    /**
     * Detect which platform a movie originated from.
     *
     * Uses multiple signals: is_muno, munowatch_id, external_url, page_source_url, url patterns.
     *
     * @param  MovieModel $movie
     * @return string  'munowatch' | 'myvj' | 'ugandahotgirls' | 'unknown'
     */
    public function detectPlatform(MovieModel $movie): string
    {
        // Signal 1: is_muno flag
        if ($movie->is_muno === 'Yes') {
            return 'munowatch';
        }

        // Signal 2: munowatch_id present
        if (!empty($movie->munowatch_id)) {
            return 'munowatch';
        }

        // Signal 3: external_url or page_source_url contains platform hints
        $checkUrls = array_filter([
            $movie->external_url ?? '',
            $movie->page_source_url ?? '',
            $movie->getRawOriginal('url') ?? '',
        ]);

        foreach ($checkUrls as $u) {
            $lower = strtolower($u);
            if (str_contains($lower, 'munowatch')) {
                return 'munowatch';
            }
            if (str_contains($lower, 'ugawatch') || str_contains($lower, 'myvj')) {
                return 'myvj';
            }
            if (str_contains($lower, 'ugandahotgirls')) {
                return 'ugandahotgirls';
            }
        }

        // Signal 4: URL on munoserver / BunnyCDN → likely munowatch
        foreach ($checkUrls as $u) {
            $lower = strtolower($u);
            if (str_contains($lower, 'munoserver') || str_contains($lower, 'munotek.b-cdn.net')) {
                return 'munowatch';
            }
        }

        // Signal 5: stars/imdb_url contains MyVj
        if ($movie->stars === 'MyVj' || $movie->imdb_url === 'MyVj') {
            return 'myvj';
        }

        return 'unknown';
    }

    // ─────────────────────────────────────────────
    //  DATA FETCHING
    // ─────────────────────────────────────────────

    /**
     * Fetch fresh data from the movie's original source.
     *
     * @param  MovieModel $movie
     * @param  string     $platform
     * @return array      ['success' => bool, 'preview' => array|null, 'raw_response' => string|null, 'error' => string|null]
     */
    public function fetchFreshData(MovieModel $movie, string $platform): array
    {
        return match ($platform) {
            'munowatch'      => $this->fetchFromMunowatch($movie),
            'myvj'           => $this->fetchFromMyVj($movie),
            'ugandahotgirls' => $this->fail('UgandaHotGirls platform auto-fix not yet supported'),
            default          => $this->fail('Unknown platform: ' . $platform),
        };
    }

    /**
     * Fetch fresh movie data from Munowatch API.
     *
     * The API URL is typically already stored on the movie record in page_source_url
     * or external_url (e.g. https://munowatch.org/api/preview/v2/{videoId}/{userId}).
     * We use that URL directly — same as the mobile app does.
     *
     * Fallback: construct the URL from munowatch_id or external_id.
     */
    protected function fetchFromMunowatch(MovieModel $movie): array
    {
        $apiUrl = null;

        // Strategy 1 (PRIMARY): Use page_source_url or external_url from the movie record.
        // These already contain the full API URL with the correct user ID.
        foreach (['page_source_url', 'external_url'] as $field) {
            $url = trim($movie->$field ?? '');
            if (!empty($url) && str_contains($url, 'munowatch.org/api/')) {
                $apiUrl = $url;
                Log::info("[MovieFixer] #{$movie->id}: Using {$field} → {$apiUrl}");
                break;
            }
        }

        // Strategy 2: Construct URL from munowatch_id
        if (empty($apiUrl) && !empty($movie->munowatch_id) && is_numeric($movie->munowatch_id)) {
            $apiUrl = self::MUNOWATCH_API_BASE . '/preview/v2/' . $movie->munowatch_id . '/' . self::MUNOWATCH_USER_ID;
            Log::info("[MovieFixer] #{$movie->id}: Constructed from munowatch_id={$movie->munowatch_id} → {$apiUrl}");
        }

        // Strategy 3: Construct URL from external_id
        if (empty($apiUrl) && !empty($movie->external_id) && is_numeric($movie->external_id)) {
            $apiUrl = self::MUNOWATCH_API_BASE . '/preview/v2/' . $movie->external_id . '/' . self::MUNOWATCH_USER_ID;
            Log::info("[MovieFixer] #{$movie->id}: Constructed from external_id={$movie->external_id} → {$apiUrl}");
        }

        // Strategy 4 (last resort): Look up the associated MovieCrawlerPage
        if (empty($apiUrl)) {
            $crawler = $this->findCrawlerPage($movie);
            if ($crawler && str_contains($crawler->url ?? '', 'munowatch.org/api/')) {
                $apiUrl = $crawler->url;
                Log::info("[MovieFixer] #{$movie->id}: Using crawler page URL → {$apiUrl}");
            }
        }

        if (empty($apiUrl)) {
            return [
                'success' => false,
                'error'   => 'Could not find Munowatch API URL. No page_source_url, external_url, munowatch_id, or crawler page found.',
                'preview' => null,
                'raw_response' => null,
            ];
        }

        // Make the authenticated API call (matching mobile app headers exactly)
        try {
            $headers = [
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'User-Agent'    => 'okhttp/4.9.0',
                'Authorization' => 'Bearer ' . self::MUNOWATCH_JWT,
                'X-Api-Key'     => self::MUNOWATCH_JWT,
            ];

            $rawResponse = Utils::get_url_with_auth($apiUrl, $headers);

            if (empty($rawResponse)) {
                return [
                    'success'      => false,
                    'error'        => 'Munowatch API returned empty response from: ' . $apiUrl,
                    'preview'      => null,
                    'raw_response' => null,
                ];
            }

            // Parse JSON
            $jsonData = json_decode($rawResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success'      => false,
                    'error'        => 'Failed to parse Munowatch JSON: ' . json_last_error_msg() . '. Response starts with: ' . substr($rawResponse, 0, 200),
                    'preview'      => null,
                    'raw_response' => $rawResponse,
                ];
            }

            // Extract preview data using the same logic as MovieCrawlerPage
            $preview = $this->extractPreviewData($jsonData);

            if (empty($preview)) {
                return [
                    'success'      => false,
                    'error'        => 'Munowatch response parsed OK but no preview/movie/data found. Keys: ' . implode(', ', array_keys($jsonData)),
                    'preview'      => null,
                    'raw_response' => $rawResponse,
                ];
            }

            return [
                'success'      => true,
                'preview'      => $preview,
                'raw_response' => $rawResponse,
                'error'        => null,
                'api_url'      => $apiUrl,
            ];

        } catch (\Throwable $e) {
            return [
                'success'      => false,
                'error'        => 'Munowatch API request failed: ' . $e->getMessage(),
                'preview'      => null,
                'raw_response' => null,
            ];
        }
    }

    /**
     * Fetch fresh movie data from MyVJ / UgaWatch.
     *
     * MyVJ movies are HTML-parsed, which is fragile. This attempts a re-fetch.
     */
    protected function fetchFromMyVj(MovieModel $movie): array
    {
        $sourceUrl = $movie->page_source_url ?? $movie->external_url ?? null;

        if (empty($sourceUrl) || !str_contains($sourceUrl, 'ugawatch.com')) {
            // Try crawler page
            $crawler = $this->findCrawlerPage($movie);
            if ($crawler && !empty($crawler->url)) {
                $sourceUrl = $crawler->url;
            }
        }

        if (empty($sourceUrl)) {
            return [
                'success' => false,
                'error'   => 'No MyVJ/UgaWatch source URL available for re-fetch.',
                'preview' => null,
                'raw_response' => null,
            ];
        }

        try {
            $rawResponse = Utils::get_url($sourceUrl);

            if (empty($rawResponse)) {
                return [
                    'success'      => false,
                    'error'        => 'MyVJ returned empty response from: ' . $sourceUrl,
                    'preview'      => null,
                    'raw_response' => null,
                ];
            }

            // Parse HTML to extract video URL
            $videoUrl = $this->extractVideoUrlFromHtml($rawResponse);
            if (empty($videoUrl)) {
                return [
                    'success'      => false,
                    'error'        => 'Could not extract video URL from MyVJ HTML page.',
                    'preview'      => null,
                    'raw_response' => $rawResponse,
                ];
            }

            // Build a preview-like structure for consistency
            $preview = [
                'playingUrl'  => $videoUrl,
                'video_title' => $movie->title, // Keep existing title
                'thumbnail'   => $movie->thumbnail_url,
                '_source'     => 'myvj_html_parse',
            ];

            return [
                'success'      => true,
                'preview'      => $preview,
                'raw_response' => $rawResponse,
                'error'        => null,
            ];

        } catch (\Throwable $e) {
            return [
                'success'      => false,
                'error'        => 'MyVJ fetch failed: ' . $e->getMessage(),
                'preview'      => null,
                'raw_response' => null,
            ];
        }
    }

    // ─────────────────────────────────────────────
    //  DATA EXTRACTION
    // ─────────────────────────────────────────────

    /**
     * Extract preview data from a Munowatch API JSON response.
     * Mirrors MovieCrawlerPage::extractMovieDataFromResponse().
     *
     * @param  array $jsonData  Decoded JSON
     * @return array|null       The preview/movie data, or null if not found
     */
    public function extractPreviewData(array $jsonData): ?array
    {
        // Try standard paths (same as MovieCrawlerPage)
        if (!empty($jsonData['preview']) && is_array($jsonData['preview'])) {
            return $jsonData['preview'];
        }
        if (!empty($jsonData['movie']) && is_array($jsonData['movie'])) {
            return $jsonData['movie'];
        }
        if (!empty($jsonData['data']) && is_array($jsonData['data'])) {
            return $jsonData['data'];
        }

        // Try nested dashboard structures
        if (!empty($jsonData['dashboard']) && is_array($jsonData['dashboard'])) {
            foreach ($jsonData['dashboard'] as $section) {
                if (isset($section['movies']) && is_array($section['movies'])) {
                    return $section['movies'][0] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Extract the best video URL from preview data.
     * Priority: playingUrl > embedurl > openload.
     * Returns the URL exactly as the server provided it — no modifications.
     *
     * @param  array $preview
     * @return string|null
     */
    public function extractBestVideoUrl(array $preview): ?string
    {
        $candidates = [
            $preview['playingUrl'] ?? null,
            $preview['embedurl'] ?? null,
            $preview['openload'] ?? null,
        ];

        foreach ($candidates as $url) {
            if (!empty($url) && strlen(trim($url)) > 5) {
                return trim($url);
            }
        }

        return null;
    }

    /**
     * Extract a video URL from MyVJ HTML page content.
     * Uses three strategies matching MovieCrawlerPage::process_my_vj().
     */
    protected function extractVideoUrlFromHtml(string $html): ?string
    {
        // Strategy 1: <video> <source> tag
        if (preg_match('/<source[^>]+src=["\']([^"\']+\.mp4[^"\']*)["\']/', $html, $m)) {
            return $m[1];
        }

        // Strategy 2: Download button href
        if (preg_match('/href=["\']([^"\']+\.mp4[^"\']*)["\'][^>]*class=["\'][^"\']*download/i', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/class=["\'][^"\']*download[^"\']*["\'][^>]*href=["\']([^"\']+\.mp4[^"\']*)["\']/', $html, $m)) {
            return $m[1];
        }

        // Strategy 3: Any .mp4 link
        if (preg_match('/https?:\/\/[^\s"\'<>]+\.mp4/', $html, $m)) {
            return $m[0];
        }

        return null;
    }

    // ─────────────────────────────────────────────
    //  DATA APPLICATION
    // ─────────────────────────────────────────────

    /**
     * Apply fresh preview data onto a MovieModel, tracking what changed.
     *
     * Only overwrites fields where the new data is non-empty and different.
     * Series-aware: For episodes (type='Series'), NEVER overwrites:
     *   - category_id (FK to SeriesMovie parent)
     *   - category (series title)
     *   - type, episode_number, season_number, series_title, episode_title, is_first_episode
     *
     * @param  MovieModel $movie
     * @param  array      $preview   Fresh data from the API
     * @param  string     $newUrl    Validated video URL
     * @param  bool       $isSeries  Whether this movie is a series episode
     * @return array                 Map of changed fields => ['old' => ..., 'new' => ...]
     */
    protected function applyFreshData(MovieModel $movie, array $preview, string $newUrl, bool $isSeries = false): array
    {
        $changes = [];

        // ── Video URL (most critical) ──
        $oldUrlRaw = $movie->getRawOriginal('url') ?? '';
        if ($newUrl !== $oldUrlRaw) {
            // Preserve old URL for reference
            if (!empty($oldUrlRaw) && strlen($oldUrlRaw) > 5) {
                $movie->old_video_url = $this->normalizeOldVideoUrlForStorage($oldUrlRaw);
            }
            $movie->url = $newUrl;
            $changes['url'] = ['old' => $oldUrlRaw, 'new' => $newUrl];
        }

        // ── Title ──
        $newTitle = trim($preview['video_title'] ?? '');
        if (!empty($newTitle) && $newTitle !== ($movie->title ?? '')) {
            $changes['title'] = ['old' => $movie->title, 'new' => $newTitle];
            $movie->title = $newTitle;
        }

        // ── Thumbnail / Image URLs ──
        $newThumb = trim($preview['thumbnail'] ?? '');
        if (!empty($newThumb) && $newThumb !== ($movie->thumbnail_url ?? '')) {
            $changes['thumbnail_url'] = ['old' => $movie->thumbnail_url, 'new' => $newThumb];
            $movie->thumbnail_url = $newThumb;
            $movie->image_url     = $newThumb;
            $movie->poster_url    = $newThumb;
        }

        // ── Description ──
        $newDesc = trim($preview['description'] ?? '');
        if (!empty($newDesc) && $newDesc !== ($movie->description ?? '')) {
            $changes['description'] = ['old' => substr($movie->description ?? '', 0, 50) . '...', 'new' => substr($newDesc, 0, 50) . '...'];
            $movie->description = $newDesc;
        }

        // ── Genre ──
        // For series episodes: update genre but NEVER overwrite category (it holds the series title)
        $newGenre = trim($preview['genre'] ?? '');
        if (!empty($newGenre) && $newGenre !== ($movie->genre ?? '')) {
            $changes['genre'] = ['old' => $movie->genre, 'new' => $newGenre];
            $movie->genre = $newGenre;
            if (!$isSeries) {
                // Only update category for standalone movies — series episodes use category for series title
                $movie->category = $newGenre;
            }
        }

        // ── Duration ──
        $newDuration = trim($preview['duration'] ?? '');
        if (!empty($newDuration) && $newDuration !== ($movie->duration ?? '')) {
            $changes['duration'] = ['old' => $movie->duration, 'new' => $newDuration];
            $movie->duration = $newDuration;
        }

        // ── VJ ──
        $newVj = trim($preview['vjname'] ?? '');
        if (!empty($newVj) && $newVj !== ($movie->vj ?? '')) {
            $changes['vj'] = ['old' => $movie->vj, 'new' => $newVj];
            $movie->vj = $newVj;
        }

        // ── Year ──
        $recordingDate = $preview['recording_date'] ?? '';
        if (!empty($recordingDate)) {
            $newYear = date('Y', strtotime($recordingDate));
            if ($newYear && $newYear !== ($movie->year ?? '')) {
                $changes['year'] = ['old' => $movie->year, 'new' => $newYear];
                $movie->year = $newYear;
            }
        }

        // ── Language ──
        $newLang = trim($preview['lang_name'] ?? '');
        if (!empty($newLang) && $newLang !== ($movie->language ?? '')) {
            $changes['language'] = ['old' => $movie->language, 'new' => $newLang];
            $movie->language = $newLang;
        }

        // ── Size ──
        $newSize = trim($preview['size'] ?? '');
        if (!empty($newSize)) {
            preg_match('/(\d+\.?\d*)\s*(MB|GB)/i', $newSize, $matches);
            if (isset($matches[1], $matches[2])) {
                $sizeValue = (float) $matches[1];
                if (strtoupper($matches[2]) === 'GB') {
                    $sizeValue *= 1024;
                }
                if (abs($sizeValue - (float)($movie->size ?? 0)) > 0.1) {
                    $changes['size'] = ['old' => $movie->size, 'new' => $sizeValue];
                    $movie->size = $sizeValue;
                }
            }
        }

        // ── Munowatch ID (if not set) ──
        // For series episodes: munowatch_id should be the episode's video ID, NOT the series_code
        $videoId = $preview['id'] ?? null;
        if (!empty($videoId) && empty($movie->munowatch_id)) {
            $movie->munowatch_id = $videoId;
            $movie->external_id  = $videoId;
            $changes['munowatch_id'] = ['old' => null, 'new' => $videoId];
        }

        // ── Category ID ──
        // CRITICAL: For series episodes, category_id is the FK to series_movies.id — NEVER overwrite!
        // The munowatch API returns category_id as a content category (e.g. 5 = TV Series), which
        // is completely different from our local FK usage.
        // For non-series (Movie type): category_id must ALWAYS be null — never set from API.
        if (!$isSeries) {
            // Movies must NOT have a category_id — it's a FK to series_movies only
            if (!empty($movie->category_id)) {
                $changes['category_id'] = ['old' => $movie->category_id, 'new' => null];
                $movie->category_id = null;
            }
        } else {
            Log::info("[MovieFixer] #{$movie->id}: Series episode — skipping category_id update (protected FK to SeriesMovie #{$movie->category_id})");
        }

        // ── Series-specific fields from preview data ──
        if ($isSeries) {
            // Update episode_title if the API returns a more specific title
            $apiTitle = trim($preview['video_title'] ?? '');
            if (!empty($apiTitle) && empty($movie->episode_title)) {
                $movie->episode_title = $apiTitle;
                $changes['episode_title'] = ['old' => null, 'new' => $apiTitle];
            }

            // Extract episode number from API if missing locally
            // Munowatch sometimes puts "EPS 3" in nxt_eps or episode info
            if (empty($movie->episode_number)) {
                $epNum = $this->extractEpisodeNumber($preview);
                if ($epNum) {
                    $movie->episode_number = $epNum;
                    $changes['episode_number'] = ['old' => null, 'new' => $epNum];
                }
            }

            // Extract season number if available and missing locally
            if (empty($movie->season_number) || $movie->season_number == 1) {
                $seasonNum = $this->extractSeasonNumber($preview);
                if ($seasonNum && $seasonNum > 1) {
                    $movie->season_number = $seasonNum;
                    $changes['season_number'] = ['old' => $movie->season_number ?? 1, 'new' => $seasonNum];
                }
            }
        }

        // ── Rating ──
        $newRating = $preview['age_id'] ?? null;
        if (!empty($newRating) && $newRating !== ($movie->rating ?? null)) {
            $changes['rating'] = ['old' => $movie->rating, 'new' => $newRating];
            $movie->rating = $newRating;
        }

        return $changes;
    }

    // ─────────────────────────────────────────────
    //  FAILURE RECORD MANAGEMENT
    // ─────────────────────────────────────────────

    /**
     * Increment fix attempt count on all pending failures for a movie.
     */
    protected function incrementFixAttempts(int $movieId): void
    {
        VideoPlaybackFailure::where('movie_id', $movieId)
            ->where('status', '!=', 'resolved')
            ->update([
                'number_of_fix_attempts' => \DB::raw('number_of_fix_attempts + 1'),
                'fix_status'             => 'PENDING',
                'last_fix_attempt_at'    => now(),
            ]);
    }

    /**
     * Mark all pending failures for a movie as FIXED.
     */
    protected function markFailuresFixed(int $movieId, array $changes, bool $urlAccessible): void
    {
        $changeSummary = [];
        foreach ($changes as $field => $vals) {
            $changeSummary[] = "{$field}: " . ($vals['old'] ?? 'null') . " → " . ($vals['new'] ?? 'null');
        }

        $message = 'Auto-fixed on ' . now()->format('Y-m-d H:i:s') . '. ';
        $message .= empty($changeSummary) ? 'No field changes.' : 'Changes: ' . implode('; ', $changeSummary) . '.';
        if (!$urlAccessible) {
            $message .= ' Note: URL not directly accessible from server, CDN fallback may apply.';
        }

        VideoPlaybackFailure::where('movie_id', $movieId)
            ->where('status', '!=', 'resolved')
            ->update([
                'fix_status'         => 'FIXED',
                'fix_status_message' => $message,
                'status'             => 'resolved',
                'resolved_at'        => now(),
                'admin_notes'        => \DB::raw("CONCAT(COALESCE(admin_notes, ''), '\n[Auto-Fix] " . addslashes($message) . "')"),
            ]);
    }

    /**
     * Mark all pending failures for a movie as FAILED.
     */
    protected function markFailuresFailed(int $movieId, string $reason): void
    {
        VideoPlaybackFailure::where('movie_id', $movieId)
            ->where('status', '!=', 'resolved')
            ->update([
                'fix_status'         => 'FAILED',
                'fix_status_message' => 'Fix failed on ' . now()->format('Y-m-d H:i:s') . ': ' . $reason,
            ]);
    }

    // ─────────────────────────────────────────────
    //  SERIES → MOVIE REVERSAL
    // ─────────────────────────────────────────────

    /**
     * Determine if a movie currently typed as 'Series' should be reversed back to 'Movie'.
     *
     * Uses the SAME signal logic as process_munowatch_intelligent() to re-evaluate classification
     * with fresh data. A movie should be reversed if the cumulative series signal strength is < 3.
     *
     * Key insight: On munowatch, every video has series_code = its own ID. That's NOT a series
     * indicator. Only series_code pointing to a DIFFERENT show counts.
     *
     * @param  MovieModel $movie    The movie currently typed as 'Series'
     * @param  array      $preview  Fresh API data
     * @return bool                 true if the movie should be reversed to type='Movie'
     */
    protected function shouldReverseToMovie(MovieModel $movie, array $preview): bool
    {
        $signalStrength = 0;
        $signals = [];

        $videoId    = $preview['id'] ?? $preview['vid'] ?? $movie->munowatch_id ?? null;
        $seriesCode = $preview['series_code'] ?? $preview['seriesCode'] ?? '';
        $genre      = strtolower($preview['genre'] ?? '');
        $episodes   = (int)($preview['episodes'] ?? 0);
        $epState    = strtoupper($preview['episode_state'] ?? '');
        $nxtEpsId   = (int)($preview['nxt_eps_id'] ?? 0);

        // Signal 1: Genre contains "series" (weight 3)
        if (strpos($genre, 'series') !== false) {
            $signalStrength += 3;
            $signals[] = 'genre_series';
        }

        // Signal 2: Multiple episodes (weight 3)
        if ($episodes > 1) {
            $signalStrength += 3;
            $signals[] = "multi_episode({$episodes})";
        }

        // Signal 3: series_code differs from own video ID (weight 2)
        // CRITICAL: if series_code == videoId, it's just a self-reference, NOT a series indicator
        if (!empty($seriesCode) && (string)$seriesCode !== (string)$videoId) {
            $signalStrength += 2;
            $signals[] = "has_series_code({$seriesCode}≠{$videoId})";
        }

        // Signal 4: episode_state is NEXT/PREV (weight 2)
        if (in_array($epState, ['NEXT', 'PREV'])) {
            $signalStrength += 2;
            $signals[] = "episode_state({$epState})";
        }

        // Signal 5: nxt_eps_id > 0 and != own ID (weight 2)
        if ($nxtEpsId > 0 && $nxtEpsId != (int)($videoId ?? 0)) {
            $signalStrength += 2;
            $signals[] = "has_nxt_eps_id({$nxtEpsId})";
        }

        $shouldReverse = $signalStrength < 3;

        Log::info("[MovieFixer] #{$movie->id}: Series reversal check — strength={$signalStrength}, signals=[" . implode(', ', $signals) . "], reverse=" . ($shouldReverse ? 'YES' : 'NO'));

        return $shouldReverse;
    }

    /**
     * Reverse a wrongly-classified Series episode back to a standalone Movie.
     *
     * Steps:
     *  1. Record the old series parent (category_id → SeriesMovie)
     *  2. Set type = 'Movie'
     *  3. Clear all series-specific fields (category_id, episode_number, season_number, etc.)
     *  4. Update the parent SeriesMovie episode count (decrement)
     *  5. Update any associated crawler page type
     *
     * @param  MovieModel $movie    The movie to reverse
     * @param  array      $preview  Fresh API data (used for restoring correct genre/category)
     * @return array                Map of changed fields
     */
    protected function reverseToMovie(MovieModel $movie, array $preview): array
    {
        $changes = [];
        $oldSeriesId = $movie->category_id;

        // ── Change type from Series to Movie ──
        $changes['type'] = ['old' => $movie->type, 'new' => 'Movie'];
        $movie->type = 'Movie';

        // ── Clear series-specific fields ──
        if (!empty($movie->category_id)) {
            $changes['category_id'] = ['old' => $movie->category_id, 'new' => null];
            $movie->category_id = null;
        }
        if (!empty($movie->episode_number)) {
            $changes['episode_number'] = ['old' => $movie->episode_number, 'new' => null];
            $movie->episode_number = null;
        }
        if (!empty($movie->season_number)) {
            $changes['season_number'] = ['old' => $movie->season_number, 'new' => null];
            $movie->season_number = null;
        }
        if (!empty($movie->series_title)) {
            $changes['series_title'] = ['old' => $movie->series_title, 'new' => null];
            $movie->series_title = null;
        }
        if (!empty($movie->episode_title)) {
            $changes['episode_title'] = ['old' => $movie->episode_title, 'new' => null];
            $movie->episode_title = null;
        }
        if (!empty($movie->is_first_episode)) {
            $changes['is_first_episode'] = ['old' => $movie->is_first_episode, 'new' => null];
            $movie->is_first_episode = null;
        }

        // ── Restore genre-based category from fresh data ──
        $newGenre = trim($preview['genre'] ?? '');
        if (!empty($newGenre)) {
            $movie->category = $newGenre;
            $movie->genre = $newGenre;
        }

        // ── Update parent SeriesMovie: decrement episode count ──
        if (!empty($oldSeriesId)) {
            $parentSeries = SeriesMovie::find($oldSeriesId);
            if ($parentSeries) {
                // Recount actual episodes remaining under this series
                $remainingEpisodes = MovieModel::where('category_id', $oldSeriesId)
                    ->where('type', 'Series')
                    ->where('id', '!=', $movie->id) // exclude the one being reversed
                    ->count();

                $parentSeries->total_episodes = $remainingEpisodes;
                $parentSeries->save();

                Log::info("[MovieFixer] #{$movie->id}: Removed from SeriesMovie #{$oldSeriesId} ({$parentSeries->title}). Remaining episodes: {$remainingEpisodes}");
            }
        }

        // ── Update associated crawler page type ──
        $crawlerPage = $this->findCrawlerPage($movie);
        if ($crawlerPage) {
            $crawlerPage->type = 'Movie';
            $crawlerPage->notes = ($crawlerPage->notes ?? '') . ' | REVERSED to Movie by fixer on ' . now()->format('Y-m-d H:i:s');
            $crawlerPage->save();
        }

        Log::info("[MovieFixer] #{$movie->id}: REVERSED '{$movie->title}' from Series to Movie. Cleared series fields, updated parent.");

        return $changes;
    }

    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────

    /**
     * Find the associated MovieCrawlerPage for a movie.
     */
    protected function findCrawlerPage(MovieModel $movie): ?MovieCrawlerPage
    {
        // Try by movie_id link
        $page = MovieCrawlerPage::where('movie_id', $movie->id)->first();
        if ($page) return $page;

        // Try by page_source_url
        if (!empty($movie->page_source_url)) {
            $page = MovieCrawlerPage::where('url', $movie->page_source_url)->first();
            if ($page) return $page;
        }

        // Try by external_url
        if (!empty($movie->external_url)) {
            $page = MovieCrawlerPage::where('url', $movie->external_url)->first();
            if ($page) return $page;
        }

        // Try by munowatch_id in URL
        if (!empty($movie->munowatch_id)) {
            $page = MovieCrawlerPage::where('url', 'LIKE', '%/preview/v2/' . $movie->munowatch_id . '/%')->first();
            if ($page) return $page;
        }

        return null;
    }

    /**
     * Update the associated MovieCrawlerPage with fresh data.
     */
    protected function updateCrawlerPage(MovieModel $movie, ?string $rawResponse): void
    {
        $page = $this->findCrawlerPage($movie);
        if (!$page) return;

        if ($rawResponse) {
            $page->page_content = $rawResponse;
        }
        $page->movie_id      = $movie->id;
        $page->status         = 'success';
        $page->error_message  = null;
        $page->muno_processed = 'Yes';
        $page->muno_success   = 'Yes';
        $page->last_fetched_at = now();
        $page->save();
    }

    /**
     * Get the file extension from a video URL, if any.
     */
    protected function getVideoExtension(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (empty($path)) return null;

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return !empty($ext) ? strtolower($ext) : null;
    }

    /**
     * Convert a MovieModel to a flat array for the debug player UI.
     * Includes series-specific fields when the movie is a series episode.
     */
    protected function movieToArray(MovieModel $movie): array
    {
        $data = [
            'id'             => $movie->id,
            'title'          => $movie->title,
            'url'            => $movie->url,
            'external_url'   => $movie->external_url,
            'type'           => $movie->type,
            'status'         => $movie->status,
            'genre'          => $movie->genre,
            'vj'             => $movie->vj,
            'thumbnail_url'  => $movie->thumbnail_url,
            'category'       => $movie->category,
            'episode_number' => $movie->episode_number,
            'season_number'  => $movie->season_number,
            'series_title'   => $movie->series_title,
            'episode_title'  => $movie->episode_title,
            'views_count'    => $movie->views_count,
            'munowatch_id'   => $movie->munowatch_id,
            'duration'       => $movie->duration,
            'year'           => $movie->year,
            'language'       => $movie->language,
        ];

        // Add parent series info if this is an episode
        if ($this->isSeries($movie) && !empty($movie->category_id)) {
            $series = SeriesMovie::find($movie->category_id);
            if ($series) {
                $data['series_id']            = $series->id;
                $data['series_title']         = $data['series_title'] ?: $series->title;
                $data['series_total_episodes'] = $series->total_episodes;
                $data['series_total_seasons']  = $series->total_seasons;
                $data['series_code']           = $series->series_code ?? $series->munowatch_id;
            }
        }

        return $data;
    }

    /**
     * Extract episode number from munowatch preview data.
     *
     * Looks at: title patterns ("EP 3", "EPS 3", "Episode 3"), nxt_eps field, etc.
     */
    protected function extractEpisodeNumber(array $preview): ?int
    {
        // Check video_title for episode patterns like "S01E03", "EP 3", "EPS 3", "Episode 3"
        $title = $preview['video_title'] ?? '';
        if (preg_match('/\bS\d+E(\d+)\b/i', $title, $m)) return (int) $m[1];
        if (preg_match('/\b(?:EP|EPS|Episode)\s*(\d+)\b/i', $title, $m)) return (int) $m[1];

        // Check nxt_eps field (format like "EPS   3")
        $nxtEps = trim($preview['nxt_eps'] ?? '');
        if (!empty($nxtEps) && preg_match('/(\d+)/', $nxtEps, $m)) {
            // nxt_eps is the NEXT episode, so current is nxt - 1 (if > 0)
            $next = (int) $m[1];
            return $next > 1 ? $next - 1 : null; // uncertain if it's current or next
        }

        // Check episode_state or other fields
        if (!empty($preview['episode_number'])) return (int) $preview['episode_number'];

        return null;
    }

    /**
     * Extract season number from munowatch preview data.
     *
     * Looks at title patterns ("S02E05", "Season 2") or could be in metadata.
     */
    protected function extractSeasonNumber(array $preview): ?int
    {
        $title = $preview['video_title'] ?? '';
        if (preg_match('/\bS(\d+)E\d+\b/i', $title, $m)) return (int) $m[1];
        if (preg_match('/\bSeason\s*(\d+)\b/i', $title, $m)) return (int) $m[1];

        // Some APIs return season info
        if (!empty($preview['season_number'])) return (int) $preview['season_number'];
        if (!empty($preview['season'])) return (int) $preview['season'];

        return null;
    }

    /**
     * Sync metadata from API preview back to the parent SeriesMovie record.
     *
     * Updates the parent series with fresh data from munowatch without
     * breaking any existing episode relationships.
     */
    protected function syncSeriesMetadata(MovieModel $movie, array $preview): void
    {
        if (empty($movie->category_id)) return;

        $series = SeriesMovie::find($movie->category_id);
        if (!$series) {
            Log::info("[MovieFixer] #{$movie->id}: Series episode but parent SeriesMovie #{$movie->category_id} not found — skipping sync");
            return;
        }

        $updated = false;

        // Update VJ if set on the preview and missing on the series
        $vjName = trim($preview['vjname'] ?? '');
        if (!empty($vjName) && empty($series->vj)) {
            $series->vj = $vjName;
            $updated = true;
        }

        // Update total_episodes if API reports more than what we have
        $apiEpisodes = (int) ($preview['episodes'] ?? 0);
        if ($apiEpisodes > 0 && $apiEpisodes > (int) $series->total_episodes) {
            $series->total_episodes = $apiEpisodes;
            $updated = true;
        }

        // Update thumbnail if missing on series but present in preview
        $newThumb = trim($preview['thumbnail'] ?? '');
        if (!empty($newThumb) && empty($series->thumbnail)) {
            $series->thumbnail = $newThumb;
            $series->poster_url = $newThumb;
            $updated = true;
        }

        // Update description if missing
        $newDesc = trim($preview['description'] ?? '');
        if (!empty($newDesc) && empty($series->description)) {
            $series->description = $newDesc;
            $updated = true;
        }

        // Store series_code from API if not yet set
        $seriesCode = $preview['series_code'] ?? $preview['seriesCode'] ?? null;
        if (!empty($seriesCode) && empty($series->series_code)) {
            $series->series_code = $seriesCode;
            $updated = true;
        }
        if (!empty($seriesCode) && empty($series->munowatch_id)) {
            $series->munowatch_id = $seriesCode;
            $updated = true;
        }

        if ($updated) {
            $series->save();
            Log::info("[MovieFixer] #{$movie->id}: Synced metadata to parent SeriesMovie #{$series->id} ({$series->title})");
        }
    }

    /**
     * Build a failure result and update DB failure records.
     */
    protected function failMovie(MovieModel $movie, string $reason): array
    {
        Log::warning("[MovieFixer] #{$movie->id}: FAILED — {$reason}");

        $this->markFailuresFailed($movie->id, $reason);

        return [
            'success' => false,
            'message' => $reason,
            'movie'   => $this->movieToArray($movie),
        ];
    }

    /**
     * Simple failure result (no movie context).
     */
    protected function fail(string $reason): array
    {
        return [
            'success' => false,
            'message' => $reason,
            'error'   => $reason,
        ];
    }

    /**
     * Guard against oversized/invalid strings before persisting old video URLs.
     */
    protected function normalizeOldVideoUrlForStorage(?string $url): ?string
    {
        $value = trim((string) $url);
        if ($value === '') {
            return null;
        }

        // Remove control chars that can break SQL/text rendering.
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;

        // TEXT supports up to 65535 bytes; keep safe headroom.
        return mb_substr($value, 0, 60000);
    }
}
