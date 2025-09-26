<?php

require_once 'vendor/autoload.php';

// Test Firebase connection with corrected configuration
echo "=== Firebase Storage Connection Test ===\n";

try {
    // Create Firebase factory with service account
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(__DIR__ . '/storage/app/firebase-credentials.json')
        ->withProjectId('ugflix-71aa8');

    echo "✅ Factory created successfully\n";

    // Get storage service
    $storage = $factory->createStorage();
    echo "✅ Storage service created\n";

    // Test bucket access
    $bucket = $storage->getBucket('ugflix-71aa8'); // Using the correct bucket name
    echo "✅ Bucket accessed: " . $bucket->name() . "\n";

    // Test listing files (just to verify bucket access)
    echo "📂 Testing bucket access by listing objects...\n";
    $objects = $bucket->objects(['maxResults' => 5]);
    $count = 0;
    foreach ($objects as $object) {
        echo "  - File: " . $object->name() . "\n";
        $count++;
        if ($count >= 3) break; // Just show first 3 files
    }
    
    if ($count === 0) {
        echo "  (Bucket is empty - this is normal for a new bucket)\n";
    }

    echo "\n🎉 SUCCESS: Firebase Storage is properly configured!\n";
    echo "✅ Project ID: ugflix-71aa8\n";
    echo "✅ Bucket: ugflix-71aa8\n";
    echo "✅ Ready for video uploads\n";

} catch (\Kreait\Firebase\Exception\FirebaseException $e) {
    echo "❌ Firebase Error: " . $e->getMessage() . "\n";
} catch (\Google\Cloud\Core\Exception\ServiceException $e) {
    echo "❌ Google Cloud Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "❌ General Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";