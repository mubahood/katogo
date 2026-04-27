# Swagger Endpoint Inventory

Generated from `storage/api-docs/route-inventory.json`. All listed endpoints are documented in OpenAPI.

## Normalized Endpoints

| # | Method | Path | Controller@Action | Auth | Tag | Status | Example |
|---|---|---|---|---|---|---|---|
| 1 | GET | /api/account/dashboard | App\Http\Controllers\DynamicCrudController@get_account_dashboard | jwt | Account | Done | Yes |
| 2 | GET | /api/account/likes | App\Http\Controllers\DynamicCrudController@get_liked_movies | jwt | Account | Done | Yes |
| 3 | POST | /api/account/likes/toggle | App\Http\Controllers\DynamicCrudController@toggle_movie_like | jwt | Account | Done | Yes |
| 4 | GET | /api/account/watch-history | App\Http\Controllers\DynamicCrudController@get_watch_history | jwt | Account | Done | Yes |
| 5 | GET | /api/account/watchlist | App\Http\Controllers\DynamicCrudController@get_watchlist | jwt | Account | Done | Yes |
| 6 | POST | /api/account/watchlist/add | App\Http\Controllers\DynamicCrudController@add_to_watchlist | jwt | Account | Done | Yes |
| 7 | DELETE | /api/account/watchlist/{movie_id} | App\Http\Controllers\DynamicCrudController@remove_from_watchlist | jwt | Account | Done | Yes |
| 8 | GET | /api/account/wishlist | App\Http\Controllers\DynamicCrudController@get_wishlisted_movies | jwt | Account | Done | Yes |
| 9 | POST | /api/account/wishlist/toggle | App\Http\Controllers\DynamicCrudController@toggle_movie_wishlist | jwt | Account | Done | Yes |
| 10 | POST | /api/api/{model} | App\Http\Controllers\ApiController@my_update | public | Diagnostics/Test | Done | Yes |
| 11 | GET | /api/api/{model} | App\Http\Controllers\ApiController@my_list | public | Diagnostics/Test | Done | Yes |
| 12 | POST | /api/auth/google | App\Http\Controllers\ApiController@googleAuth | public | Auth | Done | Yes |
| 13 | POST | /api/auth/login | App\Http\Controllers\ApiController@login | public | Auth | Done | Yes |
| 14 | POST | /api/auth/password-reset | App\Http\Controllers\ApiController@password_reset | public | Auth | Done | Yes |
| 15 | POST | /api/auth/register | App\Http\Controllers\ApiController@register | public | Auth | Done | Yes |
| 16 | POST | /api/auth/request-password-reset-code | App\Http\Controllers\ApiController@request_password_reset_code | public | Auth | Done | Yes |
| 17 | POST | /api/chat-delete | App\Http\Controllers\ApiController@chat_delete | jwt | Account | Done | Yes |
| 18 | GET | /api/chat-heads | App\Http\Controllers\ApiController@chat_heads | jwt | Account | Done | Yes |
| 19 | POST | /api/chat-mark-as-read | App\Http\Controllers\ApiController@chat_mark_as_read | jwt | Account | Done | Yes |
| 20 | GET | /api/chat-messages | App\Http\Controllers\ApiController@chat_messages | jwt | Account | Done | Yes |
| 21 | POST | /api/chat-send | App\Http\Controllers\ApiController@chat_send | jwt | Account | Done | Yes |
| 22 | POST | /api/chat-start | App\Http\Controllers\ApiController@chat_start | jwt | Account | Done | Yes |
| 23 | GET | /api/checkers/{any?} | Closure | jwt | Diagnostics/Test | Done | Yes |
| 24 | POST | /api/checkers/{any?} | Closure | jwt | Diagnostics/Test | Done | Yes |
| 25 | GET | /api/coins/{any?} | Closure | jwt | Diagnostics/Test | Done | Yes |
| 26 | POST | /api/coins/{any?} | Closure | jwt | Diagnostics/Test | Done | Yes |
| 27 | POST | /api/consultation-card-payment | App\Http\Controllers\DynamicCrudController@consultation_card_payment | jwt | Diagnostics/Test | Done | Yes |
| 28 | GET | /api/debug-chat/{id} | App\Http\Controllers\ApiController@debug_chat | jwt | Diagnostics/Test | Done | Yes |
| 29 | POST | /api/disable-account | App\Http\Controllers\ApiController@disable_account | jwt | Diagnostics/Test | Done | Yes |
| 30 | POST | /api/dynamic-delete | App\Http\Controllers\DynamicCrudController@delete | jwt | Diagnostics/Test | Done | Yes |
| 31 | GET | /api/dynamic-list | App\Http\Controllers\DynamicCrudController@index | jwt | Diagnostics/Test | Done | Yes |
| 32 | POST | /api/dynamic-save | App\Http\Controllers\DynamicCrudController@save | jwt | Diagnostics/Test | Done | Yes |
| 33 | POST | /api/file-uploading | App\Http\Controllers\ApiController@file_uploading | public | Diagnostics/Test | Done | Yes |
| 34 | POST | /api/fix-series-type-single | Closure | admin | Diagnostics/Test | Done | Yes |
| 35 | GET | /api/game/{any?} | Closure | jwt | Diagnostics/Test | Done | Yes |
| 36 | POST | /api/game/{any?} | Closure | jwt | Diagnostics/Test | Done | Yes |
| 37 | GET | /api/ludo/{any?} | Closure | jwt | Diagnostics/Test | Done | Yes |
| 38 | POST | /api/ludo/{any?} | Closure | jwt | Diagnostics/Test | Done | Yes |
| 39 | GET | /api/manifest | App\Http\Controllers\ApiController@manifest | jwt | Diagnostics/Test | Done | Yes |
| 40 | GET | /api/me | App\Http\Controllers\ApiController@me | jwt | Diagnostics/Test | Done | Yes |
| 41 | POST | /api/moderation/block-user | App\Http\Controllers\ModerationController@blockUser | jwt | Moderation | Done | Yes |
| 42 | GET | /api/moderation/blocked-users | App\Http\Controllers\ModerationController@getBlockedUsers | jwt | Moderation | Done | Yes |
| 43 | GET | /api/moderation/dashboard | App\Http\Controllers\ModerationController@getModerationDashboard | jwt | Moderation | Done | Yes |
| 44 | POST | /api/moderation/filter-content | App\Http\Controllers\ModerationController@filterContent | public | Moderation | Done | Yes |
| 45 | POST | /api/moderation/legal-consent | App\Http\Controllers\ModerationController@updateLegalConsent | jwt | Moderation | Done | Yes |
| 46 | GET | /api/moderation/legal-consent-status | App\Http\Controllers\ModerationController@getLegalConsentStatus | jwt | Moderation | Done | Yes |
| 47 | GET | /api/moderation/my-reports | App\Http\Controllers\ModerationController@getUserReports | jwt | Moderation | Done | Yes |
| 48 | POST | /api/moderation/report | App\Http\Controllers\ModerationController@reportContent | jwt | Moderation | Done | Yes |
| 49 | POST | /api/moderation/report-content | App\Http\Controllers\ModerationController@reportContent | jwt | Moderation | Done | Yes |
| 50 | POST | /api/moderation/stock-item | App\Http\Controllers\ModerationController@moderateStockItem | jwt | Moderation | Done | Yes |
| 51 | POST | /api/moderation/stock-sub-category | App\Http\Controllers\ModerationController@moderateStockSubCategory | jwt | Moderation | Done | Yes |
| 52 | POST | /api/moderation/unblock-user | App\Http\Controllers\ModerationController@unblockUser | jwt | Moderation | Done | Yes |
| 53 | POST | /api/moderation/update-legal-consent | App\Http\Controllers\ModerationController@updateLegalConsent | jwt | Moderation | Done | Yes |
| 54 | GET | /api/moderation/user-reports | App\Http\Controllers\ModerationController@getUserReports | jwt | Moderation | Done | Yes |
| 55 | GET | /api/movie/{id} | App\Http\Controllers\DynamicCrudController@movie | jwt | Movies | Done | Yes |
| 56 | GET | /api/movies | App\Http\Controllers\DynamicCrudController@movies | jwt | Movies | Done | Yes |
| 57 | GET | /api/movies/{id} | App\Http\Controllers\DynamicCrudController@movie | jwt | Movies | Done | Yes |
| 58 | POST | /api/post-media-upload | App\Http\Controllers\ApiController@upload_media | jwt | Diagnostics/Test | Done | Yes |
| 59 | POST | /api/product-create | App\Http\Controllers\ApiController@product_create | jwt | Diagnostics/Test | Done | Yes |
| 60 | GET | /api/products-1 | App\Http\Controllers\ApiController@products_1 | public | Diagnostics/Test | Done | Yes |
| 61 | POST | /api/products-delete | App\Http\Controllers\ApiController@products_delete | jwt | Diagnostics/Test | Done | Yes |
| 62 | GET | /api/random-movie | App\Http\Controllers\DynamicCrudController@random_movie | public | Movies | Done | Yes |
| 63 | POST | /api/run-migration | App\Http\Controllers\MigrationController@runMigration | public | Diagnostics/Test | Done | Yes |
| 64 | POST | /api/save-view-progress | App\Http\Controllers\ApiController@save_view_progress | public | Diagnostics/Test | Done | Yes |
| 65 | GET | /api/stock-items | Closure | public | Diagnostics/Test | Done | Yes |
| 66 | GET | /api/stock-sub-categories | Closure | public | Diagnostics/Test | Done | Yes |
| 67 | GET | /api/subscription-plans | App\Http\Controllers\SubscriptionApiController@listPlans | public | Subscription | Done | Yes |
| 68 | POST | /api/subscriptions/check-status | App\Http\Controllers\SubscriptionApiController@checkStatus | jwt | Subscription | Done | Yes |
| 69 | POST | /api/subscriptions/create | App\Http\Controllers\SubscriptionApiController@create | jwt | Subscription | Done | Yes |
| 70 | GET | /api/subscriptions/default-gateway | App\Http\Controllers\SubscriptionApiController@getDefaultGateway | jwt | Subscription | Done | Yes |
| 71 | POST | /api/subscriptions/default-gateway | App\Http\Controllers\SubscriptionApiController@setDefaultGateway | jwt | Subscription | Done | Yes |
| 72 | GET | /api/subscriptions/flutterwave/callback | App\Http\Controllers\SubscriptionApiController@flutterwaveCallback | public | Subscription | Done | Yes |
| 73 | POST | /api/subscriptions/flutterwave/webhook | App\Http\Controllers\SubscriptionApiController@flutterwaveWebhook | public | Subscription | Done | Yes |
| 74 | GET | /api/subscriptions/history | App\Http\Controllers\SubscriptionApiController@history | jwt | Subscription | Done | Yes |
| 75 | GET | /api/subscriptions/my-subscription | App\Http\Controllers\SubscriptionApiController@mySubscription | jwt | Subscription | Done | Yes |
| 76 | GET | /api/subscriptions/payment-gateways | App\Http\Controllers\SubscriptionApiController@paymentGateways | public | Subscription | Done | Yes |
| 77 | GET | /api/subscriptions/payment-status/{trackingId} | App\Http\Controllers\SubscriptionApiController@getPaymentStatus | public | Subscription | Done | Yes |
| 78 | GET | /api/subscriptions/pending | App\Http\Controllers\SubscriptionApiController@getPending | jwt | Subscription | Done | Yes |
| 79 | GET | /api/subscriptions/pesapal/callback | App\Http\Controllers\SubscriptionApiController@pesapalCallback | public | Subscription | Done | Yes |
| 80 | POST | /api/subscriptions/pesapal/callback | App\Http\Controllers\SubscriptionApiController@pesapalCallback | public | Subscription | Done | Yes |
| 81 | POST | /api/subscriptions/pesapal/ipn | App\Http\Controllers\SubscriptionApiController@pesapalIpn | public | Subscription | Done | Yes |
| 82 | GET | /api/subscriptions/pesapal/test | App\Http\Controllers\SubscriptionApiController@pesapalTest | public | Subscription | Done | Yes |
| 83 | POST | /api/subscriptions/retry-payment | App\Http\Controllers\SubscriptionApiController@retryPayment | jwt | Subscription | Done | Yes |
| 84 | POST | /api/subscriptions/{id}/action | App\Admin\Controllers\SubscriptionController@adminAction | admin | Subscription | Done | Yes |
| 85 | POST | /api/subscriptions/{id}/cancel | App\Http\Controllers\SubscriptionApiController@cancelPending | jwt | Subscription | Done | Yes |
| 86 | POST | /api/subscriptions/{id}/check-payment | App\Http\Controllers\SubscriptionApiController@checkPendingPayment | jwt | Subscription | Done | Yes |
| 87 | POST | /api/subscriptions/{id}/initiate-payment | App\Http\Controllers\SubscriptionApiController@initiatePayment | jwt | Subscription | Done | Yes |
| 88 | GET | /api/test-auto-assignment/{user_id?} | App\Http\Controllers\FreeTrialTestController@testAutoAssignment | public | Diagnostics/Test | Done | Yes |
| 89 | DELETE | /api/test-free-trial-cleanup/{user_id?} | App\Http\Controllers\FreeTrialTestController@cleanupTestData | public | Diagnostics/Test | Done | Yes |
| 90 | GET | /api/test-free-trial-plan | App\Http\Controllers\FreeTrialTestController@getFreeTrialPlan | public | Diagnostics/Test | Done | Yes |
| 91 | GET | /api/test-free-trial-stats | App\Http\Controllers\FreeTrialTestController@getFreeTrialStats | public | Diagnostics/Test | Done | Yes |
| 92 | GET | /api/test-free-trial/{user_id?} | App\Http\Controllers\FreeTrialTestController@testFreeTrial | public | Diagnostics/Test | Done | Yes |
| 93 | POST | /api/track-event | App\Http\Controllers\PageVisitController@event | internal/test | Diagnostics/Test | Done | Yes |
| 94 | POST | /api/track-visit | App\Http\Controllers\PageVisitController@store | internal/test | Diagnostics/Test | Done | Yes |
| 95 | GET | /api/user | Closure | internal/test | Diagnostics/Test | Done | Yes |
| 96 | GET | /api/users | Closure | admin | Diagnostics/Test | Done | Yes |
| 97 | GET | /api/users-list | App\Http\Controllers\DynamicCrudController@users_list | jwt | Diagnostics/Test | Done | Yes |
| 98 | GET | /api/v2/blog | App\Http\Controllers\Api\V2\BlogController@index | jwt | V2 Blog | Done | Yes |
| 99 | POST | /api/v2/blog/comment/{id}/like | App\Http\Controllers\Api\V2\BlogController@toggleCommentLike | jwt | V2 Blog | Done | Yes |
| 100 | POST | /api/v2/blog/comment/{id}/report | App\Http\Controllers\Api\V2\BlogController@reportComment | jwt | V2 Blog | Done | Yes |
| 101 | GET | /api/v2/blog/marquee | App\Http\Controllers\Api\V2\BlogController@marquee | jwt | V2 Blog | Done | Yes |
| 102 | GET | /api/v2/blog/{id} | App\Http\Controllers\Api\V2\BlogController@show | jwt | V2 Blog | Done | Yes |
| 103 | POST | /api/v2/blog/{id}/comment | App\Http\Controllers\Api\V2\BlogController@addComment | jwt | V2 Blog | Done | Yes |
| 104 | POST | /api/v2/blog/{id}/like | App\Http\Controllers\Api\V2\BlogController@toggleLike | jwt | V2 Blog | Done | Yes |
| 105 | POST | /api/v2/downloads/record | App\Http\Controllers\Api\V2\DownloadController@record | jwt | V2 Downloads | Done | Yes |
| 106 | GET | /api/v2/downloads/stats | App\Http\Controllers\Api\V2\DownloadController@stats | jwt | V2 Downloads | Done | Yes |
| 107 | GET | /api/v2/game-stats | App\Http\Controllers\Api\V2\GameStatsController@index | jwt | V2 Game Stats | Done | Yes |
| 108 | POST | /api/v2/game-stats/sync | App\Http\Controllers\Api\V2\GameStatsController@sync | jwt | V2 Game Stats | Done | Yes |
| 109 | GET | /api/v2/manifest | App\Http\Controllers\Api\V2\ManifestController@index | jwt | V2 Manifest | Done | Yes |
| 110 | GET | /api/v2/movies | App\Http\Controllers\Api\V2\MovieController@index | jwt | V2 Movies | Done | Yes |
| 111 | GET | /api/v2/movies/search | App\Http\Controllers\Api\V2\MovieController@search | jwt | V2 Movies | Done | Yes |
| 112 | GET | /api/v2/movies/{id} | App\Http\Controllers\Api\V2\MovieController@show | jwt | V2 Movies | Done | Yes |
| 113 | POST | /api/v2/movies/{id}/fix | App\Http\Controllers\Api\V2\MovieController@fix | jwt | V2 Movies | Done | Yes |
| 114 | POST | /api/v2/movies/{id}/playback | App\Http\Controllers\Api\V2\MovieController@playback | jwt | V2 Movies | Done | Yes |
| 115 | GET | /api/v2/movies/{id}/related | App\Http\Controllers\Api\V2\MovieController@related | jwt | V2 Movies | Done | Yes |
| 116 | GET | /api/v2/safemode/history | App\Http\Controllers\Api\V2\SafeModeAnalyticsController@history | jwt | V2 SafeMode | Done | Yes |
| 117 | POST | /api/v2/safemode/progress | App\Http\Controllers\Api\V2\SafeModeAnalyticsController@saveProgress | jwt | V2 SafeMode | Done | Yes |
| 118 | GET | /api/v2/safemode/progress/{external_video_id} | App\Http\Controllers\Api\V2\SafeModeAnalyticsController@getProgress | jwt | V2 SafeMode | Done | Yes |
| 119 | POST | /api/v2/safemode/track | App\Http\Controllers\Api\V2\SafeModeAnalyticsController@track | jwt | V2 SafeMode | Done | Yes |
| 120 | GET | /api/v2/search/all | App\Http\Controllers\Api\V2\SearchController@searchAll | jwt | V2 Search | Done | Yes |
| 121 | GET | /api/v2/search/all/suggestions | App\Http\Controllers\Api\V2\SearchController@allSuggestions | jwt | V2 Search | Done | Yes |
| 122 | GET | /api/v2/search/all/trending | App\Http\Controllers\Api\V2\SearchController@allTrending | jwt | V2 Search | Done | Yes |
| 123 | GET | /api/v2/search/history | App\Http\Controllers\Api\V2\SearchController@history | jwt | V2 Search | Done | Yes |
| 124 | DELETE | /api/v2/search/history | App\Http\Controllers\Api\V2\SearchController@clearHistory | jwt | V2 Search | Done | Yes |
| 125 | DELETE | /api/v2/search/history/{id} | App\Http\Controllers\Api\V2\SearchController@deleteHistory | jwt | V2 Search | Done | Yes |
| 126 | GET | /api/v2/search/series | App\Http\Controllers\Api\V2\SearchController@searchSeries | jwt | V2 Search | Done | Yes |
| 127 | GET | /api/v2/search/suggestions | App\Http\Controllers\Api\V2\SearchController@suggestions | jwt | V2 Search | Done | Yes |
| 128 | GET | /api/v2/search/trending | App\Http\Controllers\Api\V2\SearchController@trending | jwt | V2 Search | Done | Yes |
| 129 | GET | /api/v2/series | App\Http\Controllers\Api\V2\MovieController@seriesIndex | jwt | V2 Movies | Done | Yes |
| 130 | GET | /api/v2/series/{id}/episodes | App\Http\Controllers\Api\V2\MovieController@episodes | jwt | V2 Movies | Done | Yes |
| 131 | GET | /api/v2/streaming/categories | App\Http\Controllers\Api\V2\StreamingController@categories | jwt | V2 Streaming | Done | Yes |
| 132 | GET | /api/v2/streaming/home | App\Http\Controllers\Api\V2\StreamingController@home | jwt | V2 Streaming | Done | Yes |
| 133 | GET | /api/v2/streaming/stations | App\Http\Controllers\Api\V2\StreamingController@index | jwt | V2 Streaming | Done | Yes |
| 134 | GET | /api/v2/streaming/stations/{id} | App\Http\Controllers\Api\V2\StreamingController@show | jwt | V2 Streaming | Done | Yes |
| 135 | POST | /api/v2/subscriptions/auto-fix | App\Http\Controllers\Api\V2\SubscriptionFixController@autoFix | jwt | Subscription | Done | Yes |
| 136 | GET | /api/v2/subscriptions/fixable | App\Http\Controllers\Api\V2\SubscriptionFixController@fixable | jwt | Subscription | Done | Yes |
| 137 | GET | /api/v2/subscriptions/{id}/diagnostic | App\Http\Controllers\Api\V2\SubscriptionFixController@diagnostic | jwt | Subscription | Done | Yes |
| 138 | POST | /api/v2/subscriptions/{id}/force-check | App\Http\Controllers\Api\V2\SubscriptionFixController@forceCheck | jwt | Subscription | Done | Yes |
| 139 | GET | /api/v2/trivia/questions | App\Http\Controllers\Api\V2\TriviaController@questions | jwt | V2 Trivia | Done | Yes |
| 140 | GET | /api/v2/trivia/stats | App\Http\Controllers\Api\V2\TriviaController@stats | jwt | V2 Trivia | Done | Yes |
| 141 | GET | /api/v2/trivia/version | App\Http\Controllers\Api\V2\TriviaController@version | jwt | V2 Trivia | Done | Yes |
| 142 | POST | /api/video-playback-failures | App\Http\Controllers\Api\VideoPlaybackFailureController@store | public | Movies | Done | Yes |
| 143 | POST | /api/video-progress | App\Http\Controllers\DynamicCrudController@save_video_progress | jwt | Watch History | Done | Yes |
| 144 | GET | /api/video-progress/{movie_id} | App\Http\Controllers\DynamicCrudController@get_video_progress | jwt | Watch History | Done | Yes |
| 145 | POST | /api/video-progress/{movie_id}/delete | App\Http\Controllers\DynamicCrudController@delete_video_progress | jwt | Watch History | Done | Yes |
| 146 | GET | /api/video-transfers | App\Http\Controllers\ApiVideoTransferController@index | public | Movies | Done | Yes |
| 147 | GET | /api/video-transfers/{id} | App\Http\Controllers\ApiVideoTransferController@show | public | Movies | Done | Yes |
| 148 | POST | /api/video-transfers/{id}/retry | App\Http\Controllers\ApiVideoTransferController@retry | jwt | Movies | Done | Yes |
| 149 | GET | /api/video-transfers/{id}/stream-url | App\Http\Controllers\ApiVideoTransferController@getStreamUrl | public | Movies | Done | Yes |
| 150 | GET | /api/watch-history | App\Http\Controllers\DynamicCrudController@get_watch_history | jwt | Watch History | Done | Yes |

## Alias Groups

Routes sharing the same method and controller action are listed as aliases/backward compatibility mappings.

### Alias Group 1

- Method: `GET`
- Action: `App\Http\Controllers\DynamicCrudController@get_watch_history`
- Paths:
  - `/api/account/watch-history`
  - `/api/watch-history`

### Alias Group 2

- Method: `GET`
- Action: `Closure`
- Paths:
  - `/api/checkers/{any?}`
  - `/api/coins/{any?}`
  - `/api/game/{any?}`
  - `/api/ludo/{any?}`
  - `/api/stock-items`
  - `/api/stock-sub-categories`
  - `/api/user`
  - `/api/users`

### Alias Group 3

- Method: `POST`
- Action: `Closure`
- Paths:
  - `/api/checkers/{any?}`
  - `/api/coins/{any?}`
  - `/api/fix-series-type-single`
  - `/api/game/{any?}`
  - `/api/ludo/{any?}`

### Alias Group 4

- Method: `POST`
- Action: `App\Http\Controllers\ModerationController@updateLegalConsent`
- Paths:
  - `/api/moderation/legal-consent`
  - `/api/moderation/update-legal-consent`

### Alias Group 5

- Method: `GET`
- Action: `App\Http\Controllers\ModerationController@getUserReports`
- Paths:
  - `/api/moderation/my-reports`
  - `/api/moderation/user-reports`

### Alias Group 6

- Method: `POST`
- Action: `App\Http\Controllers\ModerationController@reportContent`
- Paths:
  - `/api/moderation/report`
  - `/api/moderation/report-content`

### Alias Group 7

- Method: `GET`
- Action: `App\Http\Controllers\DynamicCrudController@movie`
- Paths:
  - `/api/movie/{id}`
  - `/api/movies/{id}`

