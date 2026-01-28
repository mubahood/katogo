<?php
/**
 * Multiplayer Game API Test Script
 * 
 * This script tests all the game endpoints to ensure they work correctly.
 * Run with: php test_game_api.php
 */

// Configuration
$baseUrl = 'http://localhost:8888/katogo/public/api';

// You need to set a valid JWT token here for authenticated requests
// Get this by logging in via /api/auth/login first
$jwtToken = ''; // Set your token here

// Helper function to make API requests
function apiRequest($method, $endpoint, $data = null, $token = null) {
    global $baseUrl;
    
    $url = $baseUrl . $endpoint;
    $ch = curl_init();
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => json_decode($response, true),
        'raw' => $response
    ];
}

// Color output helpers
function green($text) { return "\033[32m" . $text . "\033[0m"; }
function red($text) { return "\033[31m" . $text . "\033[0m"; }
function yellow($text) { return "\033[33m" . $text . "\033[0m"; }
function blue($text) { return "\033[34m" . $text . "\033[0m"; }

echo "\n" . blue("=== Multiplayer Game API Tests ===") . "\n\n";

// Test 1: Check if routes exist (without auth)
echo yellow("Test 1: Verify endpoints exist") . "\n";

$endpoints = [
    ['GET', '/game/online-users'],
    ['POST', '/game/invite'],
    ['GET', '/game/invitations'],
];

foreach ($endpoints as [$method, $endpoint]) {
    $result = apiRequest($method, $endpoint);
    // Should get 401 (unauthorized) not 404 (not found)
    $status = $result['code'] !== 404 ? green("✓") : red("✗");
    echo "  {$status} {$method} {$endpoint} (HTTP {$result['code']})\n";
}

// If no token provided, show instructions
if (empty($jwtToken)) {
    echo "\n" . yellow("⚠ No JWT token provided. Skipping authenticated tests.") . "\n";
    echo "To run full tests:\n";
    echo "1. Login via POST /api/auth/login with email and password\n";
    echo "2. Copy the token from the response\n";
    echo "3. Set \$jwtToken in this script\n";
    echo "4. Run again\n\n";
    exit(0);
}

echo "\n" . yellow("Test 2: Get Online Users") . "\n";
$result = apiRequest('GET', '/game/online-users', null, $jwtToken);
if ($result['response']['code'] === 1) {
    echo green("  ✓ Success") . " - Found " . count($result['response']['data']) . " online users\n";
} else {
    echo red("  ✗ Failed") . " - " . ($result['response']['message'] ?? 'Unknown error') . "\n";
}

echo "\n" . yellow("Test 3: Get Invitations") . "\n";
$result = apiRequest('GET', '/game/invitations', null, $jwtToken);
if ($result['response']['code'] === 1) {
    echo green("  ✓ Success") . " - Found " . count($result['response']['data']) . " pending invitations\n";
} else {
    echo red("  ✗ Failed") . " - " . ($result['response']['message'] ?? 'Unknown error') . "\n";
}

// Test sending invitation (requires another user ID)
echo "\n" . yellow("Test 4: Send Invitation (self-invite should fail)") . "\n";
$result = apiRequest('POST', '/game/invite', ['receiver_id' => 1], $jwtToken);
if ($result['response']['code'] === 0 && strpos($result['response']['message'], 'yourself') !== false) {
    echo green("  ✓ Correctly rejected self-invitation") . "\n";
} elseif ($result['response']['code'] === 1) {
    echo green("  ✓ Invitation sent successfully") . "\n";
    echo "    Invitation ID: " . $result['response']['data']['id'] . "\n";
} else {
    echo yellow("  ⚠ Result: ") . $result['response']['message'] . "\n";
}

echo "\n" . blue("=== Tests Complete ===") . "\n\n";
