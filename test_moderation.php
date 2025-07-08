<?php

// Comprehensive test script for moderation APIs
// Run this in browser: http://localhost:8888/katogo/test_moderation.php

header('Content-Type: text/plain');
echo "=== COMPREHENSIVE MODERATION API TESTING ===\n\n";

$baseUrl = 'http://localhost:8888/katogo/api';

// Test 1: Content Filtering (no authentication required)
echo "=== Testing Content Filtering API ===\n";

// Test clean content
$filterData = [
    'content' => 'Hello world, this is a clean message',
    'content_type' => 'text',
    'user_id' => 1
];

$result = testAPI($baseUrl . '/moderation/filter-content', 'POST', $filterData);
echo "✅ Clean Content Filter: " . ($result['response']['data']['is_violation'] ? 'FAILED' : 'PASSED') . "\n";
echo "   Result: " . $result['response']['data']['message'] . "\n";

// Test profanity detection
$filterData2 = [
    'content' => 'This message contains damn profanity',
    'content_type' => 'text',
    'user_id' => 1
];

$result2 = testAPI($baseUrl . '/moderation/filter-content', 'POST', $filterData2);
echo "✅ Profanity Detection: " . ($result2['response']['data']['is_violation'] ? 'PASSED' : 'FAILED') . "\n";
echo "   Violation Type: " . $result2['response']['data']['violation_type'] . "\n";

// Test hate speech detection
$filterData3 = [
    'content' => 'kill yourself you should die',
    'content_type' => 'text',
    'user_id' => 1
];

$result3 = testAPI($baseUrl . '/moderation/filter-content', 'POST', $filterData3);
echo "✅ Hate Speech Detection: " . ($result3['response']['data']['is_violation'] ? 'PASSED' : 'FAILED') . "\n";
echo "   Violation Type: " . $result3['response']['data']['violation_type'] . "\n";

// Test sexual content detection
$filterData4 = [
    'content' => 'Looking for nude photos and sex',
    'content_type' => 'text',
    'user_id' => 1
];

$result4 = testAPI($baseUrl . '/moderation/filter-content', 'POST', $filterData4);
echo "✅ Sexual Content Detection: " . ($result4['response']['data']['is_violation'] ? 'PASSED' : 'FAILED') . "\n";
echo "   Violation Type: " . $result4['response']['data']['violation_type'] . "\n";

// Test violence detection
$filterData5 = [
    'content' => 'I will kill and murder you',
    'content_type' => 'text',
    'user_id' => 1
];

$result5 = testAPI($baseUrl . '/moderation/filter-content', 'POST', $filterData5);
echo "✅ Violence Detection: " . ($result5['response']['data']['is_violation'] ? 'PASSED' : 'FAILED') . "\n";
echo "   Violation Type: " . $result5['response']['data']['violation_type'] . "\n";

echo "\n=== Testing Authentication Required Endpoints (should fail without auth) ===\n";

// Test report content without auth
$reportData = [
    'reported_content_type' => 'movie',
    'reported_content_id' => 1,
    'reported_user_id' => 2,
    'report_type' => 'inappropriate_content',
    'description' => 'This content is inappropriate'
];

$result = testAPI($baseUrl . '/moderation/report-content', 'POST', $reportData);
echo "✅ Report Content (no auth): " . ($result['response']['code'] == 0 ? 'PASSED' : 'FAILED') . "\n";
echo "   Message: " . $result['response']['message'] . "\n";

// Test block user without auth
$blockData = [
    'blocked_user_id' => 2,
    'reason' => 'Inappropriate behavior'
];

$result = testAPI($baseUrl . '/moderation/block-user', 'POST', $blockData);
echo "✅ Block User (no auth): " . ($result['response']['code'] == 0 ? 'PASSED' : 'FAILED') . "\n";
echo "   Message: " . $result['response']['message'] . "\n";

echo "\n=== Testing Database Tables ===\n";
testDatabaseTables();

echo "\n=== Creating Test Users ===\n";
createTestUsers();

echo "\n=== Testing Authenticated Endpoints with Mock Token ===\n";
testAuthenticatedEndpoints();

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

function testDatabaseTables() {
    try {
        // Database connection - using socket for MAMP
        $host = '127.0.0.1';
        $dbname = 'katogo';
        $username = 'root';
        $password = 'root';
        $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
        
        $pdo = new PDO("mysql:unix_socket=$socket;dbname=$dbname", $username, $password);
        
        // Test content_reports table
        $stmt = $pdo->query("DESCRIBE content_reports");
        if ($stmt) {
            echo "✅ content_reports table exists\n";
        }
        
        // Test user_blocks table
        $stmt = $pdo->query("DESCRIBE user_blocks");
        if ($stmt) {
            echo "✅ user_blocks table exists\n";
        }
        
        // Test content_moderation_logs table
        $stmt = $pdo->query("DESCRIBE content_moderation_logs");
        if ($stmt) {
            echo "✅ content_moderation_logs table exists\n";
        }
        
        // Test admin_users table has new legal fields
        $stmt = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'terms_of_service_accepted'");
        if ($stmt && $stmt->fetch()) {
            echo "✅ admin_users table has legal consent fields\n";
        } else {
            echo "❌ admin_users table missing legal consent fields\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Database connection error: " . $e->getMessage() . "\n";
    }
}

function createTestUsers() {
    try {
        $host = '127.0.0.1';
        $dbname = 'katogo';
        $username = 'root';
        $password = 'root';
        $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
        
        $pdo = new PDO("mysql:unix_socket=$socket;dbname=$dbname", $username, $password);
        
        // Create test users in admin_users table
        $users = [
            ['id' => 1, 'name' => 'Test User 1', 'username' => 'testuser1', 'email' => 'test1@example.com'],
            ['id' => 2, 'name' => 'Test User 2', 'username' => 'testuser2', 'email' => 'test2@example.com'],
            ['id' => 3, 'name' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com']
        ];
        
        foreach ($users as $userData) {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE id = ?");
            $stmt->execute([$userData['id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO admin_users (id, name, username, email, password, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
                $insertStmt->execute([
                    $userData['id'], 
                    $userData['name'], 
                    $userData['username'],
                    $userData['email'], 
                    $hashedPassword
                ]);
                echo "✅ Created user: " . $userData['name'] . "\n";
            } else {
                echo "✅ User already exists: " . $userData['name'] . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error creating test users: " . $e->getMessage() . "\n";
    }
}

function testAuthenticatedEndpoints() {
    global $baseUrl;
    
    echo "Note: Testing auth endpoints without valid tokens (expected to fail)\n";
    
    // Test legal consent update
    $consentData = [
        'terms_of_service_accepted' => 'Yes',
        'privacy_policy_accepted' => 'Yes',
        'community_guidelines_accepted' => 'Yes'
    ];
    
    $result = testAPI($baseUrl . '/moderation/update-legal-consent', 'POST', $consentData);
    echo "✅ Legal Consent Update (no auth): " . ($result['response']['code'] == 0 ? 'PASSED' : 'FAILED') . "\n";
    echo "   Message: " . $result['response']['message'] . "\n";
    
    // Test get blocked users
    $result = testAPI($baseUrl . '/moderation/blocked-users', 'GET');
    echo "✅ Get Blocked Users (no auth): " . ($result['response']['code'] == 0 ? 'PASSED' : 'FAILED') . "\n";
    echo "   Message: " . $result['response']['message'] . "\n";
    
    // Test get user reports
    $result = testAPI($baseUrl . '/moderation/user-reports', 'GET');
    echo "✅ Get User Reports (no auth): " . ($result['response']['code'] == 0 ? 'PASSED' : 'FAILED') . "\n";
    echo "   Message: " . $result['response']['message'] . "\n";
    
    // Test moderation dashboard (admin only)
    $result = testAPI($baseUrl . '/moderation/dashboard', 'GET');
    echo "✅ Moderation Dashboard (no auth): " . ($result['response']['code'] == 0 ? 'PASSED' : 'FAILED') . "\n";
    echo "   Message: " . $result['response']['message'] . "\n";
}

function createTestUser() {
    try {
        $host = '127.0.0.1';
        $dbname = 'katogo';
        $username = 'root';
        $password = 'root';
        $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
        
        $pdo = new PDO("mysql:unix_socket=$socket;dbname=$dbname", $username, $password);
        
        // First, let's see what columns exist in admin_users table
        $stmt = $pdo->query("DESCRIBE admin_users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Admin users table columns: " . implode(', ', $columns) . "\n";
        
        // Check if user with ID 1 exists
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE id = 1");
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (!$user) {
            // Create test user with minimal required fields
            $insertStmt = $pdo->prepare("
                INSERT INTO admin_users (name, username, email, password, created_at, updated_at) 
                VALUES ('Test User', 'testuser', 'test@example.com', ?, NOW(), NOW())
            ");
            $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
            $insertStmt->execute([$hashedPassword]);
            
            // Get the created user ID
            $userId = $pdo->lastInsertId();
            echo "✅ Created test user with ID $userId\n";
        } else {
            echo "✅ Test user with ID 1 already exists\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error creating test user: " . $e->getMessage() . "\n";
    }
}
