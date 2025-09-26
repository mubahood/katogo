<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Firebase Configuration Debug ===\n";

echo "Environment Variables:\n";
echo "FIREBASE_PROJECT_ID: " . env('FIREBASE_PROJECT_ID') . "\n";
echo "FIREBASE_STORAGE_BUCKET: " . env('FIREBASE_STORAGE_BUCKET') . "\n";
echo "FIREBASE_CREDENTIALS_PATH: " . env('FIREBASE_CREDENTIALS_PATH') . "\n\n";

echo "Config Values:\n";
echo "firebase.project_id: " . config('firebase.project_id') . "\n";
echo "firebase.storage.bucket: " . config('firebase.storage.bucket') . "\n";
echo "firebase.credentials.file: " . config('firebase.credentials.file') . "\n\n";

try {
    // Test Firebase factory directly
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(config('firebase.credentials.file'))
        ->withProjectId(config('firebase.project_id'));

    echo "✅ Factory created successfully\n";

    // Test storage service
    $storage = $factory->createStorage();
    echo "✅ Storage service created\n";

    // Test bucket access with different bucket names
    $bucketName = config('firebase.storage.bucket');
    echo "🔍 Trying bucket: $bucketName\n";
    
    try {
        $bucket = $storage->getBucket($bucketName);
        echo "✅ Bucket '$bucketName' accessed successfully\n";
    } catch (\Exception $e) {
        echo "❌ Bucket '$bucketName' failed: " . $e->getMessage() . "\n";
        
        // Try default bucket
        echo "🔍 Trying default bucket...\n";
        try {
            $bucket = $storage->getBucket();
            echo "✅ Default bucket accessed successfully\n";
        } catch (\Exception $e2) {
            echo "❌ Default bucket failed: " . $e2->getMessage() . "\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Firebase setup error: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";