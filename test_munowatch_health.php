<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MovieCrawlerWebsite;
use App\Models\MunowatchMovieCategory;

echo "=== MUNOWATCH CRAWLER HEALTH CHECK ===\n\n";

try {
    // Check if munowatch website is configured
    $website = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
    if (!$website) {
        echo "❌ Munowatch website not found in database\n";
        exit(1);
    }
    
    echo "✅ Munowatch Website Found:\n";
    echo "   Name: {$website->name}\n";
    echo "   Status: {$website->status}\n";
    echo "   URL Template: {$website->url}\n";
    echo "   Current Page: {$website->page_number}\n";
    echo "   Max Page: {$website->max_page}\n\n";
    
    // Check if categories are available
    $categoriesCount = MunowatchMovieCategory::count();
    echo "📋 Munowatch Categories: $categoriesCount\n";
    
    if ($categoriesCount > 0) {
        $activeCategories = MunowatchMovieCategory::where('status', 'active')->count();
        echo "   Active Categories: $activeCategories\n";
        
        // Show current category
        if ($website->current_munowatch_category_id) {
            $currentCategory = MunowatchMovieCategory::find($website->current_munowatch_category_id);
            if ($currentCategory) {
                echo "   Current Category: {$currentCategory->category_name}\n";
                echo "   API Endpoint: {$currentCategory->api_endpoint_type}\n";
            }
        }
    }
    
    echo "\n";
    
    // Test URL generation
    echo "🔗 Testing URL Generation:\n";
    try {
        $nextUrl = $website->get_next_page_link();
        echo "   Next Page URL: $nextUrl\n";
    } catch (Exception $e) {
        echo "   ❌ URL Generation Failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Check recent pages
    $recentPages = \App\Models\MovieCrawlerPage::where('movie_crawler_website_id', $website->id)
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get(['id', 'url', 'status', 'title', 'type']);
    
    echo "📄 Recent Pages (Last 5):\n";
    foreach ($recentPages as $page) {
        echo "   ID: {$page->id} | Status: {$page->status} | Type: {$page->type} | " . substr($page->title, 0, 30) . "...\n";
    }
    
    echo "\n=== HEALTH CHECK COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error during health check: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}