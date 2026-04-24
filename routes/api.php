<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\CoinController;
use App\Http\Controllers\DynamicCrudController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LudoController;
use App\Http\Controllers\CheckersController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\SubscriptionApiController;
use App\Http\Controllers\Api\V2\MovieController as V2MovieController;
use App\Http\Controllers\Api\V2\SearchController as V2SearchController;
use App\Http\Controllers\Api\V2\ManifestController as V2ManifestController;
use App\Http\Controllers\Api\V2\BlogController as V2BlogController;
use App\Http\Controllers\Api\V2\SafeModeAnalyticsController as V2SafeModeAnalyticsController;
use App\Http\Controllers\Api\V2\SubscriptionFixController as V2SubscriptionFixController;
use App\Http\Controllers\Api\V2\StreamingController as V2StreamingController;
use App\Http\Controllers\Api\V2\DownloadController as V2DownloadController;
use App\Http\Controllers\Api\V2\TriviaController as V2TriviaController;
use App\Http\Controllers\Api\V2\GameStatsController as V2GameStatsController;
use App\Models\StockItem;
use App\Models\StockSubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use function Laravel\Prompts\search;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Controllers\MigrationController;



// ── One-time migration endpoint (REMOVE after migration) ────
Route::post('run-migration', [MigrationController::class, 'runMigration']);

Route::middleware('throttle:auth')->group(function () {
    Route::post('auth/password-reset', [ApiController::class, 'password_reset']);
    Route::post('auth/register', [ApiController::class, 'register']);
    Route::post('auth/request-password-reset-code', [ApiController::class, 'request_password_reset_code']);
    Route::post('auth/login', [ApiController::class, 'login']);
    Route::post('auth/google', [ApiController::class, 'googleAuth']);
});

// Content filtering endpoint (used by app's automated systems)
Route::post('moderation/filter-content', [ModerationController::class, 'filterContent']);

// Video Playback Failure Reporting (Public - no authentication required)
Route::post('video-playback-failures', [App\Http\Controllers\Api\VideoPlaybackFailureController::class, 'store']);

// Public endpoint for random movie (no authentication required)
Route::get('random-movie', [DynamicCrudController::class, 'random_movie']);

// ========================================
// SUBSCRIPTION ROUTES
// ========================================

// Public Subscription Routes (no authentication required)
Route::get('subscription-plans', [SubscriptionApiController::class, 'listPlans']);
Route::get('subscriptions/payment-gateways', [SubscriptionApiController::class, 'paymentGateways']);

// Pesapal Callback & IPN (no authentication required)
// NOTE: Using pesapalCallback/pesapalIpn which include robust error handling
Route::get('subscriptions/pesapal/callback', [SubscriptionApiController::class, 'pesapalCallback']);
Route::post('subscriptions/pesapal/callback', [SubscriptionApiController::class, 'pesapalCallback']); // Support both GET and POST
Route::post('subscriptions/pesapal/ipn', [SubscriptionApiController::class, 'pesapalIpn']);
Route::get('subscriptions/flutterwave/callback', [SubscriptionApiController::class, 'flutterwaveCallback']);
Route::post('subscriptions/flutterwave/webhook', [SubscriptionApiController::class, 'flutterwaveWebhook']);

// Pesapal API Diagnostics (no auth — for admin testing)
Route::get('subscriptions/pesapal/test', [SubscriptionApiController::class, 'pesapalTest']);

// Payment Status Check (Public - but validates ownership)
Route::get('subscriptions/payment-status/{trackingId}', [SubscriptionApiController::class, 'getPaymentStatus']);

// Authenticated Subscription Routes
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('subscriptions/create', [SubscriptionApiController::class, 'create']);
    Route::get('subscriptions/default-gateway', [SubscriptionApiController::class, 'getDefaultGateway']);
    Route::post('subscriptions/default-gateway', [SubscriptionApiController::class, 'setDefaultGateway']);
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
    Route::get('manifest', [ApiController::class, 'manifest'])->middleware('lscache:45,manifest_v1');



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


    Route::get('account/watchlist', [DynamicCrudController::class, 'get_watchlist']);
    Route::post('account/watchlist/add', [DynamicCrudController::class, 'add_to_watchlist']);
    Route::delete('account/watchlist/{movie_id}', [DynamicCrudController::class, 'remove_from_watchlist']);
    Route::get('account/watch-history', [DynamicCrudController::class, 'get_watch_history']);
    Route::get('account/likes', [DynamicCrudController::class, 'get_liked_movies']);
    Route::post('account/likes/toggle', [DynamicCrudController::class, 'toggle_movie_like']);
    Route::get('account/wishlist', [DynamicCrudController::class, 'get_wishlisted_movies']);
    Route::post('account/wishlist/toggle', [DynamicCrudController::class, 'toggle_movie_wishlist']);


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

    Route::get('movies/{id}', [DynamicCrudController::class, 'movie']); // Alternative route for mobile app compatibility - must be before 'movies'
    Route::get('movies', [DynamicCrudController::class, 'movies']);
    Route::get('movie/{id}', [DynamicCrudController::class, 'movie']);

    // Video Progress & Watch History Routes
    Route::post('video-progress', [DynamicCrudController::class, 'save_video_progress'])->middleware('throttle:video-progress');
    Route::get('video-progress/{movie_id}', [DynamicCrudController::class, 'get_video_progress']);
    Route::get('watch-history', [DynamicCrudController::class, 'get_watch_history']);
    Route::post('video-progress/{movie_id}/delete', [DynamicCrudController::class, 'delete_video_progress']);

    // ════════════════════════════════════════════
    //  V2 API — Clean, paginated, optimised
    // ════════════════════════════════════════════
    Route::prefix('v2')->group(function () {
        // V2 Manifest — optimised, lean, cached
        Route::get('manifest',            [V2ManifestController::class, 'index'])->middleware('lscache:45,manifest_v2');

        // NOTE: cacheResponse requires `spatie/laravel-responsecache` installed (composer update)
        Route::middleware(['etag', 'cacheResponse'])->group(function () {
            Route::get('movies',              [V2MovieController::class, 'index']);
            Route::get('movies/search',       [V2MovieController::class, 'search']);
            Route::get('movies/{id}',         [V2MovieController::class, 'show']);
            Route::get('movies/{id}/related', [V2MovieController::class, 'related']);
            Route::get('series',              [V2MovieController::class, 'seriesIndex']);
            Route::get('series/{id}/episodes',[V2MovieController::class, 'episodes']);
        });

        // Playback reporting
        Route::post('movies/{id}/playback', [V2MovieController::class, 'playback']);

        // Movie fix / debug (mobile-triggered)
        Route::post('movies/{id}/fix', [V2MovieController::class, 'fix']);

        // Search — series search, suggestions, trending, history
        Route::get('search/series',       [V2SearchController::class, 'searchSeries']);
        Route::get('search/suggestions',  [V2SearchController::class, 'suggestions']);
        Route::get('search/trending',     [V2SearchController::class, 'trending']);
        Route::get('search/history',      [V2SearchController::class, 'history']);
        Route::delete('search/history/{id}', [V2SearchController::class, 'deleteHistory']);
        Route::delete('search/history',   [V2SearchController::class, 'clearHistory']);

        // Power Search — combined movies + series search (V2)
        Route::get('search/all',              [V2SearchController::class, 'searchAll']);
        Route::get('search/all/suggestions',  [V2SearchController::class, 'allSuggestions']);
        Route::get('search/all/trending',     [V2SearchController::class, 'allTrending']);

        // Blog / News
        Route::get('blog',                     [V2BlogController::class, 'index']);
        Route::get('blog/marquee',             [V2BlogController::class, 'marquee'])->middleware('lscache:120,blog_marquee');
        Route::get('blog/{id}',                [V2BlogController::class, 'show']);
        Route::post('blog/{id}/like',          [V2BlogController::class, 'toggleLike']);
        Route::post('blog/{id}/comment',       [V2BlogController::class, 'addComment']);
        Route::post('blog/comment/{id}/like',  [V2BlogController::class, 'toggleCommentLike']);
        Route::post('blog/comment/{id}/report',[V2BlogController::class, 'reportComment']);

        // Streaming (TV & Radio)
        Route::get('streaming/home',             [V2StreamingController::class, 'home'])->middleware('lscache:120,streaming_home');
        Route::get('streaming/stations',         [V2StreamingController::class, 'index']);
        Route::get('streaming/stations/{id}',    [V2StreamingController::class, 'show']);
        Route::get('streaming/categories',       [V2StreamingController::class, 'categories']);

        // Downloads — gallery & in-app tracking
        Route::post('downloads/record',          [V2DownloadController::class, 'record']);
        Route::get('downloads/stats',            [V2DownloadController::class, 'stats']);

        // SafeMode Analytics
        Route::post('safemode/track',                     [V2SafeModeAnalyticsController::class, 'track']);
        Route::post('safemode/progress',                  [V2SafeModeAnalyticsController::class, 'saveProgress']);
        Route::get('safemode/progress/{external_video_id}',[V2SafeModeAnalyticsController::class, 'getProgress']);
        Route::get('safemode/history',                    [V2SafeModeAnalyticsController::class, 'history']);

        // ════════════════════════════════════════════
        //  V2 Subscription Payment Fix Endpoints
        //  360° control for diagnosing and fixing stuck payments
        // ════════════════════════════════════════════
        Route::get('subscriptions/fixable',               [V2SubscriptionFixController::class, 'fixable']);
        Route::post('subscriptions/auto-fix',             [V2SubscriptionFixController::class, 'autoFix']);
        Route::post('subscriptions/{id}/force-check',     [V2SubscriptionFixController::class, 'forceCheck']);
        Route::get('subscriptions/{id}/diagnostic',       [V2SubscriptionFixController::class, 'diagnostic']);

        // Movie Trivia — public endpoints (no auth required for question bank)
        Route::get('trivia/version',    [V2TriviaController::class, 'version']);
        Route::get('trivia/questions',  [V2TriviaController::class, 'questions']);
        Route::get('trivia/stats',      [V2TriviaController::class, 'stats']);

        // Game Stats — offline-first sync
        Route::get('game-stats',        [V2GameStatsController::class, 'index']);
        Route::post('game-stats/sync',  [V2GameStatsController::class, 'sync']);
    });
});

// ========================================
// MULTIPLAYER GAME ROUTES — DISABLED (resource optimization)
// All game/coin/ludo endpoints return 503 maintenance response
// ========================================
Route::middleware([JwtMiddleware::class])->group(function () {
    $gameDisabled = function () {
        return response()->json([
            'success' => false,
            'message' => 'Online games are temporarily disabled for maintenance. Please try again later.',
            'data' => null,
        ], 503);
    };

    Route::match(['get','post'], 'game/{any?}', $gameDisabled)->where('any', '.*');
    Route::match(['get','post'], 'coins/{any?}', $gameDisabled)->where('any', '.*');
    Route::match(['get','post'], 'ludo/{any?}', $gameDisabled)->where('any', '.*');
    Route::match(['get','post'], 'checkers/{any?}', $gameDisabled)->where('any', '.*');
});

/*  ── ORIGINAL GAME ROUTES (commented out) ──────────────────
Route::middleware([JwtMiddleware::class, 'throttle:game'])->group(function () {
    Route::get('game/online-users', [GameController::class, 'onlineUsers']);
    Route::post('game/invite', [GameController::class, 'sendInvitation']);
    Route::get('game/invitations', [GameController::class, 'getInvitations']);
    Route::get('game/invite/{id}/status', [GameController::class, 'getInvitationStatus']);
    Route::post('game/invite/{id}/accept', [GameController::class, 'acceptInvitation']);
    Route::post('game/invite/{id}/decline', [GameController::class, 'declineInvitation']);
    Route::post('game/invite/{id}/cancel', [GameController::class, 'cancelInvitation']);
    Route::get('game/session/{id}', [GameController::class, 'getSession']);
    Route::post('game/session/{id}/action', [GameController::class, 'submitAction']);
    Route::post('game/session/{id}/leave', [GameController::class, 'leaveSession']);
    Route::get('coins/balance', [CoinController::class, 'getBalance']);
    Route::get('coins/history', [CoinController::class, 'getHistory']);
    Route::post('coins/award-offline-win', [CoinController::class, 'awardOfflineWin']);
    Route::get('coins/leaderboard', [CoinController::class, 'getLeaderboard']);
    Route::post('ludo/invite', [LudoController::class, 'sendInvitation']);
    Route::post('ludo/invite/{id}/accept', [LudoController::class, 'acceptInvitation']);
    Route::get('ludo/session/{id}', [LudoController::class, 'getSession']);
    Route::post('ludo/session/{id}/roll', [LudoController::class, 'rollDice']);
    Route::post('ludo/session/{id}/move', [LudoController::class, 'movePiece']);
    Route::post('ludo/session/{id}/pass', [LudoController::class, 'passTurn']);
    Route::post('ludo/session/{id}/leave', [LudoController::class, 'leaveGame']);

    // Checkers Game
    Route::post('checkers/invite', [CheckersController::class, 'sendInvitation']);
    Route::post('checkers/invite/{id}/accept', [CheckersController::class, 'acceptInvitation']);
    Route::post('checkers/room/create', [CheckersController::class, 'createRoom']);
    Route::post('checkers/room/join', [CheckersController::class, 'joinRoom']);
    Route::get('checkers/session/{id}', [CheckersController::class, 'getSession']);
    Route::post('checkers/session/{id}/move', [CheckersController::class, 'makeMove']);
    Route::post('checkers/session/{id}/leave', [CheckersController::class, 'leaveGame']);
    Route::get('checkers/session/{id}/chat', [CheckersController::class, 'getChat']);
    Route::post('checkers/session/{id}/chat', [CheckersController::class, 'sendChat']);
});
*/



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
// VIDEO TRANSFER ROUTES
// ========================================
use App\Http\Controllers\ApiVideoTransferController;

// Public routes (can be accessed without authentication if needed)
Route::get('video-transfers', [ApiVideoTransferController::class, 'index']);
Route::get('video-transfers/{id}', [ApiVideoTransferController::class, 'show']);
Route::get('video-transfers/{id}/stream-url', [ApiVideoTransferController::class, 'getStreamUrl']);

// Protected routes (require authentication)
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('video-transfers/{id}/retry', [ApiVideoTransferController::class, 'retry']);
});

// ========================================
// CATCH-ALL DYNAMIC ROUTES (MUST BE LAST!)
// ========================================
// These routes should be at the END to avoid intercepting specific routes above
Route::post('api/{model}', [ApiController::class, 'my_update']);
Route::get('api/{model}', [ApiController::class, 'my_list']);
