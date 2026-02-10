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



    $router->resource('munowatch-categories', MunowatchCategoryController::class);


    $router->resource('subscription-transactions', SubscriptionTransactionController::class);
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

    // ============================================
    // GAME MODULE ROUTES
    // ============================================
    
    // Game Dashboard (Statistics Overview)
    $router->get('game-dashboard', 'GameDashboardController@index')->name('game-dashboard');
    
    // Matatu Game Sessions
    $router->resource('game-sessions', GameSessionController::class);
    
    // Ludo Game Sessions
    $router->resource('ludo-sessions', LudoSessionController::class);
    
    // Game Invitations
    $router->resource('game-invitations', GameInvitationController::class);
    
    // Coin Transactions
    $router->resource('coin-transactions', CoinTransactionController::class);

    // ============================================
    // BLOG MODULE ROUTES
    // ============================================
    $router->resource('blog-posts', BlogPostController::class);
    $router->resource('blog-comments', BlogCommentController::class);

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
