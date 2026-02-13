<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
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
    ];

    /**
     * GET /api/v2/manifest
     *
     * Optimised V2 manifest:
     *  – Lean movie objects (12 fields vs 20+ in V1)
     *  – Typed sections with keys (continue_watching, trending, popular, …)
     *  – Server-side caching for shared data (genres, VJs, movie sections)
     *  – Personal data (continue_watching, subscription, stats) always fresh
     *  – Supports app_type: ugflix, lugaflix, muno_app
     */
    public function index(Request $request)
    {
        $startTime = microtime(true);

        // ── 1. Authenticate & bookkeeping ────────────────────
        $u = Utils::get_user($request);
        if (!$u) {
            return Utils::error('User not found.');
        }
        $u = User::find($u->id);
        if (!$u) {
            return Utils::error('User not found.');
        }

        $app_type = Utils::get_app_type($request);
        $validTypes = ['ugflix', 'lugaflix', 'muno_app'];
        if (!in_array($app_type, $validTypes)) {
            $app_type = 'ugflix';
        }

        $u->app_type = $app_type;
        $platform = Utils::get_platform_from_request($request);
        if ($platform) {
            $u->platform = $platform;
        }
        $u->last_online_at = now();
        $u->save();

        // ── 2. Background: check pending payments ────────────
        $this->checkPendingPayments($u->id);

        // ── 3. Build manifest ────────────────────────────────
        $userId = $u->id;

        // Daily rotation seed — content cycles each calendar day
        $cacheDate = Carbon::today()->format('Y-m-d');

        // Featured movie (cached 5 min, rotates daily)
        $featured = Cache::remember("v2_manifest_featured_{$cacheDate}", 300, function () {
            return $this->getFeaturedMovie();
        });

        // Continue Watching — personal, never cached
        $continueWatching = $this->getContinueWatching($userId);

        // Movie sections (cached 5 min, rotates daily)
        $movieSections = Cache::remember("v2_manifest_sections_{$cacheDate}", 300, function () {
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
            'whatsapp_number' => '+256783204665',
            'ios_link'        => 'https://play.google.com/store/apps/details?id=ugflix.com',
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

        return Utils::success([
            'featured'      => $featured,
            'sections'      => $sections,
            'genres'        => $genres,
            'vjs'           => $vjs,
            'config'        => $config,
            'subscription'  => $subscription,
            'stats'         => $stats,
            'safemode_auth' => $safemodeAuth,
        ], 'Manifest loaded.');
    }

    // ═══════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════

    /**
     * Get featured / trending movie (single slim object or null).
     */
    private function getFeaturedMovie(): ?array
    {
        $todaySeed = Carbon::today()->dayOfYear;

        try {
            // Pick from top 10 movies by downloads, rotating daily
            $candidates = MovieModel::where(['status' => 'Active', 'type' => 'Movie', 'is_muno' => 'Yes'])
                ->orderBy('downloads_count', 'desc')
                ->limit(10)
                ->get(self::SLIM_FIELDS);

            if ($candidates->isNotEmpty()) {
                $index = $todaySeed % $candidates->count();
                return $this->slimMovie($candidates[$index]);
            }
        } catch (\Throwable $e) {
            Log::warning('V2 Manifest: featured movie error', ['error' => $e->getMessage()]);
        }

        // Fallback: latest active movie
        $movie = MovieModel::where(['status' => 'Active', 'type' => 'Movie', 'is_muno' => 'Yes'])
            ->orderBy('created_at', 'desc')
            ->first(self::SLIM_FIELDS);

        return $movie ? $this->slimMovie($movie) : null;
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
        $movies = MovieModel::whereIn('id', $movieIds)
            ->where('status', 'Active')
            ->get(array_merge(self::SLIM_FIELDS, ['id']));
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
     * Strategy: use soft dedup — only prevent the SAME movie from appearing
     * in the very next adjacent section. This keeps all sections full even
     * when the total catalogue is small.
     */
    private function buildMovieSections(): array
    {
        $sections      = [];
        $todaySeed     = Carbon::today()->timestamp;
        $dayOffset     = Carbon::today()->dayOfYear % 4;
        $prevSectionIds = []; // IDs from previous section only (soft dedup)

        // Helper: append a section & rotate dedup window
        $addSection = function (string $key, string $title, string $icon, $collection) use (&$sections, &$prevSectionIds) {
            if ($collection->isEmpty()) return;
            $sections[] = [
                'key'   => $key,
                'title' => $title,
                'icon'  => $icon,
                'items' => $collection->map(fn ($m) => $this->slimMovie($m))->values()->toArray(),
            ];
            $prevSectionIds = $collection->pluck('id')->toArray();
        };

        // Base query scope (reusable)
        $activeMovies = fn () => MovieModel::where(['status' => 'Active', 'type' => 'Movie', 'is_muno' => 'Yes']);
        $activeSeries = fn () => MovieModel::where(['status' => 'Active', 'type' => 'Series', 'is_muno' => 'Yes']);

        // ═══════════════════════════════════════════════════
        // 1. LATEST MOVIES — newest additions first
        // ═══════════════════════════════════════════════════
        $latest = $activeMovies()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        $addSection('latest', 'Latest Movies', 'clock', $latest);

        // ═══════════════════════════════════════════════════
        // 2. TRENDING NOW — most downloaded, daily rotation
        // ═══════════════════════════════════════════════════
        $trending = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->orderBy('downloads_count', 'desc')
            ->offset($dayOffset * 3)
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        $addSection('trending', 'Trending Now', 'trending-up', $trending);

        // ═══════════════════════════════════════════════════
        // 3. POPULAR MOVIES — most viewed
        // ═══════════════════════════════════════════════════
        $popular = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->orderBy('views_time_count', 'desc')
            ->offset($dayOffset * 2)
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        $addSection('popular', 'Popular Movies', 'star', $popular);

        // ═══════════════════════════════════════════════════
        // 4. NEW THIS WEEK — added in last 7 days
        // ═══════════════════════════════════════════════════
        $weekAgo = Carbon::now()->subDays(7);
        $newThisWeek = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->where('created_at', '>=', $weekAgo)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        if ($newThisWeek->count() >= 3) {
            $addSection('new_this_week', 'New This Week', 'calendar', $newThisWeek);
        }

        // ═══════════════════════════════════════════════════
        // 5. SERIES — latest series
        // ═══════════════════════════════════════════════════
        $series = $activeSeries()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        if ($series->count() >= 2) {
            $addSection('series', 'Series', 'tv', $series);
        }

        // ═══════════════════════════════════════════════════
        // 6. MOST DOWNLOADED — top by download count
        // ═══════════════════════════════════════════════════
        $mostDownloaded = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->orderBy('downloads_count', 'desc')
            ->offset(15 + $dayOffset * 5)
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        if ($mostDownloaded->count() >= 3) {
            $addSection('most_downloaded', 'Most Downloaded', 'download-cloud', $mostDownloaded);
        }

        // ═══════════════════════════════════════════════════
        // 7–14. GENRE SECTIONS — up to 8 genres
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

                $genreMovies = $activeMovies()
                    ->where('genre', 'LIKE', "%{$g}%")
                    ->whereNotIn('id', $prevSectionIds)
                    ->orderByRaw("RAND({$todaySeed})")
                    ->limit(20)
                    ->get(self::SLIM_FIELDS);

                if ($genreMovies->count() >= 3) {
                    $addSection(
                        'genre_' . strtolower(str_replace(' ', '_', $normalised)),
                        "{$normalised} Movies",
                        'film',
                        $genreMovies
                    );
                    $genreSectionCount++;
                }
            }
        }

        // ═══════════════════════════════════════════════════
        // 15–17. VJ SPOTLIGHT — up to 3 top VJs
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
            ->limit(8)
            ->get();

        $vjSpotlightCount = 0;
        if ($topVjs->isNotEmpty()) {
            // Start from a daily-rotating index
            $vjStart = $todaySeed % $topVjs->count();
            for ($i = 0; $i < $topVjs->count() && $vjSpotlightCount < 3; $i++) {
                $vjRow  = $topVjs[($vjStart + $i) % $topVjs->count()];
                $vjName = trim($vjRow->vj);
                if (strlen($vjName) < 2) continue;

                $vjMovies = $activeMovies()
                    ->where('vj', 'LIKE', "%{$vjName}%")
                    ->whereNotIn('id', $prevSectionIds)
                    ->orderByRaw("RAND({$todaySeed})")
                    ->limit(20)
                    ->get(self::SLIM_FIELDS);

                if ($vjMovies->count() >= 3) {
                    $displayName = trim(str_ireplace(['vj ', 'VJ '], '', $vjName));
                    $addSection(
                        'vj_spotlight_' . ($vjSpotlightCount + 1),
                        "VJ {$displayName} Collection",
                        'mic',
                        $vjMovies
                    );
                    $vjSpotlightCount++;
                }
            }
        }

        // ═══════════════════════════════════════════════════
        // 18. TOP RATED — highest rating
        // ═══════════════════════════════════════════════════
        $topRated = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->where('rating', '>', 0)
            ->orderBy('rating', 'desc')
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        if ($topRated->count() >= 3) {
            $addSection('top_rated', 'Top Rated', 'thumbs-up', $topRated);
        }

        // ═══════════════════════════════════════════════════
        // 19. FOR YOU — seeded random daily pick
        // ═══════════════════════════════════════════════════
        $forYou = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->orderByRaw("RAND({$todaySeed})")
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        if ($forYou->count() >= 3) {
            $addSection('for_you', 'Recommended For You', 'heart', $forYou);
        }

        // ═══════════════════════════════════════════════════
        // 20. HIDDEN GEMS — low-view discoveries
        // ═══════════════════════════════════════════════════
        $hiddenGems = $activeMovies()
            ->where('views_time_count', '<', 100)
            ->whereNotIn('id', $prevSectionIds)
            ->orderByRaw("RAND({$todaySeed})")
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        if ($hiddenGems->count() >= 3) {
            $addSection('hidden_gems', 'Hidden Gems', 'award', $hiddenGems);
        }

        // ═══════════════════════════════════════════════════
        // 21. CLASSICS — oldest movies in the catalogue
        // ═══════════════════════════════════════════════════
        $classics = $activeMovies()
            ->whereNotIn('id', $prevSectionIds)
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get(self::SLIM_FIELDS);
        if ($classics->count() >= 3) {
            $addSection('classics', 'Classics', 'archive', $classics);
        }

        return $sections;
    }

    /**
     * Reduce a MovieModel to the minimal fields the mobile UI needs.
     * ~50-60% smaller than V1 movie objects.
     */
    private function slimMovie($movie): array
    {
        return [
            'id'            => (int) $movie->id,
            'title'         => $movie->title ?? '',
            'thumbnail_url' => $movie->thumbnail_url ?? '',
            'genre'         => $movie->genre ?? '',
            'type'          => $movie->type ?? 'Movie',
            'vj'            => $movie->vj ?? '',
            'is_premium'    => $movie->is_premium ?? 'No',
            'year'          => $movie->year ?? '',
            'duration'      => $movie->duration ?? '',
            'rating'        => (float) ($movie->rating ?? 0),
            'views'         => (int) ($movie->views_time_count ?? 0),
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
        $stats = [
            'watchlist_count'     => 0,
            'watch_history_count' => 0,
            'liked_movies_count'  => 0,
            'active_chats_count'  => 0,
        ];

        try {
            $stats['watchlist_count']     = MovieWishlist::where('user_id', $u->id)->count();
            $stats['watch_history_count'] = MovieView::where('user_id', $u->id)->count();
            $stats['liked_movies_count']  = MovieLike::where('user_id', $u->id)->count();

            $sent     = ChatMessage::where('sender_id', $u->id)->distinct('receiver_id')->count('receiver_id');
            $received = ChatMessage::where('receiver_id', $u->id)->distinct('sender_id')->count('sender_id');
            $stats['active_chats_count'] = $sent + $received;
        } catch (\Exception $e) {
            // Use defaults
        }

        return $stats;
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
