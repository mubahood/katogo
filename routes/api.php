<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\DynamicCrudController;
use App\Models\StockItem;
use App\Models\StockSubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use function Laravel\Prompts\search;
use App\Http\Middleware\JwtMiddleware;




Route::post('auth/password-reset', [ApiController::class, 'password_reset']);
Route::post('auth/register', [ApiController::class, 'register']);
Route::post('auth/request-password-reset-code', [ApiController::class, 'request_password_reset_code']);
Route::post('auth/login', [ApiController::class, 'login']);
Route::post('api/{model}', [ApiController::class, 'my_update']);
Route::get('movies', [ApiController::class, 'get_movies']);
Route::get('api/{model}', [ApiController::class, 'my_list']);
Route::post('file-uploading', [ApiController::class, 'file_uploading']);
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::get('manifest', [ApiController::class, 'manifest']);
    Route::get('movies', [DynamicCrudController::class, 'movies']);
    Route::get('/dynamic-list', [DynamicCrudController::class, 'index']);
    Route::post('/dynamic-save', [DynamicCrudController::class, 'save']);
    Route::post('/dynamic-delete', [DynamicCrudController::class, 'delete']);
    Route::POST("consultation-card-payment", [DynamicCrudController::class, 'consultation_card_payment']);
});

Route::post('save-view-progress', [ApiController::class, 'save_view_progress']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



//rout for stock-categories
Route::get('/stock-items', function (Request $request) {
    $q = $request->get('q');

    $company_id = $request->get('company_id');
    if ($company_id == null) {
        return response()->json([
            'data' => [],
        ], 400);
    }

    $sub_categories =
        StockItem::where('company_id', $company_id)
        ->where('name', 'like', "%$q%")
        ->orderBy('name', 'asc')
        ->limit(20)
        ->get();

    $data = [];

    foreach ($sub_categories as $sub_category) {
        $data[] = [
            'id' => $sub_category->id,
            'text' => $sub_category->sku . " " . $sub_category->name_text,
        ];
    }

    return response()->json([
        'data' => $data,
    ]);
});




//rout for stock-categories
Route::get('/stock-sub-categories', function (Request $request) {
    $q = $request->get('q');

    $company_id = $request->get('company_id');
    if ($company_id == null) {
        return response()->json([
            'data' => [],
        ], 400);
    }

    $sub_categories =
        StockSubCategory::where('company_id', $company_id)
        ->where('name', 'like', "%$q%")
        ->orderBy('name', 'asc')
        ->limit(20)
        ->get();

    $data = [];

    foreach ($sub_categories as $sub_category) {
        $data[] = [
            'id' => $sub_category->id,
            'text' => $sub_category->name_text . " (" . $sub_category->measurement_unit . ")",
        ];
    }

    return response()->json([
        'data' => $data,
    ]);
});
