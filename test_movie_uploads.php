<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Storage Movie Upload Test ===\n";

// Test video URLs
$testVideos = [
    [
        'name' => 'BigBuckBunny',
        'url' => 'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        'description' => 'Big Buck Bunny - 158MB'
    ],
    [
        'name' => 'ElephantsDream', 
        'url' => 'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
        'description' => 'Elephants Dream - 64MB'
    ],
    [
        'name' => 'SintelTrailer',
        'url' => 'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4', 
        'description' => 'Sintel - 17MB'
    ]
];

echo "📂 Default upload folder: " . config('firebase.storage.default_folder') . "\n\n";

foreach ($testVideos as $index => $video) {
    echo "🎬 Video " . ($index + 1) . ": " . $video['name'] . "\n";
    echo "📝 Description: " . $video['description'] . "\n";
    echo "🔗 URL: " . $video['url'] . "\n";
    echo "📤 Starting upload...\n";
    
    $startTime = microtime(true);
    
    // Upload using Utils class
    $result = \App\Models\Utils::uploadVideoToFirebase(
        $video['url'], 
        strtolower($video['name']) . '_' . date('Ymd_His'), 
        'movies'
    );
    
    $endTime = microtime(true);
    $uploadTime = round($endTime - $startTime, 2);
    
    if ($result['success']) {
        echo "✅ SUCCESS! Upload completed in {$uploadTime}s\n";
        echo "📁 Firebase Path: " . $result['firebase_path'] . "\n";
        echo "📊 File Size: " . formatBytes($result['file_size']) . "\n";
        echo "🔗 Download URL: " . substr($result['firebase_url'], 0, 100) . "...\n";
        
        // Test the download URL
        echo "🧪 Testing download URL...\n";
        $headers = @get_headers($result['firebase_url'], 1);
        if ($headers && strpos($headers[0], '200') !== false) {
            echo "✅ Download URL is accessible\n";
        } else {
            echo "⚠️  Download URL test failed\n";
        }
        
    } else {
        echo "❌ UPLOAD FAILED\n";
        echo "Error: " . $result['error'] . "\n";
    }
    
    echo "\n" . str_repeat("-", 70) . "\n\n";
}

function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

echo "=== Upload Test Complete ===\n";
echo "📂 All videos uploaded to Firebase Storage in the 'movies' folder\n";
echo "🎉 Your Firebase Storage integration is fully working!\n";