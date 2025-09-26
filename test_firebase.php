<?php
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use Kreait\Firebase\Factory;

try {
    echo "Testing Firebase Storage connection...\n";
    
    $credentialsPath = $_ENV['FIREBASE_CREDENTIALS_PATH'] ?? 'storage/app/firebase-credentials.json';
    $projectId = $_ENV['FIREBASE_PROJECT_ID'] ?? null;
    $storageBucket = $_ENV['FIREBASE_STORAGE_BUCKET'] ?? null;
    
    echo "Credentials Path: $credentialsPath\n";
    echo "Project ID: $projectId\n";
    echo "Storage Bucket: $storageBucket\n";
    
    // Check if credentials file exists
    if (!file_exists($credentialsPath)) {
        throw new Exception("Credentials file not found at: $credentialsPath");
    }
    
    // Initialize Firebase
    $factory = (new Factory)
        ->withServiceAccount($credentialsPath)
        ->withProjectId($projectId);
    
    $storage = $factory->createStorage();
    $bucket = $storage->getBucket();
    
    echo "✅ Firebase Storage connection successful!\n";
    echo "Bucket name: " . $bucket->name() . "\n";
    
    // Test upload a small text file
    $testContent = "Test upload at " . date('Y-m-d H:i:s');
    $object = $bucket->upload($testContent, [
        'name' => 'test/connection_test.txt'
    ]);
    
    echo "✅ Test file uploaded successfully!\n";
    echo "File path: " . $object->name() . "\n";
    
    // Clean up test file
    $object->delete();
    echo "✅ Test file deleted successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}