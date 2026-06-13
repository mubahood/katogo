<?php

use Illuminate\Routing\Router;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
    'as'            => config('admin.route.prefix') . '.',
], function (Router $router) {

    $router->get('dashboard', 'HomeController@index')->name('home');

    // App Download Landing Page Analytics
    $router->get('app-download-analytics', 'AppDownloadAnalyticsController@index');
    $router->get('auto-created-accounts', 'AutoCreatedAccountController@index');

    // ── Support Team & Ticket Management ──────────────────────────────
    $router->get('support-team',         'SupportTeamController@index');
    $router->post('support-team/toggle', 'SupportTeamController@toggleRole');
    $router->get('support-tickets/create', 'SupportTeamController@create');
    $router->get('support-tickets/all/create', 'SupportTeamController@create');
    $router->get('support-tickets',      'SupportTeamController@tickets');
    $router->get('support-tickets/pending', 'SupportTeamController@tickets')->defaults('segment', 'pending');
    $router->get('support-tickets/contacted', 'SupportTeamController@tickets')->defaults('segment', 'contacted');
    $router->get('support-tickets/contacted-customer-replied', 'SupportTeamController@tickets')->defaults('segment', 'contacted-customer-replied');
    $router->get('support-tickets/all', 'SupportTeamController@tickets')->defaults('segment', 'all');
    $router->get('support-tickets/ajax-movie-search', 'SupportTeamController@ajaxMovieSearch');
    $router->get('support-tickets/{id}/ajax-details', 'SupportTeamController@ajaxTicketDetails')->where(['id' => '[0-9]+']);
    $router->post('support-tickets', 'SupportTeamController@store');
    $router->post('support-tickets/all', 'SupportTeamController@store');
    $router->get('support-tickets/{id}/edit', 'SupportTeamController@edit');
    $router->put('support-tickets/{id}', 'SupportTeamController@update');
    $router->get('support-tickets/{id}', 'SupportTeamController@ticketDetail')->where(['id' => '[0-9]+']);
    $router->post('support-tickets/{id}/reply', 'SupportTeamController@replyTicket');
    $router->post('support-tickets/{id}/ajax-respond', 'SupportTeamController@ajaxRespondTicket');
    $router->post('support-ticket-records/{id}/visibility', 'SupportTeamController@updateRecordVisibility');


    $router->resource('munowatch-categories', MunowatchCategoryController::class);


    $router->resource('subscription-transactions', SubscriptionTransactionController::class);
    $router->resource('merged-accounts', MergedAccountController::class);
    $router->get('merged-accounts/{id}/sync-access', 'MergedAccountController@syncAccess')->where(['id' => '[0-9]+']);
    $router->get('subscriptions/analytics', 'SubscriptionController@analytics');
    $router->get('subscriptions/{id}/ajax-details', 'SubscriptionController@ajaxDetails')->where(['id' => '[0-9]+']);
    $router->resource('subscriptions', SubscriptionController::class);
    $router->resource('subscription-plans', SubscriptionPlanController::class);
    $router->resource('scraper-models', ScraperModelController::class);
    $router->resource('movies-active', MovieModelController::class);
    $router->resource('movies-series', MovieModelController::class);
    $router->resource('movies-movies', MovieModelController::class);
    $router->resource('movies-inactive', MovieModelController::class);
    $router->resource('movies-content-is-video', MovieModelController::class);
    $router->resource('movies-processed', MovieModelController::class);
    $router->resource('movies-not-processed', MovieModelController::class);
    $router->resource('munowatch-movie-categories', MunowatchMovieCategoryController::class);
    $router->resource('trending-notifications', TrendingNotificationController::class);


    $router->resource('movies', MovieModelController::class);
    $router->resource('series-movies', SeriesMovieController::class);

    // ── Slug-filtered series views (same controller, auto-detects slug) ──
    $router->resource('series-movies-pending', SeriesMovieController::class);
    $router->resource('series-movies-fixed', SeriesMovieController::class);
    $router->resource('series-movies-failed', SeriesMovieController::class);

    // ── Slug-filtered movie views (same controller, auto-detects slug) ──
    $router->resource('movies-movies-pending', MovieModelController::class);
    $router->resource('movies-movies-fixed', MovieModelController::class);
    $router->resource('movies-movies-failed', MovieModelController::class);

    // API: Fix Series Type — single-series AJAX endpoint for live progress modal
    $router->post('api/fix-series-type-single', function (\Illuminate\Http\Request $request) {
        return response()->json(
            \App\Admin\Actions\Post\BatchFixSeriesType::processSingle($request)
        );
    });

    // API: User search (Select2 AJAX for subscription form)
    $router->get('api/users', function (\Illuminate\Http\Request $request) {
        $q = trim((string) ($request->get('q', $request->get('search', $request->get('term', '')))));
        $perPage = 20;

        $query = \App\Models\User::query()->select(['id', 'name', 'email', 'phone_number']);

        if ($q !== '') {
            if (is_numeric($q)) {
                $query->where('id', (int) $q);
            } else {
                $query->where(function ($sq) use ($q) {
                    $sq->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone_number', 'like', "%{$q}%");
                });
            }
        }

        $paginator = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function ($u) {
            $name = trim((string) ($u->name ?? ''));
            $email = trim((string) ($u->email ?? ''));
            $phone = trim((string) ($u->phone_number ?? ''));
            $phoneText = $phone !== '' ? (' | ' . $phone) : '';

            if ($name === '' && $email === '') {
                $text = '#' . $u->id . $phoneText;
            } elseif ($email === '') {
                $text = '#' . $u->id . ' - ' . $name . $phoneText;
            } elseif ($name === '') {
                $text = '#' . $u->id . ' - ' . $email . $phoneText;
            } else {
                $text = '#' . $u->id . ' - ' . $name . ' (' . $email . ')' . $phoneText;
            }

            $u->id = (string) $u->id;
            $u->text = $text;
            return $u;
        });

        return response()->json($paginator);
    });

    // API: Admin subscription actions (recheck, activate, cancel, extend, mark-expired)
    $router->post('api/subscriptions/{id}/action', 'SubscriptionController@adminAction');
    $router->post('api/subscription-transactions/debug/inspect', 'SubscriptionTransactionController@debugInspect');
    $router->post('api/subscription-transactions/debug/apply-fix', 'SubscriptionTransactionController@debugApplyFix');
    $router->post('api/subscription-transactions/batch-fix-single', 'SubscriptionTransactionController@batchFixSingle');

    // API: Subscription debug/fix lab (direct subscription-level debugging)
    $router->post('api/subscriptions/debug/inspect', 'SubscriptionController@debugInspect');
    $router->post('api/subscriptions/debug/apply-fix', 'SubscriptionController@debugApplyFix');
    $router->post('api/subscriptions/debug/batch-fix-single', 'SubscriptionController@batchFixSingle');
    $router->post('api/subscriptions/debug/initiate-payment', 'SubscriptionController@debugInitiatePayment');

    $router->resource('companies', CompanyController::class);
    $router->resource('stock-categories', StockCategoryController::class);
    $router->resource('stock-sub-categories', StockSubCategoryController::class);
    $router->resource('financial-periods', FinancialPeriodController::class);
    $router->resource('employees', EmployeesController::class);
    $router->resource('stock-items', StockItemController::class);
    $router->resource('stock-records', StockRecordController::class);
    $router->resource('companies-edit', CompanyEditController::class);
    $router->resource('africa-app', AfricaTalkingResponseController::class);
    $router->resource('links', LinkController::class);
    $router->resource('pages', PageController::class);
    $router->resource('schools', SchoolController::class);
    $router->resource('learning-materials-categories', LearningMaterialCategoryController::class);
    $router->resource('learning-materials', LearningMaterialPostController::class);
    $router->resource('gens', GenController::class);
    $router->resource('movie-views', MovieViewController::class);
    $router->resource('movie-likes', MovieLikeController::class);
    $router->resource('movie-searches', MovieSearchController::class);
    $router->resource('movie-requests', MovieRequestController::class);

    $router->resource('my-counters', MyCounterController::class);
    $router->resource('movie-downloads', MovieDownloadController::class);
    $router->resource('product-categories', ProductCategoryController::class);
    $router->resource('products', ProductController::class);


    $router->resource('content-moderation-logs', ContentModerationLogController::class);

    // Content Moderation Admin Routes
    $router->get('moderation', 'ModerationAdminController@index')->name('moderation.index');
    $router->get('moderation/reports', 'ModerationAdminController@reports')->name('moderation.reports');
    $router->get('moderation/reports/{id}', 'ModerationAdminController@showReport')->name('moderation.reports.show');
    $router->post('moderation/reports/{id}/action', 'ModerationAdminController@actionReport')->name('moderation.reports.action');
    $router->post('moderation/reports/bulk-action', 'ModerationAdminController@bulkAction')->name('moderation.reports.bulk');
    $router->get('moderation/blocks', 'ModerationAdminController@blocks')->name('moderation.blocks');
    $router->get('moderation/logs', 'ModerationAdminController@logs')->name('moderation.logs');
    $router->get('moderation/statistics', 'ModerationAdminController@statistics')->name('moderation.statistics');
    $router->get('moderation/statistics/export', 'ModerationAdminController@exportStatistics')->name('moderation.statistics.export');


    // AJAX endpoints for moderation
    $router->get('moderation/reports/{id}', 'ModerationAdminController@getReport')->name('moderation.reports.show');
    $router->get('moderation/blocks/{id}', 'ModerationAdminController@getBlock')->name('moderation.blocks.show');
    $router->get('moderation/logs/{id}', 'ModerationAdminController@getLog')->name('moderation.logs.show');

    $router->resource('users', UserController::class);
    $router->get('users/{id}/profile', 'UserProfileController@show')->name('users.profile');

    // Action endpoints
    $router->put('moderation/reports/{id}/status', 'ModerationAdminController@updateReportStatus')->name('moderation.reports.status');
    $router->put('moderation/blocks/{id}/unblock', 'ModerationAdminController@unblockUser')->name('moderation.blocks.unblock');
    $router->delete('moderation/blocks/{id}', 'ModerationAdminController@deleteBlock')->name('moderation.blocks.delete');


    $router->resource('movie-crawler-websites', MovieCrawlerWebsiteController::class);
    $router->resource('movie-crawler-pages', MovieCrawlerPageController::class);
    
    // Imported Dating Users Routes
    $router->resource('imported-users', ImportedUsersController::class);

    // Video Playback Failure Routes
    $router->resource('video-playback-failures', VideoPlaybackFailureController::class);

    // Video Transfer to Google Drive Routes
    $router->resource('video-transfers', VideoTransferController::class);
    $router->get('video-transfers/{id}/retry', 'VideoTransferController@retry')->name('video-transfers.retry');
    $router->get('video-transfers/{id}/cancel', 'VideoTransferController@cancel')->name('video-transfers.cancel');

    // ── DB Sync Dashboard (Hetzner destination only) ─────────────────
    $router->get('sync-dashboard',         'SyncDashboardController@index');
    $router->get('sync-dashboard/live',    'SyncDashboardController@live');
    $router->post('sync-dashboard/trigger','SyncDashboardController@trigger');
    $router->post('sync-dashboard/reset',  'SyncDashboardController@resetCursor');

    // ── Movie File Transfer (Hetzner Storage) Routes ──────────────────
    $router->get('movie-file-transfers/live-data',           'MovieFileTransferController@liveStatus');
    $router->get('movie-file-transfers',                    'MovieFileTransferController@index');
    $router->get('movie-file-transfers/monitor',            'MovieFileTransferController@monitor')->name('movie-file-transfers.monitor');
    $router->get('movie-file-transfers/backfill',           'MovieFileTransferController@backfill')->name('movie-file-transfers.backfill');
    $router->post('movie-file-transfers/backfill-run',      'MovieFileTransferController@backfillRun')->name('movie-file-transfers.backfill-run');
    $router->get('movie-file-transfers/process-now',        'MovieFileTransferController@processNow')->name('movie-file-transfers.process-now');
    $router->get('movie-file-transfers/retry-all-failed',   'MovieFileTransferController@retryAllFailed')->name('movie-file-transfers.retry-all-failed');
    $router->post('movie-file-transfers/queue-single',      'MovieFileTransferController@queueSingle')->name('movie-file-transfers.queue-single-post');
    $router->get('movie-file-transfers/queue-single/{movieId}', 'MovieFileTransferController@queueSingle')->where(['movieId' => '[0-9]+'])->name('movie-file-transfers.queue-single');
    $router->get('movie-file-transfers/{id}',               'MovieFileTransferController@show')->where(['id' => '[0-9]+'])->name('movie-file-transfers.show');
    $router->get('movie-file-transfers/sync-all-urls',       'MovieFileTransferController@syncAllUrls')->name('movie-file-transfers.sync-all-urls');
    $router->get('movie-file-transfers/{id}/retry',         'MovieFileTransferController@retry')->where(['id' => '[0-9]+'])->name('movie-file-transfers.retry');
    $router->get('movie-file-transfers/{id}/cancel',        'MovieFileTransferController@cancel')->where(['id' => '[0-9]+'])->name('movie-file-transfers.cancel');
    $router->get('movie-file-transfers/{id}/sync-url',      'MovieFileTransferController@syncUrl')->where(['id' => '[0-9]+'])->name('movie-file-transfers.sync-url');

    // ── Movie URL Change / Sync Routes ───────────────────────────────
    $router->get('movie-url-changes/dashboard',          'MovieVideoURLChangeController@dashboard')->name('movie-url-changes.dashboard');
    $router->get('movie-url-changes/push-pending',       'MovieVideoURLChangeController@pushPending')->name('movie-url-changes.push-pending');
    $router->get('movie-url-changes/test-connection',    'MovieVideoURLChangeController@testConnection')->name('movie-url-changes.test-connection');
    $router->get('movie-url-changes/{id}/retry-remote',  'MovieVideoURLChangeController@retryRemote')->where(['id' => '[0-9]+'])->name('movie-url-changes.retry-remote');
    $router->get('movie-url-changes/{id}/revert',        'MovieVideoURLChangeController@revert')->where(['id' => '[0-9]+'])->name('movie-url-changes.revert');
    $router->get('movie-url-changes/{id}',               'MovieVideoURLChangeController@show')->where(['id' => '[0-9]+'])->name('movie-url-changes.show');
    $router->get('movie-url-changes',                    'MovieVideoURLChangeController@index')->name('movie-url-changes.index');

    // ── Namz Crawler Routes ───────────────────────────────────────────────────
    $router->get('namz-crawler',                          'NamzCrawlerController@dashboard')->name('namz-crawler.dashboard');
    $router->get('namz-crawler/start',                    'NamzCrawlerController@startCrawl')->name('namz-crawler.start');
    $router->get('namz-crawler/resume',                   'NamzCrawlerController@resumeCrawl')->name('namz-crawler.resume');
    $router->get('namz-crawler/stop',                     'NamzCrawlerController@stopCrawl')->name('namz-crawler.stop');
    $router->get('namz-crawler/test-login',               'NamzCrawlerController@testLogin')->name('namz-crawler.test-login');
    $router->get('namz-crawler/backfill-urls',            'NamzCrawlerController@backfillOldRecords')->name('namz-crawler.backfill');
    $router->get('namz-crawler/activate-with-url',        'NamzCrawlerController@activateMoviesWithUrl')->name('namz-crawler.activate');
    $router->get('namz-crawler/live',                     'NamzCrawlerController@liveSessions')->name('namz-crawler.live');
    $router->get('namz-crawler/retry/{id}',               'NamzCrawlerController@retryId')->where(['id' => '[0-9]+'])->name('namz-crawler.retry');
    $router->get('namz-crawler/set-url/{movieId}',        'NamzCrawlerController@setUrlForm')->where(['movieId' => '[0-9]+'])->name('namz-crawler.set-url');
    $router->post('namz-crawler/set-url/{movieId}/save',  'NamzCrawlerController@saveUrl')->where(['movieId' => '[0-9]+'])->name('namz-crawler.save-url');
    $router->get('namz-crawler/sessions/{id}',            'NamzCrawlerController@showSession')->where(['id' => '[0-9]+'])->name('namz-crawler.session');
    $router->get('namz-crawler/probe-full',               'NamzCrawlerController@probeFull')->name('namz-crawler.probe-full');
    $router->get('namz-crawler/probe-ajax/{namzId}',      'NamzCrawlerController@probeIdAjax')->where(['namzId' => '[0-9]+'])->name('namz-crawler.probe-ajax');
    $router->get('namz-crawler/movie-info/{movieId}',     'NamzCrawlerController@movieInfoJson')->where(['movieId' => '[0-9]+'])->name('namz-crawler.movie-info');
    $router->post('namz-crawler/set-url-ajax/{movieId}',  'NamzCrawlerController@setUrlAjax')->where(['movieId' => '[0-9]+'])->name('namz-crawler.set-url-ajax');
    $router->get('namz-crawler/pending-movies',           'NamzCrawlerController@pendingMovies')->name('namz-crawler.pending');
    $router->get('namz-crawler/bulk-retry-failed',        'NamzCrawlerController@bulkRetryFailed')->name('namz-crawler.bulk-retry');
    $router->get('namz-crawl-logs',                       'NamzCrawlerController@index')->name('namz-crawl-logs.index');

    // ============================================
    // GAME MODULE ROUTES — DISABLED (resource optimization)
    // ============================================
    /*
    $router->get('game-dashboard', 'GameDashboardController@index')->name('game-dashboard');
    $router->resource('game-sessions', GameSessionController::class);
    $router->resource('ludo-sessions', LudoSessionController::class);
    $router->resource('game-invitations', GameInvitationController::class);
    $router->resource('coin-transactions', CoinTransactionController::class);
    */

    // ============================================
    // OFFLINE GAME STATS
    // ============================================
    $router->get('game-stats', 'GameStatsController@index')->name('game-stats');
    $router->get('game-stats/records', 'GameStatsController@records')->name('game-stats.records');
    $router->get('game-stats/{id}', 'GameStatsController@show')->name('game-stats.show');

    // ============================================
    // STREAMING MODULE ROUTES (TV & Radio)
    // ============================================
    $router->resource('streaming-stations', StreamingStationController::class);
    $router->resource('streaming-tv', StreamingStationController::class);
    $router->resource('streaming-radio', StreamingStationController::class);

    // ============================================
    // BLOG MODULE ROUTES
    // ============================================
    $router->resource('blog-posts', BlogPostController::class);
    $router->resource('blog-comments', BlogCommentController::class);

    // System Configuration (singleton — shows edit form directly)
    $router->resource('system-configs', SystemConfigController::class);

    // Debug Player Proxy (server-side cURL video URL testing — requires admin session)
    $router->post('debug-player/proxy', 'DebugPlayerProxyController@proxy');
    // Debug Player Fix Movie (re-fetch from source, repair broken records — requires admin session)
    $router->post('debug-player/fix-movie', 'DebugPlayerProxyController@fixMovie');

    // Series Debug Player API (admin session required)
    $router->post('debug-player/series-info', 'DebugPlayerProxyController@seriesInfo');
    $router->post('debug-player/series-remote-episodes', 'DebugPlayerProxyController@seriesRemoteEpisodes');
    $router->post('debug-player/fix-series', 'DebugPlayerProxyController@fixSeries');
    $router->post('debug-player/fix-episode', 'DebugPlayerProxyController@fixEpisode');
    $router->post('debug-player/sync-series', 'DebugPlayerProxyController@syncSeries');
    $router->post('debug-player/fetch-range', 'DebugPlayerProxyController@fetchRange');
    $router->post('debug-player/check-activation', 'DebugPlayerProxyController@checkActivation');

    //https://omulimisa.org/api/v1/e-learning/inbound-outbound
    //https://omulimisa.org/api/v1/e-learning/events
});

// Debug Player Stream — OUTSIDE admin middleware so <video> element can fetch without session auth.
// Protected by a signed token (HMAC) instead — only admin panel JS can generate valid URLs.
Route::get(
    config('admin.route.prefix') . '/debug-player/stream',
    '\App\Admin\Controllers\DebugPlayerProxyController@stream'
)->name('debug-player.stream');
