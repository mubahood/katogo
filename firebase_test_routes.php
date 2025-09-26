<?php

// Add this to your routes/web.php for testing
Route::get('/test-firebase-direct', function () {
    try {
        // Test direct Google Cloud Storage connection
        $credentialsPath = storage_path('app/firebase-credentials.json');
        
        if (!file_exists($credentialsPath)) {
            return response()->json([
                'error' => 'Credentials file not found at: ' . $credentialsPath
            ]);
        }

        // Initialize Firebase Factory directly
        $factory = (new \Kreait\Firebase\Factory)
            ->withServiceAccount($credentialsPath)
            ->withProjectId('ugflix-71aa8');

        $storage = $factory->createStorage();
        
        // Try to access your existing bucket
        $bucket = $storage->getBucket('ugflix-71aa8');
        
        // Test upload a small file
        $testContent = 'Firebase test at ' . now();
        $testObject = $bucket->upload($testContent, [
            'name' => 'test/firebase_connection_test.txt',
            'metadata' => [
                'contentType' => 'text/plain'
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Direct Firebase connection successful!',
            'bucket_name' => $bucket->name(),
            'test_file' => $testObject->name(),
            'test_url' => $testObject->signedUrl(new DateTime('+1 hour'))
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Test your Utils method
Route::get('/test-utils-firebase', function () {
    try {
        $result = \App\Models\Utils::uploadVideoToFirebase(
            'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4',
            'test_video_' . time(),
            'test_uploads'
        );
        
        return response()->json($result);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});