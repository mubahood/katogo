<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\MovieDownload;
use App\Models\MovieLike;
use App\Models\MovieModel;
use App\Models\MovieView;
use App\Models\MovieWishlist;
use App\Models\SubscriptionTransaction;
use App\Models\TrendingNotification;
use App\Models\User;
use App\Models\Utils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManifestController extends Controller
{
    /**
     * Slim fields — only what the mobile card UI needs.
     * Removes: url, image_url, description, country, language, status,
     *          likes_count, dislikes_count, comments_count, etc.
     */
    private const SLIM_FIELDS = [
        'id',
        'title',
        'url',
        'local_video_link',
        'thumbnail_url',
        'genre',
        'type',
        'vj',
        'is_premium',
        'year',
        'duration',
        'rating',
        'views_time_count',
        'downloads_count',
        'category_id',
        'is_first_episode',
    ];

    /**
     * GET /api/v2/manifest
     *
     * Optimised V2 manifest:
     *  – Lean movie objects (12 fields vs 20+ in V1)
     *  – Typed sections with keys (continue_watching, trending, popular, …)
     *  – Server-side caching for shared data (genres, VJs, movie sections)
     *  – Personal data (continue_watching, subscription, stats) always fresh
     *  – Supports app_type: ugflix, lugaflix, muno_app, web
     */
    public function index(Request $request)
    {
        $startTime = microtime(true);

        // ── 1. Authenticate & bookkeeping ────────────────────
        $u = Utils::get_user($request);
        if (!$u) {
            return Utils::error('User not found.');
        }

        $app_type = Utils::get_app_type($request);
        $validTypes = ['ugflix', 'lugaflix', 'muno_app', 'web', 'vjjunior', 'katogo'];
        if (!in_array($app_type, $validTypes)) {
            $app_type = 'ugflix';
        }

        try {
            $u->app_type = $app_type;
            $platform = Utils::get_platform_from_request($request);
            if ($platform) {
                $u->platform = $platform;
            }
            $u->save();
        } catch (\Throwable $e) {
            // Non-fatal: bookkeeping update failed; continue serving manifest
        }

        // ── 2. Throttled payment check ─────────────────────
        // Faster cadence when there are unfinished payments to make
        // subscription activation reflect in manifest almost immediately.
        $paymentCacheKey = "v2_pay_check_{$u->id}";
        $hasRecentUnfinishedPayment = SubscriptionTransaction::where('user_id', $u->id)
            ->whereNotIn('status', ['Completed', 'Failed', 'Cancelled'])
            ->where('created_at', '>=', Carbon::now()->subHours(72))
            ->exists();

        $paymentCheckTtlSeconds = $hasRecentUnfinishedPayment ? 15 : 120;
        if (!Cache::has($paymentCacheKey)) {
            Cache::put($paymentCacheKey, true, $paymentCheckTtlSeconds);
            $this->checkPendingPayments($u->id);
        }

        // ── 3. Build manifest ────────────────────────────────
        $userId = $u->id;

        // Rotation seed — content cycles every 6 hours (4 times per day)
        $rotationSlot = (int) floor(Carbon::now()->timestamp / 21600);

        // Featured movie: rotate every 6 hours but re-score against real recent plays.
        // TTL = 30 min so if plays surge on a movie the hero updates within the hour.
        $today         = Carbon::now()->format('Y-m-d');
        $featuredKey   = "v2_featured_{$today}_{$rotationSlot}";
        $featured = Cache::remember($featuredKey, 1800, function () {
            return $this->getFeaturedMovie();
        });

        // Guard: verify cached featured movie is still Active.
        // A movie can be deactivated (auto-fix failure, admin action) during the 5-min TTL.
        // One cheap EXISTS query per request; the full re-pick only fires on the rare deactivation.
        if ($featured && isset($featured['id'])) {
            $stillActive = DB::table('movie_models')->where('id', $featured['id'])
                ->where('status', 'Active')
                ->exists();

            if (!$stillActive) {
                $inactiveId = $featured['id'];
                $fresh = $this->pickNextFeaturedMovie($inactiveId);
                if ($fresh) {
                    $featured = $fresh;
                    Cache::put($featuredKey, $featured, 900);
                    Log::info('V2 Manifest: cached featured movie was inactive, replaced with next active', [
                        'inactive_id' => $inactiveId,
                        'new_id'      => $featured['id'],
                    ]);
                }
            }
        }

        // Continue Watching — personal, never cached
        $continueWatching = $this->getContinueWatching($userId);

        // Movie sections (cached 15 min, rotates every 6 hours)
        $movieSections = Cache::remember("v2_manifest_sections_{$rotationSlot}", 1800, function () {
            return $this->buildMovieSections();
        });

        // Merge personal continue_watching at top, then shared sections
        $sections = [];
        if (!empty($continueWatching)) {
            $sections[] = [
                'key'   => 'continue_watching',
                'title' => 'Continue Watching',
                'icon'  => 'play',
                'items' => $continueWatching,
            ];
        }
        $sections = array_merge($sections, $movieSections);

        // Genres & VJs — cached 10 min (rarely change)
        $genres = Cache::remember('v2_manifest_genres', 600, function () {
            return $this->getUniqueGenres();
        });
        $vjs = Cache::remember('v2_manifest_vjs', 600, function () {
            return $this->getUniqueVjs();
        });

        // Config
        $config = [
            'app_version'     => 25,
            'update_notes'    => "New V2 home with faster loading, personalised sections & improved design.\n- Continue Watching with progress\n- Trending & Popular sections\n- VJ Spotlight\n- Hidden Gems discovery",
            'whatsapp_number' => '+256706638494',
            'ios_link'        => 'https://lugaflix.store',
            'android_link'    => 'https://play.google.com/store/apps/details?id=ugflix.com',
        ];

        // Subscription — personal, always fresh
        $subscription = $this->getSubscriptionInfo($u);

        // Dashboard stats — personal, always fresh
        $stats = $this->getDashboardStats($u);

        // SafeMode auth
        $safemodeAuth = $this->getSafemodeAuth();

        // ── 4. Performance log ───────────────────────────────
        $elapsed = round((microtime(true) - $startTime) * 1000);
        Log::info('📊 V2 Manifest served', [
            'user_id'    => $userId,
            'app_type'   => $app_type,
            'sections'   => count($sections),
            'elapsed_ms' => $elapsed,
        ]);

        $responseData = [
            'featured'      => $featured,
            'sections'      => $sections,
            'genres'        => $genres,
            'vjs'           => $vjs,
            'config'        => $config,
            'subscription'  => $subscription,
            'stats'         => $stats,
            'safemode_auth' => $safemodeAuth,
        ];

        // Write pre-bootstrap cache (per user+app_type)
        $cacheDir = storage_path('api_cache');
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        $pbcKey = 'u_' . md5('/api/v2/manifest' . $userId . $app_type);
        @file_put_contents("{$cacheDir}/{$pbcKey}", json_encode(['code' => 1, 'message' => 'Manifest loaded.', 'data' => $responseData]));

        // LiteSpeed Cache: headers set by lscache middleware on route

        return response()->json([
            'code' => 1,
            'message' => 'Manifest loaded.',
            'data' => $responseData,
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    }

    // ═══════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════

    /**
     * Get featured / trending movie (single slim object or null).
     *
     * Priority:
     *  1. Movies with real recent plays (last 30 days) — scored by unique viewers + total watch time
     *  2. Rotates within top-40 recently-played candidates so the hero always reflects live activity
     *  3. Falls back to all-time views_time_count if no recent plays data exists
     */
    private function getFeaturedMovie(?int $excludeId = null): ?array
    {
        $rotationSlot = (int) floor(Carbon::now()->timestamp / 21600);
        $recentWindow = Carbon::now()->subDays(30);
        $topN         = 40; // rotate within top-40 recently trending movies

        try {
            // Step 1: find movies with actual recent plays, scored by engagement
            $recentlyPlayed = DB::table('movie_views as mv')
                ->join('movie_models as m', 'm.id', '=', 'mv.movie_model_id')
                ->where('mv.created_at', '>=', $recentWindow)
                ->where('m.status', 'Active')
                ->where('m.type', 'Movie')
                ->where('m.is_muno', 'Yes')
                ->when($excludeId, fn ($q) => $q->where('m.id', '!=', $excludeId))
                ->selectRaw('mv.movie_model_id, COUNT(DISTINCT mv.user_id) as unique_viewers, COUNT(*) as play_count, SUM(mv.progress) as total_watch_secs')
                ->groupBy('mv.movie_model_id')
                ->having('play_count', '>=', 2)
                ->orderByRaw('(COUNT(DISTINCT mv.user_id) * 5 + COUNT(*) * 2 + SUM(mv.progress) / 3600) DESC')
                ->limit($topN)
                ->pluck('mv.movie_model_id')
                ->toArray();

            if (!empty($recentlyPlayed)) {
                $index   = $rotationSlot % count($recentlyPlayed);
                $movieId = $recentlyPlayed[$index];
                $movie   = DB::table('movie_models')
                    ->where('id', $movieId)
                    ->where('status', 'Active')
                    ->select(self::SLIM_FIELDS)->first();

                if ($movie) {
                    Log::info('V2 Manifest: featured movie from recent plays', [
                        'id'             => $movie->id,
                        'title'          => $movie->title,
                        'pool_size'      => count($recentlyPlayed),
                        'rotation_index' => $index,
                    ]);
                    return $this->slimMovie($movie);
                }
            }

            // Step 2: no recent play data — fall back to all-time views, still rotating
            Log::info('V2 Manifest: no recent plays found, falling back to all-time views_time_count');
            $totalCandidates = DB::table('movie_models')
                ->where('status', 'Active')->where('type', 'Movie')->where('is_muno', 'Yes')
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->where('views_time_count', '>', 0)
                ->count();

            if ($totalCandidates > 0) {
                $index = $rotationSlot % min($totalCandidates, $topN);
                $movie = DB::table('movie_models')
                    ->where('status', 'Active')->where('type', 'Movie')->where('is_muno', 'Yes')
                    ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                    ->where('views_time_count', '>', 0)
                    ->orderBy('views_time_count', 'desc')
                    ->offset($index)->limit(1)
                    ->select(self::SLIM_FIELDS)->first();

                if ($movie) return $this->slimMovie($movie);
            }
        } catch (\Throwable $e) {
            Log::warning('V2 Manifest: featured movie error', ['error' => $e->getMessage()]);
        }

        // Final fallback: most recent active movie
        $movie = DB::table('movie_models')
            ->where('status', 'Active')->where('type', 'Movie')->where('is_muno', 'Yes')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('created_at', 'desc')
            ->select(self::SLIM_FIELDS)->first();

        return $movie ? $this->slimMovie($movie) : null;
    }

    /**
     * Pick the next best active featured movie, excluding a specific ID.
     * Called when the cached featured movie is found to be inactive.
     */
    private function pickNextFeaturedMovie(int $excludeId): ?array
    {
        return $this->getFeaturedMovie($excludeId);
    }

    /**
     * Personal "Continue Watching" — uses MovieView records.
     * Batch-loads movies to avoid N+1 queries (V1 had N+1 problem).
     */
    private function getContinueWatching(int $userId): array
    {
        $views = MovieView::where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();

        if ($views->isEmpty()) {
            return [];
        }

        // Batch load all movie objects at once
        $movieIds = $views->pluck('movie_model_id')->unique()->toArray();
        $movies = DB::table('movie_models')
            ->select(array_merge(self::SLIM_FIELDS, ['id']))
            ->whereIn('id', $movieIds)
            ->where('status', 'Active')
            ->get();
        $moviesMap = $movies->keyBy('id');

        $items = [];
        foreach ($views as $view) {
            $movie = $moviesMap->get($view->movie_model_id);
            if (!$movie) {
                continue;
            }

            $slim = $this->slimMovie($movie);
            $slim['progress']      = (float) ($view->progress ?? 0);
            $slim['last_position'] = (int) ($view->last_position ?? 0);
            $slim['watched_at']    = $view->updated_at ? $view->updated_at->toIso8601String() : null;
            $items[] = $slim;
        }

        return $items;
    }

    /**
     * Build the shared (non-personal) movie sections.
     * Cached server-side for 5 minutes.
     *
     * ROTATION STRATEGY:
     *  – Page-cycling: each section has totalPages = ceil(pool / limit).
     *    currentPage = rotationSlot % totalPages. This guarantees the ENTIRE
     *    catalogue is shown before any movie repeats in that section.
     *  – Deterministic shuffle: discovery sections use ORDER BY RAND(fixedSeed)
     *    with a CONSTANT seed per section (not per slot). The same shuffled order
     *    always, but a different PAGE every 6 hours — zero overlap between slots.
     *  – Soft dedup: adjacent sections exclude each other's IDs so two
     *    neighbouring rows never show the same movie.
     */
    private function buildMovieSections(): array
    {
        $sections       = [];
        $rotationSlot   = (int) floor(Carbon::now()->timestamp / 21600);   // changes every 6 hours
        $prevSectionIds = [];

        // Helper: append a section & rotate dedup window
        $addSection = function (string $key, string $title, string $icon, $collection, array $filterParams = []) use (&$sections, &$prevSectionIds) {
            if ($collection->isEmpty()) return;
            $sections[] = [
                'key'           => $key,
                'title'         => $title,
                'icon'          => $icon,
                'filter_params' => $filterParams,   // so Flutter "See All" can paginate
                'items'         => $collection->map(fn ($m) => $this->slimMovie($m))->values()->toArray(),
            ];
            $prevSectionIds = $collection->pluck('id')->toArray();
        };

        /**
         * Page-cycle offset calculator.
         * Given a total pool size and desired page size, returns the SQL OFFSET
         * for today so that the full pool is covered before repeating.
         */
        $cycledOffset = function (int $poolSize, int $pageSize) use ($rotationSlot): int {
            $totalPages = max(1, (int) ceil($poolSize / max(1, $pageSize)));
            $page = $rotationSlot % $totalPages;
            return $page * $pageSize;
        };

        // Base scopes
        $activeMovies = fn () => DB::table('movie_models')->where('status', 'Active')->where('type', 'Movie')->where('is_muno', 'Yes');
        // Series scope: only first episodes so each series appears once
        $activeSeries = fn () => DB::table('movie_models')->where('status', 'Active')->where('type', 'Series')->where('is_muno', 'Yes')->where('is_first_episode', 'Yes');

        // Total counts (cached 10 min — used for page calculation)
        $totalMovies = Cache::remember('v2_total_movies', 600, fn () =>
            DB::table('movie_models')->where('status', 'Active')->where('type', 'Movie')->where('is_muno', 'Yes')->count()
        );
        $totalSeries = Cache::remember('v2_total_series', 600, fn () =>
            DB::table('movie_models')->where('status', 'Active')->where('type', 'Series')->where('is_muno', 'Yes')->where('is_first_episode', 'Yes')->count()
        );

        $limit = 20;

        // ═══════════════════════════════════════════════════
        // 1. LATEST MOVIES — newest, no cycling needed
        // ═══════════════════════════════════════════════════
        $latest = $activeMovies()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->select(self::SLIM_FIELDS)->get();
        $addSection('latest', 'Latest Movies', 'clock', $latest, ['sort' => 'latest']);

        // ═══════════════════════════════════════════════════
        // 2. TRENDING NOW — movies with most real plays in last 30 days
        //    Falls back to all-time downloads if play data is sparse
        // ═══════════════════════════════════════════════════
        $recentWindow = Carbon::now()->subDays(30);
        $trendingIds = DB::table('movie_views as mv')
            ->join('movie_models as m', 'm.id', '=', 'mv.movie_model_id')
            ->where('mv.created_at', '>=', $recentWindow)
            ->where('m.status', 'Active')
            ->where('m.type', 'Movie')
            ->where('m.is_muno', 'Yes')
            ->whereNotIn('mv.movie_model_id', $prevSectionIds)
            ->selectRaw('mv.movie_model_id, COUNT(DISTINCT mv.user_id) as unique_viewers, COUNT(*) as play_count')
            ->groupBy('mv.movie_model_id')
            ->orderByRaw('(COUNT(DISTINCT mv.user_id) * 5 + COUNT(*) * 2) DESC')
            ->limit($limit * 3)
            ->pluck('mv.movie_model_id')
            ->toArray();

        $trending = null;
        if (count($trendingIds) >= 3) {
            // Rotate within trending pool so different movies surface each slot
            $trendPool  = count($trendingIds);
            $trendPage  = $rotationSlot % max(1, (int) ceil($trendPool / $limit));
            $trendSlice = array_slice($trendingIds, $trendPage * $limit, $limit) ?: array_slice($trendingIds, 0, $limit);
            $trending = $activeMovies()
                ->whereIn('id', $trendSlice)
                ->orderByRaw('FIELD(id,' . implode(',', $trendSlice) . ')')
                ->select(self::SLIM_FIELDS)->get();
        }

        if (!$trending || $trending->count() < 3) {
            // Fallback: all-time downloads with page cycling
            $trendingOffset = $cycledOffset($totalMovies, $limit);
            $trending = $activeMovies()
                ->whereNotIn('id', $prevSectionIds)
                ->orderBy('downloads_count', 'desc')
                ->offset($trendingOffset)
                ->limit($limit)
                ->select(self::SLIM_FIELDS)->get();
            if ($trending->count() < 3) {
                $trending = $activeMovies()
                    ->whereNotIn('id', $prevSectionIds)
                    ->orderBy('downloads_count', 'desc')
                    ->limit($limit)
                    ->select(self::SLIM_FIELDS)->get();
            }
        }
        $addSection('trending', 'Trending Now', 'trending-up', $trending, ['sort' => 'popular']);

        // ═══════════════════════════════════════════════════
        // 3. POPULAR MOVIES — by views, offset by half-cycle
        //    (shifted so it doesn't overlap with trending's page)
        // ═══════════════════════════════════════════════════
        $popularPages = max(1, (int) ceil($totalMovies / $limit));
        $popularPage  = ($rotationSlot + (int) floor($popularPages / 2)) % $popularPages;
        $popular = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->orderBy('views_time_count', 'desc')
            ->offset($popularPage * $limit)
            ->limit($limit)
            ->select(self::SLIM_FIELDS)->get();
        if ($popular->count() < 3) {
            $popular = $activeMovies()
                ->whereNotIn('id', $prevSectionIds)
                ->orderBy('views_time_count', 'desc')
                ->limit($limit)
                ->select(self::SLIM_FIELDS)->get();
        }
        $addSection('popular', 'Popular Movies', 'star', $popular, ['sort' => 'popular']);

        // ═══════════════════════════════════════════════════
        // 4. NEW THIS WEEK — last 7 days (naturally fresh)
        // ═══════════════════════════════════════════════════
        $weekAgo = Carbon::now()->subDays(7);
        $newThisWeek = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->where('created_at', '>=', $weekAgo)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->select(self::SLIM_FIELDS)->get();
        if ($newThisWeek->count() >= 3) {
            $addSection('new_this_week', 'New This Week', 'calendar', $newThisWeek, ['sort' => 'latest']);
        }

        // ═══════════════════════════════════════════════════
        // 5. SERIES — first episode of each series only (deduplicated by category_id)
        // ═══════════════════════════════════════════════════
        if ($totalSeries >= 2) {
            $seriesOffset = $cycledOffset($totalSeries, $limit);
            // Fetch extra to account for duplicates, then deduplicate by category_id
            $series = $activeSeries()
                ->orderBy('created_at', 'desc')
                ->offset($seriesOffset)
                ->limit($limit * 2)
                ->select(self::SLIM_FIELDS)->get()
                ->unique('category_id')
                ->take($limit)
                ->values();
            if ($series->count() < 2) {
                $series = $activeSeries()
                    ->orderBy('created_at', 'desc')
                    ->limit($limit * 2)
                    ->select(self::SLIM_FIELDS)->get()
                    ->unique('category_id')
                    ->take($limit)
                    ->values();
            }
            if ($series->count() >= 2) {
                $addSection('series', 'Series', 'tv', $series, ['type' => 'Series', 'sort' => 'latest']);
            }
        }

        // ═══════════════════════════════════════════════════
        // 6. MOST DOWNLOADED — page-cycled (offset shifted by 1/3)
        // ═══════════════════════════════════════════════════
        $dlPages  = max(1, (int) ceil($totalMovies / $limit));
        $dlPage   = ($rotationSlot + (int) floor($dlPages / 3)) % $dlPages;
        $mostDownloaded = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->orderBy('downloads_count', 'desc')
            ->offset($dlPage * $limit)
            ->limit($limit)
            ->select(self::SLIM_FIELDS)->get();
        if ($mostDownloaded->count() < 3) {
            $mostDownloaded = $activeMovies()
                ->whereNotIn('id', $prevSectionIds)
                ->orderBy('downloads_count', 'desc')
                ->offset(0)
                ->limit($limit)
                ->select(self::SLIM_FIELDS)->get();
        }
        if ($mostDownloaded->count() >= 3) {
            $addSection('most_downloaded', 'Most Downloaded', 'download-cloud', $mostDownloaded, ['sort' => 'popular']);
        }

        // ═══════════════════════════════════════════════════
        // 7–14. GENRE SECTIONS — up to 8 genres, page-cycled
        //   Each genre's movies are in a fixed shuffled order
        //   (RAND with constant seed per genre), paged daily.
        // ═══════════════════════════════════════════════════
        $topGenres = DB::table('movie_models')
            ->select('genre', DB::raw('COUNT(*) as cnt'))
            ->where('status', 'Active')
            ->where('type', 'Movie')
            ->where('is_muno', 'Yes')
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->groupBy('genre')
            ->orderByDesc('cnt')
            ->limit(40)
            ->get();

        $seenGenres = [];
        $genreSectionCount = 0;
        $maxGenreSections = 8;
        foreach ($topGenres as $row) {
            if ($genreSectionCount >= $maxGenreSections) break;
            $parts = array_map('trim', preg_split('/[,\/]/', $row->genre));
            foreach ($parts as $g) {
                if ($genreSectionCount >= $maxGenreSections) break;
                $normalised = ucfirst(strtolower(trim($g)));
                if (strlen($normalised) < 2 || in_array($normalised, $seenGenres)) continue;
                $seenGenres[] = $normalised;

                // Fixed seed per genre (constant) — same shuffle order always
                $genreSeed = crc32($normalised);
                $genreCount = $activeMovies()->where('genre', 'LIKE', "%{$g}%")->count();
                $genreOffset = $cycledOffset($genreCount, $limit);

                $genreMovies = $activeMovies()
                    ->where('genre', 'LIKE', "%{$g}%")
                    ->whereNotIn('id', $prevSectionIds)
                    ->orderByRaw("RAND({$genreSeed})")
                    ->offset($genreOffset)
                    ->limit($limit)
                    ->select(self::SLIM_FIELDS)->get();

                // Wrap if near end
                if ($genreMovies->count() < 3) {
                    $genreMovies = $activeMovies()
                        ->where('genre', 'LIKE', "%{$g}%")
                        ->whereNotIn('id', $prevSectionIds)
                        ->orderByRaw("RAND({$genreSeed})")
                        ->limit($limit)
                        ->select(self::SLIM_FIELDS)->get();
                }

                if ($genreMovies->count() >= 3) {
                    $addSection(
                        'genre_' . strtolower(str_replace(' ', '_', $normalised)),
                        "{$normalised} Movies",
                        'film',
                        $genreMovies,
                        ['genre' => $normalised]
                    );
                    $genreSectionCount++;
                }
            }
        }

        // ═══════════════════════════════════════════════════
        // 15–17. VJ SPOTLIGHT — 3 VJs, rotating daily
        //   Day 1: VJ A,B,C → Day 2: VJ D,E,F → …
        //   Cycles through all VJs before repeating.
        // ═══════════════════════════════════════════════════
        $topVjs = DB::table('movie_models')
            ->select('vj', DB::raw('COUNT(*) as cnt'))
            ->where('status', 'Active')
            ->where('type', 'Movie')
            ->where('is_muno', 'Yes')
            ->whereNotNull('vj')
            ->where('vj', '!=', '')
            ->groupBy('vj')
            ->orderByDesc('cnt')
            ->limit(15)
            ->get();

        $vjSpotlightCount = 0;
        $vjsPerDay = 3;
        if ($topVjs->isNotEmpty()) {
            $vjTotal = $topVjs->count();
            // Rotate: day 0 → VJs 0,1,2 | day 1 → VJs 3,4,5 | …
            $vjStartIdx = ($rotationSlot * $vjsPerDay) % $vjTotal;
            for ($i = 0; $i < $vjTotal && $vjSpotlightCount < $vjsPerDay; $i++) {
                $vjRow  = $topVjs[($vjStartIdx + $i) % $vjTotal];
                $vjName = trim($vjRow->vj);
                if (strlen($vjName) < 2) continue;

                $vjSeed = crc32($vjName);
                $vjMovieCount = $activeMovies()->where('vj', 'LIKE', "%{$vjName}%")->count();
                $vjOffset = $cycledOffset($vjMovieCount, $limit);

                $vjMovies = $activeMovies()
                    ->where('vj', 'LIKE', "%{$vjName}%")
                    ->whereNotIn('id', $prevSectionIds)
                    ->orderByRaw("RAND({$vjSeed})")
                    ->offset($vjOffset)
                    ->limit($limit)
                    ->select(self::SLIM_FIELDS)->get();

                if ($vjMovies->count() < 3) {
                    $vjMovies = $activeMovies()
                        ->where('vj', 'LIKE', "%{$vjName}%")
                        ->orderByRaw("RAND({$vjSeed})")
                        ->limit($limit)
                        ->select(self::SLIM_FIELDS)->get();
                }

                if ($vjMovies->count() >= 3) {
                    $displayName = trim(str_ireplace(['vj ', 'VJ '], '', $vjName));
                    $addSection(
                        'vj_spotlight_' . ($vjSpotlightCount + 1),
                        "VJ {$displayName} Collection",
                        'mic',
                        $vjMovies,
                        ['vj' => $vjName]
                    );
                    $vjSpotlightCount++;
                }
            }
        }

        // ═══════════════════════════════════════════════════
        // 18. TOP RATED — page-cycled
        // ═══════════════════════════════════════════════════
        $ratedCount = $activeMovies()->where('rating', '>', 0)->count();
        $ratedOffset = $cycledOffset($ratedCount, $limit);
        $topRated = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->where('rating', '>', 0)
            ->orderBy('rating', 'desc')
            ->offset($ratedOffset)
            ->limit($limit)
            ->select(self::SLIM_FIELDS)->get();
        if ($topRated->count() < 3) {
            $topRated = $activeMovies()
                ->whereNotIn('id', $prevSectionIds)
                ->where('rating', '>', 0)
                ->orderBy('rating', 'desc')
                ->limit($limit)
                ->select(self::SLIM_FIELDS)->get();
        }
        if ($topRated->count() >= 3) {
            $addSection('top_rated', 'Top Rated', 'thumbs-up', $topRated, ['sort' => 'popular']);
        }

        // ═══════════════════════════════════════════════════
        // 19. FOR YOU — deterministic shuffle, page-cycled
        //   Fixed random order (seed=42), different page each day.
        //   Full catalogue cycles before any movie repeats.
        // ═══════════════════════════════════════════════════
        $forYouOffset = $cycledOffset($totalMovies, $limit);
        // Shift by 2/3 so it doesn't align with trending/popular pages
        $forYouPages = max(1, (int) ceil($totalMovies / $limit));
        $forYouPage  = ($rotationSlot + (int) floor($forYouPages * 2 / 3)) % $forYouPages;
        $forYou = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->orderByRaw('RAND(42)')
            ->offset($forYouPage * $limit)
            ->limit($limit)
            ->select(self::SLIM_FIELDS)->get();
        if ($forYou->count() < 3) {
            $forYou = $activeMovies()
                ->whereNotIn('id', $prevSectionIds)
                ->orderByRaw('RAND(42)')
                ->limit($limit)
                ->select(self::SLIM_FIELDS)->get();
        }
        if ($forYou->count() >= 3) {
            $addSection('for_you', 'Recommended For You', 'heart', $forYou, ['sort' => 'popular']);
        }

        // ═══════════════════════════════════════════════════
        // 20. HIDDEN GEMS — low views, page-cycled
        // ═══════════════════════════════════════════════════
        $gemsCount = $activeMovies()->where('views_time_count', '<', 100)->count();
        $gemsOffset = $cycledOffset($gemsCount, $limit);
        $hiddenGems = $activeMovies()
            ->where('views_time_count', '<', 100)
            ->whereNotIn('id', $prevSectionIds)
            ->orderByRaw('RAND(7777)')
            ->offset($gemsOffset)
            ->limit($limit)
            ->select(self::SLIM_FIELDS)->get();
        if ($hiddenGems->count() < 3) {
            $hiddenGems = $activeMovies()
                ->where('views_time_count', '<', 100)
                ->orderByRaw('RAND(7777)')
                ->limit($limit)
                ->select(self::SLIM_FIELDS)->get();
        }
        if ($hiddenGems->count() >= 3) {
            $addSection('hidden_gems', 'Hidden Gems', 'award', $hiddenGems, ['sort' => 'latest']);
        }

        // ═══════════════════════════════════════════════════
        // 21. CLASSICS — oldest movies, page-cycled
        // ═══════════════════════════════════════════════════
        $classicsOffset = $cycledOffset($totalMovies, $limit);
        $classicsPages  = max(1, (int) ceil($totalMovies / $limit));
        $classicsPage   = ($rotationSlot + (int) floor($classicsPages / 4)) % $classicsPages;
        $classics = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->orderBy('created_at', 'asc')
            ->offset($classicsPage * $limit)
            ->limit($limit)
            ->select(self::SLIM_FIELDS)->get();
        if ($classics->count() < 3) {
            $classics = $activeMovies()
                ->whereNotIn('id', $prevSectionIds)
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->select(self::SLIM_FIELDS)->get();
        }
        if ($classics->count() >= 3) {
            $addSection('classics', 'Classics', 'archive', $classics, ['sort' => 'year']);
        }

        return $sections;
    }

    /**
     * Reduce a MovieModel to the minimal fields the mobile UI needs.
     * ~50-60% smaller than V1 movie objects.
     */
    private function slimMovie($movie): array
    {
        $storageBase = rtrim(config('app.url'), '/') . '/storage/';

        $url = $movie->url ?? '';
        if ($url !== '') {
            $url = str_replace(' ', '%20', $url);
            $url = preg_replace('/^http:/i', 'https:', $url);
        }

        $thumb = $movie->thumbnail_url ?? '';
        if ($thumb !== '' && !str_starts_with($thumb, 'http')) {
            $thumb = $storageBase . ltrim($thumb, '/');
        }

        return [
            'id'               => (int) $movie->id,
            'title'            => $movie->title ?? '',
            'url'              => $url,
            'thumbnail_url'    => $thumb,
            'genre'            => $movie->genre ?? '',
            'type'             => $movie->type ?? 'Movie',
            'vj'               => $movie->vj ?? '',
            'is_premium'       => $movie->is_premium ?? 'No',
            'year'             => $movie->year ?? '',
            'duration'         => $movie->duration ?? '',
            'rating'           => (float) ($movie->rating ?? 0),
            'views'            => (int) ($movie->views_time_count ?? 0),
            'category_id'      => $movie->category_id ? (int) $movie->category_id : null,
            'is_first_episode' => $movie->is_first_episode ?? null,
        ];
    }

    /**
     * Extract unique genres from all active movies.
     * Handles comma and slash delimiters.
     */
    private function getUniqueGenres(): array
    {
        $rows = DB::select(
            "SELECT DISTINCT genre FROM movie_models WHERE genre IS NOT NULL AND genre != '' AND status = 'Active' AND is_muno = 'Yes'"
        );

        $unique = [];
        foreach ($rows as $row) {
            foreach (preg_split('/[,\/]/', $row->genre ?? '') as $g) {
                $g = trim($g);
                if (strlen($g) >= 2 && !in_array($g, $unique)) {
                    $unique[] = $g;
                }
            }
        }
        sort($unique);
        return $unique;
    }

    /**
     * Extract unique VJ names from all active movies.
     */
    private function getUniqueVjs(): array
    {
        $rows = DB::select(
            "SELECT DISTINCT vj FROM movie_models WHERE vj IS NOT NULL AND vj != '' AND status = 'Active' AND is_muno = 'Yes'"
        );

        $unique = [];
        foreach ($rows as $row) {
            foreach (preg_split('/[,]/', $row->vj ?? '') as $v) {
                $v = trim(str_ireplace(['vj', 'VJ', 'Vj'], '', $v));
                $v = str_replace([' ', '-'], '', $v);
                if (strlen($v) > 0 && !in_array($v, $unique)) {
                    $unique[] = $v;
                }
            }
        }
        sort($unique);
        return $unique;
    }

    /**
     * Build subscription info array for the authenticated user.
     */
    private function getSubscriptionInfo(User $u): array
    {
        $info = [
            'has_active_subscription' => false,
            'days_remaining'          => 0,
            'hours_remaining'         => 0,
            'is_in_grace_period'      => false,
            'subscription_status'     => 'No Active Subscription',
            'end_date'                => null,
            'require_subscription'    => true,
        ];

        try {
            $status    = $u->getSubscriptionStatus();
            $hasActive = $status['has_active_subscription'] ?? false;
            $days      = $status['days_remaining'] ?? 0;
            $statusStr = $status['status'] ?? 'No Active Subscription';

            // Consistency fix: if days > 0 or status Active, force has_active true
            if (($days > 0 || $statusStr === 'Active') && !$hasActive) {
                Log::warning('V2 Manifest: subscription data inconsistency', [
                    'user_id' => $u->id,
                    'has_active' => $hasActive,
                    'days' => $days,
                    'status' => $statusStr,
                ]);
                $hasActive = true;
            }

            $info = [
                'has_active_subscription' => $hasActive,
                'days_remaining'          => $days,
                'hours_remaining'         => $status['hours_remaining'] ?? 0,
                'is_in_grace_period'      => $status['is_in_grace_period'] ?? false,
                'subscription_status'     => $statusStr,
                'end_date'                => $status['end_date'] ?? null,
                'require_subscription'    => !$hasActive,
            ];
        } catch (\Exception $e) {
            Log::error('V2 Manifest: subscription check failed', [
                'user_id' => $u->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return $info;
    }

    /**
     * Dashboard statistics for the authenticated user.
     */
    private function getDashboardStats(User $u): array
    {
        return Cache::remember("v2_dash_stats_{$u->id}", 120, function () use ($u) {
            $stats = [
                'watchlist_count'     => 0,
                'watch_history_count' => 0,
                'liked_movies_count'  => 0,
                'active_chats_count'  => 0,
                'downloads'           => ['total' => 0, 'in_app' => 0, 'gallery' => 0],
            ];

            try {
                $stats['watchlist_count']     = MovieWishlist::where('user_id', $u->id)->count();
                $stats['watch_history_count'] = MovieView::where('user_id', $u->id)->count();
                $stats['liked_movies_count']  = MovieLike::where('user_id', $u->id)->count();

                $sent     = ChatMessage::where('sender_id', $u->id)->distinct('receiver_id')->count('receiver_id');
                $received = ChatMessage::where('receiver_id', $u->id)->distinct('sender_id')->count('sender_id');
                $stats['active_chats_count'] = $sent + $received;

                $dl = MovieDownload::where('user_id', $u->id)
                    ->selectRaw('COUNT(*) as total, SUM(CASE WHEN download_type = "in_app" THEN 1 ELSE 0 END) as in_app, SUM(CASE WHEN download_type = "gallery" THEN 1 ELSE 0 END) as gallery')
                    ->first();
                $stats['downloads'] = [
                    'total'   => $dl->total ?? 0,
                    'in_app'  => $dl->in_app ?? 0,
                    'gallery' => $dl->gallery ?? 0,
                ];
            } catch (\Exception $e) {
                // Use defaults
            }

            return $stats;
        });
    }

    /**
     * SafeMode (MunoWatch) authentication credentials.
     */
    private function getSafemodeAuth(): array
    {
        return [
            'user_id'            => 169464,
            'session_id'         => 'e5d66b26a9392f23e0236221cd260ff1',
            'username'           => 'Imdaad',
            'avatar'             => 'https://lh3.googleusercontent.com/a/ACg8ocIbh-PGzTzJDeqeMXSyhJZDfZ70cWI1cDTtvwUsSl3cWXzZew=s96-c',
            'email'              => 'Jumaperejunior@gmail.com',
            'password'           => base64_encode('uganda7766'),
            'api_endpoint'       => 'https://munowatch.org/api/users/login/v2',
            'dashboard_endpoint' => 'https://munowatch.org/api/dashboard/v2',
            'bearer_token'       => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0',
        ];
    }

    /**
     * Check pending subscription payments (fast, non-blocking per-request).
     */
    private function checkPendingPayments(int $userId): void
    {
        try {
            $pending = SubscriptionTransaction::whereNotIn('status', ['Completed'])
                ->where('created_at', '>=', Carbon::now()->subHours(72))
                ->where('user_id', $userId)
                ->orderBy('id', 'desc')
                ->limit(3)
                ->get();

            foreach ($pending as $pay) {
                if ($pay->status === 'Completed') {
                    continue;
                }
                $checked = (int) $pay->number_of_times_checked;
                if ($checked > 20) {
                    $pay->status        = 'Failed';
                    $pay->refund_reason = 'Payment not completed after multiple checks.';
                    $pay->save();
                    continue;
                }
                try {
                    $pay->check_payment_status();
                } catch (\Throwable $e) {
                    // Silent fail
                }
            }
        } catch (\Throwable $e) {
            Log::warning('V2 Manifest: pending payment check failed', ['error' => $e->getMessage()]);
        }
    }
}
