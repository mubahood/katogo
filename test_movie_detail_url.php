<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Utils;

echo "=== TESTING MOVIE DETAIL ENDPOINT ===\n\n";

$testUrl = 'https://munowatch.org/api/preview/v2/3522/169464';
$headers = [
    'X-Api-Key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0',
    'User-Agent' => 'okhttp/4.9.0'
];

echo "Testing URL: {$testUrl}\n\n";

try {
    $response = Utils::get_url($testUrl, $headers);
    
    echo "✅ SUCCESS: Got response\n";
    echo "Response length: " . strlen($response) . " chars\n";
    
    // Parse JSON to see structure
    $json = json_decode($response, true);
    if ($json) {
        echo "Response structure:\n";
        echo "Keys: " . implode(', ', array_keys($json)) . "\n\n";
        
        if (isset($json['preview'])) {
            echo "Preview data found!\n";
            $preview = $json['preview'];
            echo "Preview keys: " . implode(', ', array_keys($preview)) . "\n";
            echo "Title: " . ($preview['video_title'] ?? 'N/A') . "\n";
            echo "Duration: " . ($preview['duration'] ?? 'N/A') . "\n";
            echo "Genre: " . ($preview['genre'] ?? 'N/A') . "\n";
        }
    } else {
        echo "Failed to parse JSON: " . json_last_error_msg() . "\n";
        echo "Raw response: " . substr($response, 0, 500) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n🎯 Movie detail URL test complete!\n";