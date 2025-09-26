<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Project Investigation ===\n";

try {
    // Create Firebase factory
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(storage_path('app/firebase-credentials.json'))
        ->withProjectId('ugflix-71aa8');

    echo "✅ Firebase factory created\n";

    // Check if we can list buckets using Google Cloud Storage client
    echo "\n🔍 Investigating available storage buckets...\n";
    
    $storage = $factory->createStorage();
    
    // Try to list all buckets for this project
    $client = $storage->getStorageClient();
    echo "✅ Storage client obtained\n";
    
    try {
        $buckets = $client->buckets();
        echo "📦 Available buckets:\n";
        foreach ($buckets as $bucket) {
            echo "  - " . $bucket->name() . "\n";
        }
    } catch (\Exception $e) {
        echo "❌ Cannot list buckets: " . $e->getMessage() . "\n";
    }
    
    // Let's try creating a Firebase Storage bucket
    echo "\n🔧 Attempting to get/create Firebase Storage bucket...\n";
    
    try {
        // Try different bucket naming patterns
        $possibleBuckets = [
            'ugflix-71aa8.appspot.com',
            'ugflix-71aa8-default-rtdb',
            'ugflix-71aa8',
            'gs://ugflix-71aa8.appspot.com'
        ];
        
        foreach ($possibleBuckets as $bucketName) {
            echo "🧪 Testing bucket: $bucketName\n";
            try {
                $bucket = $storage->getBucket($bucketName);
                echo "  ✅ Bucket exists: " . $bucket->name() . "\n";
                
                // Test upload
                $testData = "test-" . time();
                $object = $bucket->upload($testData, ['name' => 'test.txt']);
                echo "  ✅ Upload successful!\n";
                $object->delete();
                echo "  ✅ Delete successful!\n";
                echo "  🎉 THIS BUCKET WORKS!\n\n";
                break;
                
            } catch (\Exception $e) {
                echo "  ❌ " . $e->getMessage() . "\n";
            }
        }
        
    } catch (\Exception $e) {
        echo "❌ Bucket operation failed: " . $e->getMessage() . "\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Investigation Complete ===\n";