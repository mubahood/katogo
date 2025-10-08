<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MovieCrawlerPage;

echo "=== TESTING MUNOWATCH PAGE FETCHING ===\n\n";

// Get a munowatch page that's currently failing
$page = MovieCrawlerPage::where('url', 'like', '%munowatch.org/api/preview%')
    ->where('status', 'error')
    ->first();

if (!$page) {
    $page = MovieCrawlerPage::where('url', 'like', '%munowatch.org/api/preview%')
        ->first();
}

if (!$page) {
    echo "❌ No munowatch pages found in database\n";
    exit;
}

echo "Found page: ID {$page->id}\n";
echo "URL: {$page->url}\n";
echo "Current status: {$page->status}\n";
echo "Current error: " . ($page->error_message ?: 'None') . "\n\n";

echo "Testing fetch_page_content()...\n";

try {
    // Clear previous error
    $page->status = 'pending';
    $page->error_message = null;
    $page->save();
    
    $page->fetch_page_content();
    
    $page->refresh();
    echo "✅ fetch_page_content() completed\n";
    echo "New status: {$page->status}\n";
    echo "Error message: " . ($page->error_message ?: 'None') . "\n";
    echo "Page content length: " . strlen($page->page_content ?: '') . " chars\n";
    
    if ($page->page_content) {
        // Check if it's valid JSON
        $json = json_decode($page->page_content, true);
        if ($json) {
            echo "JSON structure: " . implode(', ', array_keys($json)) . "\n";
            if (isset($json['preview'])) {
                echo "✅ Preview data found in response\n";
                $preview = $json['preview'];
                echo "Movie title: " . ($preview['video_title'] ?? 'N/A') . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
}

echo "\n🎯 Munowatch page fetch test complete!\n";