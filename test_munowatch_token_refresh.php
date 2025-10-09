<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Utils;
use App\Models\MovieCrawlerWebsite;

echo "=== TESTING MUNOWATCH LOGIN AND TOKEN REFRESH ===\n\n";

/**
 * Test munowatch login functionality
 */
function test_munowatch_login()
{
    echo "1. Testing munowatch login...\n";
    
    // Login credentials from Flutter app
    $email = 'Jumaperejunior@gmail.com';
    $password = 'uganda7766';
    $version = '3.4.0';
    $deviceInfo = 'Laravel Katogo Crawler';
    $device = 'server';
    
    // Login endpoint
    $loginUrl = 'https://munowatch.org/api/users/login/v2';
    
    // Prepare POST data
    $postData = [
        'email' => $email,
        'password' => $password,
        'version' => $version,
        'deviceinfo' => $deviceInfo,
        'device' => $device
    ];
    
    echo "   Email: $email\n";
    echo "   URL: $loginUrl\n";
    
    // Initialize cURL
    $ch = curl_init();
    
    // Get current token from munowatch website for initial auth
    $munowatchWebsite = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
    $currentToken = $munowatchWebsite ? $munowatchWebsite->token : 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $loginUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Laravel Katogo v3.4',
            'Accept: application/json',
            'Authorization: Bearer ' . $currentToken
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        echo "   ❌ cURL Error: $error\n";
        return false;
    }
    
    if ($httpCode !== 200) {
        echo "   ❌ HTTP Error: $httpCode\n";
        echo "   Response: $response\n";
        return false;
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "   ❌ JSON Error: " . json_last_error_msg() . "\n";
        echo "   Response: $response\n";
        return false;
    }
    
    // Check if login was successful and extract session_id
    if (isset($data['user']['session_id']) && !empty($data['user']['session_id'])) {
        $sessionId = $data['user']['session_id'];
        $userId = $data['user']['id'];
        
        echo "   ✅ Login successful!\n";
        echo "   User ID: $userId\n";
        echo "   Session ID: $sessionId\n";
        echo "   Username: " . $data['user']['username'] . "\n";
        echo "   Email: " . $data['user']['email'] . "\n";
        
        return [
            'success' => true,
            'token' => $sessionId, // Use session_id as token
            'api_key' => 'Api-munowatch-2024',
            'user_id' => $userId
        ];
        
    } else {
        $errorMsg = isset($data['error']) ? $data['error'] : 'Unknown login error';
        echo "   ❌ Login failed: $errorMsg\n";
        echo "   Full response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        return false;
    }
}

/**
 * Test updating munowatch website token
 */
function test_update_munowatch_token($newToken, $apiKey)
{
    echo "\n2. Testing token update in database...\n";
    
    // Find the munowatch website record
    $website = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
    
    if (!$website) {
        echo "   ❌ Munowatch website record not found\n";
        return false;
    }
    
    echo "   Found website: {$website->name} (ID: {$website->id})\n";
    echo "   Current token: " . substr($website->token, 0, 20) . "...\n";
    
    // Update the token and API key
    $website->token = $newToken;
    $website->email = $apiKey; // API key stored in email field
    $website->updated_at = now();
    $website->save();
    
    echo "   ✅ Token updated successfully!\n";
    echo "   New token: " . substr($newToken, 0, 20) . "...\n";
    
    return true;
}

/**
 * Test API call with new token
 */
function test_api_call_with_new_token($token, $apiKey)
{
    echo "\n3. Testing API call with new token...\n";
    
    // Test URL from routes/web.php
    $testUrl = "https://munowatch.org/api/preview/v2/169464/9467";
    
    echo "   URL: $testUrl\n";
    
    try {
        $response = Utils::call_munowatch_api($testUrl, $token, $apiKey);
        
        echo "   ✅ API call successful!\n";
        echo "   Response length: " . strlen($response) . " characters\n";
        
        $data = json_decode($response, true);
        if ($data && isset($data['data'])) {
            echo "   Response contains data object\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "   ❌ API call failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run the tests
echo "Starting munowatch login and token refresh tests...\n\n";

// Test 1: Login and get fresh token
$loginResult = test_munowatch_login();

if ($loginResult && is_array($loginResult)) {
    // Test 2: Update token in database
    $updateResult = test_update_munowatch_token($loginResult['token'], $loginResult['api_key']);
    
    if ($updateResult) {
        // Test 3: Test API call with new token
        test_api_call_with_new_token($loginResult['token'], $loginResult['api_key']);
    }
}

echo "\n=== TEST COMPLETE ===\n";