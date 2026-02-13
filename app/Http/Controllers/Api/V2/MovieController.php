<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\MovieCrawlerPage;
use App\Models\SeriesMovie;
use App\Models\MovieView;
use App\Models\MovieLike;
use App\Models\MovieWishlist;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ═══════════════════════════════════════════════════════════════
 *  V2 Movie API Controller
 * ═══════════════════════════════════════════════════════════════
 *
 *  Clean, paginated, optimised movie endpoints.
 *  Same auth (JwtMiddleware) and response format as V1.
 *
 *  Endpoints:
 *    GET  /api/v2/movies              – Paginated movie listing
 *    GET  /api/v2/movies/search       – Relevance-scored search
 *    GET  /api/v2/movies/{id}         – Single movie detail
 *    GET  /api/v2/movies/{id}/related – Related movies (max 30)
 *    GET  /api/v2/series/{id}/episodes – Episodes for a series
 *
 *  Design rules:
 *    • Never return more than `max_per_page` (50) items
 *    • Only return fields the mobile app needs (slim payload)
 *    • Log every request for monitoring
 *    • Proper pagination metadata in every list response
 * ═══════════════════════════════════════════════════════════════
 */
class MovieController extends Controller
{
    use ApiResponser;

    // ── Field sets (only what mobile apps actually need) ────────

    /** Fields returned in list/search results */
    protected const LIST_FIELDS = [
        'id', 'title', 'url', 'image_url', 'thumbnail_url',
        'year', 'rating', 'duration', 'genre', 'language',
        'type', 'status', 'vj', 'is_premium', 'category_id',
        'views_count', 'likes_count', 'episode_number',
        'season_number', 'series_title', 'is_first_episode',
        'description', 'country',
    ];

    /** Fields returned in full detail response */
    protected const DETAIL_FIELDS = [
        'id', 'title', 'url', 'image_url', 'thumbnail_url',
        'description', 'year', 'rating', 'duration', 'size',
        'genre', 'director', 'stars', 'country', 'language',
        'type', 'status', 'vj', 'actor', 'is_premium',
        'category_id', 'category', 'views_count', 'likes_count',
        'episode_number', 'season_number', 'series_title',
        'episode_title', 'is_first_episode', 'external_url',
        'poster_url', 'imdb_rating', 'munowatch_id', 'is_muno',
        'content_type', 'content_is_video',
    ];

    /** Fields returned for episode listings (minimal) */
    protected const EPISODE_FIELDS = [
        'id', 'title', 'url', 'thumbnail_url', 'image_url',
        'description', 'year', 'rating', 'duration', 'size',
        'genre', 'type', 'status', 'category_id', 'vj',
        'episode_number', 'season_number', 'is_premium',
        'views_count', 'likes_count', 'munowatch_id',
        'is_first_episode', 'episode_title', 'series_title',
        'is_muno', 'language', 'country',
    ];

    /** Pagination defaults */
    protected const DEFAULT_PER_PAGE = 20;
    protected const MAX_PER_PAGE     = 50;

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/movies — Paginated movie listing
    // ═══════════════════════════════════════════════════════════

    /**
     * List movies with pagination and optional filters.
     *
     * Query params:
     *   type       – "Movie" (default) | "Series"
     *   genre      – Filter by genre (partial match)
     *   language   – Filter by language (partial match)
     *   vj         – Filter by VJ name (partial match)
     *   year       – Filter by year
     *   status     – "Active" (default) | "Inactive"
     *   sort       – "latest" (default) | "popular" | "title" | "year"
     *   page       – Page number (default 1)
     *   per_page   – Items per page (default 20, max 50)
     */
    public function index(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $startTime = microtime(true);

        $perPage = $this->resolvePerPage($request);
        $sort    = $request->get('sort', 'latest');

        // Build base query based on requested type
        $type = $request->get('type', 'Movie');
        $query = MovieModel::select(self::LIST_FIELDS)
            ->where('status', 'Active');

        if ($type === 'Series') {
            // Series tab: only first episodes of series
            $query->where('type', 'Series')
                  ->where('is_first_episode', 'Yes');
        } else {
            // Movies tab: everything EXCEPT non-first-episode series entries
            // This includes type=Movie, Episode, or any other type, plus series first episodes
            $query->where(function ($q) {
                $q->where('type', '!=', 'Series')
                  ->orWhere('is_first_episode', 'Yes');
            });
        }

        // Optional filters
        if ($request->filled('genre'))    $query->where('genre', 'LIKE', '%' . $request->get('genre') . '%');
        if ($request->filled('language')) $query->where('language', 'LIKE', '%' . $request->get('language') . '%');
        if ($request->filled('vj'))       $query->where('vj', 'LIKE', '%' . $request->get('vj') . '%');
        if ($request->filled('year'))     $query->where('year', $request->get('year'));

        // Sorting
        switch ($sort) {
            case 'popular':
                $query->orderByDesc('views_count');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'year':
                $query->orderByDesc('year')->orderByDesc('id');
                break;
            case 'latest':
            default:
                $query->orderByDesc('id');
                break;
        }

        $paginated = $query->paginate($perPage);
        $items = $this->cleanUrls($paginated->items());

        $elapsed = round((microtime(true) - $startTime) * 1000);
        $typeUsed = $type;
        Log::info("[V2:movies] type={$typeUsed} sort={$sort} page={$paginated->currentPage()} per_page={$perPage} total={$paginated->total()} ms={$elapsed}");

        return $this->success([
            'items'      => $items,
            'pagination' => $this->paginationMeta($paginated),
        ], "Movies retrieved successfully.");
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/series — Rich series listing with sections
    // ═══════════════════════════════════════════════════════════

    /**
     * Dedicated series listing endpoint with:
     *   • Episode count & mini-series / full-series classification
     *   • Sections manifest on page 1 (popular, recent, mini-series)
     *   • Available filter options on page 1
     *   • Zero-episode series excluded (unless searching)
     *
     * Query params:
     *   sort       – "latest" (default) | "popular" | "year" | "episodes"
     *   genre      – Filter by genre (partial match)
     *   language   – Filter by language (partial match)
     *   vj         – Filter by VJ name (partial match)
     *   year       – Filter by year
     *   min_episodes – Minimum episode count (e.g. 1 for mini filter)
     *   max_episodes – Maximum episode count (e.g. 5 for mini filter)
     *   page       – Page number (default 1)
     *   per_page   – Items per page (default 20, max 50)
     */
    public function seriesIndex(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $startTime = microtime(true);

        $page    = max(1, (int) $request->get('page', 1));
        $perPage = $this->resolvePerPage($request);
        $sort    = $request->get('sort', 'latest');

        // ── Build base query: first-episode movies joined with series_movies ──
        $selectCols = array_map(fn($f) => "movie_models.{$f}", self::LIST_FIELDS);
        $selectCols[] = 'series_movies.total_episodes as episode_count';
        $selectCols[] = 'series_movies.total_views as series_views';

        $query = MovieModel::query()
            ->join('series_movies', 'movie_models.category_id', '=', 'series_movies.id')
            ->select($selectCols)
            ->where('movie_models.type', 'Series')
            ->where('movie_models.is_first_episode', 'yes')
            ->where('movie_models.status', 'Active')
            ->where('series_movies.total_episodes', '>', 0);  // never send 0-ep series

        // ── Optional filters ──
        if ($request->filled('genre'))    $query->where('movie_models.genre', 'LIKE', '%' . $request->get('genre') . '%');
        if ($request->filled('language')) $query->where('movie_models.language', 'LIKE', '%' . $request->get('language') . '%');
        if ($request->filled('vj'))       $query->where('movie_models.vj', 'LIKE', '%' . $request->get('vj') . '%');
        if ($request->filled('year'))     $query->where('movie_models.year', $request->get('year'));
        if ($request->filled('min_episodes')) $query->where('series_movies.total_episodes', '>=', (int) $request->get('min_episodes'));
        if ($request->filled('max_episodes')) $query->where('series_movies.total_episodes', '<=', (int) $request->get('max_episodes'));

        // ── Sorting ──
        switch ($sort) {
            case 'popular':
                $query->orderByDesc('series_movies.total_views');
                break;
            case 'year':
                $query->orderByDesc('movie_models.year')->orderByDesc('movie_models.id');
                break;
            case 'episodes':
                $query->orderByDesc('series_movies.total_episodes');
                break;
            case 'latest':
            default:
                $query->orderByDesc('movie_models.id');
                break;
        }

        // ── Paginate ──
        $paginated = $query->paginate($perPage);

        // ── Process items: add series_type, clean URLs ──
        $items = array_map(function ($item) {
            $data = $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array) $item;
            $data = $this->cleanUrlSingle($data);
            $eps = (int) ($data['episode_count'] ?? 0);
            $data['series_type'] = $eps <= 5 ? 'mini' : 'full';
            return $data;
        }, $paginated->items());

        // ── Build response ──
        $response = [
            'items'      => $items,
            'pagination' => $this->paginationMeta($paginated),
        ];

        // ── Page 1 extras: sections + filters ──
        if ($page === 1) {
            $response['sections'] = $this->buildSeriesSections();
            $response['filters']  = $this->getSeriesFilterOptions();
        }

        $elapsed = round((microtime(true) - $startTime) * 1000);
        Log::info("[V2:series] sort={$sort} page={$page} per_page={$perPage} total={$paginated->total()} ms={$elapsed}");

        return $this->success($response, 'Series retrieved successfully.');
    }

    /**
     * Build the three featured sections for the series landing page.
     *
     * Returns:
     *   popular     – 15 series, ordered by total_views (most watched)
     *   recent      – 15 series, ordered by newest
     *   mini_series – 15 random series with 1-5 episodes
     */
    private function buildSeriesSections(): array
    {
        $selectCols = array_map(fn($f) => "movie_models.{$f}", self::LIST_FIELDS);
        $selectCols[] = 'series_movies.total_episodes as episode_count';
        $selectCols[] = 'series_movies.total_views as series_views';

        $baseQuery = fn() => MovieModel::query()
            ->join('series_movies', 'movie_models.category_id', '=', 'series_movies.id')
            ->select($selectCols)
            ->where('movie_models.type', 'Series')
            ->where('movie_models.is_first_episode', 'yes')
            ->where('movie_models.status', 'Active')
            ->where('series_movies.total_episodes', '>', 0);

        // ── 1. Popular: top 15 by view count ──
        $popular = $baseQuery()
            ->orderByDesc('series_movies.total_views')
            ->limit(15)
            ->get()
            ->map(fn($i) => $this->enrichSeriesItem($i))
            ->toArray();

        // ── 2. Recent: newest 15 ──
        $recent = $baseQuery()
            ->orderByDesc('movie_models.id')
            ->limit(15)
            ->get()
            ->map(fn($i) => $this->enrichSeriesItem($i))
            ->toArray();

        // ── 3. Mini-series: 1-5 episodes, 15 random ──
        $mini = $baseQuery()
            ->where('series_movies.total_episodes', '<=', 5)
            ->inRandomOrder()
            ->limit(15)
            ->get()
            ->map(fn($i) => $this->enrichSeriesItem($i))
            ->toArray();

        return [
            [
                'key'      => 'popular',
                'title'    => 'Most Popular',
                'subtitle' => 'Most watched series',
                'items'    => $popular,
            ],
            [
                'key'      => 'recent',
                'title'    => 'Recently Added',
                'subtitle' => 'Newest series',
                'items'    => $recent,
            ],
            [
                'key'      => 'mini_series',
                'title'    => 'Mini Series',
                'subtitle' => 'Quick watches · 1-5 episodes',
                'items'    => $mini,
            ],
        ];
    }

    /**
     * Enrich a series item with series_type, clean URLs.
     */
    private function enrichSeriesItem($item): array
    {
        $data = $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array) $item;
        $data = $this->cleanUrlSingle($data);
        $eps  = (int) ($data['episode_count'] ?? 0);
        $data['series_type'] = $eps <= 5 ? 'mini' : 'full';
        return $data;
    }

    /**
     * Get available filter options for the series listing.
     * Only returns values that actually exist in active series.
     */
    private function getSeriesFilterOptions(): array
    {
        $base = MovieModel::query()
            ->join('series_movies', 'movie_models.category_id', '=', 'series_movies.id')
            ->where('movie_models.type', 'Series')
            ->where('movie_models.is_first_episode', 'yes')
            ->where('movie_models.status', 'Active')
            ->where('series_movies.total_episodes', '>', 0);

        // Genres: split comma-separated genre strings and collect unique values
        $rawGenres = (clone $base)
            ->whereNotNull('movie_models.genre')
            ->where('movie_models.genre', '!=', '')
            ->pluck('movie_models.genre');

        $genres = $rawGenres
            ->flatMap(fn($g) => array_map('trim', explode(',', $g)))
            ->filter(fn($g) => strlen($g) > 0)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Languages
        $languages = (clone $base)
            ->whereNotNull('movie_models.language')
            ->where('movie_models.language', '!=', '')
            ->distinct()
            ->pluck('movie_models.language')
            ->sort()
            ->values()
            ->toArray();

        // Years
        $years = (clone $base)
            ->whereNotNull('movie_models.year')
            ->where('movie_models.year', '!=', '')
            ->distinct()
            ->pluck('movie_models.year')
            ->sortDesc()
            ->values()
            ->toArray();

        return [
            'genres'    => $genres,
            'languages' => $languages,
            'years'     => $years,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/movies/search — Relevance-scored search
    // ═══════════════════════════════════════════════════════════

    /**
     * Search movies + first episodes of series with relevance scoring.
     *
     * Query params:
     *   q         – Search term (required, min 2 chars)
     *   page      – Page number (default 1)
     *   per_page  – Items per page (default 20, max 50)
     *
     * Algorithm:
     *   Phase 1: Full phrase match in Movies      → score 1000
     *   Phase 2: Full phrase in Series titles      → score  900
     *   Phase 3: Progressive word removal          → score  500–700
     *   Phase 4: Individual word matches           → score  200
     *   Results are de-duplicated and sorted by score desc.
     */
    public function search(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $startTime = microtime(true);

        $searchTerm = trim($request->get('q', ''));
        if (mb_strlen($searchTerm) < 2) {
            return $this->error('Search term must be at least 2 characters.', 422);
        }

        $perPage = $this->resolvePerPage($request);
        $page    = max(1, (int) $request->get('page', 1));

        $ignoreWords = ['the', 'a', 'an', 'of', 'in', 'on', 'at', 'for', 'and', 'that', 'with', 'to', 'is', 'are', 'was', 'were'];
        $movieScores = [];

        // ── Phase 1: Full phrase → Movies ──
        $fullMatchIds = MovieModel::where('type', 'Movie')
            ->where('status', 'Active')
            ->where('title', 'LIKE', '%' . $searchTerm . '%')
            ->pluck('id')
            ->toArray();

        foreach ($fullMatchIds as $id) {
            $movieScores[$id] = ($movieScores[$id] ?? 0) + 1000;
        }

        // ── Phase 2: Full phrase → First episode of each Series ──
        $seriesMatches = SeriesMovie::where('title', 'LIKE', '%' . $searchTerm . '%')
            ->where('is_active', 'Yes')
            ->pluck('id')
            ->toArray();

        if (!empty($seriesMatches)) {
            $firstEpisodes = MovieModel::whereIn('category_id', $seriesMatches)
                ->where('type', 'Series')
                ->where('status', 'Active')
                ->select('id', 'category_id')
                ->orderByRaw('CAST(NULLIF(episode_number, "") AS UNSIGNED) ASC')
                ->orderBy('id', 'asc')
                ->get()
                ->unique('category_id');

            foreach ($firstEpisodes as $ep) {
                $movieScores[$ep->id] = ($movieScores[$ep->id] ?? 0) + 900;
            }
        }

        // ── Phase 3: Progressive word removal ──
        $words = explode(' ', $searchTerm);
        if (count($words) > 1) {
            // Remove from end
            $tempWords = $words;
            while (count($tempWords) > 1) {
                array_pop($tempWords);
                $validWords = array_filter($tempWords, fn($w) => !in_array(strtolower($w), $ignoreWords));
                if (empty($validWords)) break;

                $phrase = implode(' ', $tempWords);
                $matches = MovieModel::where('type', 'Movie')
                    ->where('status', 'Active')
                    ->where('title', 'LIKE', '%' . $phrase . '%')
                    ->whereNotIn('id', array_keys($movieScores))
                    ->pluck('id')
                    ->toArray();

                $score = 700 / count($words);
                foreach ($matches as $id) {
                    $movieScores[$id] = ($movieScores[$id] ?? 0) + $score;
                }
            }

            // Remove from start
            $tempWords = $words;
            while (count($tempWords) > 1) {
                array_shift($tempWords);
                $validWords = array_filter($tempWords, fn($w) => !in_array(strtolower($w), $ignoreWords));
                if (empty($validWords)) break;

                $phrase = implode(' ', $tempWords);
                $matches = MovieModel::where('type', 'Movie')
                    ->where('status', 'Active')
                    ->where('title', 'LIKE', '%' . $phrase . '%')
                    ->whereNotIn('id', array_keys($movieScores))
                    ->pluck('id')
                    ->toArray();

                $score = 500 / count($words);
                foreach ($matches as $id) {
                    $movieScores[$id] = ($movieScores[$id] ?? 0) + $score;
                }
            }
        }

        // ── Phase 4: Individual significant words ──
        $sigWords = array_filter($words, fn($w) => !in_array(strtolower($w), $ignoreWords) && mb_strlen($w) >= 3);
        if (!empty($sigWords)) {
            $wordMatchIds = MovieModel::where('status', 'Active')
                ->where(function ($q) use ($sigWords) {
                    foreach ($sigWords as $w) {
                        $q->orWhere('title', 'LIKE', '%' . $w . '%');
                    }
                })
                ->whereNotIn('id', array_keys($movieScores))
                ->limit(100)
                ->pluck('id')
                ->toArray();

            foreach ($wordMatchIds as $id) {
                $movieScores[$id] = ($movieScores[$id] ?? 0) + 200;
            }
        }

        // Sort by score descending, apply pagination
        arsort($movieScores);
        $total = count($movieScores);
        $sliced = array_slice($movieScores, ($page - 1) * $perPage, $perPage, true);

        if (empty($sliced)) {
            $elapsed = round((microtime(true) - $startTime) * 1000);
            Log::info("[V2:search] q='{$searchTerm}' results=0 ms={$elapsed}");
            return $this->success([
                'items'      => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => 0,
                    'last_page'    => 1,
                ],
            ], "No results found.");
        }

        $movies = MovieModel::select(self::LIST_FIELDS)
            ->whereIn('id', array_keys($sliced))
            ->get()
            ->keyBy('id');

        // Preserve score order
        $items = [];
        foreach ($sliced as $id => $score) {
            if (isset($movies[$id])) {
                $items[] = $movies[$id];
            }
        }

        $items = $this->cleanUrls($items);

        $elapsed = round((microtime(true) - $startTime) * 1000);
        Log::info("[V2:search] q='{$searchTerm}' results={$total} page={$page} ms={$elapsed}");

        return $this->success([
            'items'      => array_values($items),
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
            ],
        ], "Search results retrieved successfully.");
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/movies/{id} — Single movie detail
    // ═══════════════════════════════════════════════════════════

    /**
     * Get full detail for a single movie, with user interaction state.
     *
     * Returns: movie data + user_interactions (liked, wishlisted, viewed)
     */
    public function show(Request $request, $id)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $startTime = microtime(true);

        $movie = MovieModel::select(self::DETAIL_FIELDS)->find($id);
        if (!$movie) {
            return $this->error('Movie not found.', 404);
        }

        // Live counts
        $movie->views_count = MovieView::where('movie_model_id', $id)->count();
        $movie->likes_count = MovieLike::where('movie_model_id', $id)->count();

        $movieData = $this->cleanUrlSingle($movie->toArray());

        // Series parent info if this is an episode
        $seriesInfo = null;
        if ($movie->type === 'Series' && !empty($movie->category_id)) {
            $parent = SeriesMovie::select('id', 'title', 'thumbnail', 'total_episodes', 'total_seasons', 'genre', 'language', 'vj', 'year')
                ->find($movie->category_id);
            if ($parent) {
                $seriesInfo = $parent->toArray();
            }
        }

        // Episode count & seasons for series
        $episodesInfo = null;
        if ($movie->type === 'Series' && !empty($movie->category_id)) {
            $episodesInfo = [
                'total_episodes' => MovieModel::where('category_id', $movie->category_id)
                    ->where('status', 'Active')->where('type', 'Series')->count(),
                'seasons' => MovieModel::where('category_id', $movie->category_id)
                    ->where('status', 'Active')->where('type', 'Series')
                    ->whereNotNull('season_number')->where('season_number', '!=', '')
                    ->where('season_number', '!=', '0')
                    ->distinct()->pluck('season_number')
                    ->sort(fn($a, $b) => intval($a) - intval($b))
                    ->values()->toArray(),
            ];
        }

        $elapsed = round((microtime(true) - $startTime) * 1000);
        Log::info("[V2:movie] id={$id} type={$movie->type} ms={$elapsed}");

        return $this->success([
            'movie'             => $movieData,
            'series_info'       => $seriesInfo,
            'episodes_info'     => $episodesInfo,
            'user_interactions' => [
                'has_liked'      => MovieLike::hasUserLikedMovie($user->id, $id),
                'has_wishlisted' => MovieWishlist::hasUserWishlistedMovie($user->id, $id),
                'has_viewed'     => MovieView::where('movie_model_id', $id)
                    ->where('user_id', $user->id)
                    ->where('status', 'Active')
                    ->exists(),
            ],
        ], "Movie retrieved successfully.");
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/movies/{id}/related — Related movies
    // ═══════════════════════════════════════════════════════════

    /**
     * Get related movies for a given movie.
     * Max 30 results. Scored by: genre > vj > title similarity > year.
     *
     * For Series episodes, this returns other episodes in the same series
     * PLUS a few related movies from the same genre.
     *
     * Query params:
     *   per_page – Items to return (default 20, max 50)
     */
    public function related(Request $request, $id)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $startTime = microtime(true);

        $movie = MovieModel::find($id);
        if (!$movie) {
            return $this->error('Movie not found.', 404);
        }

        $perPage = $this->resolvePerPage($request, 20);
        $scored  = [];

        // ── Build exclusion list (for series, exclude same-series episodes) ──
        $excludeIds = [$movie->id];
        if ($movie->type === 'Series' && !empty($movie->category_id)) {
            $sameSeriesIds = MovieModel::where('category_id', $movie->category_id)
                ->pluck('id')
                ->toArray();
            $excludeIds = array_unique(array_merge($excludeIds, $sameSeriesIds));
        }
        $isSeries = $movie->type === 'Series';

        // ── Genre match ──
        if (!empty($movie->genre) && count($scored) < $perPage * 2) {
            $genreQuery = MovieModel::where('genre', $movie->genre)
                ->where('status', 'Active')
                ->whereNotIn('id', array_merge(array_keys($scored), $excludeIds));
            if ($isSeries) {
                $genreQuery->where(function ($q) {
                    $q->where('type', '!=', 'Series')
                      ->orWhere('is_first_episode', 'yes');
                });
            }
            $genreIds = $genreQuery->limit(30)->pluck('id')->toArray();
            foreach ($genreIds as $gid) {
                $scored[$gid] = 5000;
            }
        }

        // ── VJ match ──
        if (!empty($movie->vj) && count($scored) < $perPage * 2) {
            $vjQuery = MovieModel::where('vj', 'LIKE', '%' . $movie->vj . '%')
                ->where('status', 'Active')
                ->whereNotIn('id', array_merge(array_keys($scored), $excludeIds));
            if ($isSeries) {
                $vjQuery->where(function ($q) {
                    $q->where('type', '!=', 'Series')
                      ->orWhere('is_first_episode', 'yes');
                });
            }
            $vjIds = $vjQuery->limit(20)->pluck('id')->toArray();
            foreach ($vjIds as $vid) {
                $scored[$vid] = 4000;
            }
        }

        // ── Title similarity ──
        $titleWords = $this->extractSignificantWords($movie->title);
        if (!empty($titleWords) && count($scored) < $perPage * 2) {
            $phrase = implode(' ', $titleWords);
            $titleQuery = MovieModel::where('title', 'LIKE', '%' . $phrase . '%')
                ->where('status', 'Active')
                ->whereNotIn('id', array_merge(array_keys($scored), $excludeIds));
            if ($isSeries) {
                $titleQuery->where(function ($q) {
                    $q->where('type', '!=', 'Series')
                      ->orWhere('is_first_episode', 'yes');
                });
            }
            $titleIds = $titleQuery->limit(20)->pluck('id')->toArray();
            foreach ($titleIds as $tid) {
                $scored[$tid] = 3000;
            }
        }

        // ── Year match ──
        if (!empty($movie->year) && count($scored) < $perPage * 2) {
            $yearQuery = MovieModel::where('year', $movie->year)
                ->where('status', 'Active')
                ->whereNotIn('id', array_merge(array_keys($scored), $excludeIds));
            if ($isSeries) {
                $yearQuery->where(function ($q) {
                    $q->where('type', '!=', 'Series')
                      ->orWhere('is_first_episode', 'yes');
                });
            }
            $yearIds = $yearQuery->limit(15)->pluck('id')->toArray();
            foreach ($yearIds as $yid) {
                $scored[$yid] = 800;
            }
        }

        // Sort by score, take top N
        arsort($scored);
        $topIds = array_slice(array_keys($scored), 0, $perPage);

        $items = [];
        if (!empty($topIds)) {
            $movies = MovieModel::select(self::LIST_FIELDS)
                ->whereIn('id', $topIds)
                ->get()
                ->keyBy('id');

            foreach ($topIds as $tid) {
                if (isset($movies[$tid])) {
                    $items[] = $movies[$tid];
                }
            }
            $items = $this->cleanUrls($items);
        }

        $elapsed = round((microtime(true) - $startTime) * 1000);
        Log::info("[V2:related] movie_id={$id} type={$movie->type} results=" . count($items) . " ms={$elapsed}");

        return $this->success([
            'items'      => $items,
            'pagination' => [
                'current_page' => 1,
                'per_page'     => count($items),
                'total'        => count($items),
                'last_page'    => 1,
            ],
        ], "Related movies retrieved successfully.");
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/series/{id}/episodes — Series episodes
    // ═══════════════════════════════════════════════════════════

    /**
     * Get episodes for a specific series, ordered by episode number.
     * Deduplicated by season+episode number.
     *
     * Path param:
     *   id        – SeriesMovie ID (category_id in movie_models)
     *
     * Query params:
     *   season    – Filter by season number (optional)
     *   page      – Page number (default 1)
     *   per_page  – Items per page (default 50, max 50)
     */
    public function episodes(Request $request, $id)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $startTime = microtime(true);

        $series = SeriesMovie::select('id', 'title', 'thumbnail', 'total_episodes', 'total_seasons', 'is_active')
            ->find($id);

        if (!$series) {
            return $this->error('Series not found.', 404);
        }

        $query = MovieModel::select(self::EPISODE_FIELDS)
            ->where('category_id', $id)
            ->where('status', 'Active')
            ->where('type', 'Series');

        if ($request->filled('season')) {
            $query->where('season_number', $request->get('season'));
        }

        $query->orderByRaw('CAST(NULLIF(season_number, "") AS UNSIGNED) ASC')
            ->orderByRaw('CAST(NULLIF(episode_number, "") AS UNSIGNED) ASC')
            ->orderBy('id', 'asc');

        $episodes = $query->get();

        // Deduplicate by season+episode number
        $seen = [];
        $unique = $episodes->filter(function ($ep) use (&$seen) {
            $epNum = trim($ep->episode_number ?? '');
            if (empty($epNum) || $epNum === '0') return true;
            $seasonKey = ($ep->season_number ?? '1') . '-' . $epNum;
            if (isset($seen[$seasonKey])) return false;
            $seen[$seasonKey] = true;
            return true;
        });

        $items = $this->cleanUrls($unique->values()->all());

        $elapsed = round((microtime(true) - $startTime) * 1000);
        Log::info("[V2:episodes] series_id={$id} title=\"{$series->title}\" episodes=" . count($items) . " ms={$elapsed}");

        return $this->success([
            'series' => [
                'id'             => $series->id,
                'title'          => $series->title,
                'thumbnail'      => $series->thumbnail,
                'total_episodes' => $series->total_episodes,
                'total_seasons'  => $series->total_seasons,
                'is_active'      => $series->is_active,
            ],
            'items'      => $items,
            'pagination' => [
                'current_page' => 1,
                'per_page'     => count($items),
                'total'        => count($items),
                'last_page'    => 1,
            ],
        ], "Episodes retrieved successfully.");
    }

    // ═══════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    /**
     * Resolve authenticated user from request.
     */
    private function resolveUser(Request $request): ?User
    {
        $u = Utils::get_user($request);
        if ($u) {
            $u = User::find($u->id);
            if ($u) {
                $u->last_online_at = now();
                $u->save();
            }
        }
        return $u;
    }

    /**
     * Resolve per_page with hard ceiling.
     */
    private function resolvePerPage(Request $request, int $default = self::DEFAULT_PER_PAGE): int
    {
        $perPage = (int) $request->get('per_page', $default);
        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Standard pagination metadata from LengthAwarePaginator.
     */
    private function paginationMeta($paginated): array
    {
        return [
            'current_page' => $paginated->currentPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'last_page'    => $paginated->lastPage(),
        ];
    }

    /**
     * Clean URLs in a collection (spaces → %20, http → https).
     */
    private function cleanUrls(array $items): array
    {
        return array_map(function ($item) {
            $data = $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array) $item;
            return $this->cleanUrlSingle($data);
        }, $items);
    }

    /**
     * Clean URLs in a single item array.
     */
    private function cleanUrlSingle(array $data): array
    {
        $urlFields = ['url', 'image_url', 'thumbnail_url', 'poster_url', 'external_url'];
        foreach ($urlFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = str_replace(' ', '%20', $data[$field]);
                $data[$field] = preg_replace('/^http:/i', 'https:', $data[$field]);
            }
        }
        return $data;
    }

    /**
     * Extract significant words from a title (drop stop words and short words).
     */
    private function extractSignificantWords(string $title): array
    {
        $ignoreWords = ['the', 'a', 'an', 'of', 'in', 'on', 'at', 'for', 'and', 'that', 'with', 'to', 'is', 'are', 'was', 'were', 'part', 'episode'];
        $words = explode(' ', strtolower(trim($title)));
        return array_values(array_filter($words, function ($w) use ($ignoreWords) {
            return mb_strlen($w) >= 3 && !in_array($w, $ignoreWords);
        }));
    }

    // ═══════════════════════════════════════════════════════════
    //  POST /api/v2/movies/{id}/playback — Report playback event
    // ═══════════════════════════════════════════════════════════

    /**
     * Record playback events: start, progress, stop.
     *
     * Body params:
     *   event      – "start" | "progress" | "stop"
     *   position   – Current position in seconds
     *   duration   – Total duration in seconds
     *   percentage – Playback percentage (optional)
     */
    public function playback(Request $request, $id)
    {
        $user = $this->resolveUser($request);
        $movie = MovieModel::find($id);

        if (!$movie) {
            return $this->error('Movie not found', 404);
        }

        $event      = $request->input('event', 'progress');
        $position   = (int) $request->input('position', 0);
        $duration   = (int) $request->input('duration', 0);
        $percentage = $request->input('percentage', $duration > 0 ? round(($position / $duration) * 100, 1) : 0);

        // Update or create view record
        if ($user) {
            $view = MovieView::updateOrCreate(
                ['user_id' => $user->id, 'movie_model_id' => $movie->id],
                [
                    'progress'     => $position,
                    'max_progress' => $duration,
                    'status'       => $event === 'stop' ? 'Paused' : 'Active',
                ]
            );
        }

        // Increment views_count on "start" event only
        if ($event === 'start') {
            $movie->increment('views_count');
        }

        Log::info("V2 Playback [{$event}] movie={$id} pos={$position}/{$duration} ({$percentage}%)" .
            ($user ? " user={$user->id}" : ' guest'));

        return $this->success([
            'event'      => $event,
            'movie_id'   => (int) $id,
            'position'   => $position,
            'duration'   => $duration,
            'percentage' => $percentage,
        ], 'Playback recorded');
    }

    // ═══════════════════════════════════════════════════════════
    //  POST /api/v2/movies/{id}/fix — Mobile-triggered movie fix
    // ═══════════════════════════════════════════════════════════

    /**
     * Perform fix / diagnostic actions on a movie.
     *
     * Body:
     *   action – "diagnose" | "refresh" | "test_url" | "sync_episodes"
     */
    public function fix(Request $request, $id)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $movie = MovieModel::find($id);
        if (!$movie) {
            return $this->error('Movie not found.', 404);
        }

        $action = $request->input('action', 'diagnose');

        Log::info("V2 Fix [{$action}] movie={$id} user=" . ($user->id ?? 'guest'));

        switch ($action) {

            // ── Fix (centralized): Use MovieFixerService for full re-fetch + repair ──
            case 'fix':
                return $this->fixCentralized($movie);

            // ── Diagnose: return diagnostic info without changing anything ──
            case 'diagnose':
                return $this->fixDiagnose($movie);

            // ── Refresh: re-fetch movie data from munowatch source ──
            case 'refresh':
                return $this->fixRefresh($movie);

            // ── Test URL: check if the video URL is accessible ──
            case 'test_url':
                return $this->fixTestUrl($movie);

            // ── Sync Episodes: re-fetch episodes for a series ──
            case 'sync_episodes':
                return $this->fixSyncEpisodes($movie);

            default:
                return $this->error("Unknown fix action: {$action}", 400);
        }
    }

    /**
     * Centralized fix — uses MovieFixerService for full re-fetch from MunoWatch + repair.
     *
     * This is the PRIMARY fix action for mobile apps. It:
     *   1. Detects the movie's source platform (MunoWatch, MyVJ, etc.)
     *   2. Fetches fresh data directly from the original API
     *   3. Extracts the best video URL
     *   4. Applies all changes to the DB record
     *   5. Updates fix tracking (fix_status, fix_counter, fix_date)
     *   6. Returns the fully updated movie in DETAIL_FIELDS format
     *
     * The returned movie data can be used by the mobile app to update local state
     * and immediately reload the player without any additional calls.
     */
    private function fixCentralized(MovieModel $movie)
    {
        try {
            $fixer = new \App\Services\MovieFixerService();
            $result = $fixer->fix($movie->id);

            if ($result['success'] ?? false) {
                // Reload movie with DETAIL_FIELDS and clean URLs
                $movieData = $this->cleanUrlSingle(
                    MovieModel::select(self::DETAIL_FIELDS)->find($movie->id)->toArray()
                );

                return $this->success([
                    'action'  => 'fix',
                    'movie'   => $movieData,
                    'changes' => $result['changes'] ?? [],
                    'old_url' => $result['old_url'] ?? null,
                    'new_url' => $result['new_url'] ?? null,
                    'message' => $result['message'] ?? 'Movie fixed successfully.',
                ], $result['message'] ?? 'Movie fixed.');
            } else {
                return $this->error($result['message'] ?? 'Fix failed.', 400);
            }
        } catch (\Throwable $e) {
            Log::error("V2 Fix centralized failed for movie {$movie->id}: " . $e->getMessage());
            return $this->error('Fix failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Diagnose a movie — return diagnostic info about URLs, source, status.
     */
    private function fixDiagnose(MovieModel $movie)
    {
        $diagnostics = [
            'id'          => $movie->id,
            'title'       => $movie->title,
            'type'        => $movie->type,
            'status'      => $movie->status,
            'has_url'     => !empty($movie->url) && strlen($movie->url) > 5,
            'url_domain'  => !empty($movie->url) ? parse_url($movie->url, PHP_URL_HOST) : null,
            'has_external_url' => !empty($movie->external_url) && strlen($movie->external_url) > 5,
            'is_muno'     => $movie->is_muno ?? 'No',
            'munowatch_id'=> $movie->munowatch_id ?? null,
            'category_id' => $movie->category_id,
            'genre'       => $movie->genre,
            'vj'          => $movie->vj,
            'views_count' => MovieView::where('movie_model_id', $movie->id)->count(),
            'likes_count' => MovieLike::where('movie_model_id', $movie->id)->count(),
            'has_thumbnail' => !empty($movie->thumbnail_url) && strlen($movie->thumbnail_url) > 5,
            'has_image'   => !empty($movie->image_url) && strlen($movie->image_url) > 5,
            'content_type'=> $movie->content_type ?? null,
            'content_is_video' => $movie->content_is_video ?? null,
        ];

        // Series-specific diagnostics
        if ($movie->type === 'Series' && !empty($movie->category_id)) {
            $series = SeriesMovie::find($movie->category_id);
            $diagnostics['series'] = $series ? [
                'id'             => $series->id,
                'title'          => $series->title,
                'total_episodes' => $series->total_episodes ?? 0,
                'is_active'      => $series->is_active ?? 'No',
                'is_muno'        => $series->is_muno ?? 'No',
            ] : null;

            $diagnostics['episode_count'] = MovieModel::where('category_id', $movie->category_id)
                ->where('status', 'Active')->where('type', 'Series')->count();
        }

        // Check if crawler page exists
        $crawler = MovieCrawlerPage::where('url', $movie->external_url)->first();
        if (!$crawler && !empty($movie->page_source_url)) {
            $crawler = MovieCrawlerPage::where('url', $movie->page_source_url)->first();
        }
        $diagnostics['has_crawler_page'] = $crawler !== null;
        $diagnostics['crawler_status']   = $crawler ? $crawler->status : null;

        return $this->success([
            'action'      => 'diagnose',
            'diagnostics' => $diagnostics,
        ], 'Diagnostics retrieved.');
    }

    /**
     * Refresh a movie — re-process from munowatch source.
     */
    private function fixRefresh(MovieModel $movie)
    {
        try {
            // Check if the movie has a valid source URL
            $sourceUrl = $movie->page_source_url ?? $movie->external_url;
            if (empty($sourceUrl) || strlen($sourceUrl) < 10) {
                return $this->error('Movie has no valid source URL to refresh from.', 400);
            }

            // Store original values for comparison
            $originalUrl   = $movie->url;
            $originalTitle = $movie->title;
            $originalThumb = $movie->thumbnail_url;

            // Reset processing flags so process_munowatch re-fetches
            $movie->muno_processed = 'No';
            $movie->save();

            // Run the munowatch processor
            MovieModel::process_munowatch($movie);

            // Reload movie data
            $movie->refresh();

            // Build change summary
            $changes = [];
            if ($movie->url !== $originalUrl)               $changes[] = 'video_url';
            if ($movie->title !== $originalTitle)            $changes[] = 'title';
            if ($movie->thumbnail_url !== $originalThumb)    $changes[] = 'thumbnail';

            $movieData = $this->cleanUrlSingle(
                MovieModel::select(self::DETAIL_FIELDS)->find($movie->id)->toArray()
            );

            return $this->success([
                'action'  => 'refresh',
                'movie'   => $movieData,
                'changes' => $changes,
                'message' => count($changes) > 0
                    ? 'Movie refreshed. Updated: ' . implode(', ', $changes)
                    : 'Movie refreshed. No changes detected.',
            ], 'Movie data refreshed successfully.');

        } catch (\Throwable $e) {
            Log::error("V2 Fix refresh failed for movie {$movie->id}: " . $e->getMessage());
            return $this->error('Refresh failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test a movie's video URL accessibility via cURL.
     */
    private function fixTestUrl(MovieModel $movie)
    {
        if (empty($movie->url) || strlen($movie->url) < 10) {
            return $this->error('Movie has no video URL to test.', 400);
        }

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $movie->url,
                CURLOPT_NOBODY         => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'okhttp/4.9.0',
            ]);

            curl_exec($ch);
            $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $fileSize    = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            $finalUrl    = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $curlError   = curl_error($ch);
            curl_close($ch);

            $isAccessible = $httpCode >= 200 && $httpCode < 400;
            $isVideo = $isAccessible && (
                stripos($contentType ?? '', 'video') !== false ||
                stripos($contentType ?? '', 'octet-stream') !== false ||
                stripos($contentType ?? '', 'mp4') !== false
            );

            $urlDomain = parse_url($movie->url, PHP_URL_HOST);

            return $this->success([
                'action'       => 'test_url',
                'is_accessible'=> $isAccessible,
                'is_video'     => $isVideo,
                'http_code'    => $httpCode,
                'content_type' => $contentType,
                'file_size'    => $fileSize > 0 ? round($fileSize / 1048576, 1) . ' MB' : 'Unknown',
                'domain'       => $urlDomain,
                'curl_error'   => $curlError ?: null,
                'message'      => $isAccessible
                    ? ($isVideo ? 'Video URL is accessible and valid.' : 'URL is accessible but may not be a video.')
                    : 'Video URL is not accessible (HTTP ' . $httpCode . ').',
            ], $isAccessible ? 'URL test passed.' : 'URL test failed.');

        } catch (\Throwable $e) {
            Log::error("V2 Fix test_url failed for movie {$movie->id}: " . $e->getMessage());
            return $this->error('URL test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Sync episodes for a series movie.
     */
    private function fixSyncEpisodes(MovieModel $movie)
    {
        if ($movie->type !== 'Series') {
            return $this->error('This is not a series episode. Episode syncing only works for series.', 400);
        }

        $categoryId = $movie->category_id;
        if (empty($categoryId) || $categoryId == '0') {
            return $this->error('Movie has no series/category link.', 400);
        }

        try {
            $series = SeriesMovie::find($categoryId);
            if (!$series) {
                return $this->error('Series not found for category_id: ' . $categoryId, 404);
            }

            // Find the crawler page for this series
            $crawlerPage = null;
            if (!empty($series->external_url)) {
                $crawlerPage = MovieCrawlerPage::where('url', $series->external_url)->first();
            }
            if (!$crawlerPage && !empty($movie->external_url)) {
                $crawlerPage = MovieCrawlerPage::where('url', $movie->external_url)->first();
            }

            $beforeCount = MovieModel::where('category_id', $categoryId)
                ->where('status', 'Active')->where('type', 'Series')->count();

            if ($crawlerPage) {
                // Use the existing generate_series_episodes logic
                MovieCrawlerPage::generate_series_episodes($crawlerPage);
            } else if ($series->is_muno === 'Yes' && !empty($series->external_url)) {
                // Try SeriesFixerService directly
                $fixer = new \App\Services\SeriesFixerService();
                $fixer->syncAllEpisodes((int) $series->id);
                $fixer->checkAndActivateSeries((int) $series->id);
            } else {
                return $this->error('No crawler page or munowatch source found for this series.', 400);
            }

            $afterCount = MovieModel::where('category_id', $categoryId)
                ->where('status', 'Active')->where('type', 'Series')->count();

            $series->refresh();

            return $this->success([
                'action'         => 'sync_episodes',
                'series_id'      => $series->id,
                'series_title'   => $series->title,
                'episodes_before'=> $beforeCount,
                'episodes_after' => $afterCount,
                'new_episodes'   => max(0, $afterCount - $beforeCount),
                'total_episodes' => $series->total_episodes ?? $afterCount,
                'message'        => $afterCount > $beforeCount
                    ? ($afterCount - $beforeCount) . ' new episodes synced.'
                    : 'Episode sync complete. No new episodes found.',
            ], 'Episodes synced successfully.');

        } catch (\Throwable $e) {
            Log::error("V2 Fix sync_episodes failed for movie {$movie->id}: " . $e->getMessage());
            return $this->error('Episode sync failed: ' . $e->getMessage(), 500);
        }
    }
}
