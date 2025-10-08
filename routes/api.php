<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\DynamicCrudController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\SubscriptionApiController;
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

// Public endpoint for random movie (no authentication required)
Route::get('random-movie', [DynamicCrudController::class, 'random_movie']);

// ========================================
// SUBSCRIPTION ROUTES
// ========================================

// Public Subscription Routes (no authentication required)
Route::get('subscription-plans', [SubscriptionApiController::class, 'listPlans']);

// Pesapal Callback & IPN (no authentication required)
Route::get('subscriptions/pesapal/callback', [SubscriptionApiController::class, 'callback']);
Route::post('subscriptions/pesapal/callback', [SubscriptionApiController::class, 'callback']); // Support both GET and POST
Route::post('subscriptions/pesapal/ipn', [SubscriptionApiController::class, 'ipn']);

// Pesapal Callback & IPN Routes (Public - No Auth Required)
Route::get('subscriptions/pesapal/callback', [SubscriptionApiController::class, 'pesapalCallback']);
Route::post('subscriptions/pesapal/ipn', [SubscriptionApiController::class, 'pesapalIpn']);

// Payment Status Check (Public - but validates ownership)
Route::get('subscriptions/payment-status/{trackingId}', [SubscriptionApiController::class, 'getPaymentStatus']);

// Authenticated Subscription Routes
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('subscriptions/create', [SubscriptionApiController::class, 'create']);
    Route::get('subscriptions/my-subscription', [SubscriptionApiController::class, 'mySubscription']);
    Route::get('subscriptions/history', [SubscriptionApiController::class, 'history']);
    Route::post('subscriptions/retry-payment', [SubscriptionApiController::class, 'retryPayment']);
    Route::post('subscriptions/check-status', [SubscriptionApiController::class, 'checkStatus']);
    
    // Pending Subscription Management Routes
    Route::get('subscriptions/pending', [SubscriptionApiController::class, 'getPending']);
    Route::post('subscriptions/{id}/initiate-payment', [SubscriptionApiController::class, 'initiatePayment']);
    Route::post('subscriptions/{id}/check-payment', [SubscriptionApiController::class, 'checkPendingPayment']);
    Route::post('subscriptions/{id}/cancel', [SubscriptionApiController::class, 'cancelPending']);
});

// FREE TRIAL TEST ROUTES (Remove in production)
Route::get('test-free-trial/{user_id?}', [App\Http\Controllers\FreeTrialTestController::class, 'testFreeTrial']);
Route::get('test-auto-assignment/{user_id?}', [App\Http\Controllers\FreeTrialTestController::class, 'testAutoAssignment']);
Route::get('test-free-trial-plan', [App\Http\Controllers\FreeTrialTestController::class, 'getFreeTrialPlan']);
Route::get('test-free-trial-stats', [App\Http\Controllers\FreeTrialTestController::class, 'getFreeTrialStats']);
Route::delete('test-free-trial-cleanup/{user_id?}', [App\Http\Controllers\FreeTrialTestController::class, 'cleanupTestData']);

Route::get('products-1', [ApiController::class, 'products_1']); 
Route::post('file-uploading', [ApiController::class, 'file_uploading']);
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('disable-account', [ApiController::class, 'disable_account']);


    Route::post("post-media-upload", [ApiController::class, 'upload_media']);
    Route::post("product-create", [ApiController::class, "product_create"]);
    Route::post('products-delete', [ApiController::class, 'products_delete']);

    Route::get('me', [ApiController::class, 'me']);
    Route::get('manifest', [ApiController::class, 'manifest']);
    
    // ===== SUBSCRIPTION-PROTECTED MOVIE ROUTES =====
    // These routes require an active subscription (allows grace period)
    Route::middleware(['subscription'])->group(function () {
        Route::get('movies', [DynamicCrudController::class, 'movies']);
        Route::get('movie/{id}', [DynamicCrudController::class, 'movie']);
        
        // Video Progress & Watch History Routes
        Route::post('video-progress', [DynamicCrudController::class, 'save_video_progress'])->middleware('throttle:video-progress');
        Route::get('video-progress/{movie_id}', [DynamicCrudController::class, 'get_video_progress']);
        Route::get('watch-history', [DynamicCrudController::class, 'get_watch_history']);
        Route::post('video-progress/{movie_id}/delete', [DynamicCrudController::class, 'delete_video_progress']);
    });
    
    Route::get('users-list', [DynamicCrudController::class, 'users_list']);
    Route::get('/dynamic-list', [DynamicCrudController::class, 'index']);
    Route::post('/dynamic-save', [DynamicCrudController::class, 'save']);
    Route::post('/dynamic-delete', [DynamicCrudController::class, 'delete']);
    Route::POST("consultation-card-payment", [DynamicCrudController::class, 'consultation_card_payment']);

    Route::POST('chat-send', [ApiController::class, 'chat_send']);
    Route::get('/debug-chat/{id}', [App\Http\Controllers\ApiController::class, 'debug_chat']);
Route::get('/chat-heads', [App\Http\Controllers\ApiController::class, 'chat_heads']);
    Route::get('chat-messages', [ApiController::class, 'chat_messages']);
    Route::POST('chat-mark-as-read', [ApiController::class, 'chat_mark_as_read']);
    Route::POST('chat-start', [ApiController::class, 'chat_start']);
    Route::POST('chat-delete', [ApiController::class, 'chat_delete']);

    // Account Layout & User Management Routes
    Route::get('account/dashboard', [DynamicCrudController::class, 'get_account_dashboard']);
    
    // ===== SUBSCRIPTION-PROTECTED WATCHLIST & PREFERENCES =====
    // Premium features that require active subscription
    Route::middleware(['subscription'])->group(function () {
        Route::get('account/watchlist', [DynamicCrudController::class, 'get_watchlist']);
        Route::post('account/watchlist/add', [DynamicCrudController::class, 'add_to_watchlist']);
        Route::delete('account/watchlist/{movie_id}', [DynamicCrudController::class, 'remove_from_watchlist']);
        Route::get('account/watch-history', [DynamicCrudController::class, 'get_watch_history']);
        Route::get('account/likes', [DynamicCrudController::class, 'get_liked_movies']);
        Route::post('account/likes/toggle', [DynamicCrudController::class, 'toggle_movie_like']);
        Route::get('account/wishlist', [DynamicCrudController::class, 'get_wishlisted_movies']);
        Route::post('account/wishlist/toggle', [DynamicCrudController::class, 'toggle_movie_wishlist']);
    });

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

// ========================================
// CATCH-ALL DYNAMIC ROUTES (MUST BE LAST!)
// ========================================
// These routes should be at the END to avoid intercepting specific routes above
Route::post('api/{model}', [ApiController::class, 'my_update']);
Route::get('api/{model}', [ApiController::class, 'my_list']);
