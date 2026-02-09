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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ═══════════════════════════════════════════════════════════════
 *  V2 Search API Controller
 * ═══════════════════════════════════════════════════════════════
 *
 *  Endpoints:
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
        'id', 'title', 'url', 'image_url', 'thumbnail_url',
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

        // ── Step 1: Find matching series from series_movies table ──
        $matchingSeriesIds = SeriesMovie::where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('genre', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('vj', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('language', 'LIKE', '%' . $searchTerm . '%');
            })
            ->where('is_active', 'Yes')
            ->pluck('id')
            ->toArray();

        // ── Step 2: Also search in movie_models titles (first episodes) ──
        $titleMatchSeriesIds = MovieModel::where('type', 'Series')
            ->where('status', 'Active')
            ->where('is_first_episode', 'yes')
            ->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('series_title', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('vj', 'LIKE', '%' . $searchTerm . '%');
            })
            ->pluck('category_id')
            ->filter()
            ->unique()
            ->toArray();

        $allSeriesIds = array_unique(array_merge($matchingSeriesIds, $titleMatchSeriesIds));

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
        $firstEpisodes = MovieModel::select(self::LIST_FIELDS)
            ->whereIn('category_id', $pagedSeriesIds)
            ->where('type', 'Series')
            ->where('status', 'Active')
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
                ->where('status', 'Active')
                ->orderByRaw('CAST(NULLIF(episode_number, "") AS UNSIGNED) ASC')
                ->orderBy('id', 'asc')
                ->get()
                ->unique('category_id');

            $firstEpisodes = $firstEpisodes->merge($fallbackEpisodes);
        }

        $items = $this->cleanUrls($firstEpisodes->values()->all());

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

        // Filter out noise (2-char fragments, common words)
        $noise = ['the', 'and', 'for', 'that', 'with', 'you', 'are', 'not', 'this', 'sex', 'xxx'];
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

        return $this->success([
            'trending'       => $items,
            'popular_series' => $popularSeries,
        ], "Trending searches retrieved.");
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

    // ═══════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    private function resolveUser(Request $request): ?User
    {
        $u = Utils::get_user($request);
        if ($u) {
            $u = User::find($u->id);
        }
        return $u;
    }

    private function cleanUrls(array $items): array
    {
        return array_map(function ($item) {
            $data = $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array) $item;
            $urlFields = ['url', 'image_url', 'thumbnail_url', 'poster_url', 'external_url'];
            foreach ($urlFields as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = str_replace(' ', '%20', $data[$field]);
                    $data[$field] = preg_replace('/^http:/i', 'https:', $data[$field]);
                }
            }
            return $data;
        }, $items);
    }
}
