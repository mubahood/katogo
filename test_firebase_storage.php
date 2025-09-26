<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Storage Direct Test ===\n";

try {
    // Create Firebase factory directly (no bucket specification - let Firebase handle it)
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(storage_path('app/firebase-credentials.json'))
        ->withProjectId('ugflix-71aa8');

    echo "✅ Firebase factory created\n";

    // Get Firebase Storage (let it use default bucket)
    $storage = $factory->createStorage();
    echo "✅ Firebase Storage service created\n";

    // Get default bucket (no name specified)
    $bucket = $storage->getBucket();
    echo "✅ Default Firebase bucket: " . $bucket->name() . "\n";

    // Test small upload first
    echo "📤 Testing small file upload...\n";
    $testContent = "Firebase test at " . date('Y-m-d H:i:s');
    
    $object = $bucket->upload($testContent, [
        'name' => 'movies/test-firebase-' . time() . '.txt',
        'metadata' => [
            'contentType' => 'text/plain'
        ]
    ]);
    
    echo "✅ SUCCESS! Small file uploaded to Firebase Storage\n";
    echo "📁 Path: " . $object->name() . "\n";
    
    // Generate signed URL
    $downloadUrl = $object->signedUrl(new \DateTime('+1 hour'));
    echo "🔗 Download URL generated successfully\n";
    
    // Test the URL
    $content = file_get_contents($downloadUrl);
    if ($content === $testContent) {
        echo "✅ Download test successful!\n";
    } else {
        echo "⚠️  Download test failed\n";
    }
    
    // Clean up
    $object->delete();
    echo "🗑️ Test file deleted\n";
    
    echo "\n🎉 Firebase Storage is working! Now testing video upload...\n\n";
    
    // Test video upload
    $videoUrl = 'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4';
    echo "🎬 Uploading Sintel video (17MB)...\n";
    echo "🔗 Source: $videoUrl\n";
    
    // Use the Utils class
    $result = \App\Models\Utils::uploadVideoToFirebase(
        $videoUrl, 
        'sintel_test_' . time(), 
        'movies'
    );
    
    if ($result['success']) {
        echo "✅ VIDEO UPLOAD SUCCESS!\n";
        echo "📁 Firebase Path: " . $result['firebase_path'] . "\n";
        echo "📊 File Size: " . formatBytes($result['file_size']) . "\n";
        echo "🔗 Download URL: " . substr($result['firebase_url'], 0, 100) . "...\n";
        
        echo "\n🎉 FIREBASE STORAGE IS FULLY WORKING!\n";
        echo "📂 Videos are being stored in: movies/ folder\n";
        
    } else {
        echo "❌ Video upload failed: " . $result['error'] . "\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

echo "\n=== Firebase Storage Test Complete ===\n";