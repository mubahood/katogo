<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MovieCrawlerWebsite;
use App\Models\MunowatchMovieCategory;
use Illuminate\Support\Facades\Log;

echo "🎬 MUNOWATCH SERIES CATEGORIES SETUP 🎬\n";
echo "======================================\n\n";

try {
    $munowatch = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
    
    if (!$munowatch) {
        throw new Exception('Munowatch website not found');
    }
    
    echo "📋 CURRENT CONFIGURATION:\n";
    echo "Current Category ID: {$munowatch->current_munowatch_category_id}\n";
    echo "Current URL: {$munowatch->url}\n\n";
    
    // Get current category details
    $currentCategory = MunowatchMovieCategory::find($munowatch->current_munowatch_category_id);
    if ($currentCategory) {
        echo "Current Category: {$currentCategory->category_name} (Type: {$currentCategory->api_endpoint_type})\n\n";
    }
    
    echo "🔍 AVAILABLE CATEGORIES:\n";
    echo "=======================\n";
    
    $categories = MunowatchMovieCategory::orderBy('id')->get();
    
    foreach ($categories as $category) {
        $seriesIndicator = '';
        $name = strtolower($category->category_name);
        
        // Identify potential series categories
        if (strpos($name, 'episode') !== false || 
            strpos($name, 'series') !== false || 
            strpos($name, 'show') !== false ||
            $category->api_endpoint_type === 'shows' ||
            strpos($name, 'continue') !== false ||
            strpos($name, 'watched') !== false) {
            $seriesIndicator = ' 🎬 [SERIES CANDIDATE]';
        }
        
        echo "ID: {$category->id} | Category: {$category->category_name} | Type: {$category->api_endpoint_type} | Munowatch ID: {$category->munowatch_category_id}$seriesIndicator\n";
    }
    
    echo "\n🎯 RECOMMENDATIONS FOR SERIES CRAWLING:\n";
    echo "======================================\n";
    
    // Find the best series candidates
    $seriesCandidates = MunowatchMovieCategory::where(function($query) {
        $query->where('category_name', 'LIKE', '%episode%')
              ->orWhere('category_name', 'LIKE', '%series%')
              ->orWhere('category_name', 'LIKE', '%show%')
              ->orWhere('category_name', 'LIKE', '%continue%')
              ->orWhere('category_name', 'LIKE', '%watched%')
              ->orWhere('api_endpoint_type', 'shows');
    })->get();
    
    if ($seriesCandidates->count() > 0) {
        echo "🎬 BEST SERIES CATEGORIES:\n";
        foreach ($seriesCandidates as $candidate) {
            echo "  - ID {$candidate->id}: {$candidate->category_name} (Type: {$candidate->api_endpoint_type})\n";
        }
        
        echo "\n💡 RECOMMENDED ACTION:\n";
        echo "Update munowatch to use series category:\n";
        echo "UPDATE movie_crawler_websites SET current_munowatch_category_id = [SERIES_CATEGORY_ID] WHERE slug = 'munowatch';\n\n";
        
        // Auto-update to a series category
        $bestSeriesCategory = $seriesCandidates->first();
        echo "🚀 AUTO-UPDATING TO SERIES CATEGORY...\n";
        echo "Switching from category {$munowatch->current_munowatch_category_id} to category {$bestSeriesCategory->id} ({$bestSeriesCategory->category_name})\n";
        
        $munowatch->current_munowatch_category_id = $bestSeriesCategory->id;
        $munowatch->save();
        
        echo "✅ SUCCESS: Munowatch now configured for series crawling!\n";
        echo "Next crawler run will fetch from: {$bestSeriesCategory->category_name}\n";
        
    } else {
        echo "❌ No obvious series categories found\n";
        echo "💡 Consider using category 2 (Continue watching) or 6 (Last watched episodes)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}