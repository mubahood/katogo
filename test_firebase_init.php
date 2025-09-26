<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Storage Initialization Guide ===\n";

echo "📋 IMPORTANT: Before Firebase Storage works, you need to:\n";
echo "   1. Go to Firebase Console: https://console.firebase.google.com/\n";
echo "   2. Select your project: ugflix-71aa8\n";
echo "   3. Click 'Storage' in the left menu\n";
echo "   4. Click 'Get started' if you haven't done so\n";
echo "   5. Choose 'Start in production mode' or 'Test mode'\n";
echo "   6. Select a location (choose closest to your users)\n";
echo "   7. Click 'Done'\n\n";

echo "🔧 Let's test different Firebase Storage configurations...\n\n";

try {
    // Test 1: Direct Firebase Storage without bucket specification
    echo "1️⃣ Testing Firebase Storage without bucket name...\n";
    
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(storage_path('app/firebase-credentials.json'))
        ->withProjectId('ugflix-71aa8');
        
    $storage = $factory->createStorage();
    echo "✅ Firebase storage service created\n";
    
    // Try to get default bucket
    try {
        $bucket = $storage->getBucket();
        echo "✅ Default bucket obtained: " . $bucket->name() . "\n";
        
        // Try a simple operation
        $testContent = "test-" . time();
        $object = $bucket->upload($testContent, ['name' => 'test-connection.txt']);
        echo "✅ Test upload successful!\n";
        
        // Clean up
        $object->delete();
        echo "✅ Test cleanup successful!\n";
        
        echo "🎉 Firebase Storage is working! You can now create folders.\n";
        
    } catch (\Exception $e) {
        echo "❌ Default bucket failed: " . $e->getMessage() . "\n";
        
        // If default fails, the user needs to initialize Firebase Storage
        echo "\n⚠️  FIREBASE STORAGE NOT INITIALIZED\n";
        echo "Please follow these steps:\n";
        echo "1. Visit: https://console.firebase.google.com/project/ugflix-71aa8/storage\n";
        echo "2. Click 'Get started' to initialize Firebase Storage\n";
        echo "3. Choose your security rules (start with test mode)\n";
        echo "4. Select a storage location\n";
        echo "5. After setup, run this test again\n\n";
        
        // Try with explicit bucket name
        echo "2️⃣ Trying with explicit bucket name...\n";
        try {
            $bucket2 = $storage->getBucket('ugflix-71aa8.appspot.com');
            echo "✅ Explicit bucket connection successful!\n";
        } catch (\Exception $e2) {
            echo "❌ Explicit bucket also failed: " . $e2->getMessage() . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ Firebase initialization failed: " . $e->getMessage() . "\n";
}

echo "\n=== Firebase Storage Test Complete ===\n";

// Show Firebase project details
echo "\n📊 Your Firebase Project Details:\n";
echo "  Project ID: ugflix-71aa8\n";
echo "  Expected Bucket: ugflix-71aa8.appspot.com\n";
echo "  Console URL: https://console.firebase.google.com/project/ugflix-71aa8/storage\n";
echo "  \n";
echo "If you're still getting 404 errors after initializing Firebase Storage,\n";
echo "the issue might be that Firebase Storage hasn't been set up in the console yet.\n";