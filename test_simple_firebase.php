<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Storage Quick Test ===\n";

try {
    echo "🔧 Creating Firebase factory...\n";
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(storage_path('app/firebase-credentials.json'))
        ->withProjectId('ugflix-71aa8');
        
    $storage = $factory->createStorage();
    echo "✅ Firebase storage created\n";
    
    // Test without specifying bucket (let Firebase use default)
    echo "📤 Testing upload without bucket specification...\n";
    
    $testContent = "Firebase test - " . date('Y-m-d H:i:s') . "\nFolder checking enabled!";
    
    // Try to upload directly without getBucket() call
    $bucket = $storage->getBucket(); // This should work if Firebase Storage is initialized
    echo "✅ Got default bucket: " . $bucket->name() . "\n";
    
    // Now test uploading to movies folder
    $object = $bucket->upload($testContent, [
        'name' => 'movies/test-' . time() . '.txt',
        'metadata' => [
            'contentType' => 'text/plain',
            'metadata' => [
                'uploaded_at' => date('Y-m-d H:i:s'),
                'purpose' => 'folder_creation_test'
            ]
        ]
    ]);
    
    echo "✅ SUCCESS! File uploaded to movies folder\n";
    echo "📁 Path: " . $object->name() . "\n";
    
    // Generate signed URL
    $downloadUrl = $object->signedUrl(new \DateTime('+1 hour'));
    echo "🔗 Download URL generated\n";
    
    // Test the download
    $content = file_get_contents($downloadUrl);
    if (strpos($content, 'Firebase test') !== false) {
        echo "✅ Download test successful!\n";
    }
    
    // List files in movies folder
    echo "\n📂 Files in movies folder:\n";
    $objects = $bucket->objects(['prefix' => 'movies/']);
    $count = 0;
    foreach ($objects as $obj) {
        echo "  - " . $obj->name() . "\n";
        $count++;
        if ($count >= 5) { // Limit to first 5 files
            echo "  ... (and more)\n";
            break;
        }
    }
    
    if ($count === 0) {
        echo "  (no files found)\n";
    }
    
    echo "\n🎉 Firebase Storage is working! Movies folder exists and is accessible.\n";
    echo "📋 You can now upload videos to the movies folder.\n";
    
    // Clean up test file
    $object->delete();
    echo "🗑️ Test file cleaned up\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 TROUBLESHOOTING:\n";
    echo "If you get 'bucket does not exist' error:\n";
    echo "1. Go to: https://console.firebase.google.com/project/ugflix-71aa8/storage\n";
    echo "2. Initialize Firebase Storage if not done yet\n";
    echo "3. Make sure Storage is enabled for your project\n";
    echo "4. Check that your service account has Storage permissions\n";
}

echo "\n=== Test Complete ===\n";