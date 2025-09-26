<?php

require_once 'vendor/autoload.php';

echo "=== Firebase Bucket Creation Test ===\n";

try {
    // Create Firebase factory directly
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(__DIR__ . '/storage/app/firebase-credentials.json')
        ->withProjectId('ugflix-71aa8');

    echo "✅ Firebase factory created\n";

    // Get storage service
    $storage = $factory->createStorage();
    echo "✅ Storage service created\n";

    // Try different bucket approaches
    echo "\n🔍 Testing different bucket access methods:\n";

    // Method 1: Default bucket
    try {
        $bucket = $storage->getBucket();
        echo "✅ Default bucket accessible: " . $bucket->name() . "\n";
    } catch (\Exception $e) {
        echo "❌ Default bucket failed: " . $e->getMessage() . "\n";
    }

    // Method 2: Explicit default bucket name
    try {
        $bucket = $storage->getBucket('ugflix-71aa8.appspot.com');
        echo "✅ Explicit default bucket accessible: " . $bucket->name() . "\n";
    } catch (\Exception $e) {
        echo "❌ Explicit default bucket failed: " . $e->getMessage() . "\n";
    }

    // Method 3: Your custom bucket
    try {
        $bucket = $storage->getBucket('ugflix-71aa8');
        echo "✅ Custom bucket accessible: " . $bucket->name() . "\n";
    } catch (\Exception $e) {
        echo "❌ Custom bucket failed: " . $e->getMessage() . "\n";
    }

    echo "\n🔧 Testing bucket creation...\n";
    
    // Try to create a small test file in the default bucket
    $bucket = $storage->getBucket();
    
    echo "📝 Attempting small file upload to test permissions...\n";
    
    $testContent = "Test file created at " . date('Y-m-d H:i:s');
    $testPath = 'test/permissions_test_' . time() . '.txt';
    
    try {
        $object = $bucket->upload($testContent, [
            'name' => $testPath,
            'metadata' => [
                'contentType' => 'text/plain'
            ]
        ]);
        
        echo "✅ Small file upload successful! Path: $testPath\n";
        
        // Generate download URL
        $downloadUrl = $object->signedUrl(new \DateTime('+1 hour'));
        echo "🔗 Download URL: $downloadUrl\n";
        
        // Test the URL
        $downloadTest = file_get_contents($downloadUrl);
        if ($downloadTest === $testContent) {
            echo "✅ Download test successful!\n";
        } else {
            echo "⚠️  Download test failed\n";
        }
        
        // Clean up
        $object->delete();
        echo "🗑️  Test file deleted\n";
        
        echo "\n🎉 Bucket permissions are working! Ready for video upload.\n";
        
    } catch (\Exception $e) {
        echo "❌ Upload failed: " . $e->getMessage() . "\n";
        
        // Check if it's a permissions issue
        if (strpos($e->getMessage(), 'storage.objects.create') !== false) {
            echo "\n⚠️  PERMISSIONS ISSUE DETECTED:\n";
            echo "The Firebase service account needs 'Storage Object Admin' permissions.\n";
            echo "Please grant permissions in Google Cloud Console.\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Setup error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";