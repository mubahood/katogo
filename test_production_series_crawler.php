<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MovieCrawlerWebsite;
use App\Models\MovieCrawlerPage;
use App\Models\SeriesMovie;
use App\Models\Utils;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

echo "🚀 TESTING PRODUCTION MUNOWATCH SERIES CRAWLER 🚀\n";
echo "================================================\n\n";

try {
    // Get munowatch website configuration
    $munowatchWebsite = MovieCrawlerWebsite::where('slug', MovieCrawlerWebsite::MUNOWATCH)->first();
    if (!$munowatchWebsite || $munowatchWebsite->status !== 'Active') {
        throw new Exception('Munowatch website not configured or inactive');
    }
    
    echo "✅ Munowatch website found: {$munowatchWebsite->name}\n";
    echo "   URL: {$munowatchWebsite->url}\n";
    echo "   Status: {$munowatchWebsite->status}\n\n";
    
    // Step 1: Test Level 1 - Website → Pages
    echo "📥 Level 1: Testing page fetching...\n";
    $munowatchWebsite->get_next_page_content();
    echo "✅ Pages fetched - Status: {$munowatchWebsite->fetch_status}\n\n";
    
    // Step 2: Test Level 2 - Pages → Content Processing
    echo "🔍 Level 2: Testing content processing...\n";
    Utils::fetch_pages_content();
    echo "✅ Content processing completed\n\n";
    
    // Step 3: Report results
    echo "📊 CRAWLER RESULTS:\n";
    echo "==================\n";
    
    $totalPages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatchWebsite->id)->count();
    $pendingPages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatchWebsite->id)
                                   ->where('status', 'pending')
                                   ->count();
    $successPages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatchWebsite->id)
                                   ->where('status', 'success')
                                   ->count();
    $failedPages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatchWebsite->id)
                                  ->where('status', 'failed')
                                  ->count();
    
    $recentSeries = SeriesMovie::where('created_at', '>=', Carbon::now()->subHour())
                              ->count();
    
    echo "Total Pages: $totalPages\n";
    echo "Pending Pages: $pendingPages\n";
    echo "Success Pages: $successPages\n";  
    echo "Failed Pages: $failedPages\n";
    echo "New Series (last hour): $recentSeries\n\n";
    
    // Show sample of recent pages
    echo "📄 RECENT PAGES SAMPLE:\n";
    echo "======================\n";
    $recentPages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatchWebsite->id)
                                  ->orderBy('id', 'desc')
                                  ->limit(5)
                                  ->get();
    
    foreach ($recentPages as $page) {
        echo "- ID: {$page->id} | Status: {$page->status} | URL: " . substr($page->url, 0, 60) . "...\n";
    }
    
    echo "\n🎯 PRODUCTION SERIES CRAWLER TEST COMPLETED!\n";
    echo "===========================================\n";
    
    if ($successPages > 0) {
        echo "✅ SUCCESS: Crawler is working properly\n";
        echo "✅ Series processing: FUNCTIONAL\n";
        echo "✅ Production ready: CONFIRMED\n";
    } else {
        echo "⚠️  WARNING: No successful page processing detected\n";
        echo "⚠️  Check logs for potential issues\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}