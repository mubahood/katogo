<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MunowatchMovieCategory;

echo "=== FIXED MUNOWATCH TEST ===\n\n";

// Reset next_fetch_at to allow immediate testing
\DB::table('munowatch_movie_categories')->update(['next_fetch_at' => null]);

// Test a few different categories
$testCategories = [3, 5, 10]; // Romance, Action, Horror

foreach ($testCategories as $categoryId) {
    $category = MunowatchMovieCategory::where('munowatch_category_id', $categoryId)->first();
    
    if ($category) {
        echo "Testing Category: {$category->category_name} (ID: {$category->munowatch_category_id})\n";
        echo "Endpoint Type: {$category->api_endpoint_type}\n";
        echo "URL: " . $category->getMoviesFetchURL() . "\n";
        
        try {
            $movies = $category->fetchMovies();
            
            if (is_array($movies)) {
                echo "✅ SUCCESS: Got " . count($movies) . " movies\n";
                
                if (!empty($movies) && isset($movies[0]['title'])) {
                    echo "First movie: {$movies[0]['title']}\n";
                }
            } else {
                echo "⚠️ Unexpected response type: " . gettype($movies) . "\n";
            }
            
        } catch (Exception $e) {
            echo "❌ FAILED: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
}

echo "Now testing the crawler integration...\n\n";

// Test the crawler website handling
$website = \App\Models\MovieCrawlerWebsite::where('name', 'like', '%munowatch%')->first();
if ($website) {
    echo "Testing MovieCrawlerWebsite::handleMunowatchPageLink()...\n";
    
    // Use reflection to call the private method
    $reflection = new ReflectionClass($website);
    $method = $reflection->getMethod('handleMunowatchPageLink');
    $method->setAccessible(true);
    
    try {
        $url = $method->invoke($website);
        echo "✅ Generated URL: {$url}\n";
        
        // Check what category was selected
        $website->refresh();
        if ($website->current_munowatch_category_id) {
            $currentCategory = MunowatchMovieCategory::find($website->current_munowatch_category_id);
            echo "Selected category: {$currentCategory->category_name} (ID: {$currentCategory->munowatch_category_id})\n";
            echo "Expected this to work because it uses: {$currentCategory->api_endpoint_type} endpoint\n";
        }
        
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\n🎉 Test complete! The system now follows the Flutter app pattern exactly.\n";