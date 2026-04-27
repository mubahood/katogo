<?php

$projectRoot = '/Applications/MAMP/htdocs/katogo';
$inventoryPath = $projectRoot . '/storage/api-docs/route-inventory.json';
$data = json_decode(file_get_contents($inventoryPath), true);

if (!is_array($data)) {
    fwrite(STDERR, "Invalid route inventory JSON\n");
    exit(1);
}

$skipUris = ['api/documentation', 'api/oauth2-callback'];
$alreadyAnnotated = [
    'POST /api/auth/login',
    'POST /api/auth/google',
    'POST /api/auth/register',
    'POST /api/auth/password-reset',
    'POST /api/auth/request-password-reset-code',
];

// ─── Lookup: endpoint key → human-readable summary ───────────────────────────
$summaryMap = [
    // Account
    'GET /api/account/dashboard'           => 'Get user account dashboard',
    'GET /api/account/likes'               => 'List movies liked by the user',
    'POST /api/account/likes/toggle'       => 'Toggle like on a movie',
    'GET /api/account/watch-history'       => 'Get user watch history',
    'GET /api/account/watchlist'           => 'List user watchlist',
    'POST /api/account/watchlist/add'      => 'Add a movie to the watchlist',
    'DELETE /api/account/watchlist/{movie_id}' => 'Remove a movie from the watchlist',
    'GET /api/account/wishlist'            => 'List user wishlist',
    'POST /api/account/wishlist/add'       => 'Add a movie to the wishlist',
    'DELETE /api/account/wishlist/{movie_id}'  => 'Remove a movie from the wishlist',
    // Chat
    'GET /api/chat-messages'               => 'List chat messages',
    'POST /api/chat-messages'              => 'Send a chat message',
    'DELETE /api/chat-messages/{id}'       => 'Delete a chat message',
    // Movies
    'GET /api/random-movie'                => 'Get a random movie',
    'GET /api/movies'                      => 'List all movies',
    'GET /api/movies/{id}'                 => 'Get movie details by ID',
    'GET /api/movie/{id}'                  => 'Get movie details (alias)',
    'GET /api/video-transfers'             => 'List video transfer tasks',
    'POST /api/video-playback-failures'    => 'Report a video playback failure',
    // Watch History
    'POST /api/video-progress'             => 'Save video playback progress',
    'GET /api/video-progress/{movie_model_id}' => 'Get saved progress for a movie',
    'GET /api/watch-history'               => 'Get full watch history (v1)',
    'DELETE /api/watch-history/{id}'       => 'Remove an entry from watch history',
    // Subscription
    'GET /api/subscription-plans'          => 'List all available subscription plans',
    'GET /api/subscription-plans/{id}'     => 'Get details of a subscription plan',
    'POST /api/subscriptions/{id}/initiate-payment' => 'Initiate a new subscription payment',
    'POST /api/subscriptions/gateway/init' => 'Initialise a payment gateway session',
    'GET /api/subscriptions/status'        => 'Get current subscription status',
    'GET /api/subscriptions/history'       => 'Get subscription payment history',
    'POST /api/subscriptions/verify'       => 'Verify a completed payment',
    'POST /api/subscriptions/renew'        => 'Renew an existing subscription',
    'POST /api/subscriptions/cancel'       => 'Cancel an active subscription',
    'GET /api/subscriptions/payment-status/{trackingId}' => 'Check payment status by tracking ID',
    'POST /api/subscriptions/flutterwave/webhook' => 'Receive Flutterwave payment webhook',
    'POST /api/subscriptions/pesapal/webhook'     => 'Receive Pesapal payment webhook',
    // V2 Subscriptions
    'GET /api/v2/subscriptions/plans'         => 'List subscription plans (v2)',
    'POST /api/v2/subscriptions/initiate'     => 'Initiate subscription payment (v2)',
    'GET /api/v2/subscriptions/status'        => 'Get subscription status (v2)',
    'POST /api/v2/subscriptions/verify'       => 'Verify payment (v2)',
    'POST /api/v2/subscriptions/cancel'       => 'Cancel subscription (v2)',
    // Moderation
    'POST /api/moderation/filter-content'     => 'Filter content for policy violations',
    'POST /api/moderation/report'             => 'Report content for moderation',
    'POST /api/moderation/report-content'     => 'Report content for moderation review',
    'POST /api/moderation/block-user'         => 'Block another user',
    'POST /api/moderation/unblock-user'       => 'Unblock a previously blocked user',
    'GET /api/moderation/blocked-users'       => 'List users blocked by the current user',
    'GET /api/moderation/my-reports'          => 'List content reports filed by the current user',
    'GET /api/moderation/user-reports'        => 'List all user-filed reports (admin)',
    'POST /api/moderation/update-legal-consent' => 'Update legal consent preferences',
    'POST /api/moderation/legal-consent'      => 'Submit legal consent',
    'GET /api/moderation/legal-consent-status' => 'Get current legal consent status',
    'GET /api/moderation/dashboard'           => 'Get moderation admin dashboard data',
    // V2 Movies
    'GET /api/v2/movies'                      => 'List movies (v2)',
    'GET /api/v2/movies/{id}'                 => 'Get movie details (v2)',
    'GET /api/v2/series'                      => 'List series (v2)',
    'GET /api/v2/series/{id}'                 => 'Get series details (v2)',
    // V2 Search
    'GET /api/v2/search'                      => 'Search for movies and series (v2)',
    'GET /api/v2/search/suggestions'          => 'Get search suggestions (v2)',
    // V2 Blog
    'GET /api/v2/blog'                        => 'List blog posts (v2)',
    'GET /api/v2/blog/{id}'                   => 'Get blog post details (v2)',
    // V2 Streaming
    'GET /api/v2/streaming/{id}'              => 'Get streaming URL for a movie (v2)',
    'POST /api/v2/streaming/progress'         => 'Save streaming progress (v2)',
    // V2 Downloads
    'POST /api/v2/downloads/record'           => 'Record a movie download (v2)',
    'GET /api/v2/downloads/stats'             => 'Get download statistics (v2)',
    // V2 SafeMode
    'POST /api/v2/safemode/track'             => 'Track a safemode video interaction',
    'POST /api/v2/safemode/progress'          => 'Save safemode video progress',
    'GET /api/v2/safemode/history'            => 'Get safemode view history',
    'GET /api/v2/safemode/progress/{external_video_id}' => 'Get saved progress for a safemode video',
    // V2 Trivia
    'GET /api/v2/trivia'                      => 'List trivia questions (v2)',
    'POST /api/v2/trivia/submit'              => 'Submit a trivia answer (v2)',
    // V2 Game Stats
    'POST /api/v2/game-stats/sync'            => 'Sync bulk game statistics (v2)',
    'GET /api/v2/game-stats'                  => 'Get game statistics leaderboard (v2)',
    // V2 Manifest
    'GET /api/v2/manifest'                    => 'Get app content manifest (v2)',
    // Diagnostics
    'GET /api/run-migration'                  => '[Internal] Run pending database migrations',
    'POST /api/run-migration'                 => '[Internal] Run pending database migrations',
];

// ─── Lookup: endpoint key → request body schema $ref ─────────────────────────
$requestSchemaMap = [
    'POST /api/account/likes/toggle'          => 'LikeToggleRequest',
    'POST /api/account/watchlist/add'         => 'WatchlistAddRequest',
    'POST /api/account/wishlist/add'          => 'WishlistAddRequest',
    'POST /api/video-progress'                => 'VideoProgressRequest',
    'POST /api/video-playback-failures'       => 'VideoProgressRequest',
    'POST /api/subscriptions/{id}/initiate-payment' => 'InitPaymentRequest',
    'POST /api/subscriptions/gateway/init'    => 'InitGatewayRequest',
    'POST /api/subscriptions/verify'          => 'VerifyPaymentRequest',
    'POST /api/subscriptions/renew'           => 'VerifyPaymentRequest',
    'POST /api/subscriptions/cancel'          => 'CancelSubscriptionRequest',
    'POST /api/v2/subscriptions/initiate'     => 'InitPaymentRequest',
    'POST /api/v2/subscriptions/verify'       => 'VerifyPaymentRequest',
    'POST /api/v2/subscriptions/cancel'       => 'CancelSubscriptionRequest',
    'POST /api/moderation/filter-content'     => 'ReportContentRequest',
    'POST /api/moderation/report'             => 'ReportContentRequest',
    'POST /api/moderation/report-content'     => 'ReportContentRequest',
    'POST /api/moderation/block-user'         => 'BlockUserRequest',
    'POST /api/moderation/unblock-user'       => 'UnblockUserRequest',
    'POST /api/moderation/update-legal-consent' => 'LegalConsentRequest',
    'POST /api/moderation/legal-consent'      => 'LegalConsentRequest',
    'POST /api/v2/safemode/track'             => 'SafeModeTrackRequest',
    'POST /api/v2/safemode/progress'          => 'SafeModeProgressRequest',
    'POST /api/v2/game-stats/sync'            => 'GameStatsBulkRequest',
    'POST /api/v2/downloads/record'           => 'DownloadRequest',
];

// ─── Lookup: endpoint key → description ──────────────────────────────────────
$descriptionMap = [
    'POST /api/account/likes/toggle'       => 'Adds or removes a like for the specified movie for the authenticated user.',
    'POST /api/account/watchlist/add'      => 'Adds the specified movie to the authenticated user\'s watchlist.',
    'DELETE /api/account/watchlist/{movie_id}' => 'Removes the specified movie from the authenticated user\'s watchlist.',
    'POST /api/account/wishlist/add'       => 'Adds the specified movie to the authenticated user\'s wishlist.',
    'POST /api/video-progress'             => 'Saves the current playback position for a movie so the user can resume later.',
    'POST /api/subscriptions/{id}/initiate-payment' => 'Initiates a new subscription payment. Returns a payment URL or reference to complete checkout.',
    'POST /api/subscriptions/gateway/init' => 'Initialises a payment gateway session for the selected gateway.',
    'POST /api/subscriptions/verify'       => 'Verifies a completed payment and activates the subscription if confirmed.',
    'POST /api/subscriptions/cancel'       => 'Cancels an active subscription. The subscription remains active until its expiry date.',
    'POST /api/subscriptions/flutterwave/webhook' => 'Webhook endpoint called by Flutterwave after a payment event. Must be publicly accessible.',
    'POST /api/subscriptions/pesapal/webhook'     => 'Webhook endpoint called by Pesapal after a payment event. Must be publicly accessible.',
    'POST /api/moderation/block-user'      => 'Blocks another user to prevent them from interacting with the current user.',
    'POST /api/moderation/report-content'  => 'Submits a content report for moderator review.',
    'POST /api/moderation/update-legal-consent' => 'Updates the authenticated user\'s consent preferences for terms, privacy policy, and marketing.',
    'POST /api/v2/safemode/track'          => 'Records a user interaction (view, play, like, or add to list) for a safemode video.',
    'POST /api/v2/safemode/progress'       => 'Saves the playback progress for a safemode video.',
    'POST /api/v2/game-stats/sync'         => 'Submits an array of game statistics (up to 10 game types) in a single request.',
    'POST /api/v2/downloads/record'        => 'Records that the user has downloaded a movie, tracking download type.',
];

// ─── Lookup: endpoint key → rich 200 response schema ─────────────────────────
$responseSchemaMap = [
    'GET /api/account/dashboard'           => 'UserResource',
    'GET /api/account/likes'               => 'MovieResource',
    'GET /api/account/watch-history'       => 'VideoProgressResource',
    'GET /api/account/watchlist'           => 'MovieResource',
    'GET /api/account/wishlist'            => 'MovieResource',
    'GET /api/movies'                      => 'MovieResource',
    'GET /api/movies/{id}'                 => 'MovieResource',
    'GET /api/movie/{id}'                  => 'MovieResource',
    'GET /api/random-movie'                => 'MovieResource',
    'GET /api/subscription-plans'          => 'SubscriptionPlanResource',
    'GET /api/subscription-plans/{id}'     => 'SubscriptionPlanResource',
    'GET /api/subscriptions/status'        => 'SubscriptionResource',
    'GET /api/subscriptions/history'       => 'SubscriptionResource',
    'GET /api/v2/subscriptions/plans'      => 'SubscriptionPlanResource',
    'GET /api/v2/subscriptions/status'     => 'SubscriptionResource',
    'GET /api/v2/game-stats'              => 'ApiResponse',
    'GET /api/v2/downloads/stats'         => 'ApiResponse',
    'GET /api/v2/movies'                   => 'MovieResource',
    'GET /api/v2/movies/{id}'              => 'MovieResource',
    'GET /api/v2/series'                   => 'MovieResource',
    'GET /api/v2/series/{id}'              => 'MovieResource',
    'GET /api/video-progress/{movie_model_id}' => 'VideoProgressResource',
];

$tagFromUri = static function (string $uri): string {
    if (str_starts_with($uri, 'api/v2/manifest')) return 'V2 Manifest';
    if (str_starts_with($uri, 'api/v2/movies') || str_starts_with($uri, 'api/v2/series')) return 'V2 Movies';
    if (str_starts_with($uri, 'api/v2/search')) return 'V2 Search';
    if (str_starts_with($uri, 'api/v2/blog')) return 'V2 Blog';
    if (str_starts_with($uri, 'api/v2/streaming')) return 'V2 Streaming';
    if (str_starts_with($uri, 'api/v2/downloads')) return 'V2 Downloads';
    if (str_starts_with($uri, 'api/v2/safemode')) return 'V2 SafeMode';
    if (str_starts_with($uri, 'api/v2/trivia')) return 'V2 Trivia';
    if (str_starts_with($uri, 'api/v2/game-stats')) return 'V2 Game Stats';
    if (str_starts_with($uri, 'api/v2/subscriptions')) return 'Subscription';
    if (str_starts_with($uri, 'api/auth/')) return 'Auth';
    if (str_starts_with($uri, 'api/subscription') || str_starts_with($uri, 'api/subscriptions/')) return 'Subscription';
    if (str_starts_with($uri, 'api/moderation/')) return 'Moderation';
    if (str_starts_with($uri, 'api/account/')) return 'Account';
    if (str_starts_with($uri, 'api/chat-')) return 'Account';
    if (str_starts_with($uri, 'api/test-') || str_starts_with($uri, 'api/run-migration') || str_starts_with($uri, 'api/debug-') || str_starts_with($uri, 'api/fix-')) return 'Diagnostics/Test';
    if (str_starts_with($uri, 'api/video-progress') || str_starts_with($uri, 'api/watch-history')) return 'Watch History';
    if (str_starts_with($uri, 'api/random-movie') || str_starts_with($uri, 'api/movies') || str_starts_with($uri, 'api/movie/')) return 'Movies';
    if (str_starts_with($uri, 'api/video-transfers') || str_starts_with($uri, 'api/video-playback-failures')) return 'Movies';
    return 'Diagnostics/Test';
};

$authFromMiddleware = static function (array $mw): string {
    $text = implode('|', $mw);
    if (str_contains($text, 'JwtMiddleware')) return 'jwt';
    if (str_contains($text, 'admin') || str_contains($text, 'NoReferrerPolicy')) return 'admin';
    if (str_contains($text, 'Authenticate:sanctum')) return 'internal/test';
    if (str_contains($text, 'web') && !str_contains($text, 'api')) return 'internal/test';
    return 'public';
};

// Convert a controller method name like "get_liked_movies" → "Get liked movies"
$humanSummary = static function (string $method, string $path, string $action): string {
    // Try to extract method name from action (e.g. "App\Http\Controllers\Foo@myMethod")
    $parts = explode('@', $action);
    $methodName = end($parts);
    if ($methodName && $methodName !== 'Closure' && !str_starts_with($methodName, '{')) {
        $readable = str_replace(['_', '-'], ' ', $methodName);
        $readable = preg_replace('/([a-z])([A-Z])/', '$1 $2', $readable); // camelCase → words
        return ucfirst(strtolower($readable));
    }
    // Fallback: capitalise the HTTP method + last path segment
    $segments = array_filter(explode('/', trim($path, '/')));
    $last = end($segments);
    $last = str_replace(['{', '}', '-', '_'], ['', '', ' ', ' '], $last);
    return ucfirst(strtolower($method)) . ' ' . trim($last);
};

$methodMap = ['GET' => 'Get', 'POST' => 'Post', 'PUT' => 'Put', 'PATCH' => 'Patch', 'DELETE' => 'Delete'];
$ops = [];

foreach ($data as $route) {
    $uri = $route['uri'] ?? '';
    if ($uri === '' || !str_starts_with($uri, 'api/') || in_array($uri, $skipUris, true)) {
        continue;
    }

    $methods = array_values(array_filter(explode('|', (string)($route['method'] ?? '')), static fn ($m) => $m !== 'HEAD' && $m !== ''));
    $middleware = $route['middleware'] ?? [];
    $auth = $authFromMiddleware($middleware);
    $tag = $tagFromUri($uri);
    $action = $route['action'] ?? 'Closure';
    $path = '/' . ltrim($uri, '/');

    preg_match_all('/\{([^}]+)\}/', $path, $matches);
    $params = $matches[1] ?? [];

    foreach ($methods as $method) {
        if (!isset($methodMap[$method])) {
            continue;
        }

        $key = strtoupper($method) . ' ' . $path;
        if (in_array($key, $alreadyAnnotated, true)) {
            continue;
        }

        // Resolve summary, description, request schema, response schema
        $summary     = $summaryMap[$key]     ?? $humanSummary($method, $path, $action);
        $description = $descriptionMap[$key] ?? 'Controller: ' . addslashes($action);
        $reqSchema   = $requestSchemaMap[$key]  ?? null;
        $resSchema   = $responseSchemaMap[$key] ?? null;

        $op = [];
        $op[] = ' * @OA\\' . $methodMap[$method] . '(';
        $op[] = ' *   path="' . $path . '",';
        $op[] = ' *   operationId="auto' . preg_replace('/[^A-Za-z0-9]+/', '', ucfirst(strtolower($method)) . '_' . trim(str_replace(['/', '{', '}', '-', '.'], '_', $path), '_')) . '",';
        $op[] = ' *   tags={"' . addslashes($tag) . '"},';
        $op[] = ' *   summary="' . addslashes($summary) . '",';
        $op[] = ' *   description="' . addslashes($description) . '",';

        foreach ($params as $pRaw) {
            $required = !str_ends_with($pRaw, '?');
            $p = rtrim($pRaw, '?');
            $type = in_array($p, ['id', 'movie_id', 'movie_model_id', 'plan_id', 'subscription_id'], true) ? 'integer' : 'string';
            $op[] = ' *   @OA\\Parameter(name="' . $p . '", in="path", required=' . ($required ? 'true' : 'false') . ', description="' . ucfirst(str_replace('_', ' ', $p)) . '", @OA\\Schema(type="' . $type . '", example="1")),';
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            if ($reqSchema) {
                $op[] = ' *   @OA\\RequestBody(required=true, @OA\\JsonContent(ref="#/components/schemas/' . $reqSchema . '")),';
            } else {
                $op[] = ' *   @OA\\RequestBody(required=false, @OA\\JsonContent(type="object")),';
            }
        }

        if ($auth === 'jwt') {
            $op[] = ' *   security={{"bearerAuth":{}}},';
        }

        if ($resSchema) {
            $op[] = ' *   @OA\\Response(response=200, description="Successful response", @OA\\JsonContent(ref="#/components/schemas/' . $resSchema . '")),';
        } else {
            $op[] = ' *   @OA\\Response(response=200, description="Successful response", @OA\\JsonContent(ref="#/components/schemas/ApiResponse")),';
        }
        $op[] = ' *   @OA\\Response(response=401, description="Unauthorized", @OA\\JsonContent(ref="#/components/schemas/ErrorResponse")),';
        $op[] = ' *   @OA\\Response(response=403, description="Forbidden", @OA\\JsonContent(ref="#/components/schemas/ErrorResponse")),';
        $op[] = ' *   @OA\\Response(response=404, description="Not Found", @OA\\JsonContent(ref="#/components/schemas/ErrorResponse")),';
        $op[] = ' *   @OA\\Response(response=422, description="Validation error", @OA\\JsonContent(ref="#/components/schemas/ErrorResponse")),';
        $op[] = ' *   @OA\\Response(response=500, description="Server error", @OA\\JsonContent(ref="#/components/schemas/ErrorResponse"))';
        $op[] = ' * )';

        $ops[] = implode(PHP_EOL, $op);
    }
}

$out = [];
$out[] = '<?php';
$out[] = '';
$out[] = 'namespace App\\OpenApi;';
$out[] = '';
$out[] = '/**';
$out[] = implode(PHP_EOL, $ops);
$out[] = ' */';
$out[] = 'class GeneratedApiEndpoints';
$out[] = '{';
$out[] = '}';

file_put_contents($projectRoot . '/app/OpenApi/GeneratedApiEndpoints.php', implode(PHP_EOL, $out) . PHP_EOL);
echo 'Generated ' . count($ops) . " operations" . PHP_EOL;

