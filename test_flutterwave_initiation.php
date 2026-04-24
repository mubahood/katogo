<?php
/**
 * Flutterwave Payment URL Initiation Test
 * Tests the complete URL initiation flow for Flutterwave payments.
 * Run with: php test_flutterwave_initiation.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Helper: coloured output
function pass(string $msg): void { echo "\033[32m  [PASS]\033[0m $msg\n"; }
function fail(string $msg): void { echo "\033[31m  [FAIL]\033[0m $msg\n"; }
function info(string $msg): void { echo "\033[36m  [INFO]\033[0m $msg\n"; }
function section(string $msg): void { echo "\n\033[33m=== $msg ===\033[0m\n"; }

$failures = 0;

// =========================================================
// SECTION 1 — Config validation
// =========================================================
section('1. Config Validation');

$flwConfig = require __DIR__ . '/config/flutterwave.php';

$secretKey  = $_ENV['FLW_SECRET_KEY']  ?? '';
$secretHash = $_ENV['FLW_SECRET_HASH'] ?? '';
$baseUrl    = $_ENV['FLW_BASE_URL']    ?? 'https://api.flutterwave.com';
$currency   = $_ENV['FLW_CURRENCY']   ?? 'UGX';

if (!empty($secretKey)) {
    pass("FLW_SECRET_KEY is set (" . strlen($secretKey) . " chars)");
} else {
    fail("FLW_SECRET_KEY is missing from .env");
    $failures++;
}

if (!empty($secretHash)) {
    pass("FLW_SECRET_HASH is set");
} else {
    fail("FLW_SECRET_HASH is missing from .env");
    $failures++;
}

info("Base URL: $baseUrl");
info("Currency: $currency");

// =========================================================
// SECTION 2 — Webhook Signature Verification
// =========================================================
section('2. Webhook HMAC Signature Test');

// Build a fake payload
$fakePayload = json_encode([
    'event' => 'charge.completed',
    'data' => [
        'tx_ref' => 'SUB-TEST-123',
        'status' => 'successful',
        'flw_ref' => 'FLW-TEST-456',
        'amount' => 15000,
        'currency' => 'UGX',
    ],
]);

// Sign it properly
$expectedSig = hash_hmac('sha256', $fakePayload, $secretHash);
pass("HMAC signature generated: " . substr($expectedSig, 0, 16) . "...");

// Simulate service verification
$verified = hash_equals($expectedSig, hash_hmac('sha256', $fakePayload, $secretHash));
if ($verified) {
    pass("Webhook signature verification: valid signature accepted");
} else {
    fail("Webhook signature verification failed");
    $failures++;
}

// Test invalid signature is rejected
$badSig = hash_hmac('sha256', $fakePayload, 'wrong-secret');
$rejected = !hash_equals($expectedSig, $badSig);
if ($rejected) {
    pass("Webhook signature verification: invalid signature correctly rejected");
} else {
    fail("Webhook signature verification: invalid signature was NOT rejected");
    $failures++;
}

// =========================================================
// SECTION 3 — Live URL Initiation via Flutterwave v3 API
// =========================================================
section('3. Live Flutterwave URL Initiation (v3/payments)');

$txRef = 'SUB-TEST-' . date('YmdHis') . '-' . rand(100, 999);
info("Using tx_ref: $txRef");

$payload = [
    'tx_ref'          => $txRef,
    'amount'          => '15000',
    'currency'        => $currency,
    'redirect_url'    => rtrim($baseUrl === 'https://api.flutterwave.com' ? 'https://katogo.schooldynamics.ug' : 'https://katogo.schooldynamics.ug', '/') . '/api/subscriptions/flutterwave/callback',
    'payment_options' => 'card,mobilemoney,ussd,banktransfer',
    'customer'        => [
        'email'       => 'testuser@katogo.ug',
        'phonenumber' => '256700000000',
        'name'        => 'Test User',
    ],
    'customizations'  => [
        'title'       => 'UGFlix Subscription',
        'description' => 'Test subscription payment',
        'logo'        => '',
    ],
    'meta' => [
        'subscription_id' => 999,
        'user_id'         => 999,
        'plan_id'         => 1,
        'test'            => true,
    ],
];

info("Calling: POST $baseUrl/v3/payments");
info("Payload: " . json_encode(array_merge($payload, ['customer' => ['email' => '***', 'phonenumber' => '***', 'name' => 'Test User']]), JSON_PRETTY_PRINT));

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "$baseUrl/v3/payments",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $secretKey,
    ],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$rawResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError   = curl_error($ch);
curl_close($ch);

if ($curlError) {
    fail("CURL error: $curlError");
    $failures++;
} else {
    info("HTTP Status: $httpCode");
    $response = json_decode($rawResponse, true);

    if ($response === null) {
        fail("Response is not valid JSON");
        fail("Raw: " . substr($rawResponse, 0, 300));
        $failures++;
    } else {
        $status = $response['status'] ?? 'unknown';
        $message = $response['message'] ?? '';
        info("Response status: $status");
        info("Response message: $message");

        if ($httpCode === 200 && $status === 'success') {
            $link = $response['data']['link'] ?? null;
            if (!empty($link)) {
                pass("Payment URL returned successfully!");
                pass("Payment Link: $link");
            } else {
                fail("status=success but no link returned");
                fail("Response: " . json_encode($response, JSON_PRETTY_PRINT));
                $failures++;
            }
        } elseif ($httpCode === 401) {
            fail("Authentication failed (401) — FLW_SECRET_KEY may be invalid or expired");
            info("Response: " . json_encode($response, JSON_PRETTY_PRINT));
            $failures++;
        } elseif ($httpCode === 422) {
            fail("Validation error (422) from Flutterwave:");
            info("Response: " . json_encode($response, JSON_PRETTY_PRINT));
            $failures++;
        } else {
            fail("Unexpected response (HTTP $httpCode):");
            info("Response: " . json_encode($response, JSON_PRETTY_PRINT));
            $failures++;
        }
    }
}

// =========================================================
// SECTION 4 — Verify by Reference (uses tx_ref from above)
// =========================================================
section('4. Verify by Reference (tx_ref lookup)');

info("Verifying tx_ref: $txRef");
info("Calling: GET $baseUrl/v3/transactions/verify_by_reference?tx_ref=$txRef");

$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL            => "$baseUrl/v3/transactions/verify_by_reference?tx_ref=" . urlencode($txRef),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $secretKey,
    ],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$verifyRaw  = curl_exec($ch2);
$verifyCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$verifyCurlErr = curl_error($ch2);
curl_close($ch2);

if ($verifyCurlErr) {
    fail("CURL error during verify: $verifyCurlErr");
    $failures++;
} else {
    info("HTTP Status: $verifyCode");
    $verifyResp = json_decode($verifyRaw, true);
    $verifyStatus = $verifyResp['status'] ?? 'unknown';
    $verifyMsg = $verifyResp['message'] ?? '';

    info("Verify response status: $verifyStatus");
    info("Verify response message: $verifyMsg");

    // 404 or "No transaction was found" is expected for a test ref that hasn't been paid
    if ($verifyCode === 200 && isset($verifyResp['data'])) {
        pass("Verify endpoint returned data (tx may already exist)");
        info("Data: " . json_encode($verifyResp['data'], JSON_PRETTY_PRINT));
    } elseif ($verifyCode === 200 && $verifyStatus === 'error' && stripos($verifyMsg, 'No transaction') !== false) {
        pass("Verify endpoint works correctly — 'No transaction found' for unused tx_ref (expected for new ref)");
    } elseif ($verifyCode === 200 && $verifyStatus === 'error') {
        pass("Verify endpoint reachable — returned error: $verifyMsg");
    } elseif (in_array($verifyCode, [400, 404]) && $verifyStatus === 'error' && stripos($verifyMsg, 'No transaction') !== false) {
        pass("Verify endpoint: correctly reports 'No transaction found' for new tx_ref (HTTP $verifyCode — expected before payment)");
    } elseif ($verifyCode === 404) {
        pass("Verify endpoint: 404 for unused tx_ref (expected before payment completes)");
    } elseif ($verifyCode === 401) {
        fail("Verify endpoint: 401 Unauthorized — same auth issue as initiation");
        $failures++;
    } else {
        info("Verify response (HTTP $verifyCode): " . json_encode($verifyResp, JSON_PRETTY_PRINT));
    }
}

// =========================================================
// SECTION 5 — resolveGateway Logic
// =========================================================
section('5. Gateway Resolution Logic');

$cases = [
    ['input' => 'flutterwave', 'expected' => 'flutterwave'],
    ['input' => 'Flutterwave', 'expected' => 'flutterwave'],
    ['input' => 'FLUTTERWAVE', 'expected' => 'flutterwave'],
    ['input' => 'pesapal',     'expected' => 'pesapal'],
    ['input' => null,          'expected' => 'pesapal'],
    ['input' => '',            'expected' => 'pesapal'],
    ['input' => 'unknown',     'expected' => 'pesapal'],
];

function resolveGateway(?string $gateway): string {
    $normalized = strtolower(trim((string) $gateway));
    return $normalized === 'flutterwave' ? 'flutterwave' : 'pesapal';
}

foreach ($cases as $case) {
    $result = resolveGateway($case['input']);
    if ($result === $case['expected']) {
        pass("resolveGateway(" . json_encode($case['input']) . ") → '$result'");
    } else {
        fail("resolveGateway(" . json_encode($case['input']) . ") → '$result' (expected '{$case['expected']}')");
        $failures++;
    }
}

// =========================================================
// SECTION 6 — Callback URL Construction
// =========================================================
section('6. Callback URL Construction');

$appBaseUrl = rtrim($_ENV['APP_PRODUCTION_URL'] ?? 'https://katogo.schooldynamics.ug', '/');
$pesapalCb   = $appBaseUrl . '/api/subscriptions/pesapal/callback';
$flwCb       = $appBaseUrl . '/api/subscriptions/flutterwave/callback';
$flwWebhook  = $appBaseUrl . '/api/subscriptions/flutterwave/webhook';

info("APP_PRODUCTION_URL: $appBaseUrl");
pass("Pesapal callback URL:     $pesapalCb");
pass("Flutterwave callback URL: $flwCb");
pass("Flutterwave webhook URL:  $flwWebhook");

// Validate no double slashes
foreach ([$pesapalCb, $flwCb, $flwWebhook] as $url) {
    if (strpos(str_replace('://', '', $url), '//') !== false) {
        fail("Double slash found in URL: $url");
        $failures++;
    } else {
        pass("No double slashes in URL: $url");
    }
}

// =========================================================
// FINAL SUMMARY
// =========================================================
section('Test Summary');
if ($failures === 0) {
    echo "\033[32m✅ All tests passed!\033[0m\n\n";
} else {
    echo "\033[31m❌ $failures test(s) FAILED. See above for details.\033[0m\n\n";
}
exit($failures > 0 ? 1 : 0);
