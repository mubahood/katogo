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

    $router->resource('scraper-models', ScraperModelController::class);
    $router->resource('movies-active', MovieModelController::class);
    $router->resource('movies-series', MovieModelController::class);
    $router->resource('movies-movies', MovieModelController::class);
    $router->resource('movies-inactive', MovieModelController::class);
    $router->resource('movies-content-is-video', MovieModelController::class);
    $router->resource('movies-processed', MovieModelController::class);
    $router->resource('movies-not-processed', MovieModelController::class);


    $router->resource('movies', MovieModelController::class);
    $router->resource('series-movies', SeriesMovieController::class);

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

    $router->resource('my-counters', MyCounterController::class);
    $router->resource('movie-downloads', MovieDownloadController::class);
    $router->resource('product-categories', ProductCategoryController::class);

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


    // Action endpoints
    $router->put('moderation/reports/{id}/status', 'ModerationAdminController@updateReportStatus')->name('moderation.reports.status');
    $router->put('moderation/blocks/{id}/unblock', 'ModerationAdminController@unblockUser')->name('moderation.blocks.unblock');
    $router->delete('moderation/blocks/{id}', 'ModerationAdminController@deleteBlock')->name('moderation.blocks.delete');




    //https://omulimisa.org/api/v1/e-learning/inbound-outbound
    //https://omulimisa.org/api/v1/e-learning/events
});
