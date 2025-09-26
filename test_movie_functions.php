<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING MOVIE MODEL FUNCTIONS WITH IDs 1-6 ===\n\n";

use App\Models\MovieModel;

// Test movies 1-6
$movieIds = [1, 2, 3, 4, 5, 6];

foreach ($movieIds as $id) {
    echo "--- Testing Movie ID: $id ---\n";
    
    $movie = MovieModel::find($id);
    if (!$movie) {
        echo "❌ Movie ID $id not found\n\n";
        continue;
    }
    
    echo "📽️ Title: {$movie->title}\n";
    echo "🔗 URL: {$movie->url}\n";
    echo "🔗 External URL: " . ($movie->external_url ?? 'NULL') . "\n";
    
    // Test 1: Test external URL
    echo "\n1️⃣ Testing External URL...\n";
    $urlResult = $movie->testExternalVideoUrl();
    echo "   Result: $urlResult\n";
    
    // Refresh model to see updated data
    $movie = $movie->fresh();
    echo "   Video tested by curl: {$movie->video_url_tested_by_curl}\n";
    echo "   Video works: {$movie->video_url_tested_by_curl_works}\n";
    echo "   Content type: " . ($movie->content_type ?? 'NULL') . "\n";
    echo "   Content is video: {$movie->content_is_video}\n";
    
    // Test 2: If URL works, try Firebase transfer
    if ($movie->video_url_tested_by_curl_works === 'Yes') {
        echo "\n2️⃣ Testing Firebase Transfer...\n";
        $transferResult = $movie->transferToFirebase();
        
        if ($transferResult['success']) {
            echo "   ✅ Transfer successful!\n";
            echo "   Firebase URL: {$transferResult['firebase_url']}\n";
            
            // Refresh model
            $movie = $movie->fresh();
            
            // Test 3: Test Firebase URL
            echo "\n3️⃣ Testing Firebase URL...\n";
            $firebaseResult = $movie->testFirebaseVideoUrl();
            echo "   Result: $firebaseResult\n";
            
            $movie = $movie->fresh();
            echo "   Firebase tested: {$movie->firebase_video_tested_by_curl}\n";
            echo "   Firebase works: {$movie->firebase_video_tested_by_curl_works}\n";
            
        } else {
            echo "   ❌ Transfer failed: {$transferResult['error']}\n";
        }
    } else {
        echo "\n⏭️ Skipping Firebase transfer (URL doesn't work)\n";
    }
    
    echo "\n" . str_repeat("-", 80) . "\n\n";
}

echo "=== TESTING STATIC METHODS ===\n\n";

// Test static methods
echo "📊 Movies needing URL testing: " . MovieModel::getNeedsUrlTesting(10)->count() . "\n";
echo "📊 Movies ready for Firebase: " . MovieModel::getNeedsFirebaseTransfer(10)->count() . "\n";
echo "📊 Movies needing Firebase testing: " . MovieModel::getNeedsFirebaseUrlTesting(10)->count() . "\n";

echo "\n=== TESTING COMPLETE ===\n";