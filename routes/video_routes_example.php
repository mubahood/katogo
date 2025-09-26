<?php

// Add this to routes/web.php or routes/api.php

Route::get('/video/{filename}', function ($filename) {
    // Validate filename
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(mp4|mov|avi|mkv)$/', $filename)) {
        return abort(404, 'Invalid video file');
    }
    
    $firebasePath = "movies/{$filename}";
    
    // Generate fresh signed URL (24 hours)
    $result = \App\Models\Utils::getFirebaseDownloadUrl($firebasePath, 24);
    
    if ($result['success']) {
        // Option A: Redirect to Firebase URL
        return redirect($result['url']);
        
        // Option B: Return JSON with URL
        // return response()->json([
        //     'video_url' => $result['url'],
        //     'expires_at' => $result['expires_at']
        // ]);
    }
    
    return abort(404, 'Video not found');
})->name('video.stream');

// Usage in your Blade templates:
// <video src="{{ route('video.stream', ['filename' => 'big_buck_bunny_test.mp4']) }}" controls></video>

// Or in API responses:
// GET /video/big_buck_bunny_test.mp4 -> redirects to Firebase URL