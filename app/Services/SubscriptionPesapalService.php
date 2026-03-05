<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Subscription Pesapal Service - BULLETPROOF IMPLEMENTATION
 * 
 * Handles all Pesapal payment integration for subscriptions.
 * Features:
 * - Config validation on construction (fail-fast)
 * - Automatic retry with exponential backoff on all API calls
 * - IPN ID caching to avoid re-registration every request
 * - Comprehensive error handling with clear error messages
 * - SSL fallback for environments with certificate issues
 * - Token refresh on 401 responses
 */
class SubscriptionPesapalService
{
    private $consumerKey;
    private $consumerSecret;
    private $baseUrl;
    private $ipnUrl;
    private $callbackUrl;
    private $appBaseUrl;

    /** @var int Maximum retry attempts for API calls */
    private const MAX_RETRIES = 3;

    /** @var int Base delay in milliseconds for exponential backoff */
    private const RETRY_DELAY_MS = 500;

    /** @var int Token cache duration in seconds (4.5 min, tokens expire in 5 min) */
    private const TOKEN_CACHE_SECONDS = 270;

    /** @var int IPN ID cache duration in seconds (24 hours) */
    private const IPN_CACHE_SECONDS = 86400;

    /** @var int cURL timeout in seconds */
    private const CURL_TIMEOUT = 30;

    /** @var int cURL connection timeout in seconds */
    private const CURL_CONNECT_TIMEOUT = 10;

    public function __construct()
    {
        // ===== CREDENTIAL VALIDATION (FAIL-FAST) =====
        $this->consumerKey = env('PESAPAL_CONSUMER_KEY');
        $this->consumerSecret = env('PESAPAL_CONSUMER_SECRET');
        $this->baseUrl = env('PESAPAL_PRODUCTION_URL', 'https://pay.pesapal.com/v3');

        if (empty($this->consumerKey) || empty($this->consumerSecret)) {
            $missing = [];
            if (empty($this->consumerKey)) $missing[] = 'PESAPAL_CONSUMER_KEY';
            if (empty($this->consumerSecret)) $missing[] = 'PESAPAL_CONSUMER_SECRET';
            Log::critical('Pesapal: Missing API credentials in .env', ['missing' => $missing]);
            throw new \RuntimeException('Pesapal payment system misconfigured: missing ' . implode(', ', $missing) . ' in .env');
        }

        if (empty($this->baseUrl) || !filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            Log::critical('Pesapal: Invalid base URL', ['base_url' => $this->baseUrl]);
            throw new \RuntimeException('Pesapal payment system misconfigured: invalid PESAPAL_PRODUCTION_URL');
        }

        // Use APP_PRODUCTION_URL for externally-reachable URLs (not APP_URL which may be localhost)
        $this->appBaseUrl = env('APP_PRODUCTION_URL', env('APP_URL', 'https://katogo.schooldynamics.ug'));

        if (empty($this->appBaseUrl) || !filter_var($this->appBaseUrl, FILTER_VALIDATE_URL)) {
            Log::critical('Pesapal: Invalid app base URL', ['app_base_url' => $this->appBaseUrl]);
            throw new \RuntimeException('Pesapal payment system misconfigured: APP_PRODUCTION_URL or APP_URL is invalid');
        }

        // Warn if using localhost (common misconfiguration)
        if (strpos($this->appBaseUrl, 'localhost') !== false || strpos($this->appBaseUrl, '127.0.0.1') !== false) {
            Log::warning('Pesapal: APP_PRODUCTION_URL contains localhost - IPN/Callback will NOT work in production!', [
                'app_base_url' => $this->appBaseUrl,
            ]);
        }

        // CRITICAL: Always construct the correct IPN and callback URLs
        $this->ipnUrl = rtrim($this->appBaseUrl, '/') . '/api/subscriptions/pesapal/ipn';
        $this->callbackUrl = rtrim($this->appBaseUrl, '/') . '/api/subscriptions/pesapal/callback';

        Log::info('Pesapal Service initialized', [
            'ipn_url' => $this->ipnUrl,
            'callback_url' => $this->callbackUrl,
            'base_url' => $this->baseUrl,
            'has_consumer_key' => !empty($this->consumerKey),
        ]);
    }

    // ================================================================
    // PRIVATE HELPER: Retry-enabled cURL executor
    // ================================================================

    /**
     * Execute a cURL request with automatic retry and exponential backoff.
     * Handles SSL fallback, token refresh on 401, and detailed error logging.
     *
     * @param string $url Full API URL
     * @param string $method 'GET' or 'POST'
     * @param array|null $payload POST body (will be JSON-encoded)
     * @param string|null $bearerToken Authorization token
     * @param string $operationName Human-readable name for logging
     * @return array ['http_code' => int, 'body' => string, 'data' => array|null]
     * @throws \Exception on all retries exhausted
     */
    private function curlWithRetry(string $url, string $method, ?array $payload, ?string $bearerToken, string $operationName): array
    {
        $lastError = null;
        $sslVerify = true;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CURL_CONNECT_TIMEOUT);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
            ];
            if ($bearerToken) {
                $headers[] = 'Authorization: Bearer ' . $bearerToken;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, 1);
                if ($payload !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                }
            }

            $response = curl_exec($ch);
            $curlErrno = curl_errno($ch);
            $curlError = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // ----- cURL-level error (network, DNS, SSL, timeout) -----
            if ($curlErrno !== 0) {
                $lastError = "{$operationName} connection failed (attempt {$attempt}/" . self::MAX_RETRIES . ", curl error {$curlErrno}): {$curlError}";
                Log::warning("Pesapal: {$lastError}");

                // If SSL error and this was first attempt, retry with SSL verification disabled
                if (in_array($curlErrno, [CURLE_SSL_CERTPROBLEM, CURLE_SSL_CACERT, CURLE_SSL_CONNECT_ERROR, 60, 77]) && $sslVerify) {
                    Log::warning("Pesapal: SSL error on {$operationName}, retrying without SSL verification");
                    $sslVerify = false;
                }

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_MS * pow(2, $attempt - 1); // 500ms, 1000ms, 2000ms
                    usleep($delay * 1000);
                    continue;
                }
                throw new \Exception($lastError);
            }

            // ----- Parse JSON -----
            $data = json_decode($response, true);
            if ($data === null && !empty($response) && $response !== 'null') {
                $lastError = "{$operationName} returned invalid JSON. HTTP {$httpCode}. Body: " . substr($response, 0, 300);
                Log::warning("Pesapal: {$lastError}");

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_MS * pow(2, $attempt - 1);
                    usleep($delay * 1000);
                    continue;
                }
                throw new \Exception($lastError);
            }

            // ----- HTTP 5xx server error → retry -----
            if ($httpCode >= 500 && $attempt < self::MAX_RETRIES) {
                $lastError = "{$operationName} server error HTTP {$httpCode} (attempt {$attempt})";
                Log::warning("Pesapal: {$lastError}");
                $delay = self::RETRY_DELAY_MS * pow(2, $attempt - 1);
                usleep($delay * 1000);
                continue;
            }

            // ----- HTTP 429 rate limit → retry with longer backoff -----
            if ($httpCode === 429 && $attempt < self::MAX_RETRIES) {
                $lastError = "{$operationName} rate limited (attempt {$attempt})";
                Log::warning("Pesapal: {$lastError}");
                usleep(2000 * 1000); // 2 seconds
                continue;
            }

            // ----- Return result (caller decides if success/failure based on status) -----
            return [
                'http_code' => $httpCode,
                'body' => $response,
                'data' => $data,
            ];
        }

        throw new \Exception("{$operationName} failed after " . self::MAX_RETRIES . " attempts. Last error: {$lastError}");
    }

    /**
     * Get token cache key
     */
    private function tokenCacheKey(): string
    {
        return 'pesapal_subscription_token_' . md5($this->consumerKey);
    }

    /**
     * Get IPN ID cache key
     */
    private function ipnCacheKey(): string
    {
        return 'pesapal_ipn_id_' . md5($this->ipnUrl);
    }

    /**
     * Clear cached token (used on 401 to force re-auth)
     */
    private function clearTokenCache(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    // ================================================================
    // STEP 1: Authenticate with Pesapal
    // ================================================================

    /**
     * Authenticate with Pesapal API and retrieve a JWT token.
     * Cached for 4.5 minutes (tokens expire in 5 minutes).
     * Retries up to MAX_RETRIES times on failure.
     *
     * @param bool $forceRefresh Force re-authentication (ignore cache)
     * @return string JWT token
     * @throws \Exception on authentication failure
     */
    public function authenticate(bool $forceRefresh = false): string
    {
        try {
            $cacheKey = $this->tokenCacheKey();

            if (!$forceRefresh) {
                $cachedToken = Cache::get($cacheKey);
                if ($cachedToken) {
                    return $cachedToken;
                }
            }

            $payload = [
                'consumer_key' => $this->consumerKey,
                'consumer_secret' => $this->consumerSecret,
            ];

            $result = $this->curlWithRetry(
                $this->baseUrl . '/api/Auth/RequestToken',
                'POST',
                $payload,
                null,
                'Authentication'
            );

            if ($result['http_code'] === 200 && isset($result['data']['token'])) {
                $token = $result['data']['token'];

                // Validate token is not empty
                if (empty(trim($token))) {
                    throw new \Exception('Authentication returned empty token');
                }

                Cache::put($cacheKey, $token, self::TOKEN_CACHE_SECONDS);

                Log::info('Pesapal: Authentication successful', [
                    'cache_duration' => self::TOKEN_CACHE_SECONDS . 's',
                    'expires_at' => now()->addSeconds(self::TOKEN_CACHE_SECONDS)->toDateTimeString(),
                ]);

                return $token;
            }

            // Extract meaningful error message
            $errorMsg = $result['data']['error']['message']
                ?? $result['data']['message']
                ?? $result['data']['error']
                ?? ('HTTP ' . $result['http_code']);

            throw new \Exception("Authentication failed: {$errorMsg}");

        } catch (\Exception $e) {
            Log::error('Pesapal: Authentication failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ================================================================
    // STEP 2: Register IPN URL (with caching)
    // ================================================================

    /**
     * Register IPN URL with Pesapal and return the IPN ID.
     * Caches the IPN ID for 24 hours to avoid re-registering every request.
     * If Pesapal says it's already registered, uses the existing ID.
     *
     * @param string|null $ipnUrl Override IPN URL (must be a valid URL)
     * @return string IPN ID
     * @throws \Exception on registration failure
     */
    public function registerIpnUrl($ipnUrl = null): string
    {
        $ipnUrl = $ipnUrl ?: $this->ipnUrl;

        // Validate IPN URL
        if (empty($ipnUrl) || !filter_var($ipnUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception("Invalid IPN URL: '{$ipnUrl}'. Must be a valid HTTPS URL.");
        }

        try {
            // Check cache first — IPN IDs don't change for the same URL
            $cacheKey = $this->ipnCacheKey();
            $cachedIpnId = Cache::get($cacheKey);

            if ($cachedIpnId) {
                Log::debug('Pesapal: Using cached IPN ID', ['ipn_id' => $cachedIpnId]);
                return $cachedIpnId;
            }

            $token = $this->authenticate();

            $payload = [
                'url' => $ipnUrl,
                'ipn_notification_type' => 'POST',
            ];

            $result = $this->curlWithRetry(
                $this->baseUrl . '/api/URLSetup/RegisterIPN',
                'POST',
                $payload,
                $token,
                'IPN Registration'
            );

            // Handle 401 — token expired, refresh and retry once
            if ($result['http_code'] === 401) {
                Log::warning('Pesapal: IPN registration got 401, refreshing token');
                $this->clearTokenCache();
                $token = $this->authenticate(true);

                $result = $this->curlWithRetry(
                    $this->baseUrl . '/api/URLSetup/RegisterIPN',
                    'POST',
                    $payload,
                    $token,
                    'IPN Registration (retry auth)'
                );
            }

            if (isset($result['data']['ipn_id']) && !empty($result['data']['ipn_id'])) {
                $ipnId = $result['data']['ipn_id'];

                // Cache for 24 hours
                Cache::put($cacheKey, $ipnId, self::IPN_CACHE_SECONDS);

                Log::info('Pesapal: IPN registered successfully', [
                    'ipn_id' => $ipnId,
                    'url' => $ipnUrl,
                    'cached_for' => self::IPN_CACHE_SECONDS . 's',
                ]);

                return $ipnId;
            }

            // Extract error
            $errorMsg = $result['data']['error']['message']
                ?? $result['data']['message']
                ?? $result['data']['error']
                ?? ('HTTP ' . $result['http_code']);

            throw new \Exception("IPN registration failed: {$errorMsg}");

        } catch (\Exception $e) {
            Log::error('Pesapal: IPN registration failed', [
                'error' => $e->getMessage(),
                'ipn_url' => $ipnUrl,
            ]);
            throw $e;
        }
    }

    // ================================================================
    // STEP 3: Initialize Payment — BULLETPROOF
    // ================================================================

    /**
     * Initialize payment with Pesapal.
     * Pre-validates ALL required data before making any API calls.
     * Retries on transient failures, refreshes token on 401.
     *
     * @param Subscription $subscription Must have: user, plan, merchant_reference, amount, currency
     * @param string|null $notificationId Cached IPN ID (auto-registered if null)
     * @param string|null $callbackUrl Override callback URL (must contain full path)
     * @return array ['success' => true, 'order_tracking_id' => ..., 'redirect_url' => ..., ...]
     * @throws \Exception on payment initialization failure
     */
    public function initializePayment($subscription, $notificationId = null, $callbackUrl = null)
    {
        // ===== PRE-FLIGHT VALIDATION =====
        $errors = [];

        if (!$subscription || !$subscription->id) {
            $errors[] = 'Subscription record is null or unsaved';
        }

        if (empty($subscription->pesapal_merchant_reference)) {
            $errors[] = 'Subscription has no merchant reference';
        }

        if (empty($subscription->amount_paid) || (float) $subscription->amount_paid <= 0) {
            $errors[] = 'Subscription amount is zero or negative: ' . ($subscription->amount_paid ?? 'null');
        }

        if (empty($subscription->currency)) {
            $errors[] = 'Subscription currency is empty';
        }

        $user = $subscription->user;
        if (!$user) {
            $errors[] = 'Subscription has no associated user (user_id: ' . ($subscription->user_id ?? 'null') . ')';
        }

        $plan = $subscription->plan;
        if (!$plan) {
            $errors[] = 'Subscription has no associated plan (plan_id: ' . ($subscription->plan_id ?? 'null') . ')';
        }

        if (!empty($errors)) {
            $errorMsg = 'Payment pre-flight validation failed: ' . implode('; ', $errors);
            Log::error('Pesapal: ' . $errorMsg, [
                'subscription_id' => $subscription->id ?? null,
                'user_id' => $subscription->user_id ?? null,
            ]);
            throw new \Exception($errorMsg);
        }

        try {
            // ===== CALLBACK URL VALIDATION =====
            if ($callbackUrl && strpos($callbackUrl, '/api/subscriptions/pesapal/callback') !== false) {
                // Valid callback URL override — use it
            } else {
                if ($callbackUrl) {
                    Log::warning('Pesapal: Invalid callback URL override, using default', [
                        'received' => $callbackUrl,
                        'using' => $this->callbackUrl,
                    ]);
                }
                $callbackUrl = $this->callbackUrl;
            }

            // Final callback URL validation
            if (empty($callbackUrl) || !filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
                throw new \Exception("Callback URL is invalid: '{$callbackUrl}'");
            }

            Log::info('Pesapal: Initializing payment', [
                'subscription_id' => $subscription->id,
                'merchant_reference' => $subscription->pesapal_merchant_reference,
                'amount' => $subscription->amount_paid,
                'currency' => $subscription->currency,
                'callback_url' => $callbackUrl,
                'user_email' => $user->email ?? 'none',
            ]);

            // ===== AUTHENTICATE =====
            $token = $this->authenticate();

            // ===== GET OR REGISTER IPN =====
            if (!$notificationId) {
                $notificationId = $this->registerIpnUrl();
            }

            if (empty($notificationId)) {
                throw new \Exception('Failed to obtain IPN notification ID');
            }

            // ===== BUILD PAYLOAD =====
            $payload = [
                'id' => $subscription->pesapal_merchant_reference,
                'currency' => strtoupper(trim($subscription->currency)),
                'amount' => round((float) $subscription->amount_paid, 2),
                'description' => "Subscription: {$plan->name} - {$plan->duration_days} days",
                'callback_url' => $callbackUrl,
                'notification_id' => $notificationId,
                'billing_address' => [
                    'email_address' => $user->email ?? '',
                    'phone_number' => $user->phone_number ?? $user->phone_number_1 ?? '',
                    'country_code' => 'UG',
                    'first_name' => $user->first_name ?? $user->name ?? 'User',
                    'last_name' => $user->last_name ?? '',
                    'line_1' => '',
                    'line_2' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'zip_code' => '',
                ],
            ];

            // ===== SUBMIT ORDER =====
            $result = $this->curlWithRetry(
                $this->baseUrl . '/api/Transactions/SubmitOrderRequest',
                'POST',
                $payload,
                $token,
                'SubmitOrderRequest'
            );

            // Handle 401 — token expired mid-flow, refresh and retry
            if ($result['http_code'] === 401) {
                Log::warning('Pesapal: SubmitOrderRequest got 401, refreshing token and retrying');
                $this->clearTokenCache();
                $token = $this->authenticate(true);

                $result = $this->curlWithRetry(
                    $this->baseUrl . '/api/Transactions/SubmitOrderRequest',
                    'POST',
                    $payload,
                    $token,
                    'SubmitOrderRequest (retry auth)'
                );
            }

            Log::info('Pesapal: SubmitOrderRequest response', [
                'http_code' => $result['http_code'],
                'has_tracking_id' => isset($result['data']['order_tracking_id']),
                'has_redirect_url' => isset($result['data']['redirect_url']),
                'subscription_id' => $subscription->id,
            ]);

            // ===== VALIDATE RESPONSE =====
            if ($result['http_code'] === 200 && isset($result['data']['order_tracking_id'])) {
                $trackingId = $result['data']['order_tracking_id'];
                $redirectUrl = $result['data']['redirect_url'] ?? null;

                // CRITICAL: Ensure we got a redirect URL
                if (empty($redirectUrl)) {
                    Log::error('Pesapal: SubmitOrderRequest returned tracking_id but NO redirect_url', [
                        'tracking_id' => $trackingId,
                        'full_response' => $result['data'],
                    ]);
                    throw new \Exception('Pesapal returned order but no payment redirect URL. Tracking ID: ' . $trackingId);
                }

                // Validate redirect URL is a proper URL
                if (!filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
                    Log::error('Pesapal: Invalid redirect URL returned', [
                        'redirect_url' => $redirectUrl,
                        'tracking_id' => $trackingId,
                    ]);
                    throw new \Exception('Pesapal returned invalid redirect URL: ' . substr($redirectUrl, 0, 100));
                }

                // ===== UPDATE SUBSCRIPTION =====
                $subscription->pesapal_tracking_id = $trackingId;
                $subscription->pesapal_response = $result['data'];
                $subscription->payment_url = $redirectUrl;
                $subscription->payment_status = 'Processing';
                $subscription->save();

                // ===== CREATE TRANSACTION RECORD =====
                SubscriptionTransaction::create([
                    'subscription_id' => $subscription->id,
                    'user_id' => $user->id,
                    'transaction_type' => $subscription->is_extension ? 'Renewal' : 'Initial',
                    'amount' => $subscription->amount_paid,
                    'currency' => $subscription->currency,
                    'status' => 'Pending',
                    'pesapal_tracking_id' => $trackingId,
                    'merchant_reference' => $subscription->pesapal_merchant_reference,
                    'request_payload' => $payload,
                    'response_payload' => $result['data'],
                ]);

                Log::info('Pesapal: Payment initialized successfully', [
                    'subscription_id' => $subscription->id,
                    'tracking_id' => $trackingId,
                    'redirect_url_domain' => parse_url($redirectUrl, PHP_URL_HOST),
                    'merchant_reference' => $subscription->pesapal_merchant_reference,
                    'amount' => $subscription->amount_paid,
                    'currency' => $subscription->currency,
                ]);

                return [
                    'success' => true,
                    'order_tracking_id' => $trackingId,
                    'merchant_reference' => $subscription->pesapal_merchant_reference,
                    'redirect_url' => $redirectUrl,
                    'status' => '200',
                ];
            }

            // ===== HANDLE SPECIFIC ERROR CODES =====
            $errorMsg = $result['data']['error']['message']
                ?? $result['data']['message']
                ?? $result['data']['error']
                ?? null;

            if ($result['http_code'] === 400) {
                throw new \Exception('Pesapal rejected the order (400 Bad Request): ' . ($errorMsg ?? 'Check payload format'));
            }
            if ($result['http_code'] === 403) {
                throw new \Exception('Pesapal access denied (403): ' . ($errorMsg ?? 'Check API credentials'));
            }
            if ($result['http_code'] === 422) {
                throw new \Exception('Pesapal validation error (422): ' . ($errorMsg ?? 'Check order fields'));
            }

            throw new \Exception('Payment initialization failed: ' . ($errorMsg ?? 'HTTP ' . $result['http_code']));

        } catch (\Exception $e) {
            Log::error('Pesapal: Payment initialization failed', [
                'subscription_id' => $subscription->id,
                'merchant_reference' => $subscription->pesapal_merchant_reference ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ================================================================
    // STEP 4: Check Transaction Status
    // ================================================================

    /**
     * Check transaction status with Pesapal.
     * Retries on transient failures, refreshes token on 401.
     *
     * @param string $orderTrackingId
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function getTransactionStatus($orderTrackingId)
    {
        try {
            if (empty($orderTrackingId)) {
                throw new \Exception('Order tracking ID is empty');
            }

            $token = $this->authenticate();

            $result = $this->curlWithRetry(
                $this->baseUrl . '/api/Transactions/GetTransactionStatus?orderTrackingId=' . urlencode($orderTrackingId),
                'GET',
                null,
                $token,
                'GetTransactionStatus'
            );

            // Handle 401 — token expired, refresh and retry
            if ($result['http_code'] === 401) {
                Log::warning('Pesapal: GetTransactionStatus got 401, refreshing token');
                $this->clearTokenCache();
                $token = $this->authenticate(true);

                $result = $this->curlWithRetry(
                    $this->baseUrl . '/api/Transactions/GetTransactionStatus?orderTrackingId=' . urlencode($orderTrackingId),
                    'GET',
                    null,
                    $token,
                    'GetTransactionStatus (retry auth)'
                );
            }

            if ($result['http_code'] === 200) {
                Log::info('Pesapal: Transaction status retrieved', [
                    'tracking_id' => $orderTrackingId,
                    'status' => $result['data']['status_code'] ?? 'unknown',
                ]);

                return [
                    'success' => true,
                    'data' => $result['data'],
                ];
            }

            throw new \Exception('Status check failed: HTTP ' . $result['http_code']);

        } catch (\Exception $e) {
            Log::error('Pesapal: Transaction status check failed', [
                'tracking_id' => $orderTrackingId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update subscription status after payment
     * COMPREHENSIVE IMPLEMENTATION - Handles all fields carefully
     * 
     * @param string $orderTrackingId
     * @param array $statusData
     * @return array
     */
    public function updateSubscriptionStatus($orderTrackingId, $statusData)
    {
        try {
            Log::info('🔄 Pesapal: Starting subscription status update', [
                'tracking_id' => $orderTrackingId,
                'status_data' => $statusData,
            ]);

            $subscription = Subscription::where('pesapal_tracking_id', $orderTrackingId)->first();

            if (!$subscription) {
                Log::error('❌ Pesapal: Subscription not found', [
                    'tracking_id' => $orderTrackingId,
                ]);
                throw new \Exception('Subscription not found for tracking ID: ' . $orderTrackingId);
            }

            Log::info('📦 Pesapal: Found subscription', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'current_status' => $subscription->status,
                'current_payment_status' => $subscription->payment_status,
                'plan_id' => $subscription->plan_id,
            ]);

            $transaction = $subscription->transactions()
                ->where('pesapal_tracking_id', $orderTrackingId)
                ->orderBy('created_at', 'DESC')
                ->first();

            if (!$transaction) {
                Log::warning('⚠️ Pesapal: Transaction record not found, will create one', [
                    'subscription_id' => $subscription->id,
                ]);
            }

            $paymentStatus = $statusData['payment_status_description'] ?? $statusData['status'] ?? null;
            $statusCode = $statusData['status_code'] ?? $statusData['payment_status_code'] ?? null;

            Log::info('🔍 Pesapal: Analyzing payment status', [
                'payment_status' => $paymentStatus,
                'status_code' => $statusCode,
                'status_code_type' => gettype($statusCode),
                'full_data' => $statusData,
            ]);

            // Normalize status code to integer for strict comparison
            $statusCodeInt = is_numeric($statusCode) ? (int)$statusCode : null;

            // ==================== PAYMENT SUCCESS ====================
            // FIXED: Use strict === comparison to avoid type coercion issues
            if ($statusCodeInt === 1 || strtolower($paymentStatus ?? '') === 'completed') {
                
        
                // Check if subscription is already active
                $isAlreadyActive = ($subscription->status === 'Active' && $subscription->payment_status === 'Completed');
                
                if ($isAlreadyActive) {
                    Log::info('ℹ️ Pesapal: Subscription ALREADY ACTIVE - Will not update start_date_time', [
                        'subscription_id' => $subscription->id,
                        'existing_start_date' => $subscription->start_date_time,
                        'existing_end_date' => $subscription->end_date_time,
                    ]);
                }

                // Update subscription - CAREFULLY
                $subscription->status = 'Active';
                $subscription->payment_status = 'Completed';
                
                // Only set start_date_time if NOT already active
                if (!$isAlreadyActive && !$subscription->start_date_time) {
                    $subscription->start_date_time = now();
                    Log::info('📅 Pesapal: Setting start_date_time', [
                        'start_date_time' => $subscription->start_date_time,
                    ]);
                } else {
                    Log::info('📅 Pesapal: Keeping existing start_date_time', [
                        'start_date_time' => $subscription->start_date_time,
                    ]);
                }

                // Always calculate/update end_date_time
                if (!$subscription->end_date_time) {
                    $startDate = $subscription->start_date_time ?? now();
                    $subscription->end_date_time = \Carbon\Carbon::parse($startDate)->addDays($subscription->days);
                    Log::info('📅 Pesapal: Calculated end_date_time', [
                        'days' => $subscription->days,
                        'end_date_time' => $subscription->end_date_time,
                    ]);
                }

                // Set payment confirmed timestamp
                if (!$subscription->payment_confirmed_at) {
                    $subscription->payment_confirmed_at = now();
                    Log::info('✅ Pesapal: Setting payment_confirmed_at', [
                        'payment_confirmed_at' => $subscription->payment_confirmed_at,
                    ]);
                }

                // Clear failed_at if it was set
                if ($subscription->failed_at) {
                    $subscription->failed_at = null;
                    Log::info('🔄 Pesapal: Clearing failed_at timestamp');
                }

                // Clear payment_failure_reason on success (in case this was a retry after failure)
                if ($subscription->payment_failure_reason) {
                    $subscription->payment_failure_reason = null;
                    Log::info('🔄 Pesapal: Clearing payment_failure_reason (retry succeeded)');
                }

                // Update pesapal response data
                $subscription->pesapal_response = array_merge(
                    $subscription->pesapal_response ?? [],
                    ['status_check' => $statusData]
                );

                $subscription->save();

                Log::info('💾 Pesapal: Subscription SAVED successfully', [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'payment_status' => $subscription->payment_status,
                    'start_date_time' => $subscription->start_date_time,
                    'end_date_time' => $subscription->end_date_time,
                    'payment_confirmed_at' => $subscription->payment_confirmed_at,
                ]);

                // Update or create transaction record
                if ($transaction) {
                    Log::info('📝 Pesapal: Updating existing transaction', [
                        'transaction_id' => $transaction->id,
                    ]);

                    $transaction->status = 'Completed';
                    $transaction->payment_method = $statusData['payment_method'] ?? $transaction->payment_method;
                    $transaction->confirmation_code = $statusData['confirmation_code'] ?? $transaction->confirmation_code;
                    $transaction->payment_account = $statusData['payment_account'] ?? $statusData['account_number'] ?? null;
                    $transaction->response_payload = array_merge(
                        $transaction->response_payload ?? [],
                        ['status_check' => $statusData]
                    );
                    $transaction->error_message = null; // Clear any errors
                    $transaction->save();

                    Log::info('💾 Pesapal: Transaction updated successfully', [
                        'transaction_id' => $transaction->id,
                        'status' => $transaction->status,
                        'payment_method' => $transaction->payment_method,
                        'confirmation_code' => $transaction->confirmation_code,
                    ]);
                } else {
                    // Create transaction if it doesn't exist
                    Log::info('📝 Pesapal: Creating new transaction record');

                    $transaction = SubscriptionTransaction::create([
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'transaction_type' => $subscription->is_extension ? 'Renewal' : 'Initial',
                        'amount' => $subscription->amount_paid,
                        'currency' => $subscription->currency,
                        'status' => 'Completed',
                        'pesapal_tracking_id' => $orderTrackingId,
                        'merchant_reference' => $subscription->pesapal_merchant_reference,
                        'payment_method' => $statusData['payment_method'] ?? null,
                        'confirmation_code' => $statusData['confirmation_code'] ?? null,
                        'payment_account' => $statusData['payment_account'] ?? $statusData['account_number'] ?? null,
                        'request_payload' => null,
                        'response_payload' => ['status_check' => $statusData],
                    ]);

                    Log::info('💾 Pesapal: Transaction created successfully', [
                        'transaction_id' => $transaction->id,
                    ]);
                }

                Log::info('🎉 Pesapal: Subscription ACTIVATED successfully', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'plan_id' => $subscription->plan_id,
                    'tracking_id' => $orderTrackingId,
                    'start_date' => $subscription->start_date_time,
                    'end_date' => $subscription->end_date_time,
                    'duration_days' => $subscription->days,
                ]);

                return [
                    'status' => 'success',
                    'subscription' => $subscription->fresh(['plan', 'transactions']),
                    'transaction' => $transaction,
                    'message' => 'Payment successful! Your subscription is now active.',
                ];

            } 
            // ==================== PAYMENT FAILED ====================
            // FIXED: Use strict === comparison
            elseif ($statusCodeInt === 2 || in_array(strtolower($paymentStatus ?? ''), ['failed', 'invalid'], true)) {
                
                Log::warning('❌ Pesapal: Payment FAILED', [
                    'subscription_id' => $subscription->id,
                    'tracking_id' => $orderTrackingId,
                    'reason' => $paymentStatus,
                    'status_code' => $statusCode,
                ]);

                // Build detailed failure reason for admin follow-up
                $failureDetails = [];
                $failureDetails[] = 'Status: ' . ($paymentStatus ?? 'Unknown');
                $failureDetails[] = 'Status Code: ' . ($statusCode ?? 'N/A');
                if (!empty($statusData['payment_method'])) {
                    $failureDetails[] = 'Payment Method: ' . $statusData['payment_method'];
                }
                if (!empty($statusData['description'])) {
                    $failureDetails[] = 'Description: ' . $statusData['description'];
                }
                if (!empty($statusData['message'])) {
                    $failureDetails[] = 'Message: ' . $statusData['message'];
                }
                if (!empty($statusData['error'])) {
                    $failureDetails[] = 'Error: ' . (is_array($statusData['error']) ? json_encode($statusData['error']) : $statusData['error']);
                }
                if (!empty($statusData['confirmation_code'])) {
                    $failureDetails[] = 'Confirmation Code: ' . $statusData['confirmation_code'];
                }
                if (!empty($statusData['payment_account'])) {
                    $failureDetails[] = 'Payment Account: ' . $statusData['payment_account'];
                }
                $failureDetails[] = 'Timestamp: ' . now()->toDateTimeString();
                $failureReasonText = implode(' | ', $failureDetails);

                // Update subscription
                $subscription->status = 'Failed';
                $subscription->payment_status = 'Failed';
                $subscription->failed_at = now();
                $subscription->cancelled_reason = 'Payment failed: ' . $paymentStatus;
                $subscription->payment_failure_reason = $failureReasonText;
                $subscription->pesapal_response = array_merge(
                    $subscription->pesapal_response ?? [],
                    ['status_check' => $statusData]
                );
                $subscription->save();

                Log::info('💾 Pesapal: Subscription marked as FAILED', [
                    'subscription_id' => $subscription->id,
                    'failed_at' => $subscription->failed_at,
                    'failure_reason' => $failureReasonText,
                ]);

                // Update transaction
                if ($transaction) {
                    $transaction->status = 'Failed';
                    $transaction->error_message = 'Payment failed: ' . $paymentStatus;
                    $transaction->response_payload = array_merge(
                        $transaction->response_payload ?? [],
                        ['status_check' => $statusData]
                    );
                    $transaction->save();

                    Log::info('💾 Pesapal: Transaction marked as FAILED', [
                        'transaction_id' => $transaction->id,
                    ]);
                }

                return [
                    'status' => 'failed',
                    'subscription' => $subscription->fresh(['plan', 'transactions']),
                    'transaction' => $transaction,
                    'message' => 'Payment failed: ' . $paymentStatus,
                    'failure_reason' => $failureReasonText,
                    'api_response' => [
                        'payment_status' => $paymentStatus,
                        'status_code' => $statusCode,
                        'payment_method' => $statusData['payment_method'] ?? null,
                        'description' => $statusData['description'] ?? null,
                        'message' => $statusData['message'] ?? null,
                    ],
                ];

            } 
            // ==================== PAYMENT PENDING ====================
            else {
                
                Log::info('⏳ Pesapal: Payment still PENDING', [
                    'subscription_id' => $subscription->id,
                    'tracking_id' => $orderTrackingId,
                    'payment_status' => $paymentStatus,
                    'status_code' => $statusCode,
                ]);

                // Update payment status to Processing if it was Pending
                if ($subscription->payment_status === 'Pending') {
                    $subscription->payment_status = 'Processing';
                    $subscription->pesapal_response = array_merge(
                        $subscription->pesapal_response ?? [],
                        ['status_check' => $statusData]
                    );
                    $subscription->save();
 
                }

                return [
                    'status' => 'pending',
                    'subscription' => $subscription->fresh(['plan', 'transactions']),
                    'transaction' => $transaction,
                    'message' => 'Payment is being processed. Please check back shortly.',
                ];
            }

        } catch (\Exception $e) {
            Log::error('💥 Pesapal: CRITICAL ERROR in subscription status update', [
                'tracking_id' => $orderTrackingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Process IPN callback
     * 
     * @param string $orderTrackingId
     * @param string|null $merchantReference
     * @return array
     */
    public function processIpnCallback($orderTrackingId, $merchantReference = null)
    {
        try {
            if (empty($orderTrackingId)) {
                throw new \Exception('IPN callback received empty orderTrackingId');
            }

            // Get latest transaction status from Pesapal
            $statusResult = $this->getTransactionStatus($orderTrackingId);

            if (!$statusResult['success']) {
                throw new \Exception('Failed to get transaction status: ' . ($statusResult['error'] ?? 'unknown'));
            }

            // Update subscription status
            $result = $this->updateSubscriptionStatus($orderTrackingId, $statusResult['data']);
 
            return $result;

        } catch (\Exception $e) {
            Log::error('Pesapal: IPN callback processing failed', [
                'tracking_id' => $orderTrackingId,
                'merchant_reference' => $merchantReference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ================================================================
    // DIAGNOSTICS: Test the full payment flow without creating records
    // ================================================================

    /**
     * Test the Pesapal API connection end-to-end.
     * Steps: Authenticate → Register IPN → (optionally submit test order)
     * Does NOT create any database records.
     *
     * @param bool $submitTestOrder If true, submits a real $1 test order
     * @return array Diagnostic results
     */
    public function testConnection(bool $submitTestOrder = false): array
    {
        $results = [
            'timestamp' => now()->toDateTimeString(),
            'config' => [
                'base_url' => $this->baseUrl,
                'ipn_url' => $this->ipnUrl,
                'callback_url' => $this->callbackUrl,
                'has_consumer_key' => !empty($this->consumerKey),
                'consumer_key_prefix' => substr($this->consumerKey, 0, 8) . '...',
            ],
            'steps' => [],
            'overall' => 'pending',
        ];

        // Step 1: Auth
        try {
            $start = microtime(true);
            $token = $this->authenticate(true); // force refresh
            $elapsed = round((microtime(true) - $start) * 1000);

            $results['steps']['auth'] = [
                'status' => 'OK',
                'token_length' => strlen($token),
                'token_prefix' => substr($token, 0, 20) . '...',
                'elapsed_ms' => $elapsed,
            ];
        } catch (\Exception $e) {
            $results['steps']['auth'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
            ];
            $results['overall'] = 'FAILED at authentication';
            return $results;
        }

        // Step 2: IPN Registration
        try {
            // Clear IPN cache to test fresh
            Cache::forget($this->ipnCacheKey());

            $start = microtime(true);
            $ipnId = $this->registerIpnUrl();
            $elapsed = round((microtime(true) - $start) * 1000);

            $results['steps']['ipn_registration'] = [
                'status' => 'OK',
                'ipn_id' => $ipnId,
                'ipn_url' => $this->ipnUrl,
                'elapsed_ms' => $elapsed,
            ];
        } catch (\Exception $e) {
            $results['steps']['ipn_registration'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
            ];
            $results['overall'] = 'FAILED at IPN registration';
            return $results;
        }

        // Step 3: (Optional) Submit a test order
        if ($submitTestOrder) {
            try {
                $testRef = 'TEST-' . time() . '-' . rand(1000, 9999);
                $payload = [
                    'id' => $testRef,
                    'currency' => 'UGX',
                    'amount' => 500,
                    'description' => 'API Connection Test - Do Not Pay',
                    'callback_url' => $this->callbackUrl,
                    'notification_id' => $ipnId,
                    'billing_address' => [
                        'email_address' => 'test@katogo.test',
                        'phone_number' => '',
                        'country_code' => 'UG',
                        'first_name' => 'Test',
                        'last_name' => 'User',
                        'line_1' => '',
                        'line_2' => '',
                        'city' => '',
                        'state' => '',
                        'postal_code' => '',
                        'zip_code' => '',
                    ],
                ];

                $start = microtime(true);
                $result = $this->curlWithRetry(
                    $this->baseUrl . '/api/Transactions/SubmitOrderRequest',
                    'POST',
                    $payload,
                    $token,
                    'Test SubmitOrderRequest'
                );
                $elapsed = round((microtime(true) - $start) * 1000);

                if ($result['http_code'] === 200 && isset($result['data']['redirect_url'])) {
                    $results['steps']['submit_order'] = [
                        'status' => 'OK',
                        'test_reference' => $testRef,
                        'tracking_id' => $result['data']['order_tracking_id'] ?? null,
                        'redirect_url' => $result['data']['redirect_url'],
                        'elapsed_ms' => $elapsed,
                    ];
                } else {
                    $results['steps']['submit_order'] = [
                        'status' => 'FAILED',
                        'http_code' => $result['http_code'],
                        'response' => $result['data'],
                        'elapsed_ms' => $elapsed,
                    ];
                    $results['overall'] = 'FAILED at order submission';
                    return $results;
                }
            } catch (\Exception $e) {
                $results['steps']['submit_order'] = [
                    'status' => 'FAILED',
                    'error' => $e->getMessage(),
                ];
                $results['overall'] = 'FAILED at order submission';
                return $results;
            }
        }

        $results['overall'] = 'ALL PASSED';
        return $results;
    }

    /**
     * Get list of registered IPN URLs from Pesapal
     * Useful for debugging IPN configuration issues
     *
     * @return array
     */
    public function getRegisteredIpnList(): array
    {
        try {
            $token = $this->authenticate();

            $result = $this->curlWithRetry(
                $this->baseUrl . '/api/URLSetup/GetIpnList',
                'GET',
                null,
                $token,
                'GetIpnList'
            );

            return [
                'success' => $result['http_code'] === 200,
                'data' => $result['data'] ?? [],
                'http_code' => $result['http_code'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
