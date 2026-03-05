<?php
/**
 * PESAPAL PAYMENT LINK GENERATION TEST
 * 
 * Tests the full Pesapal API flow:
 * 1. Authentication (get JWT token)
 * 2. IPN URL Registration (get notification ID)
 * 3. Submit Order Request (get payment redirect URL)
 * 
 * Usage: php test_pesapal_payment.php
 * or:   php test_pesapal_payment.php --submit-order
 * 
 * This does NOT require Laravel or database. It calls the Pesapal API directly.
 */

// ===== CONFIG (from your .env) =====
$CONSUMER_KEY    = 'lRkoOQIl7QQc17Ej//RtpRfrq4Z9qzl/';
$CONSUMER_SECRET = 'AlcvoKfr+Al2nCL9u0AH/eASyTk=';
$BASE_URL        = 'https://pay.pesapal.com/v3';
$IPN_URL         = 'https://katogo.schooldynamics.ug/api/subscriptions/pesapal/ipn';
$CALLBACK_URL    = 'https://katogo.schooldynamics.ug/api/subscriptions/pesapal/callback';

$submitOrder = in_array('--submit-order', $argv ?? []);

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║   PESAPAL PAYMENT LINK GENERATION TEST              ║\n";
echo "╠══════════════════════════════════════════════════════╣\n";
echo "║  Base URL:     {$BASE_URL}\n";
echo "║  IPN URL:      {$IPN_URL}\n";
echo "║  Callback URL: {$CALLBACK_URL}\n";
echo "║  Consumer Key: " . substr($CONSUMER_KEY, 0, 12) . "...\n";
echo "║  Submit Order: " . ($submitOrder ? 'YES' : 'NO (add --submit-order flag)') . "\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

// ===== HELPER FUNCTION =====
function pesapal_request($url, $method, $payload, $token, $label) {
    echo "  → {$label}...\n";
    echo "    URL: {$url}\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($payload) {
            $jsonPayload = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            echo "    Payload: " . substr($jsonPayload, 0, 200) . "\n";
        }
    }
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $elapsed = round((microtime(true) - $start) * 1000);
    
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "    HTTP: {$httpCode} | Time: {$elapsed}ms\n";
    
    if ($curlErrno !== 0) {
        echo "    ❌ CURL ERROR #{$curlErrno}: {$curlError}\n";
        
        // Retry without SSL if it was an SSL error
        if (in_array($curlErrno, [60, 77, 35, 51])) {
            echo "    ⚠️  SSL error detected, retrying without SSL verification...\n";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, 1);
                if ($payload) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
            $response = curl_exec($ch);
            $curlErrno2 = curl_errno($ch);
            $curlError2 = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($curlErrno2 !== 0) {
                echo "    ❌ Still failing: {$curlError2}\n";
                return null;
            }
            echo "    ✅ Succeeded without SSL verification (HTTP {$httpCode})\n";
        } else {
            return null;
        }
    }
    
    $data = json_decode($response, true);
    if ($data === null && !empty($response)) {
        echo "    ❌ Invalid JSON response: " . substr($response, 0, 300) . "\n";
        return null;
    }
    
    return ['http_code' => $httpCode, 'data' => $data, 'elapsed' => $elapsed];
}

// ===================================================================
// STEP 1: AUTHENTICATION
// ===================================================================
echo "━━━ STEP 1: AUTHENTICATION ━━━\n";
$authResult = pesapal_request(
    $BASE_URL . '/api/Auth/RequestToken',
    'POST',
    ['consumer_key' => $CONSUMER_KEY, 'consumer_secret' => $CONSUMER_SECRET],
    null,
    'Requesting JWT token'
);

if (!$authResult || $authResult['http_code'] !== 200 || !isset($authResult['data']['token'])) {
    echo "\n❌ AUTHENTICATION FAILED\n";
    echo "   Response: " . json_encode($authResult['data'] ?? 'null', JSON_PRETTY_PRINT) . "\n";
    echo "\n   Possible causes:\n";
    echo "   - Invalid PESAPAL_CONSUMER_KEY or PESAPAL_CONSUMER_SECRET\n";
    echo "   - Network connectivity issue\n";
    echo "   - Pesapal API is down\n";
    exit(1);
}

$token = $authResult['data']['token'];
$expiryDate = $authResult['data']['expiryDate'] ?? 'unknown';
echo "    ✅ Token obtained! Length: " . strlen($token) . " | Expires: {$expiryDate}\n";
echo "    Token prefix: " . substr($token, 0, 30) . "...\n\n";

// ===================================================================
// STEP 2: REGISTER IPN URL
// ===================================================================
echo "━━━ STEP 2: REGISTER IPN URL ━━━\n";
$ipnResult = pesapal_request(
    $BASE_URL . '/api/URLSetup/RegisterIPN',
    'POST',
    ['url' => $IPN_URL, 'ipn_notification_type' => 'POST'],
    $token,
    'Registering IPN URL'
);

if (!$ipnResult || !isset($ipnResult['data']['ipn_id'])) {
    echo "\n❌ IPN REGISTRATION FAILED\n";
    echo "   Response: " . json_encode($ipnResult['data'] ?? 'null', JSON_PRETTY_PRINT) . "\n";
    echo "\n   Possible causes:\n";
    echo "   - IPN URL is not reachable from the internet\n";
    echo "   - IPN URL format is invalid\n";
    echo "   - Token expired between steps\n";
    exit(1);
}

$ipnId = $ipnResult['data']['ipn_id'];
$ipnStatus = $ipnResult['data']['status'] ?? 'unknown';
echo "    ✅ IPN registered! ID: {$ipnId} | Status: {$ipnStatus}\n\n";

// ===================================================================
// STEP 2b: LIST ALL REGISTERED IPN URLs
// ===================================================================
echo "━━━ STEP 2b: LIST REGISTERED IPN URLs ━━━\n";
$ipnListResult = pesapal_request(
    $BASE_URL . '/api/URLSetup/GetIpnList',
    'GET',
    null,
    $token,
    'Getting IPN list'
);

if ($ipnListResult && $ipnListResult['http_code'] === 200 && is_array($ipnListResult['data'])) {
    echo "    Registered IPN URLs:\n";
    foreach ($ipnListResult['data'] as $i => $ipn) {
        $activeIcon = ($ipn['ipn_id'] === $ipnId) ? ' ← USING THIS' : '';
        echo "      [{$i}] ID: {$ipn['ipn_id']} | URL: {$ipn['url']} | Status: " . ($ipn['ipn_status'] ?? $ipn['status'] ?? '?') . "{$activeIcon}\n";
    }
} else {
    echo "    ⚠️  Could not retrieve IPN list\n";
}
echo "\n";

// ===================================================================
// STEP 3: SUBMIT TEST ORDER (optional)
// ===================================================================
if ($submitOrder) {
    echo "━━━ STEP 3: SUBMIT TEST ORDER ━━━\n";
    $testRef = 'TEST-' . time() . '-' . rand(1000, 9999);
    $orderPayload = [
        'id' => $testRef,
        'currency' => 'UGX',
        'amount' => 500,
        'description' => 'API Test Order - ' . date('Y-m-d H:i:s'),
        'callback_url' => $CALLBACK_URL,
        'notification_id' => $ipnId,
        'billing_address' => [
            'email_address' => 'test@example.com',
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

    $orderResult = pesapal_request(
        $BASE_URL . '/api/Transactions/SubmitOrderRequest',
        'POST',
        $orderPayload,
        $token,
        'Submitting test order'
    );

    if (!$orderResult || $orderResult['http_code'] !== 200 || !isset($orderResult['data']['redirect_url'])) {
        echo "\n❌ ORDER SUBMISSION FAILED\n";
        echo "   HTTP Code: " . ($orderResult['http_code'] ?? 'N/A') . "\n";
        echo "   Response: " . json_encode($orderResult['data'] ?? 'null', JSON_PRETTY_PRINT) . "\n";
        echo "\n   Possible causes:\n";
        echo "   - Duplicate merchant reference\n";
        echo "   - Invalid amount or currency\n";
        echo "   - IPN ID not valid\n";
        echo "   - Token expired\n";
        exit(1);
    }

    $trackingId = $orderResult['data']['order_tracking_id'] ?? 'none';
    $redirectUrl = $orderResult['data']['redirect_url'];
    $merchantRef = $orderResult['data']['merchant_reference'] ?? $testRef;
    
    echo "    ✅ ORDER SUBMITTED SUCCESSFULLY!\n";
    echo "    Tracking ID:        {$trackingId}\n";
    echo "    Merchant Reference: {$merchantRef}\n";
    echo "    Redirect URL:       {$redirectUrl}\n\n";

    // Validate redirect URL
    if (filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
        echo "    ✅ Redirect URL is a valid URL\n";
    } else {
        echo "    ❌ Redirect URL is NOT a valid URL!\n";
    }
    echo "\n";
} else {
    echo "━━━ STEP 3: SUBMIT ORDER ━━━\n";
    echo "    ⏭️  Skipped. Run with --submit-order to test order submission.\n\n";
}

// ===================================================================
// SUMMARY
// ===================================================================
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║                     RESULTS                         ║\n";
echo "╠══════════════════════════════════════════════════════╣\n";
echo "║  Step 1 - Auth:     ✅ PASSED                       ║\n";
echo "║  Step 2 - IPN:      ✅ PASSED (ID: {$ipnId})\n";
if ($submitOrder) {
    echo "║  Step 3 - Order:    ✅ PASSED                       ║\n";
    echo "║                                                      ║\n";
    echo "║  🔗 PAYMENT LINK GENERATED SUCCESSFULLY!             ║\n";
} else {
    echo "║  Step 3 - Order:    ⏭️  SKIPPED                      ║\n";
}
echo "╚══════════════════════════════════════════════════════╝\n";
echo "\nAll tests passed! The Pesapal API is working correctly.\n";
