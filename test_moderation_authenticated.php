<?php

// Authenticated moderation API testing
// This requires a valid JWT token from the login endpoint

header('Content-Type: text/plain');
echo "=== AUTHENTICATED MODERATION API TESTING ===\n\n";

$baseUrl = 'http://localhost:8888/katogo/api';

// Step 1: Login to get JWT token
echo "=== Authenticating Test User ===\n";
$loginData = [
    'email' => 'admin@gmail.com',
    'password' => 'password123'
];

$loginResult = testAPI($baseUrl . '/auth/login', 'POST', $loginData);
if ($loginResult['response']['code'] != 1) {
    echo "❌ Login failed: " . $loginResult['response']['message'] . "\n";
    echo "Response: " . json_encode($loginResult['response'], JSON_PRETTY_PRINT) . "\n";
    exit;
}

// Handle different possible token locations in response
$token = null;
$user = null;

if (isset($loginResult['response']['data']['token'])) {
    $token = $loginResult['response']['data']['token'];
    $user = $loginResult['response']['data']['user'] ?? null;
} elseif (isset($loginResult['response']['data']['access_token'])) {
    $token = $loginResult['response']['data']['access_token'];
    $user = $loginResult['response']['data']['user'] ?? null;
} elseif (isset($loginResult['response']['token'])) {
    $token = $loginResult['response']['token'];
    $user = $loginResult['response']['user'] ?? null;
} elseif (isset($loginResult['response']['data']['user']['token'])) {
    // Token is inside the user object
    $token = $loginResult['response']['data']['user']['token'];
    $user = $loginResult['response']['data']['user'];
} else {
    // Print full response to debug
    echo "❌ Token not found in response\n";
    echo "Full response: " . json_encode($loginResult['response'], JSON_PRETTY_PRINT) . "\n";
    exit;
}

echo "✅ Login successful for user: " . ($user['name'] ?? 'Unknown') . "\n";
echo "   Token: " . substr($token, 0, 20) . "...\n";
$userId = $user['id'];
echo "   User ID: " . $userId . "\n\n";

// Step 2: Test authenticated moderation endpoints
echo "=== Testing Authenticated Moderation Endpoints ===\n";

// Test report content
$reportData = [
    'reported_content_type' => 'movie',
    'reported_content_id' => 1,
    'reported_user_id' => 2,
    'report_type' => 'inappropriate_content',
    'description' => 'This movie contains inappropriate content'
];

$result = testAuthenticatedAPI($baseUrl . '/moderation/report-content', 'POST', $reportData, $token, $userId);
echo "✅ Report Content: " . ($result['response']['code'] == 1 ? 'PASSED' : 'FAILED') . "\n";
echo "   Message: " . $result['response']['message'] . "\n";

// Test block user
$blockData = [
    'blocked_user_id' => 2,
    'reason' => 'Inappropriate behavior',
    'block_type' => 'user_initiated'
];

$result = testAuthenticatedAPI($baseUrl . '/moderation/block-user', 'POST', $blockData, $token, $userId);
echo "✅ Block User: " . ($result['response']['code'] == 1 ? 'PASSED' : 'FAILED') . "\n";
echo "   Message: " . $result['response']['message'] . "\n";

// Test get blocked users
$result = testAuthenticatedAPI($baseUrl . '/moderation/blocked-users', 'GET', null, $token, $userId);
echo "✅ Get Blocked Users: " . ($result['response']['code'] == 1 ? 'PASSED' : 'FAILED') . "\n";
echo "   Message: " . $result['response']['message'] . "\n";
if ($result['response']['code'] == 1 && is_array($result['response']['data'])) {
    echo "   Blocked users count: " . count($result['response']['data']) . "\n";
}

// Test unblock user
$unblockData = [
    'blocked_user_id' => 2
];

$result = testAuthenticatedAPI($baseUrl . '/moderation/unblock-user', 'POST', $unblockData, $token, $userId);
echo "✅ Unblock User: " . ($result['response']['code'] == 1 ? 'PASSED' : 'FAILED') . "\n";
echo "   Message: " . $result['response']['message'] . "\n";

// Test get user reports
$result = testAuthenticatedAPI($baseUrl . '/moderation/user-reports', 'GET', null, $token, $userId);
echo "✅ Get User Reports: " . ($result['response']['code'] == 1 ? 'PASSED' : 'FAILED') . "\n";
echo "   Message: " . $result['response']['message'] . "\n";
if ($result['response']['code'] == 1 && is_array($result['response']['data'])) {
    echo "   Reports count: " . count($result['response']['data']) . "\n";
}

// Test update legal consent
$consentData = [
    'terms_of_service_accepted' => 'Yes',
    'privacy_policy_accepted' => 'Yes',
    'community_guidelines_accepted' => 'Yes',
    'content_moderation_consent' => 'Yes'
];

$result = testAuthenticatedAPI($baseUrl . '/moderation/update-legal-consent', 'POST', $consentData, $token, $userId);
echo "✅ Update Legal Consent: " . ($result['response']['code'] == 1 ? 'PASSED' : 'FAILED') . "\n";
echo "   Message: " . $result['response']['message'] . "\n";

// Test moderation dashboard (should fail unless user is admin)
$result = testAuthenticatedAPI($baseUrl . '/moderation/dashboard', 'GET', null, $token, $userId);
echo "✅ Moderation Dashboard (non-admin): " . ($result['response']['code'] == 0 ? 'PASSED' : 'FAILED') . "\n";
echo "   Message: " . $result['response']['message'] . "\n";

echo "\n=== Testing with Admin User ===\n";

// Login as admin user (assuming user ID 3 is admin)
$adminLoginData = [
    'email' => 'admin@example.com',
    'password' => 'password123'
];

$adminLoginResult = testAPI($baseUrl . '/auth/login', 'POST', $adminLoginData);
if ($adminLoginResult['response']['code'] == 1) {
    // Extract admin token using same logic
    $adminToken = null;
    $adminUser = null;
    
    if (isset($adminLoginResult['response']['data']['user']['token'])) {
        $adminToken = $adminLoginResult['response']['data']['user']['token'];
        $adminUser = $adminLoginResult['response']['data']['user'];
    } elseif (isset($adminLoginResult['response']['data']['token'])) {
        $adminToken = $adminLoginResult['response']['data']['token'];
        $adminUser = $adminLoginResult['response']['data']['user'] ?? null;
    }
    
    $adminUserId = $adminUser['id'] ?? null;
    
    echo "✅ Admin login successful\n";
    
    // Test admin dashboard access
    $result = testAuthenticatedAPI($baseUrl . '/moderation/dashboard', 'GET', null, $adminToken, $adminUserId);
    echo "✅ Admin Dashboard Access: " . ($result['response']['code'] == 1 ? 'PASSED' : 'FAILED') . "\n";
    echo "   Message: " . $result['response']['message'] . "\n";
    
    if ($result['response']['code'] == 1 && isset($result['response']['data']['stats'])) {
        $stats = $result['response']['data']['stats'];
        echo "   Dashboard Stats:\n";
        echo "     - Total pending reports: " . $stats['total_pending'] . "\n";
        echo "     - Overdue reports: " . $stats['overdue_reports'] . "\n";
        echo "     - High priority: " . $stats['high_priority'] . "\n";
        echo "     - Avg response time: " . $stats['average_response_time'] . " hours\n";
    }
} else {
    echo "❌ Admin login failed: " . $adminLoginResult['response']['message'] . "\n";
}

echo "\n=== Testing Content Moderation Logs ===\n";
testModerationLogs();

// Helper functions
function testAPI($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'raw_response' => $response
    ];
}

function testAuthenticatedAPI($url, $method = 'GET', $data = null, $token = null, $userId = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'Tok: Bearer ' . $token;
    }
    
    if ($userId) {
        $headers[] = 'logged_in_user_id: ' . $userId;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            // Add logged_in_user_id to the body data as well
            if ($userId && is_array($data)) {
                $data['logged_in_user_id'] = $userId;
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'raw_response' => $response
    ];
}

function testModerationLogs() {
    try {
        $host = '127.0.0.1';
        $dbname = 'katogo';
        $username = 'root';
        $password = 'root';
        $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
        
        $pdo = new PDO("mysql:unix_socket=$socket;dbname=$dbname", $username, $password);
        
        // Check moderation logs
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM content_moderation_logs");
        $result = $stmt->fetch();
        echo "✅ Content moderation logs: " . $result['count'] . " entries\n";
        
        // Check recent log entries
        $stmt = $pdo->query("
            SELECT action_type, automated, severity_level, created_at 
            FROM content_moderation_logs 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $logs = $stmt->fetchAll();
        
        if (count($logs) > 0) {
            echo "   Recent log entries:\n";
            foreach ($logs as $log) {
                $automated = $log['automated'] ? 'Auto' : 'Manual';
                echo "     - {$log['action_type']} ({$automated}, {$log['severity_level']}) at {$log['created_at']}\n";
            }
        }
        
        // Check content reports
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM content_reports");
        $result = $stmt->fetch();
        echo "✅ Content reports: " . $result['count'] . " entries\n";
        
        // Check user blocks
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_blocks");
        $result = $stmt->fetch();
        echo "✅ User blocks: " . $result['count'] . " entries\n";
        
    } catch (Exception $e) {
        echo "❌ Error checking moderation logs: " . $e->getMessage() . "\n";
    }
}

?>
