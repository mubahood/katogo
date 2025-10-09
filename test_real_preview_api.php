<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Utils;

echo "=== TESTING REAL MUNOWATCH PREVIEW API ===\n\n";

// Test with a real movie ID from the dashboard
$videoId = '35547';
$userId = '169464';
$testUrl = "https://munowatch.org/api/preview/v2/{$userId}/{$videoId}";

// Get the real token from database
$website = \App\Models\MovieCrawlerWebsite::where('slug', 'munowatch')->first();
$realToken = $website ? $website->token : '';

echo "Testing URL: {$testUrl}\n";
echo "Token available: " . (!empty($realToken) ? 'YES' : 'NO') . "\n";
echo "Token length: " . strlen($realToken) . " chars\n\n";

if (empty($realToken)) {
    echo "❌ No token available - cannot test\n";
    exit;
}

$headers = [
    'X-Api-Key' => $realToken,
    'User-Agent' => 'okhttp/4.9.0'
];

echo "Making API call...\n";

try {
    $response = Utils::get_url($testUrl, $headers);
    
    echo "✅ Got response!\n";
    echo "Response length: " . strlen($response) . " chars\n\n";
    
    // Try to parse as JSON
    $json = json_decode($response, true);
    if ($json) {
        echo "✅ Valid JSON response\n";
        echo "Response keys: " . implode(', ', array_keys($json)) . "\n\n";
        
        if (isset($json['preview'])) {
            echo "✅ Preview data found!\n";
            $preview = $json['preview'];
            echo "Preview keys: " . implode(', ', array_keys($preview)) . "\n\n";
            
            // Check for video URLs
            $videoUrlFields = ['playingUrl', 'embedurl', 'openload', 'nxt_playing_url'];
            echo "Video URL Fields:\n";
            foreach ($videoUrlFields as $field) {
                $value = $preview[$field] ?? 'NOT FOUND';
                echo "- {$field}: {$value}\n";
            }
            
            echo "\nOther key fields:\n";
            echo "- video_title: " . ($preview['video_title'] ?? 'NOT FOUND') . "\n";
            echo "- duration: " . ($preview['duration'] ?? 'NOT FOUND') . "\n";
            echo "- genre: " . ($preview['genre'] ?? 'NOT FOUND') . "\n";
            
        } else {
            echo "❌ No preview data in response\n";
            echo "Available keys: " . implode(', ', array_keys($json)) . "\n";
        }
        
    } else {
        echo "❌ Invalid JSON response\n";
        echo "Raw response: " . substr($response, 0, 500) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ API call failed: " . $e->getMessage() . "\n";
    
    // Check if it's a specific error type
    if (strpos($e->getMessage(), '400') !== false) {
        echo "This is a 400 error - likely expired token\n";
    } elseif (strpos($e->getMessage(), '404') !== false) {
        echo "This is a 404 error - endpoint or video not found\n";
    } elseif (strpos($e->getMessage(), '401') !== false) {
        echo "This is a 401 error - unauthorized/invalid token\n";
    }
}

echo "\n🎯 Real API test complete!\n";