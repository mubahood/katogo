<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Storage URL Generation Test ===\n";

try {
    // The file path from Firebase Storage
    $firebasePath = 'movies/big_buck_bunny_test.mp4';
    
    echo "🔗 Getting download URL for: $firebasePath\n";
    
    // Method 1: Using our Utils function
    echo "\n📋 Method 1: Using Utils::getFirebaseDownloadUrl()\n";
    $result = \App\Models\Utils::getFirebaseDownloadUrl($firebasePath, 24); // 24 hour expiry
    
    if ($result['success']) {
        echo "✅ Success!\n";
        echo "🔗 Download URL: " . $result['url'] . "\n";
        echo "⏰ Expires at: " . $result['expires_at'] . "\n";
        
        // Test the URL by getting headers
        echo "\n🧪 Testing URL accessibility...\n";
        $headers = get_headers($result['url'], 1);
        if ($headers && strpos($headers[0], '200') !== false) {
            echo "✅ URL is accessible!\n";
            if (isset($headers['Content-Length'])) {
                echo "📊 File size: " . formatBytes($headers['Content-Length']) . "\n";
            }
            if (isset($headers['Content-Type'])) {
                echo "📄 Content type: " . $headers['Content-Type'] . "\n";
            }
        } else {
            echo "⚠️  URL might need authentication\n";
        }
        
    } else {
        echo "❌ Failed: " . $result['error'] . "\n";
    }
    
    // Method 2: Direct Firebase SDK access
    echo "\n📋 Method 2: Direct Firebase SDK access\n";
    
    $storage = app('firebase.storage');
    $bucket = $storage->getBucket(config('firebase.storage.bucket'));
    $object = $bucket->object($firebasePath);
    
    if ($object->exists()) {
        echo "✅ File exists in Firebase Storage\n";
        
        // Get different types of URLs
        echo "\n🔗 URL Options:\n";
        
        // 1. Signed URL (temporary, secure)
        $signedUrl1Hour = $object->signedUrl(new \DateTime('+1 hour'));
        echo "1️⃣ Signed URL (1 hour): " . substr($signedUrl1Hour, 0, 100) . "...\n";
        
        $signedUrl1Day = $object->signedUrl(new \DateTime('+1 day'));
        echo "2️⃣ Signed URL (1 day): " . substr($signedUrl1Day, 0, 100) . "...\n";
        
        $signedUrl1Year = $object->signedUrl(new \DateTime('+1 year'));
        echo "3️⃣ Signed URL (1 year): " . substr($signedUrl1Year, 0, 100) . "...\n";
        
        // Get file info
        $info = $object->info();
        echo "\n📊 File Information:\n";
        echo "   📁 Name: " . $info['name'] . "\n";
        echo "   📏 Size: " . formatBytes($info['size']) . "\n";
        echo "   📅 Created: " . $info['timeCreated'] . "\n";
        echo "   🏷️ Content Type: " . $info['contentType'] . "\n";
        
    } else {
        echo "❌ File not found\n";
    }
    
    // Method 3: List all files in movies folder
    echo "\n📂 All files in movies folder:\n";
    $objects = $bucket->objects(['prefix' => 'movies/']);
    $count = 0;
    foreach ($objects as $obj) {
        if ($obj->name() !== 'movies/') { // Skip folder marker
            $count++;
            $objInfo = $obj->info();
            $downloadUrl = $obj->signedUrl(new \DateTime('+1 day'));
            
            echo "   $count. " . $obj->name() . "\n";
            echo "      📏 Size: " . formatBytes($objInfo['size']) . "\n";
            echo "      🔗 URL: " . substr($downloadUrl, 0, 80) . "...\n";
        }
        
        if ($count >= 5) { // Limit to first 5 files
            echo "   ... (and more)\n";
            break;
        }
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

echo "\n=== URL Generation Complete ===\n";
echo "\n💡 USAGE IN YOUR APP:\n";
echo "\$url = Utils::getFirebaseDownloadUrl('movies/big_buck_bunny_test.mp4', 24);\n";
echo "if (\$url['success']) {\n";
echo "    echo \$url['url']; // Use this URL in your video player\n";
echo "}\n";