<?php

// Quick authentication test using logged_in_user_id header approach
header('Content-Type: text/plain');
echo "=== QUICK MODERATION AUTH TEST ===\n\n";

$baseUrl = 'http://localhost:8888/katogo/api';
$userId = 1; // Using existing admin user

// Test 1: Report Content
echo "Testing Report Content with user ID $userId...\n";
$reportData = [
    'reported_content_type' => 'movie',
    'reported_content_id' => 1,
    'reported_user_id' => 2,
    'report_type' => 'inappropriate_content',
    'description' => 'Test report',
    'logged_in_user_id' => $userId
];

$result = quickTest($baseUrl . '/moderation/report-content', $reportData, $userId);
echo "Report Content: " . ($result['success'] ? 'PASSED' : 'FAILED') . "\n";
echo "Message: " . $result['message'] . "\n\n";

// Test 2: Block User
echo "Testing Block User...\n";
$blockData = [
    'blocked_user_id' => 2,
    'reason' => 'Test block',
    'logged_in_user_id' => $userId
];

$result = quickTest($baseUrl . '/moderation/block-user', $blockData, $userId);
echo "Block User: " . ($result['success'] ? 'PASSED' : 'FAILED') . "\n";
echo "Message: " . $result['message'] . "\n\n";

// Test 3: Update Legal Consent
echo "Testing Legal Consent Update...\n";
$consentData = [
    'terms_of_service_accepted' => 'Yes',
    'privacy_policy_accepted' => 'Yes',
    'logged_in_user_id' => $userId
];

$result = quickTest($baseUrl . '/moderation/update-legal-consent', $consentData, $userId);
echo "Legal Consent: " . ($result['success'] ? 'PASSED' : 'FAILED') . "\n";
echo "Message: " . $result['message'] . "\n\n";

// Test 4: Get Blocked Users
echo "Testing Get Blocked Users...\n";
$result = quickTestGet($baseUrl . '/moderation/blocked-users', $userId);
echo "Get Blocked Users: " . ($result['success'] ? 'PASSED' : 'FAILED') . "\n";
echo "Message: " . $result['message'] . "\n";
if ($result['success'] && isset($result['data'])) {
    echo "Blocked users count: " . count($result['data']) . "\n";
}

function quickTest($url, $data, $userId) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'logged_in_user_id: ' . $userId
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return [
        'success' => $result['code'] == 1,
        'message' => $result['message'] ?? 'Unknown error',
        'data' => $result['data'] ?? null
    ];
}

function quickTestGet($url, $userId) {
    $urlWithParam = $url . '?logged_in_user_id=' . $userId;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlWithParam);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'logged_in_user_id: ' . $userId
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return [
        'success' => $result['code'] == 1,
        'message' => $result['message'] ?? 'Unknown error',
        'data' => $result['data'] ?? null
    ];
}

?>
