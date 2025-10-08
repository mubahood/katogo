<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MovieCrawlerPage;

echo "=== DEBUGGING MUNOWATCH TOKEN ACCESS ===\n\n";

// Get a munowatch page
$page = MovieCrawlerPage::where('url', 'like', '%munowatch.org/api/preview%')
    ->first();

if (!$page) {
    echo "❌ No munowatch pages found\n";
    exit;
}

echo "Page ID: {$page->id}\n";
echo "URL: {$page->url}\n";
echo "Website ID: {$page->movie_crawler_website_id}\n\n";

// Check the relationship
$website = $page->movie_crawler_website;
if ($website) {
    echo "✅ Website relationship found\n";
    echo "Website name: {$website->name}\n";
    echo "Website slug: {$website->slug}\n";
    echo "Token present: " . (empty($website->token) ? 'NO' : 'YES') . "\n";
    echo "Token length: " . strlen($website->token ?: '') . " chars\n";
    echo "Token prefix: " . substr($website->token ?: '', 0, 20) . "...\n";
    
    // Check if it matches the expected format
    if ($website->token) {
        $parts = explode('.', $website->token);
        echo "Token format: " . (count($parts) === 3 ? 'Valid JWT' : 'Invalid') . "\n";
    }
} else {
    echo "❌ Website relationship NOT found\n";
    
    // Check if website exists in database
    $website = \App\Models\MovieCrawlerWebsite::find($page->movie_crawler_website_id);
    if ($website) {
        echo "Website exists in DB but relationship not working\n";
    } else {
        echo "Website does not exist in database!\n";
    }
}

echo "\n🎯 Token access debug complete!\n";