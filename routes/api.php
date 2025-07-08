<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\DynamicCrudController;
use App\Http\Controllers\ModerationController;
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

// Content filtering endpoint (used by app's automated systems)
Route::post('moderation/filter-content', [ModerationController::class, 'filterContent']);

Route::post('api/{model}', [ApiController::class, 'my_update']);
// Route::get('movies', [ApiController::class, 'get_movies']);
Route::get('api/{model}', [ApiController::class, 'my_list']);
Route::get('products-1', [ApiController::class, 'products_1']); 
Route::post('file-uploading', [ApiController::class, 'file_uploading']);
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('disable-account', [ApiController::class, 'disable_account']);


    Route::post("post-media-upload", [ApiController::class, 'upload_media']);
    Route::post("product-create", [ApiController::class, "product_create"]);
    Route::post('products-delete', [ApiController::class, 'products_delete']);

    Route::get('me', [ApiController::class, 'me']);
    Route::get('manifest', [ApiController::class, 'manifest']);
    Route::get('movies', [DynamicCrudController::class, 'movies']);
    Route::get('users-list', [DynamicCrudController::class, 'users_list']);
    Route::get('/dynamic-list', [DynamicCrudController::class, 'index']);
    Route::post('/dynamic-save', [DynamicCrudController::class, 'save']);
    Route::post('/dynamic-delete', [DynamicCrudController::class, 'delete']);
    Route::POST("consultation-card-payment", [DynamicCrudController::class, 'consultation_card_payment']);

    Route::POST('chat-send', [ApiController::class, 'chat_send']);
    Route::get('chat-heads', [ApiController::class, 'chat_heads']);
    Route::get('chat-messages', [ApiController::class, 'chat_messages']);
    Route::POST('chat-mark-as-read', [ApiController::class, 'chat_mark_as_read']);
    Route::POST('chat-start', [ApiController::class, 'chat_start']);
    Route::POST('chat-delete', [ApiController::class, 'chat_delete']);

    // Content Moderation & Safety Routes
    Route::post('moderation/report-content', [ModerationController::class, 'reportContent']);
    Route::post('moderation/report', [ModerationController::class, 'reportContent']); // Alias for backward compatibility
    Route::post('moderation/block-user', [ModerationController::class, 'blockUser']);
    Route::post('moderation/unblock-user', [ModerationController::class, 'unblockUser']);
    Route::get('moderation/blocked-users', [ModerationController::class, 'getBlockedUsers']);
    Route::get('moderation/my-reports', [ModerationController::class, 'getUserReports']);
    Route::get('moderation/user-reports', [ModerationController::class, 'getUserReports']); // Alias
    Route::post('moderation/legal-consent', [ModerationController::class, 'updateLegalConsent']);
    Route::post('moderation/update-legal-consent', [ModerationController::class, 'updateLegalConsent']); // Alias
    Route::get('moderation/legal-consent-status', [ModerationController::class, 'getLegalConsentStatus']);
    
    // Admin-only moderation routes
    Route::get('moderation/dashboard', [ModerationController::class, 'getModerationDashboard']);
    
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

// Moderation routes
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('moderation/stock-item', [ModerationController::class, 'moderateStockItem']);
    Route::post('moderation/stock-sub-category', [ModerationController::class, 'moderateStockSubCategory']);
});
