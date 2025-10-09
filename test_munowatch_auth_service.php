<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MunowatchAuthService;

echo "=== TESTING MUNOWATCH AUTH SERVICE ===\n\n";

/**
 * Test munowatch login functionality
 */
function test_auth_service_login()
{
    echo "1. Testing MunowatchAuthService login...\n";
    
    $result = MunowatchAuthService::login();
    
    if ($result['success']) {
        echo "   ✅ Login successful!\n";
        echo "   User ID: " . $result['user_id'] . "\n";
        echo "   Session ID: " . $result['session_id'] . "\n";
        return $result;
    } else {
        echo "   ❌ Login failed: " . $result['error'] . "\n";
        return false;
    }
}

/**
 * Test API call with auto-refresh
 */
function test_api_call_with_auto_refresh()
{
    echo "\n2. Testing API call with auto-refresh...\n";
    
    // Test URL from routes/web.php
    $testUrl = "https://munowatch.org/api/preview/v2/169464/9467";
    $bearerToken = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0";
    $apiKey = "Api-munowatch-2024";
    
    echo "   URL: $testUrl\n";
    
    try {
        $response = MunowatchAuthService::callApiWithAutoRefresh($testUrl, $bearerToken, $apiKey);
        
        echo "   ✅ API call completed!\n";
        echo "   Response length: " . strlen($response) . " characters\n";
        
        // Check if it's valid JSON
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "   Response is valid JSON\n";
            
            if (isset($data['data'])) {
                echo "   Response contains data object\n";
            }
            
            // Check for authentication errors
            if (MunowatchAuthService::isAuthenticationFailure($response)) {
                echo "   ⚠️  Response indicates authentication failure\n";
            } else {
                echo "   ✅ Authentication appears successful\n";
            }
        } else {
            echo "   ⚠️  Response is not valid JSON\n";
            echo "   Response preview: " . substr($response, 0, 100) . "...\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "   ❌ API call failed: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Test authentication failure detection
 */
function test_auth_failure_detection()
{
    echo "\n3. Testing authentication failure detection...\n";
    
    // Test cases for different error responses
    $testCases = [
        '{"error": "token expired"}' => true,
        '{"message": "unauthorized"}' => true,
        '{"status": 401}' => true,
        '{"data": {"movies": []}}' => false,
        'HTTP/1.1 403 Forbidden' => true,
        '{"success": true}' => false
    ];
    
    foreach ($testCases as $response => $expectedResult) {
        $result = MunowatchAuthService::isAuthenticationFailure($response);
        $status = ($result === $expectedResult) ? "✅" : "❌";
        echo "   $status Response: " . substr($response, 0, 30) . "... Expected: " . ($expectedResult ? 'FAIL' : 'PASS') . ", Got: " . ($result ? 'FAIL' : 'PASS') . "\n";
    }
}

// Run the tests
echo "Starting MunowatchAuthService tests...\n\n";

// Test 1: Login functionality
$loginResult = test_auth_service_login();

// Test 2: API call with auto-refresh
test_api_call_with_auto_refresh();

// Test 3: Authentication failure detection
test_auth_failure_detection();

echo "\n=== TEST COMPLETE ===\n";