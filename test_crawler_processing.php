<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MovieCrawlerWebsite;

echo "=== TESTING MUNOWATCH CRAWLER PROCESSING ===\n\n";

// Get the munowatch website
$website = MovieCrawlerWebsite::where('slug', 'munowatch')->first();

if (!$website) {
    echo "❌ No munowatch website found\n";
    exit;
}

echo "Found website: {$website->name}\n";
echo "Current status: {$website->fetch_status}\n";
echo "Current URL: {$website->last_page_url}\n\n";

// Clear any errors first
$website->update([
    'fetch_status' => null,
    'error_message' => null
]);

echo "Testing get_next_page_content()...\n";

try {
    $website->get_next_page_content();
    
    echo "✅ SUCCESS: get_next_page_content() completed\n";
    echo "New status: {$website->refresh()->fetch_status}\n";
    echo "Error message: " . ($website->error_message ?: 'None') . "\n";
    echo "Response data length: " . strlen($website->response_data ?: '') . " chars\n";
    
    // Check if any pages were created
    $pagesCount = \App\Models\MovieCrawlerPage::where('movie_crawler_website_id', $website->id)->count();
    echo "Total crawler pages: {$pagesCount}\n";
    
    // Show recent pages
    $recentPages = \App\Models\MovieCrawlerPage::where('movie_crawler_website_id', $website->id)
        ->orderBy('created_at', 'desc')
        ->limit(3)
        ->get(['url', 'title', 'status', 'created_at']);
    
    if ($recentPages->count() > 0) {
        echo "\nRecent pages created:\n";
        foreach ($recentPages as $page) {
            echo "- {$page->title} | {$page->url} | {$page->status} | {$page->created_at}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    
    // Check website state after error
    $website->refresh();
    echo "Website status: {$website->fetch_status}\n";
    echo "Error message: " . ($website->error_message ?: 'None') . "\n";
}

echo "\n🎯 Crawler processing test complete!\n";