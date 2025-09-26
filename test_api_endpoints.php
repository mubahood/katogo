<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Storage API Endpoint Test ===\n";

try {
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(storage_path('app/firebase-credentials.json'))
        ->withProjectId('ugflix-71aa8');
        
    $storage = $factory->createStorage();
    echo "✅ Firebase storage service created\n";
    
    // Test different bucket naming patterns that might work
    $bucketVariations = [
        'ugflix-71aa8.appspot.com',          // Legacy Google Cloud Storage
        'ugflix-71aa8.firebasestorage.app',  // New Firebase Storage
        'ugflix-71aa8',                      // Project ID only
        null                                 // Default bucket
    ];
    
    echo "\n🔍 Testing different bucket configurations...\n";
    
    foreach ($bucketVariations as $bucketName) {
        echo "\n" . str_repeat("-", 50) . "\n";
        if ($bucketName === null) {
            echo "🧪 Testing DEFAULT bucket (no name specified)\n";
        } else {
            echo "🧪 Testing bucket: $bucketName\n";
        }
        
        try {
            // Get bucket
            if ($bucketName === null) {
                $bucket = $storage->getBucket();
            } else {
                $bucket = $storage->getBucket($bucketName);
            }
            
            echo "✅ Bucket connection successful: " . $bucket->name() . "\n";
            
            // Test basic operations
            echo "📤 Testing upload...\n";
            $testContent = "Test upload - " . date('Y-m-d H:i:s');
            $testFileName = 'test-' . time() . '.txt';
            
            $object = $bucket->upload($testContent, [
                'name' => $testFileName,
                'metadata' => [
                    'contentType' => 'text/plain'
                ]
            ]);
            
            echo "✅ Upload successful! File: " . $object->name() . "\n";
            
            // Test folder operations
            echo "📂 Testing folder operations...\n";
            
            // Create movies folder if it doesn't exist
            try {
                $moviesObject = $bucket->upload('', [
                    'name' => 'movies/.keep',
                    'metadata' => [
                        'contentType' => 'text/plain'
                    ]
                ]);
                echo "✅ Movies folder created/verified\n";
            } catch (\Exception $e) {
                echo "ℹ️  Movies folder operation: " . $e->getMessage() . "\n";
            }
            
            // List files in movies folder
            echo "📋 Listing files in movies folder...\n";
            $objects = $bucket->objects(['prefix' => 'movies/']);
            $count = 0;
            foreach ($objects as $obj) {
                echo "  📄 " . $obj->name() . "\n";
                $count++;
                if ($count >= 5) break; // Limit to first 5
            }
            
            if ($count === 0) {
                echo "  (No files found in movies folder)\n";
            }
            
            // Test video upload to movies folder
            echo "🎬 Testing video upload to movies folder...\n";
            $videoUrl = 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4';
            
            // Download video content
            $videoContent = file_get_contents($videoUrl);
            if ($videoContent !== false) {
                $videoFileName = 'movies/test-video-' . time() . '.mp4';
                $videoObject = $bucket->upload($videoContent, [
                    'name' => $videoFileName,
                    'metadata' => [
                        'contentType' => 'video/mp4'
                    ]
                ]);
                
                echo "✅ Video upload successful! File: " . $videoObject->name() . "\n";
                
                // Generate download URL
                $downloadUrl = $videoObject->signedUrl(new \DateTime('+1 hour'));
                echo "🔗 Download URL generated (expires in 1 hour)\n";
                echo "   " . substr($downloadUrl, 0, 100) . "...\n";
                
                echo "\n🎉 THIS BUCKET CONFIGURATION WORKS PERFECTLY!\n";
                echo "✅ Bucket: " . ($bucketName ?: 'DEFAULT') . "\n";
                echo "✅ Upload: Working\n";
                echo "✅ Folder operations: Working\n";
                echo "✅ Video upload: Working\n";
                echo "✅ Download URLs: Working\n";
                
                // Clean up test files
                echo "\n🧹 Cleaning up test files...\n";
                $object->delete();
                $videoObject->delete();
                echo "✅ Test files cleaned up\n";
                
                // Update .env file with working configuration
                echo "\n📝 This is the correct bucket configuration!\n";
                echo "Use bucket name: " . ($bucketName ?: 'DEFAULT (no name)') . "\n";
                
                break; // Stop testing once we find a working configuration
                
            } else {
                echo "❌ Could not download test video\n";
            }
            
        } catch (\Exception $e) {
            echo "❌ Failed: " . $e->getMessage() . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ Firebase initialization error: " . $e->getMessage() . "\n";
}

echo "\n=== API Endpoint Test Complete ===\n";