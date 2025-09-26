Route::get('/test-firebase', function () {
    try {
        // Test Firebase connection
        $result = \App\Models\Utils::uploadVideoToFirebase(
            'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4',
            'test_video',
            'test_uploads'
        );
        
        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => 'Firebase Storage is working!',
                'firebase_url' => $result['firebase_url'],
                'file_size' => $result['file_size']
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => $result['error']
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Firebase test failed: ' . $e->getMessage()
        ]);
    }
});