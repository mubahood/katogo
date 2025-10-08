<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MovieCrawlerPage;
use App\Models\MovieCrawlerWebsite;
use App\Models\SeriesMovie;
use App\Models\MovieModel;

echo "🔍 MUNOWATCH SERIES DETECTION DEBUG 🔍\n";
echo "======================================\n\n";

try {
    // Get recent pages from munowatch
    $munowatchWebsite = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
    
    echo "📋 MUNOWATCH CONFIGURATION:\n";
    echo "Website ID: {$munowatchWebsite->id}\n";
    echo "Current Category: {$munowatchWebsite->current_munowatch_category_id}\n\n";
    
    // Get 5 most recent pages
    echo "🔍 ANALYZING RECENT PAGES:\n";
    echo "==========================\n";
    
    $recentPages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatchWebsite->id)
                                  ->where('status', 'success')
                                  ->orderBy('id', 'desc')
                                  ->limit(5)
                                  ->get();
    
    foreach ($recentPages as $page) {
        echo "\n📄 PAGE ID: {$page->id}\n";
        echo "URL: {$page->url}\n";
        
        // Parse JSON content
        $jsonData = json_decode($page->page_content, true);
        
        if (!$jsonData) {
            echo "❌ Invalid JSON content\n";
            continue;
        }
        
        echo "🔍 JSON STRUCTURE ANALYSIS:\n";
        echo "Top-level keys: " . implode(', ', array_keys($jsonData)) . "\n";
        
        // Check for series indicators
        $seriesIndicators = [];
        
        // Method 1: Direct series key
        if (isset($jsonData['series'])) {
            $seriesIndicators[] = "✅ Has 'series' key";
            echo "Series data: " . json_encode($jsonData['series']) . "\n";
        }
        
        // Method 2: Preview data analysis
        if (isset($jsonData['preview'])) {
            $preview = $jsonData['preview'];
            echo "Preview keys: " . implode(', ', array_keys($preview)) . "\n";
            
            // Check episode indicators
            $episodes = $preview['episodes'] ?? 0;
            $totalEpisodes = $preview['total_episodes'] ?? 0;
            $videoTitle = $preview['video_title'] ?? '';
            
            echo "Episodes field: $episodes\n";
            echo "Total episodes field: $totalEpisodes\n";
            echo "Video title: $videoTitle\n";
            
            if ($episodes > 1) $seriesIndicators[] = "✅ Episodes > 1 ($episodes)";
            if ($totalEpisodes > 1) $seriesIndicators[] = "✅ Total episodes > 1 ($totalEpisodes)";
            if (strpos(strtolower($videoTitle), 'episode') !== false) $seriesIndicators[] = "✅ Title contains 'episode'";
            if (strpos(strtolower($videoTitle), 'season') !== false) $seriesIndicators[] = "✅ Title contains 'season'";
        }
        
        // Method 3: Episodes array
        if (isset($jsonData['episodes']) && is_array($jsonData['episodes'])) {
            $episodeCount = count($jsonData['episodes']);
            $seriesIndicators[] = "✅ Has episodes array ($episodeCount episodes)";
        }
        
        // Show detection results
        if (count($seriesIndicators) > 0) {
            echo "🎬 SERIES INDICATORS FOUND:\n";
            foreach ($seriesIndicators as $indicator) {
                echo "  $indicator\n";
            }
        } else {
            echo "❌ NO SERIES INDICATORS FOUND - Detected as Movie\n";
        }
        
        echo "---\n";
    }
    
    // Check what's actually being created
    echo "\n📊 RECENT CREATION ANALYSIS:\n";
    echo "============================\n";
    
    $recentMovies = MovieModel::where('created_at', '>=', \Carbon\Carbon::now()->subHour())
                             ->orderBy('id', 'desc')
                             ->limit(10)
                             ->get(['id', 'title', 'type', 'category_id', 'episode_number']);
    
    echo "Recent items created in last hour:\n";
    foreach ($recentMovies as $movie) {
        echo "  ID: {$movie->id} | Type: {$movie->type} | Episode: {$movie->episode_number} | Category: {$movie->category_id} | Title: " . substr($movie->title, 0, 50) . "...\n";
    }
    
    $recentSeries = SeriesMovie::where('created_at', '>=', \Carbon\Carbon::now()->subHour())->count();
    echo "\nSeries created in last hour: $recentSeries\n";
    
    echo "\n💡 RECOMMENDATIONS:\n";
    echo "===================\n";
    
    if ($recentSeries == 0) {
        echo "🔧 Current category may not contain series content\n";
        echo "🔧 Consider switching to a different category:\n";
        echo "   - Category 6: Last watched episodes\n";
        echo "   - Category 9: Action (shows type)\n";
        echo "🔧 Or enhance series detection logic for current data format\n";
    } else {
        echo "✅ Series detection is working correctly\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}