<?php

require_once 'vendor/autoload.php';

echo "=== Firebase Storage Initialization Test ===\n";

try {
    // Create Firebase factory directly
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(__DIR__ . '/storage/app/firebase-credentials.json')
        ->withProjectId('ugflix-71aa8');

    echo "✅ Firebase factory created\n";

    // Get storage service  
    $storage = $factory->createStorage();
    echo "✅ Storage service created\n";

    // Try to initialize Firebase Storage by creating the default bucket
    echo "\n🚀 Attempting to initialize Firebase Storage...\n";
    
    try {
        // Try different bucket initialization approaches
        echo "Method 1: Default bucket without specifying name...\n";
        $bucket = $storage->getBucket();
        echo "✅ Default bucket: " . $bucket->name() . "\n";
        
        // Try a simple test upload to see what exact error we get
        echo "Testing upload to default bucket...\n";
        $testData = "Test " . date('Y-m-d H:i:s');
        
        $object = $bucket->upload($testData, [
            'name' => 'test-initialization.txt',
            'metadata' => ['contentType' => 'text/plain']
        ]);
        
        echo "✅ SUCCESS! Firebase Storage is working!\n";
        echo "📁 Test file uploaded: test-initialization.txt\n";
        
        // Clean up
        $object->delete();
        echo "🗑️  Test file deleted\n";
        
    } catch (\Exception $e) {
        echo "❌ Default bucket failed: " . $e->getMessage() . "\n";
        
        // Try with explicit project bucket name
        echo "\nMethod 2: Explicit project bucket...\n";
        try {
            $bucket = $storage->getBucket('ugflix-71aa8.appspot.com');
            echo "✅ Project bucket: " . $bucket->name() . "\n";
            
            $testData = "Test " . date('Y-m-d H:i:s');
            $object = $bucket->upload($testData, [
                'name' => 'test-initialization.txt',
                'metadata' => ['contentType' => 'text/plain']
            ]);
            
            echo "✅ SUCCESS! Firebase Storage is working with explicit bucket!\n";
            echo "📁 Test file uploaded: test-initialization.txt\n";
            
            // Clean up
            $object->delete();
            echo "🗑️  Test file deleted\n";
            
        } catch (\Exception $e2) {
            echo "❌ Explicit bucket also failed: " . $e2->getMessage() . "\n";
            
            echo "\n💡 SOLUTION NEEDED:\n";
            echo "Firebase Storage needs to be initialized in Firebase Console:\n";
            echo "1. Go to https://console.firebase.google.com\n";
            echo "2. Select project 'ugflix-71aa8'\n";
            echo "3. Go to Storage in left menu\n";
            echo "4. Click 'Get Started' to initialize Storage\n";
            echo "5. Choose your preferred region\n";
            echo "6. Set security rules (can start with test mode)\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Setup error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";