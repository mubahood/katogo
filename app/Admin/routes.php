<?php

use Illuminate\Routing\Router;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
    'as'            => config('admin.route.prefix') . '.',
], function (Router $router) {

    $router->get('/', 'HomeController@index')->name('home');

    $router->resource('scraper-models', ScraperModelController::class);
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

    //https://omulimisa.org/api/v1/e-learning/inbound-outbound
    //https://omulimisa.org/api/v1/e-learning/events
});
