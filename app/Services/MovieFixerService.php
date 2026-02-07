<?php

namespace App\Services;

use App\Models\MovieCrawlerPage;
use App\Models\MovieCrawlerWebsite;
use App\Models\MovieModel;
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
     * Core fix logic for a single movie.
     *
     * Fetches fresh data from the original server and saves it exactly as received.
     * The video URL (playingUrl) is stored as-is — no modification, no fallback construction.
     */
    protected function fixMovie(MovieModel $movie): array
    {
        $movieId = $movie->id;
        $oldUrl  = $movie->getRawOriginal('url') ?? $movie->url;

        Log::info("[MovieFixer] Starting fix for movie #{$movieId}: {$movie->title}");

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

            // Step 3: Extract the video URL exactly as the server returned it
            $newUrl = $this->extractBestVideoUrl($preview);

            if (empty($newUrl)) {
                return $this->failMovie($movie, 'Remote server returned data but no valid video URL found in playingUrl, embedurl, or openload fields.');
            }

            Log::info("[MovieFixer] #{$movieId}: Got URL from server: {$newUrl}");

            // Step 4: Apply fresh data to the movie record (URL saved exactly as fetched)
            $changes = $this->applyFreshData($movie, $preview, $newUrl);

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
     *
     * @param  MovieModel $movie
     * @param  array      $preview  Fresh data from the API
     * @param  string     $newUrl   Validated video URL
     * @return array                Map of changed fields => ['old' => ..., 'new' => ...]
     */
    protected function applyFreshData(MovieModel $movie, array $preview, string $newUrl): array
    {
        $changes = [];

        // ── Video URL (most critical) ──
        $oldUrlRaw = $movie->getRawOriginal('url') ?? '';
        if ($newUrl !== $oldUrlRaw) {
            // Preserve old URL for reference
            if (!empty($oldUrlRaw) && strlen($oldUrlRaw) > 5) {
                $movie->old_video_url = $oldUrlRaw;
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
        $newGenre = trim($preview['genre'] ?? '');
        if (!empty($newGenre) && $newGenre !== ($movie->genre ?? '')) {
            $changes['genre'] = ['old' => $movie->genre, 'new' => $newGenre];
            $movie->genre    = $newGenre;
            $movie->category = $newGenre;
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
        $videoId = $preview['id'] ?? null;
        if (!empty($videoId) && empty($movie->munowatch_id)) {
            $movie->munowatch_id = $videoId;
            $movie->external_id  = $videoId;
            $changes['munowatch_id'] = ['old' => null, 'new' => $videoId];
        }

        // ── Category ID ──
        $newCatId = $preview['category_id'] ?? null;
        if (!empty($newCatId) && $newCatId !== ($movie->category_id ?? null)) {
            $changes['category_id'] = ['old' => $movie->category_id, 'new' => $newCatId];
            $movie->category_id = $newCatId;
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
     */
    protected function movieToArray(MovieModel $movie): array
    {
        return [
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
            'views_count'    => $movie->views_count,
            'munowatch_id'   => $movie->munowatch_id,
            'duration'       => $movie->duration,
            'year'           => $movie->year,
            'language'       => $movie->language,
        ];
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
}
