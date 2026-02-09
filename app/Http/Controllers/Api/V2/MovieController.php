<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
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
        $type    = $request->get('type', 'Movie');
        $status  = $request->get('status', 'Active');
        $sort    = $request->get('sort', 'latest');

        $query = MovieModel::select(self::LIST_FIELDS)
            ->where('type', $type)
            ->where('status', $status);

        // For Movies: only show standalone movies (not series episodes)
        if ($type === 'Movie') {
            $query->where(function ($q) {
                $q->whereNull('category_id')->orWhere('category_id', 0);
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
        Log::info("[V2:movies] type={$type} sort={$sort} page={$paginated->currentPage()} per_page={$perPage} total={$paginated->total()} ms={$elapsed}");

        return $this->success([
            'items'      => $items,
            'pagination' => $this->paginationMeta($paginated),
        ], "Movies retrieved successfully.");
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

        $elapsed = round((microtime(true) - $startTime) * 1000);
        Log::info("[V2:movie] id={$id} type={$movie->type} ms={$elapsed}");

        return $this->success([
            'movie'             => $movieData,
            'series_info'       => $seriesInfo,
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

        // ── If Series episode: same-series episodes first ──
        if ($movie->type === 'Series' && !empty($movie->category_id)) {
            $sameSeriesIds = MovieModel::where('category_id', $movie->category_id)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->orderByRaw('CAST(NULLIF(episode_number, "") AS UNSIGNED) ASC')
                ->orderBy('id', 'asc')
                ->pluck('id')
                ->toArray();

            foreach ($sameSeriesIds as $sid) {
                $scored[$sid] = 10000;
            }
        }

        // ── Genre match ──
        if (!empty($movie->genre) && count($scored) < $perPage * 2) {
            $genreIds = MovieModel::where('genre', $movie->genre)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($scored))
                ->limit(30)
                ->pluck('id')
                ->toArray();

            foreach ($genreIds as $gid) {
                $scored[$gid] = 5000;
            }
        }

        // ── VJ match ──
        if (!empty($movie->vj) && count($scored) < $perPage * 2) {
            $vjIds = MovieModel::where('vj', 'LIKE', '%' . $movie->vj . '%')
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($scored))
                ->limit(20)
                ->pluck('id')
                ->toArray();

            foreach ($vjIds as $vid) {
                $scored[$vid] = 4000;
            }
        }

        // ── Title similarity ──
        $titleWords = $this->extractSignificantWords($movie->title);
        if (!empty($titleWords) && count($scored) < $perPage * 2) {
            $phrase = implode(' ', $titleWords);
            $titleIds = MovieModel::where('title', 'LIKE', '%' . $phrase . '%')
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($scored))
                ->limit(20)
                ->pluck('id')
                ->toArray();

            foreach ($titleIds as $tid) {
                $scored[$tid] = 3000;
            }
        }

        // ── Year match ──
        if (!empty($movie->year) && count($scored) < $perPage * 2) {
            $yearIds = MovieModel::where('year', $movie->year)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($scored))
                ->limit(15)
                ->pluck('id')
                ->toArray();

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
}
