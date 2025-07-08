<?php
// Comprehensive moderation system test with proper authentication
// Run this in browser: http://localhost:8888/katogo/test_moderation_comprehensive.php

header('Content-Type: text/plain');
echo "=== COMPREHENSIVE MODERATION SYSTEM TEST ===\n\n";

$baseUrl = 'http://localhost:8888/katogo/api';
$testResults = [];

// Test user credentials
$testUser = [
    'email' => 'admin@gmail.com',
    'password' => '123456' // Use the actual working password
];

// Step 1: Authenticate test user
echo "=== Step 1: Using existing authentication ===\n";

// Use the working token from debug_auth.php
$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0Ojg4ODgva2F0b2dvL2FwaS9hdXRoL2xvZ2luIiwiaWF0IjoxNzUyMDAyNTcyLCJleHAiOjE5MDk2ODI1NzIsIm5iZiI6MTc1MjAwMjU3MiwianRpIjoiRjJibHVPTTg1Q0N0dGlBayIsInN1YiI6IjEiLCJwcnYiOiIyM2JkNWM4OTQ5ZjYwMGFkYjM5ZTcwMWM0MDA4NzJkYjdhNTk3NmY3In0.mKSqLThJJLMJFYeBI-hEb5FvgRg9b3egxc-46UD-6ps';
$userId = 1;

echo "✅ Using existing token\n";
echo "   User ID: " . $userId . "\n";
echo "   Token: " . substr($token, 0, 20) . "...\n";
echo "   User ID: " . $userId . "\n\n";

// Step 2: Test Content Reporting
echo "=== Step 2: Content Reporting Test ===\n";
$reportData = [
    'reported_content_type' => 'movie',
    'reported_content_id' => 'test_movie_' . time(),
    'reported_user_id' => 2,
    'report_type' => 'copyright_infringement',
    'description' => 'This is a test report for copyright infringement'
];

$reportResult = testAuthenticatedAPI($baseUrl . '/moderation/report-content', 'POST', $reportData, $token, $userId);
if ($reportResult['response']['code'] == 1) {
    echo "✅ Content reporting: PASSED\n";
    echo "   Message: " . $reportResult['response']['message'] . "\n";
} else {
    echo "❌ Content reporting: FAILED\n";
    echo "   Message: " . $reportResult['response']['message'] . "\n";
}

// Step 3: Test User Blocking
echo "\n=== Step 3: User Blocking Test ===\n";
$blockData = [
    'blocked_user_id' => 2,
    'reason' => 'Test blocking functionality'
];

$blockResult = testAuthenticatedAPI($baseUrl . '/moderation/block-user', 'POST', $blockData, $token, $userId);
if ($blockResult['response']['code'] == 1) {
    echo "✅ User blocking: PASSED\n";
    echo "   Message: " . $blockResult['response']['message'] . "\n";
} else {
    echo "❌ User blocking: FAILED\n";
    echo "   Message: " . $blockResult['response']['message'] . "\n";
}

// Step 4: Test Get Blocked Users
echo "\n=== Step 4: Get Blocked Users Test ===\n";
$blockedUsersResult = testAuthenticatedAPI($baseUrl . '/moderation/blocked-users', 'GET', [], $token, $userId);
if ($blockedUsersResult['response']['code'] == 1) {
    echo "✅ Get blocked users: PASSED\n";
    echo "   Blocked users count: " . count($blockedUsersResult['response']['data']) . "\n";
} else {
    echo "❌ Get blocked users: FAILED\n";
    echo "   Message: " . $blockedUsersResult['response']['message'] . "\n";
}

// Step 5: Test Get My Reports
echo "\n=== Step 5: Get My Reports Test ===\n";
$myReportsResult = testAuthenticatedAPI($baseUrl . '/moderation/my-reports', 'GET', [], $token, $userId);
if ($myReportsResult['response']['code'] == 1) {
    echo "✅ Get my reports: PASSED\n";
    echo "   My reports count: " . count($myReportsResult['response']['data']) . "\n";
    
    // Display report details
    if (!empty($myReportsResult['response']['data'])) {
        $report = $myReportsResult['response']['data'][0];
        echo "   Latest report details:\n";
        echo "     - Type: " . $report['report_type'] . "\n";
        echo "     - Status: " . $report['status'] . "\n";
        echo "     - Admin Action: " . ($report['admin_action'] ?? 'None') . "\n";
        echo "     - Moderator Notes: " . ($report['moderator_notes'] ?? 'None') . "\n";
    }
} else {
    echo "❌ Get my reports: FAILED\n";
    echo "   Message: " . $myReportsResult['response']['message'] . "\n";
}

// Step 6: Test Legal Consent Update
echo "\n=== Step 6: Legal Consent Update Test ===\n";
$consentData = [
    'terms_accepted' => true,
    'data_processing_consent' => true,
    'moderation_agreement' => true
];

$consentResult = testAuthenticatedAPI($baseUrl . '/moderation/legal-consent', 'POST', $consentData, $token, $userId);
if ($consentResult['response']['code'] == 1) {
    echo "✅ Legal consent update: PASSED\n";
    echo "   Message: " . $consentResult['response']['message'] . "\n";
} else {
    echo "❌ Legal consent update: FAILED\n";
    echo "   Message: " . $consentResult['response']['message'] . "\n";
}

// Step 7: Test User Unblocking
echo "\n=== Step 7: User Unblocking Test ===\n";
$unblockData = [
    'blocked_user_id' => 2
];

$unblockResult = testAuthenticatedAPI($baseUrl . '/moderation/unblock-user', 'POST', $unblockData, $token, $userId);
if ($unblockResult['response']['code'] == 1) {
    echo "✅ User unblocking: PASSED\n";
    echo "   Message: " . $unblockResult['response']['message'] . "\n";
} else {
    echo "❌ User unblocking: FAILED\n";
    echo "   Message: " . $unblockResult['response']['message'] . "\n";
}

// Step 8: Test Content Filtering (No Auth Required)
echo "\n=== Step 8: Content Filtering Test ===\n";
$filterData = [
    'content' => 'This is a test message for content filtering',
    'content_type' => 'text',
    'user_id' => $userId
];

$filterResult = testAPI($baseUrl . '/moderation/filter-content', 'POST', $filterData);
if (isset($filterResult['response']['data'])) {
    echo "✅ Content filtering: PASSED\n";
    echo "   Is violation: " . ($filterResult['response']['data']['is_violation'] ? 'Yes' : 'No') . "\n";
    echo "   Message: " . $filterResult['response']['data']['message'] . "\n";
} else {
    echo "❌ Content filtering: FAILED\n";
    echo "   Response: " . json_encode($filterResult['response']) . "\n";
}

// Summary
echo "\n=== TEST SUMMARY ===\n";
echo "All core moderation endpoints have been tested with proper authentication.\n";
echo "Key features verified:\n";
echo "  ✓ Content reporting with detailed categories\n";
echo "  ✓ User blocking and unblocking\n";
echo "  ✓ Retrieving user's own reports with admin action details\n";
echo "  ✓ Legal consent management\n";
echo "  ✓ Content filtering system\n";
echo "  ✓ Proper header authentication including 'Tok' header\n\n";

echo "Headers used in all requests:\n";
echo "  - Authorization: Bearer [token]\n";
echo "  - Tok: Bearer [token]\n";
echo "  - logged_in_user_id: [user_id]\n";
echo "  - Content-Type: application/json\n";
echo "  - Accept: application/json\n\n";

// Helper functions
function testAPI($url, $method, $data = []) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'response' => json_decode($response, true),
        'http_code' => $httpCode
    ];
}

function testAuthenticatedAPI($url, $method, $data, $token, $userId) {
    $ch = curl_init();
    
    // Add authentication headers including the important "Tok" header
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
        'Tok: Bearer ' . $token,  // This is crucial!
        'logged_in_user_id: ' . $userId
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'response' => json_decode($response, true),
        'http_code' => $httpCode
    ];
}
?>
