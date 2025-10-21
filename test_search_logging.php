<?php

/**
 * Test script to verify MovieSearch logging
 * Run: php test_search_logging.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Models\MovieSearch;
use App\Models\User;
use Illuminate\Http\Request;

echo "🧪 Testing MovieSearch Logging\n";
echo "================================\n\n";

// Test 1: Direct logSearch method
echo "Test 1: Testing MovieSearch::logSearch() directly\n";
try {
    $searchRecord = MovieSearch::logSearch(
        'action movies',
        10,
        [1, 2, 3, 4, 5],
        null,
        null
    );
    
    if ($searchRecord) {
        echo "✅ SUCCESS: Search logged with ID: {$searchRecord->id}\n";
        echo "   - Term: {$searchRecord->search_term}\n";
        echo "   - Results: {$searchRecord->results_count}\n";
        echo "   - Search count: {$searchRecord->search_count}\n";
    } else {
        echo "❌ FAILED: logSearch returned null\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n";

// Test 2: Check database records
echo "Test 2: Checking database records\n";
try {
    $count = MovieSearch::count();
    echo "Total searches in database: {$count}\n";
    
    if ($count > 0) {
        echo "\nRecent searches:\n";
        $recent = MovieSearch::latest()->take(5)->get(['id', 'search_term', 'search_count', 'results_count', 'created_at']);
        foreach ($recent as $search) {
            echo "  - ID {$search->id}: '{$search->search_term}' ({$search->search_count} times, {$search->results_count} results) - {$search->created_at}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ ERROR querying database: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Simulate API request
echo "Test 3: Simulating API search request\n";
echo "Please make a search request from your browser/Postman:\n";
echo "  GET /api/movies?search=action\n";
echo "  or\n";
echo "  GET /api/index?model=MovieModel&search=action\n";
echo "\nThen check the logs:\n";
echo "  tail -50 storage/logs/laravel.log | grep -i 'SEARCH'\n";

echo "\n================================\n";
echo "Test complete!\n";
