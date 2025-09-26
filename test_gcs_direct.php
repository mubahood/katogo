<?php

require_once 'vendor/autoload.php';

echo "=== Google Cloud Storage Direct Bucket Creation ===\n";

try {
    // Create Google Cloud Storage client directly
    $storage = new \Google\Cloud\Storage\StorageClient([
        'projectId' => 'ugflix-71aa8',
        'keyFilePath' => __DIR__ . '/storage/app/firebase-credentials.json'
    ]);

    echo "✅ Google Cloud Storage client created\n";

    $bucketName = 'ugflix-71aa8.appspot.com';
    
    // Check if bucket exists
    $bucket = $storage->bucket($bucketName);
    
    echo "🔍 Checking if bucket exists: $bucketName\n";
    
    if ($bucket->exists()) {
        echo "✅ Bucket exists! Testing upload...\n";
        
        // Test upload
        $testContent = "Test upload at " . date('Y-m-d H:i:s');
        $object = $bucket->upload($testContent, [
            'name' => 'test/upload-test-' . time() . '.txt'
        ]);
        
        echo "✅ SUCCESS! File uploaded to existing bucket!\n";
        echo "📁 File: " . $object->name() . "\n";
        
        // Generate signed URL
        $signedUrl = $object->signedUrl(new DateTime('+1 hour'));
        echo "🔗 Signed URL: " . substr($signedUrl, 0, 80) . "...\n";
        
        // Clean up
        $object->delete();
        echo "🗑️ Test file deleted\n";
        
        echo "\n🎉 BUCKET IS WORKING! Ready for video uploads!\n";
        
    } else {
        echo "❌ Bucket doesn't exist. Attempting to create...\n";
        
        // Try to create bucket
        try {
            $bucket = $storage->createBucket($bucketName, [
                'location' => 'US',
                'storageClass' => 'STANDARD'
            ]);
            
            echo "✅ Bucket created successfully!\n";
            echo "🎉 Ready for uploads!\n";
            
        } catch (\Exception $createError) {
            echo "❌ Failed to create bucket: " . $createError->getMessage() . "\n";
            
            echo "\n💡 MANUAL SOLUTION:\n";
            echo "1. Go to Google Cloud Console > Storage\n";
            echo "2. Create a bucket named: ugflix-71aa8.appspot.com\n";
            echo "3. Choose region: US (or your preferred location)\n";
            echo "4. Use Standard storage class\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";