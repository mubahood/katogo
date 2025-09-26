<?php
require_once 'vendor/autoload.php';

// Simple Firebase test script
try {
    echo "🔥 Testing Firebase Connection...\n\n";
    
    // Check if credentials file exists
    $credentialsPath = 'storage/app/firebase-credentials.json';
    if (!file_exists($credentialsPath)) {
        throw new Exception("❌ Credentials file not found at: $credentialsPath");
    }
    echo "✅ Credentials file found\n";
    
    // Load Laravel app
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    
    // Test Firebase Storage connection
    $storage = app('firebase.storage');
    $bucket = $storage->getBucket();
    
    echo "✅ Firebase Storage connection successful!\n";
    echo "📦 Bucket name: " . $bucket->name() . "\n";
    echo "🆔 Project ID: " . config('firebase.project_id') . "\n\n";
    
    // Test a simple upload
    echo "🚀 Testing small file upload...\n";
    $testContent = "Firebase test at " . date('Y-m-d H:i:s');
    $testObject = $bucket->upload($testContent, [
        'name' => 'test/connection_test_' . time() . '.txt',
        'metadata' => ['contentType' => 'text/plain']
    ]);
    
    echo "✅ Test file uploaded successfully!\n";
    echo "📄 File name: " . $testObject->name() . "\n";
    
    // Generate download URL
    $downloadUrl = $testObject->signedUrl(new DateTime('+1 hour'));
    echo "🔗 Download URL: " . $downloadUrl . "\n\n";
    
    // Clean up test file
    $testObject->delete();
    echo "🗑️  Test file cleaned up\n\n";
    
    echo "🎉 ALL TESTS PASSED! Firebase is ready to use!\n";
    echo "💡 You can now use Utils::uploadVideoToFirebase() method\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
    echo "📂 File: " . $e->getFile() . "\n\n";
    
    // Additional debugging
    if (strpos($e->getMessage(), 'credentials') !== false) {
        echo "💡 SOLUTION: Make sure your firebase-credentials.json file is valid\n";
    } elseif (strpos($e->getMessage(), 'permission') !== false) {
        echo "💡 SOLUTION: Check your service account permissions in Google Cloud\n";
    } elseif (strpos($e->getMessage(), 'bucket') !== false) {
        echo "💡 SOLUTION: Make sure the storage bucket 'ugflix-71aa8' exists\n";
    }
}