<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING ENHANCED URL VALIDATION ===\n\n";

use App\Models\MovieModel;

// Test URLs with different types
$testUrls = [
    // Valid video URLs (should return 'Yes')
    'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4',
    
    // Invalid URLs that might have been marked as 'Yes' before (should return 'No')
    'https://example.com/index.html',
    'https://google.com',
    'https://github.com',
    
    // Test a few URLs from your database
    // (Add your actual movie URLs here for testing)
];

foreach ($testUrls as $index => $url) {
    echo "--- Test " . ($index + 1) . " ---\n";
    echo "URL: $url\n";
    
    // Create a temporary movie for testing
    $movie = new MovieModel();
    $movie->title = "Test Movie " . ($index + 1);
    $movie->external_url = $url;
    $movie->url = $url;
    
    // Don't save to database, just test the method
    try {
        echo "Testing URL validation...\n";
        $result = $movie->testExternalVideoUrl();
        echo "Result: $result\n";
        echo "Content Type: " . ($movie->content_type ?? 'NULL') . "\n";
        echo "Is Video: " . $movie->content_is_video . "\n";
        
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    echo str_repeat("-", 60) . "\n\n";
}

echo "=== TESTING EXISTING MOVIES ===\n\n";

// Test a few existing movies from your database
$existingMovies = MovieModel::limit(5)->get();

foreach ($existingMovies as $movie) {
    echo "--- Movie ID: {$movie->id} ---\n";
    echo "Title: {$movie->title}\n";
    echo "URL: " . ($movie->external_url ?? $movie->url) . "\n";
    echo "Current Status: curl_tested={$movie->video_url_tested_by_curl}, works={$movie->video_url_tested_by_curl_works}\n";
    
    // Re-test with enhanced validation
    echo "Re-testing with enhanced validation...\n";
    $result = $movie->testExternalVideoUrl();
    echo "New Result: $result\n";
    echo "Content Type: " . ($movie->content_type ?? 'NULL') . "\n";
    echo "Is Video: " . $movie->content_is_video . "\n";
    
    echo str_repeat("-", 60) . "\n\n";
}

echo "=== SUMMARY ===\n";
echo "Enhanced URL validation now includes:\n";
echo "✅ Strict video content-type validation\n";
echo "✅ Removal of 'application/octet-stream' from auto-accept list\n";
echo "✅ File extension verification for uncertain types\n";
echo "✅ Deep verification using file signature (magic bytes)\n";
echo "✅ Explicit rejection of common non-video content types\n";
echo "\nThis should eliminate false positives!\n";