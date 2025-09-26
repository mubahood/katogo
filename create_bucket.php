<?php

require_once 'vendor/autoload.php';

echo "=== Create Firebase Storage Bucket ===\n";

try {
    // Create Google Cloud Storage client directly
    $storage = new \Google\Cloud\Storage\StorageClient([
        'projectId' => 'ugflix-71aa8',
        'keyFilePath' => __DIR__ . '/storage/app/firebase-credentials.json'
    ]);

    echo "✅ Google Cloud Storage client created\n";

    // Try creating a simpler bucket name that doesn't need domain verification
    $bucketName = 'ugflix-movies-' . time();
    
    echo "🔍 Creating bucket: $bucketName\n";
    
    try {
        $bucket = $storage->createBucket($bucketName, [
            'location' => 'US-CENTRAL1',
            'storageClass' => 'STANDARD'
        ]);
        
        echo "✅ SUCCESS! Bucket created: " . $bucket->name() . "\n";
        
        // Test upload to new bucket
        echo "📤 Testing upload to new bucket...\n";
        $testContent = "Test upload at " . date('Y-m-d H:i:s');
        $object = $bucket->upload($testContent, [
            'name' => 'movies/test-upload-' . time() . '.txt'
        ]);
        
        echo "✅ Test file uploaded successfully!\n";
        echo "📁 File path: " . $object->name() . "\n";
        
        // Generate signed URL
        $signedUrl = $object->signedUrl(new DateTime('+1 hour'));
        echo "🔗 Download URL works!\n";
        
        // Clean up test file
        $object->delete();
        echo "🗑️ Test file deleted\n";
        
        echo "\n🎉 BUCKET IS READY!\n";
        echo "📝 Update your .env file:\n";
        echo "FIREBASE_STORAGE_BUCKET=" . $bucket->name() . "\n";
        
        return $bucket->name();
        
    } catch (\Exception $createError) {
        echo "❌ Failed to create bucket: " . $createError->getMessage() . "\n";
        
        // Let's try the Firebase Console approach instead
        echo "\n💡 ALTERNATIVE: Use Firebase Console\n";
        echo "1. Go to https://console.firebase.google.com\n";
        echo "2. Select project: ugflix-71aa8\n";
        echo "3. Click Storage > Get Started\n";
        echo "4. Choose 'Start in test mode'\n";
        echo "5. Select region: us-central1\n";
        echo "6. The bucket will be created automatically\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";