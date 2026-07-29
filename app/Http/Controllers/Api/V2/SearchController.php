<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\MovieSearch;
use App\Models\SeriesMovie;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ═══════════════════════════════════════════════════════════════
 *  V2 Search API Controller
 * ═══════════════════════════════════════════════════════════════
 *
 *  Endpoints:
 *    GET  /api/v2/search/all             – Power search: movies + series combined (6-phase scoring)
 *    GET  /api/v2/search/all/suggestions – Auto-suggest for power search (movies, series, VJs, genres)
 *    GET  /api/v2/search/all/trending    – Trending terms + popular movies + popular series
 *    GET  /api/v2/search/series          – Series-only search (first episodes)
 *    GET  /api/v2/search/suggestions     – Auto-suggest as user types
 *    GET  /api/v2/search/trending        – Trending/popular search terms
 *    GET  /api/v2/search/history         – User's recent search history
 *    DELETE /api/v2/search/history/{id}  – Delete a search history entry
 *    DELETE /api/v2/search/history       – Clear all user search history
 * ═══════════════════════════════════════════════════════════════
 */
class SearchController extends Controller
{
    use ApiResponser;

    protected const LIST_FIELDS = [
        'id', 'title', 'url', 'old_video_url', 'local_video_link', 'image_url', 'thumbnail_url',
        'year', 'rating', 'duration', 'genre', 'language',
        'type', 'status', 'vj', 'is_premium', 'category_id',
        'views_count', 'likes_count', 'episode_number',
        'season_number', 'series_title', 'is_first_episode',
        'description', 'country',
    ];

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/search/series — Series-only search
    // ═══════════════════════════════════════════════════════════

    /**
     * Search series ONLY. Returns first episode per matching series.
     *
     * Query params:
     *   q         – Search term (required, min 2 chars)
     *   page      – Page number (default 1)
     *   per_page  – Items per page (default 20, max 50)
     */
    public function searchSeries(Request $request)
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

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));
        $page = max(1, (int) $request->get('page', 1));

        // ── Steps 1+2 consolidated: single UNION query across series_movies + first episodes ──
        // (replaces 3 separate LIKE queries with 1 round-trip)
        $allSeriesIds = DB::table('series_movies')
            ->select('id')
            ->where('is_active', 'Yes')
            ->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('genre', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('vj', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('language', 'LIKE', '%' . $searchTerm . '%');
            })
            ->union(
                DB::table('series_movies')
                    ->select('series_movies.id')
                    ->join('movie_models', 'movie_models.category_id', '=', 'series_movies.id')
                    ->where('series_movies.is_active', 'Yes')
                    ->where('movie_models.type', 'Series')
                    ->where('movie_models.is_first_episode', 'yes')
                    ->where(function ($q) use ($searchTerm) {
                        $q->where('movie_models.title', 'LIKE', '%' . $searchTerm . '%')
                          ->orWhere('movie_models.series_title', 'LIKE', '%' . $searchTerm . '%')
                          ->orWhere('movie_models.vj', 'LIKE', '%' . $searchTerm . '%');
                    })
            )
            ->pluck('id')
            ->toArray();

        if (empty($allSeriesIds)) {
            // Log the search
            MovieSearch::logSearch($searchTerm, 0, [], $user->id, $request);

            $elapsed = round((microtime(true) - $startTime) * 1000);
            Log::info("[V2:searchSeries] q='{$searchTerm}' results=0 ms={$elapsed}");

            return $this->success([
                'items'      => [],
                'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
            ], "No series found.");
        }

        // ── Step 3: Get first episode for each matching series ──
        $total = count($allSeriesIds);
        $pagedSeriesIds = array_slice($allSeriesIds, ($page - 1) * $perPage, $perPage);

        // Get first episodes (prefer is_first_episode='yes', fallback to lowest episode_number)
        // Series visibility controlled by series_movies.is_active, not episode status
        $firstEpisodes = MovieModel::select(self::LIST_FIELDS)
            ->whereIn('category_id', $pagedSeriesIds)
            ->where('type', 'Series')
            ->where('is_first_episode', 'yes')
            ->get()
            ->unique('category_id');

        // For series without is_first_episode marked, get the lowest episode
        $foundCategoryIds = $firstEpisodes->pluck('category_id')->toArray();
        $missingIds = array_diff($pagedSeriesIds, $foundCategoryIds);

        if (!empty($missingIds)) {
            $fallbackEpisodes = MovieModel::select(self::LIST_FIELDS)
                ->whereIn('category_id', $missingIds)
                ->where('type', 'Series')
                ->orderByRaw('CAST(NULLIF(episode_number, "") AS UNSIGNED) ASC')
                ->orderBy('id', 'asc')
                ->get()
                ->unique('category_id');

            $firstEpisodes = $firstEpisodes->merge($fallbackEpisodes);
        }

        $items = $this->cleanUrls($firstEpisodes->values()->all());

        // ── Step 4: Enrich with series data (episode_count, series_views, series_type) ──
        $categoryIds = $firstEpisodes->pluck('category_id')->filter()->unique()->toArray();
        $seriesData = [];
        if (!empty($categoryIds)) {
            $seriesData = SeriesMovie::whereIn('id', $categoryIds)
                ->select('id', 'total_episodes', 'total_views')
                ->get()
                ->keyBy('id')
                ->toArray();
        }

        $items = array_map(function ($item) use ($seriesData) {
            $data = $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array) $item;
            $catId = $data['category_id'] ?? null;
            $epCount = 0;
            $views = 0;
            if ($catId && isset($seriesData[$catId])) {
                $epCount = (int) ($seriesData[$catId]['total_episodes'] ?? 0);
                $views = (int) ($seriesData[$catId]['total_views'] ?? 0);
            }
            $data['episode_count'] = $epCount;
            $data['series_views'] = $views;
            $data['series_type'] = $epCount <= 5 ? 'mini' : 'full';
            return $data;
        }, $items);

        // Log the search
        $foundIds = $firstEpisodes->pluck('id')->take(10)->toArray();
        MovieSearch::logSearch($searchTerm, count($items), $foundIds, $user->id, $request);

        $elapsed = round((microtime(true) - $startTime) * 1000);
        Log::info("[V2:searchSeries] q='{$searchTerm}' results={$total} page={$page} ms={$elapsed}");

        return $this->success([
            'items'      => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
            ],
        ], "Series search results.");
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/search/suggestions — Auto-suggest
    // ═══════════════════════════════════════════════════════════

    /**
     * Returns auto-complete suggestions as user types.
     * Combines: series titles, popular search terms, VJ names.
     *
     * Query params:
     *   q    – Partial search term (min 1 char)
     *   limit – Max results (default 10, max 20)
     */
    public function suggestions(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $query = trim($request->get('q', ''));
        $limit = min(20, max(1, (int) $request->get('limit', 10)));

        if (mb_strlen($query) < 1) {
            return $this->success(['suggestions' => []], "Type to search.");
        }

        $suggestions = [];
        $seen = [];

        // ── 1. Series titles matching ──
        $seriesTitles = SeriesMovie::where('title', 'LIKE', '%' . $query . '%')
            ->where('is_active', 'Yes')
            ->orderByDesc('total_episodes')
            ->limit($limit)
            ->pluck('title')
            ->toArray();

        foreach ($seriesTitles as $title) {
            $key = strtolower(trim($title));
            if (!isset($seen[$key])) {
                $suggestions[] = ['text' => $title, 'type' => 'series'];
                $seen[$key] = true;
            }
        }

        // ── 2. Popular previous searches ──
        if (count($suggestions) < $limit) {
            $popularSearches = MovieSearch::where('search_term_normalized', 'LIKE', '%' . strtolower($query) . '%')
                ->where('has_results', true)
                ->where('results_count', '>', 0)
                ->whereRaw('LENGTH(search_term) >= 3')
                ->orderByDesc('search_count')
                ->limit($limit - count($suggestions))
                ->pluck('search_term')
                ->toArray();

            foreach ($popularSearches as $term) {
                $key = strtolower(trim($term));
                if (!isset($seen[$key])) {
                    $suggestions[] = ['text' => $term, 'type' => 'search'];
                    $seen[$key] = true;
                }
            }
        }

        // ── 3. VJ names matching ──
        if (count($suggestions) < $limit) {
            $vjNames = MovieModel::where('vj', 'LIKE', '%' . $query . '%')
                ->where('status', 'Active')
                ->where('vj', '!=', '')
                ->whereNotNull('vj')
                ->select('vj')
                ->distinct()
                ->limit($limit - count($suggestions))
                ->pluck('vj')
                ->toArray();

            foreach ($vjNames as $vj) {
                $key = strtolower(trim($vj));
                if (!isset($seen[$key])) {
                    $suggestions[] = ['text' => $vj, 'type' => 'vj'];
                    $seen[$key] = true;
                }
            }
        }

        return $this->success([
            'suggestions' => array_slice($suggestions, 0, $limit),
        ], "Suggestions retrieved.");
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/search/trending — Trending search terms
    // ═══════════════════════════════════════════════════════════

    /**
     * Returns trending/popular search terms for discovery.
     *
     * Query params:
     *   period – "day" | "week" (default) | "month"
     *   limit  – Max results (default 15, max 30)
     */
    public function trending(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $period = $request->get('period', 'week');
        $limit = min(30, max(1, (int) $request->get('limit', 15)));

        $since = match ($period) {
            'day'   => Carbon::now()->subDay(),
            'month' => Carbon::now()->subMonth(),
            default => Carbon::now()->subWeek(),
        };

        // Cache per period — trending data changes slowly (P5-10)
        $noise = ['the', 'and', 'for', 'that', 'with', 'you', 'are', 'not', 'this', 'sex', 'xxx'];
        $data = \Illuminate\Support\Facades\Cache::remember('search_trending_' . $period, 300, function () use ($since, $noise, $limit) {
            // Trending: aggregate by normalized term, filter noise
            $trending = DB::table('movie_searches')
                ->select(
                    'search_term_normalized as term',
                    DB::raw('MAX(search_term) as display_term'),
                    DB::raw('SUM(search_count) as total_searches'),
                    DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                    DB::raw('MAX(has_results) as has_results')
                )
                ->where('last_searched_at', '>=', $since)
                ->where('has_results', true)
                ->whereRaw('LENGTH(search_term_normalized) >= 3')
                ->groupBy('search_term_normalized')
                ->orderByDesc('total_searches')
                ->limit($limit * 2) // fetch extra to filter
                ->get();

            $items = [];
            foreach ($trending as $row) {
                if (in_array($row->term, $noise)) continue;
                if (strlen($row->term) < 3) continue;
                $items[] = [
                    'term'           => $row->display_term,
                    'search_count'   => (int) $row->total_searches,
                    'unique_users'   => (int) $row->unique_users,
                ];
                if (count($items) >= $limit) break;
            }

            // Also get popular series (always useful for discovery)
            $popularSeries = SeriesMovie::where('is_active', 'Yes')
                ->whereNotNull('title')
                ->orderByDesc('total_episodes')
                ->limit(10)
                ->select('id', 'title', 'thumbnail', 'total_episodes', 'genre', 'vj')
                ->get()
                ->toArray();

            return ['trending' => $items, 'popular_series' => $popularSeries];
        });

        return $this->success($data, "Trending searches retrieved.");
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/v2/search/history — User's recent searches
    // ═══════════════════════════════════════════════════════════

    /**
     * Returns user's recent search history.
     *
     * Query params:
     *   limit – Max results (default 20, max 50)
     */
    public function history(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $limit = min(50, max(1, (int) $request->get('limit', 20)));

        $searches = MovieSearch::where('user_id', $user->id)
            ->where('has_results', true)
            ->whereRaw('LENGTH(search_term) >= 3')
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->select('id', 'search_term', 'results_count', 'search_count', 'last_searched_at')
            ->get()
            ->unique('search_term_normalized')
            ->values()
            ->toArray();

        return $this->success([
            'searches' => $searches,
        ], "Search history retrieved.");
    }

    // ═══════════════════════════════════════════════════════════
    //  DELETE /api/v2/search/history/{id} — Delete single entry
    // ═══════════════════════════════════════════════════════════

    public function deleteHistory(Request $request, $id)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $search = MovieSearch::where('id', $id)->where('user_id', $user->id)->first();
        if ($search) {
            $search->delete();
        }

        return $this->success(null, "Search entry deleted.");
    }

    // ═══════════════════════════════════════════════════════════
    //  DELETE /api/v2/search/history — Clear all user history
    // ═══════════════════════════════════════════════════════════

    public function clearHistory(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        MovieSearch::where('user_id', $user->id)->delete();

        return $this->success(null, "Search history cleared.");
    }

    // ═══════════════════════════════════════════════════════════════
    //  GET /api/v2/search/all — Power search: movies + series (6-phase scoring)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Combined search across movies AND series with 6-phase relevance scoring.
     *
     * Query params:
     *   q         – Search term (required, min 2 chars)
     *   page      – Page number (default 1)
     *   per_page  – Items per page (default 20, max 50)
     *   type      – Filter: "movie", "series", or omit for all
     *   genre     – Filter by genre (partial match)
     *   vj        – Filter by VJ name (partial match)
     *   year      – Filter by year (exact)
     *   sort      – "relevance" (default), "newest", "popular", "rating"
     */
    public function searchAll(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $startTime  = microtime(true);
        $searchTerm = trim($request->get('q', ''));

        if (mb_strlen($searchTerm) < 2) {
            return $this->error('Search term must be at least 2 characters.', 422);
        }

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));
        $page    = max(1, (int) $request->get('page', 1));
        $typeFilter  = strtolower(trim($request->get('type', '')));
        $genreFilter = trim($request->get('genre', ''));
        $vjFilter    = trim($request->get('vj', ''));
        $yearFilter  = trim($request->get('year', ''));
        $sort        = strtolower(trim($request->get('sort', 'relevance')));

        $ignoreWords = ['the', 'a', 'an', 'of', 'in', 'on', 'at', 'for', 'and', 'that', 'with', 'to', 'is', 'are', 'was', 'were', 'vj'];
        $scores      = [];      // id => score
        $itemTypes   = [];      // id => 'movie' | 'series'
        $seriesInfo  = [];      // id => ['series_name'=>..., 'episode_count'=>..., 'series_type'=>...]

        // Pre-compute words and significant words for use across all phases
        $words    = array_filter(explode(' ', $searchTerm), fn($w) => $w !== '');
        $sigWords = array_values(array_filter($words, fn($w) => !in_array(strtolower($w), $ignoreWords) && mb_strlen($w) >= 3));

        // ── Phase 1: Exact title match → Movies (1000 pts) ──
        if ($typeFilter !== 'series') {
            $movieExactIds = MovieModel::where('type', 'Movie')
                ->where('title', 'LIKE', '%' . $searchTerm . '%')
                ->pluck('id')->toArray();

            foreach ($movieExactIds as $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 1000;
                $itemTypes[$id] = 'movie';
            }
        }

        // ── Phase 2: Exact title match → Series first-episodes (950 pts) ──
        if ($typeFilter !== 'movie') {
            $seriesMatches = SeriesMovie::where('title', 'LIKE', '%' . $searchTerm . '%')
                ->where('is_active', 'Yes')
                ->get();

            if ($seriesMatches->isNotEmpty()) {
                $seriesMap = $seriesMatches->keyBy('id');

                $firstEpisodes = MovieModel::whereIn('category_id', $seriesMatches->pluck('id'))
                    ->where('type', 'Series')
                    ->select('id', 'category_id')
                    ->orderByRaw('CAST(NULLIF(episode_number, "") AS UNSIGNED) ASC')
                    ->orderBy('id', 'asc')
                    ->get()
                    ->unique('category_id');

                // Batch-fetch episode counts for ALL matched series in one GROUP BY query
                // instead of one COUNT(*) query per series (N+1 fix).
                $allCatIds = $firstEpisodes->pluck('category_id')->filter()->unique()->toArray();
                $batchEpCounts = [];
                if (!empty($allCatIds)) {
                    $batchEpCounts = DB::table('movie_models')
                        ->whereIn('category_id', $allCatIds)
                        ->where('type', 'Series')
                        ->groupBy('category_id')
                        ->select('category_id', DB::raw('COUNT(*) as cnt'))
                        ->pluck('cnt', 'category_id')
                        ->toArray();
                }

                foreach ($firstEpisodes as $ep) {
                    $scores[$ep->id] = ($scores[$ep->id] ?? 0) + 950;
                    $itemTypes[$ep->id] = 'series';
                    if (isset($seriesMap[$ep->category_id])) {
                        $s = $seriesMap[$ep->category_id];
                        $epCount = (int) ($batchEpCounts[$ep->category_id] ?? 0);
                        $seriesInfo[$ep->id] = [
                            'series_name'   => $s->title,
                            'episode_count' => $epCount,
                            'series_type'   => $epCount <= 3 ? 'MINI' : ($epCount <= 8 ? 'SERIES' : 'PRO'),
                        ];
                    }
                }
            }
        }

        // ── Phase 3: VJ name match ──
        // Pass A: full search term in vj field (900 pts — exact VJ name typed)
        $vjFullIds = MovieModel::where('vj', 'LIKE', '%' . $searchTerm . '%')
            ->whereNotIn('id', array_keys($scores))
            ->pluck('id')->toArray();

        foreach ($vjFullIds as $id) {
            $scores[$id] = ($scores[$id] ?? 0) + 900;
            if (!isset($itemTypes[$id])) $itemTypes[$id] = 'movie';
        }

        // Pass B: each significant word in vj field (750 pts per word match)
        // Catches "vj junior mechanic" → finds Vj Junior movies even when movie title is separate
        if (!empty($sigWords)) {
            $vjWordIds = MovieModel::where(function ($q) use ($sigWords) {
                    foreach ($sigWords as $w) {
                        $q->orWhere('vj', 'LIKE', '%' . $w . '%');
                    }
                })
                ->whereNotIn('id', array_keys($scores))
                ->limit(150)
                ->pluck('id')->toArray();

            foreach ($vjWordIds as $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 750;
                if (!isset($itemTypes[$id])) $itemTypes[$id] = 'movie';
            }
        }

        // ── Phase 4: Progressive word removal (700→500 pts) ──
        if (count($words) > 1) {
            // Remove from end
            $temp = $words;
            while (count($temp) > 1) {
                array_pop($temp);
                $validWords = array_filter($temp, fn($w) => !in_array(strtolower($w), $ignoreWords));
                if (empty($validWords)) break;
                $phrase = implode(' ', $temp);
                $matches = MovieModel::where('title', 'LIKE', '%' . $phrase . '%')
                    ->whereNotIn('id', array_keys($scores))
                    ->pluck('id')->toArray();
                $pts = 700 / count($words);
                foreach ($matches as $id) {
                    $scores[$id] = ($scores[$id] ?? 0) + $pts;
                    if (!isset($itemTypes[$id])) $itemTypes[$id] = 'movie';
                }
            }
            // Remove from start
            $temp = $words;
            while (count($temp) > 1) {
                array_shift($temp);
                $validWords = array_filter($temp, fn($w) => !in_array(strtolower($w), $ignoreWords));
                if (empty($validWords)) break;
                $phrase = implode(' ', $temp);
                $matches = MovieModel::where('title', 'LIKE', '%' . $phrase . '%')
                    ->whereNotIn('id', array_keys($scores))
                    ->pluck('id')->toArray();
                $pts = 500 / count($words);
                foreach ($matches as $id) {
                    $scores[$id] = ($scores[$id] ?? 0) + $pts;
                    if (!isset($itemTypes[$id])) $itemTypes[$id] = 'movie';
                }
            }
        }

        // ── Phase 5: Genre match (400 pts) ──
        // Always search the full term. If it has commas (genre suggestion clicked), also
        // search each comma-part individually so "Biography, Drama" finds Drama movies too.
        $genreTerms = [$searchTerm];
        if (strpos($searchTerm, ',') !== false) {
            foreach (array_map('trim', explode(',', $searchTerm)) as $part) {
                if (mb_strlen($part) >= 2) $genreTerms[] = $part;
            }
            $genreTerms = array_values(array_unique($genreTerms));
        }
        $genreHit = MovieModel::where(function ($q) use ($genreTerms) {
                foreach ($genreTerms as $term) {
                    $q->orWhere('genre', 'LIKE', '%' . $term . '%');
                }
            })
            ->whereNotIn('id', array_keys($scores))
            ->limit(80)
            ->pluck('id')->toArray();

        foreach ($genreHit as $id) {
            $scores[$id] = ($scores[$id] ?? 0) + 400;
            if (!isset($itemTypes[$id])) $itemTypes[$id] = 'movie';
        }

        // ── Phase 6: Individual significant words (200 pts title, 150 pts description) ──
        if (!empty($sigWords)) {
            // Title word matches
            $wordHits = MovieModel::where(function ($q) use ($sigWords) {
                    foreach ($sigWords as $w) {
                        $q->orWhere('title', 'LIKE', '%' . $w . '%');
                    }
                })
                ->whereNotIn('id', array_keys($scores))
                ->limit(100)
                ->pluck('id')->toArray();

            foreach ($wordHits as $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 200;
                if (!isset($itemTypes[$id])) $itemTypes[$id] = 'movie';
            }

            // Description word matches (150 pts — broader contextual matching)
            $descHits = MovieModel::where(function ($q) use ($sigWords) {
                    foreach ($sigWords as $w) {
                        $q->orWhere('description', 'LIKE', '%' . $w . '%');
                    }
                })
                ->whereNotIn('id', array_keys($scores))
                ->limit(80)
                ->pluck('id')->toArray();

            foreach ($descHits as $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 150;
                if (!isset($itemTypes[$id])) $itemTypes[$id] = 'movie';
            }
        }

        // ── Apply filters ──
        if (!empty($scores)) {
            $filteredQuery = MovieModel::select('id', 'type', 'category_id')
                ->whereIn('id', array_keys($scores));

            if ($typeFilter === 'movie') {
                $filteredQuery->where('type', 'Movie');
            } elseif ($typeFilter === 'series') {
                $filteredQuery->where('type', 'Series');
            }
            if (!empty($genreFilter)) {
                $filteredQuery->where('genre', 'LIKE', '%' . $genreFilter . '%');
            }
            if (!empty($vjFilter)) {
                $filteredQuery->where('vj', 'LIKE', '%' . $vjFilter . '%');
            }
            if (!empty($yearFilter)) {
                $filteredQuery->where('year', $yearFilter);
            }

            $validRows = $filteredQuery->get();
            $validIds = $validRows->pluck('type', 'id')->toArray();
            $scores = array_intersect_key($scores, $validIds);

            // Correct item types from DB
            foreach ($validIds as $id => $dbType) {
                $itemTypes[$id] = strtolower($dbType) === 'series' ? 'series' : 'movie';
            }

            // Deduplicate series: keep only first episode per category_id
            $categoryMap = $validRows->where('type', 'Series')->pluck('category_id', 'id')->toArray();
            $seenCategories = [];
            $removeIds = [];

            foreach ($categoryMap as $id => $catId) {
                if (!$catId) continue;
                if (isset($seenCategories[$catId])) {
                    // Keep whichever has a higher score
                    $existingId = $seenCategories[$catId];
                    if (($scores[$id] ?? 0) > ($scores[$existingId] ?? 0)) {
                        $removeIds[] = $existingId;
                        $seenCategories[$catId] = $id;
                    } else {
                        $removeIds[] = $id;
                    }
                } else {
                    $seenCategories[$catId] = $id;
                }
            }

            // For each kept series episode, swap it with the actual first episode if needed
            // Batch-fetch first episodes for all kept category IDs in one query (N+1 fix).
            $keptCategoryIds = array_keys($seenCategories);
            $firstEpsByCategory = [];
            if (!empty($keptCategoryIds)) {
                $firstEpsByCategory = DB::table('movie_models')
                    ->whereIn('category_id', $keptCategoryIds)
                    ->where('type', 'Series')
                    ->select('id', 'category_id')
                    ->orderByRaw('CAST(NULLIF(episode_number, "") AS UNSIGNED) ASC')
                    ->orderBy('id', 'asc')
                    ->get()
                    ->unique('category_id')
                    ->keyBy('category_id');
            }

            foreach ($seenCategories as $catId => $keptId) {
                $firstEp = $firstEpsByCategory->get($catId) ?? null;

                if ($firstEp && $firstEp->id !== $keptId) {
                    // Swap: transfer score to first episode
                    $scores[$firstEp->id] = $scores[$keptId] ?? 0;
                    $itemTypes[$firstEp->id] = 'series';
                    if (isset($seriesInfo[$keptId])) {
                        $seriesInfo[$firstEp->id] = $seriesInfo[$keptId];
                        unset($seriesInfo[$keptId]);
                    }
                    $removeIds[] = $keptId;
                }
            }

            // Remove non-first/duplicate series episodes
            foreach ($removeIds as $rid) {
                unset($scores[$rid]);
                unset($itemTypes[$rid]);
            }
        }

        // ── Sort ──
        if ($sort === 'relevance' || empty($sort)) {
            arsort($scores);
        }
        // For other sorts, we apply after fetch

        $total  = count($scores);
        $sliced = array_slice($scores, ($page - 1) * $perPage, $perPage, true);

        if (empty($sliced)) {
            $elapsed = round((microtime(true) - $startTime) * 1000);
            Log::info("[V2:searchAll] q='{$searchTerm}' results=0 ms={$elapsed}");

            // Self-heal stale suggestions: mark ALL historical records for this term as
            // has_results=false so they stop surfacing in auto-complete. Happens when
            // content that previously returned results is later deactivated.
            $normalizedTerm = strtolower($searchTerm);
            MovieSearch::where('search_term_normalized', $normalizedTerm)
                ->where('has_results', true)
                ->update(['has_results' => false, 'results_count' => 0]);

            // Log the search (creates a new record if none exists for this user/session)
            MovieSearch::logSearch($searchTerm, 0, [], $user->id, $request);

            return $this->success([
                'items'      => [],
                'filters'    => $this->buildFilterMeta($searchTerm),
                'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
            ], "No results found.");
        }

        $movies = MovieModel::select(self::LIST_FIELDS)
            ->whereIn('id', array_keys($sliced))
            ->get()
            ->keyBy('id');

        // Build result items preserving score order
        $items = [];
        foreach ($sliced as $id => $score) {
            if (!isset($movies[$id])) continue;
            $data = $movies[$id]->toArray();
            $data['_score']     = round($score);
            $data['_item_type'] = $itemTypes[$id] ?? 'movie';
            if (isset($seriesInfo[$id])) {
                $data = array_merge($data, $seriesInfo[$id]);
            }
            $items[] = $data;
        }

        // Apply secondary sorting if not relevance
        if ($sort === 'newest') {
            usort($items, fn($a, $b) => ($b['year'] ?? 0) <=> ($a['year'] ?? 0));
        } elseif ($sort === 'popular') {
            usort($items, fn($a, $b) => ($b['views_count'] ?? 0) <=> ($a['views_count'] ?? 0));
        } elseif ($sort === 'rating') {
            usort($items, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));
        }

        $items = $this->cleanUrls($items);

        $elapsed = round((microtime(true) - $startTime) * 1000);
        Log::info("[V2:searchAll] q='{$searchTerm}' type={$typeFilter} results={$total} page={$page} ms={$elapsed}");

        // Log the search for history/trending
        $foundIds = array_keys(array_slice($scores, 0, 10, true));
        MovieSearch::logSearch($searchTerm, $total, $foundIds, $user->id, $request);

        // Count types in full result set
        $movieCount  = count(array_filter($itemTypes, fn($t) => $t === 'movie'));
        $seriesCount = count(array_filter($itemTypes, fn($t) => $t === 'series'));

        return $this->success([
            'items'       => array_values($items),
            'result_meta' => [
                'total'        => $total,
                'movie_count'  => $movieCount,
                'series_count' => $seriesCount,
                'query'        => $searchTerm,
                'elapsed_ms'   => $elapsed,
            ],
            'filters'     => $this->buildFilterMeta($searchTerm),
            'pagination'  => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
            ],
        ], "Search results retrieved.");
    }

    // ═══════════════════════════════════════════════════════════════
    //  GET /api/v2/search/all/suggestions — Auto-suggest for power search
    // ═══════════════════════════════════════════════════════════════

    /**
     * Returns combined suggestions: movie titles, series titles, VJ names, genres.
     *
     * Query params:
     *   q     – Partial search term (min 1 char)
     *   limit – Max results per category (default 5, max 10)
     */
    public function allSuggestions(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $q     = trim($request->get('q', ''));
        $limit = min(10, max(1, (int) $request->get('limit', 5)));

        if (mb_strlen($q) < 1) {
            return $this->error('Query required.', 422);
        }

        // Cache suggestions for 5 min per query+limit combo
        $cacheKey = "v2_suggestions_" . md5($q . $limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $this->success($cached, "Suggestions retrieved.");
        }

        // Movie title suggestions
        $movieTitles = MovieModel::where('type', 'Movie')
            ->where('status', 'Active')
            ->where('title', 'LIKE', '%' . $q . '%')
            ->orderByDesc('views_count')
            ->limit($limit)
            ->pluck('title')
            ->map(fn($t) => ['text' => $t, 'type' => 'movie'])
            ->toArray();

        // Series title suggestions
        $seriesTitles = SeriesMovie::where('title', 'LIKE', '%' . $q . '%')
            ->where('is_active', 'Yes')
            ->orderByDesc('total_views')
            ->limit($limit)
            ->pluck('title')
            ->map(fn($t) => ['text' => $t, 'type' => 'series'])
            ->toArray();

        // VJ name suggestions
        $vjNames = MovieModel::where('status', 'Active')
            ->where('vj', 'LIKE', '%' . $q . '%')
            ->whereNotNull('vj')
            ->where('vj', '!=', '')
            ->selectRaw('DISTINCT vj')
            ->limit($limit)
            ->pluck('vj')
            ->map(fn($v) => ['text' => $v, 'type' => 'vj'])
            ->toArray();

        // Genre suggestions — parse individual genre values from potentially comma-separated fields
        $rawGenreStrings = MovieModel::where('status', 'Active')
            ->where('genre', 'LIKE', '%' . $q . '%')
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->selectRaw('DISTINCT genre')
            ->limit($limit * 4)
            ->pluck('genre')
            ->toArray();

        $genres = [];
        $seenGenres = [];
        foreach ($rawGenreStrings as $genreValue) {
            foreach (array_map('trim', explode(',', $genreValue)) as $singleGenre) {
                if (empty($singleGenre)) continue;
                $lowerGenre = strtolower($singleGenre);
                if (stripos($singleGenre, $q) !== false && !isset($seenGenres[$lowerGenre])) {
                    $seenGenres[$lowerGenre] = true;
                    $genres[] = ['text' => $singleGenre, 'type' => 'genre'];
                    if (count($genres) >= $limit) break 2;
                }
            }
        }

        // Popular past searches matching the query
        $popularSearches = MovieSearch::where('search_term', 'LIKE', '%' . $q . '%')
            ->where('has_results', true)
            ->groupBy('search_term_normalized')
            ->selectRaw('MAX(search_term) as search_term, SUM(search_count) as total')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('search_term')
            ->map(fn($s) => ['text' => $s, 'type' => 'search'])
            ->toArray();

        // Merge and deduplicate by text (case-insensitive)
        $all  = array_merge($movieTitles, $seriesTitles, $vjNames, $genres, $popularSearches);
        $seen = [];
        $suggestions = [];
        foreach ($all as $item) {
            $key = strtolower($item['text']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $suggestions[] = $item;
            }
        }

        // Sort: prioritize items starting with the query
        usort($suggestions, function ($a, $b) use ($q) {
            $aStart = stripos($a['text'], $q) === 0 ? 0 : 1;
            $bStart = stripos($b['text'], $q) === 0 ? 0 : 1;
            return $aStart <=> $bStart;
        });

        $result = [
            'suggestions' => array_slice($suggestions, 0, 20),
            'query'       => $q,
        ];

        // Cache for 5 minutes
        Cache::put($cacheKey, $result, 300);

        return $this->success($result, "Suggestions retrieved.");
    }

    // ═══════════════════════════════════════════════════════════════
    //  GET /api/v2/search/all/trending — Trending terms + popular content
    // ═══════════════════════════════════════════════════════════════

    /**
     * Returns trending search terms + popular movies + popular series.
     *
     * Query params:
     *   period – "day", "week" (default), "month"
     *   limit  – Max items per section (default 12, max 30)
     */
    public function allTrending(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $period = $request->get('period', 'week');
        $limit  = min(30, max(1, (int) $request->get('limit', 12)));

        $since = match ($period) {
            'day'   => now()->subDay(),
            'month' => now()->subMonth(),
            default => now()->subWeek(),
        };

        // Trending search terms
        $trendingTerms = MovieSearch::where('last_searched_at', '>=', $since)
            ->where('has_results', true)
            ->whereRaw('LENGTH(search_term) >= 3')
            ->groupBy('search_term_normalized')
            ->selectRaw('MAX(search_term) as search_term, SUM(search_count) as total')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('search_term')
            ->toArray();

        // Popular movies (most viewed recently)
        $popularMovies = MovieModel::where('type', 'Movie')
            ->where('status', 'Active')
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->orderByDesc('views_count')
            ->limit($limit)
            ->select(self::LIST_FIELDS)
            ->get()
            ->toArray();
        $popularMovies = $this->cleanUrls($popularMovies);

        // Popular series (first episode of most viewed series)
        $popularSeriesIds = SeriesMovie::where('is_active', 'Yes')
            ->orderByDesc('total_views')
            ->limit($limit)
            ->pluck('id', 'title')
            ->toArray();

        $popularSeries = [];
        if (!empty($popularSeriesIds)) {
            $firstEps = MovieModel::whereIn('category_id', array_values($popularSeriesIds))
                ->where('type', 'Series')
                ->where('status', 'Active')
                ->select(self::LIST_FIELDS)
                ->orderByRaw('CAST(NULLIF(episode_number, "") AS UNSIGNED) ASC')
                ->orderBy('id', 'asc')
                ->get()
                ->unique('category_id');

            // Batch-fetch episode counts for all series in one query (N+1 fix)
            $seriesCategoryIds = $firstEps->pluck('category_id')->filter()->unique()->toArray();
            $batchEpCounts = [];
            if (!empty($seriesCategoryIds)) {
                $batchEpCounts = DB::table('movie_models')
                    ->whereIn('category_id', $seriesCategoryIds)
                    ->where('type', 'Series')
                    ->groupBy('category_id')
                    ->select('category_id', DB::raw('COUNT(*) as cnt'))
                    ->pluck('cnt', 'category_id')
                    ->toArray();
            }

            foreach ($firstEps as $ep) {
                $data = $ep->toArray();
                $epCount = (int) ($batchEpCounts[$ep->category_id] ?? 0);
                $data['episode_count'] = $epCount;
                $data['series_type']   = $epCount <= 3 ? 'MINI' : ($epCount <= 8 ? 'SERIES' : 'PRO');
                $data['_item_type']    = 'series';
                $popularSeries[] = $data;
            }
            $popularSeries = $this->cleanUrls($popularSeries);
        }

        // Available genres for filter chips
        $topGenres = MovieModel::where('status', 'Active')
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->selectRaw('genre, COUNT(*) as cnt')
            ->groupBy('genre')
            ->orderByDesc('cnt')
            ->limit(20)
            ->pluck('genre')
            ->toArray();

        return $this->success([
            'trending_terms'  => $trendingTerms,
            'popular_movies'  => array_values($popularMovies),
            'popular_series'  => array_values($popularSeries),
            'top_genres'      => $topGenres,
        ], "Trending data retrieved.");
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRIVATE: Build filter metadata from matching results
    // ═══════════════════════════════════════════════════════════════

    private function buildFilterMeta(string $searchTerm): array
    {
        // Available genres in result set
        $genres = MovieModel::where('status', 'Active')
            ->where('title', 'LIKE', '%' . $searchTerm . '%')
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->selectRaw('DISTINCT genre')
            ->limit(20)
            ->pluck('genre')
            ->toArray();

        // Available years
        $years = MovieModel::where('status', 'Active')
            ->where('title', 'LIKE', '%' . $searchTerm . '%')
            ->whereNotNull('year')
            ->where('year', '!=', '')
            ->selectRaw('DISTINCT year')
            ->orderByDesc('year')
            ->limit(15)
            ->pluck('year')
            ->toArray();

        // Available VJs
        $vjs = MovieModel::where('status', 'Active')
            ->where('title', 'LIKE', '%' . $searchTerm . '%')
            ->whereNotNull('vj')
            ->where('vj', '!=', '')
            ->selectRaw('DISTINCT vj')
            ->limit(15)
            ->pluck('vj')
            ->toArray();

        return [
            'genres' => $genres,
            'years'  => $years,
            'vjs'    => $vjs,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    private function resolveUser(Request $request): ?User
    {
        return Utils::get_user($request);
    }

    private function cleanUrls(array $items): array
    {
        $storageBase = rtrim(config('app.url'), '/') . '/storage/';
        return array_map(function ($item) use ($storageBase) {
            $data = $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array) $item;

            // Storage maintenance bypass: rows that skipped the Eloquent accessor
            // (raw queries, cached arrays) still need the fallback substitution.
            if (!empty($data['url'])) {
                $data['url'] = \App\Models\MovieModel::resolveMaintenanceUrl(
                    (string) $data['url'],
                    $data['id'] ?? null,
                    isset($data['old_video_url']) ? (string) $data['old_video_url'] : null
                ) ?? $data['url'];
            }
            unset($data['old_video_url']);

            $urlFields = ['url', 'image_url', 'thumbnail_url', 'poster_url', 'external_url'];
            foreach ($urlFields as $field) {
                if (empty($data[$field])) {
                    continue;
                }
                $v = (string) $data[$field];
                if (!str_starts_with($v, 'http')) {
                    $data[$field] = $storageBase . ltrim($v, '/');
                } else {
                    $data[$field] = str_replace(' ', '%20', $v);
                    $data[$field] = preg_replace('/^http:/i', 'https:', $data[$field]);
                }
            }
            unset($data['local_video_link']);
            return $data;
        }, $items);
    }
}
