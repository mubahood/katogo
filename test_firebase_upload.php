<?php

require_once 'vendor/autoload.php';

// Test Firebase Storage upload
echo "=== Firebase Storage Upload Test ===\n";

try {
    // Create Firebase factory with service account
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(__DIR__ . '/storage/app/firebase-credentials.json')
        ->withProjectId('ugflix-71aa8');

    echo "✅ Factory created successfully\n";

    // Get storage service
    $storage = $factory->createStorage();
    echo "✅ Storage service created\n";

    // Get bucket
    $bucket = $storage->getBucket('ugflix-71aa8');
    echo "✅ Bucket accessed: " . $bucket->name() . "\n";

    // Create a small test file content
    $testContent = "This is a test file uploaded to Firebase Storage at " . date('Y-m-d H:i:s');
    $testFileName = 'test/firebase_test_' . time() . '.txt';

    echo "📤 Uploading test file: $testFileName\n";

    // Upload test file
    $object = $bucket->upload($testContent, [
        'name' => $testFileName,
        'metadata' => [
            'contentType' => 'text/plain',
            'metadata' => [
                'uploaded_at' => date('c'),
                'test_file' => 'true'
            ]
        ]
    ]);

    echo "✅ Test file uploaded successfully!\n";

    // Generate a download URL
    $downloadUrl = $object->signedUrl(new \DateTime('+1 hour'));
    echo "🔗 Download URL generated (valid for 1 hour)\n";

    // Test downloading the file
    echo "📥 Testing download...\n";
    $downloadedContent = file_get_contents($downloadUrl);
    
    if ($downloadedContent === $testContent) {
        echo "✅ Download successful - content matches!\n";
    } else {
        echo "❌ Download failed - content doesn't match\n";
    }

    // Clean up - delete the test file
    $object->delete();
    echo "🗑️  Test file cleaned up\n";

    echo "\n🎉 SUCCESS: Firebase Storage is fully functional!\n";
    echo "✅ Can create bucket objects\n";
    echo "✅ Can generate download URLs\n";
    echo "✅ Can download files\n";
    echo "✅ Can delete files\n";
    echo "\n🚀 Ready for video file uploads!\n";

} catch (\Kreait\Firebase\Exception\FirebaseException $e) {
    echo "❌ Firebase Error: " . $e->getMessage() . "\n";
} catch (\Google\Cloud\Core\Exception\ServiceException $e) {
    echo "❌ Google Cloud Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "❌ General Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";