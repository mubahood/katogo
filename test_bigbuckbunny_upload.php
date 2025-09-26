<?php

require_once 'vendor/autoload.php';

// Test uploading BigBuckBunny video to Firebase Storage
echo "=== Firebase Storage Video Upload Test ===\n";
echo "Video: BigBuckBunny.mp4\n";
echo "Source: http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4\n\n";

$videoUrl = 'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';

try {
    // Load Laravel app to access Utils class
    require_once 'bootstrap/app.php';
    $app = \Illuminate\Foundation\Application::getInstance();
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    echo "✅ Laravel app loaded\n";

    // Use the Utils class method for uploading
    echo "📤 Starting video upload...\n";
    
    $result = \App\Models\Utils::uploadVideoToFirebase(
        $videoUrl, 
        'big_buck_bunny_test', 
        'test_videos'
    );
    
    if ($result['success']) {
        echo "\n🎉 SUCCESS! Video uploaded to Firebase Storage\n";
        echo "📁 Firebase Path: " . $result['firebase_path'] . "\n";
        echo "🔗 Download URL: " . $result['firebase_url'] . "\n";
        echo "📊 File Size: " . formatBytes($result['file_size']) . "\n";
        
        // Test the download URL
        echo "\n📥 Testing download URL...\n";
        $headers = get_headers($result['firebase_url'], 1);
        if ($headers && strpos($headers[0], '200') !== false) {
            echo "✅ Download URL is accessible\n";
        } else {
            echo "⚠️  Download URL might have issues\n";
        }
        
    } else {
        echo "\n❌ UPLOAD FAILED\n";
        echo "Error: " . $result['error'] . "\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

echo "\n=== Test Complete ===\n";