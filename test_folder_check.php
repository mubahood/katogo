<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Folder Check Test ===\n";

try {
    // Test 1: Check if movies folder exists
    echo "🔍 Testing folder existence check...\n";
    $result = \App\Models\Utils::ensureFirebaseFolder('movies');
    
    if ($result['success']) {
        echo "✅ Folder check successful!\n";
        echo "📂 Folder existed: " . ($result['folder_exists'] ? 'Yes' : 'No') . "\n";
        echo "🆕 Folder created: " . ($result['folder_created'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ Folder check failed: " . $result['error'] . "\n";
    }
    
    // Test 2: Check a new folder that definitely doesn't exist
    $testFolder = 'test_folder_' . time();
    echo "\n🔍 Testing new folder creation: $testFolder\n";
    $result2 = \App\Models\Utils::ensureFirebaseFolder($testFolder);
    
    if ($result2['success']) {
        echo "✅ New folder test successful!\n";
        echo "📂 Folder existed: " . ($result2['folder_exists'] ? 'Yes' : 'No') . "\n";
        echo "🆕 Folder created: " . ($result2['folder_created'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ New folder test failed: " . $result2['error'] . "\n";
    }
    
    // Test 3: Test video upload with folder checking
    echo "\n🎬 Testing video upload with folder checking...\n";
    $videoUrl = 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4';
    
    $uploadResult = \App\Models\Utils::uploadVideoToFirebase(
        $videoUrl, 
        'test_video_with_folder_check', 
        'movies'
    );
    
    if ($uploadResult['success']) {
        echo "✅ VIDEO UPLOAD WITH FOLDER CHECK SUCCESS!\n";
        echo "📁 Firebase Path: " . $uploadResult['firebase_path'] . "\n";
        echo "📊 File Size: " . formatBytes($uploadResult['file_size']) . "\n";
        echo "🔗 Download URL: " . substr($uploadResult['firebase_url'], 0, 80) . "...\n";
    } else {
        echo "❌ Video upload failed: " . $uploadResult['error'] . "\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

echo "\n=== Folder Check Test Complete ===\n";