<?php
/**
 * INDEPENDENT TEST: Munowatch Series Detection Using Flutter App Pattern
 * 
 * This test validates the Flutter app pattern for detecting series:
 * 1. Extract series_code and show ID from API response
 * 2. Call episodes/range/{showId}/{seriesCode}/1 API 
 * 3. If episodes exist, it's a series; if not, it's a movie
 */

// Test the series detection pattern
function checkEpisodesExist($showId, $seriesCode)
{
    try {
        $seasonNumber = 1; // Start with season 1 like Flutter app
        $episodesUrl = "https://munowatch.org/api/episodes/range/{$showId}/{$seriesCode}/{$seasonNumber}";
        
        // Use the same authentication pattern as the crawler
        $jwtToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
        
        $headers = [
            'Authorization: Bearer ' . $jwtToken,
            'X-Api-Key: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0',
            'User-Agent: okhttp/4.9.0',
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 10
            ]
        ]);
        
        echo "🔍 Testing episodes API: $episodesUrl\n";
        $response = @file_get_contents($episodesUrl, false, $context);
        
        if ($response === false) {
            echo "❌ Unable to fetch episodes - probably not a series\n";
            return false;
        }
        
        $episodesData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "❌ Invalid JSON response - probably not a series\n";
            return false;
        }
        
        // Check if response contains error
        if (isset($episodesData['error']) && $episodesData['error'] === true) {
            echo "❌ API returned error: " . ($episodesData['msg'] ?? 'Unknown error') . "\n";
            return false;
        }
        
        // Check if we have episodes array
        if (is_array($episodesData) && !empty($episodesData)) {
            echo "✅ Episodes found! This is a SERIES with " . count($episodesData) . " episode ranges\n";
            echo "   First episode range: " . json_encode($episodesData[0]) . "\n";
            return true;
        }
        
        echo "❌ No episodes found - this is a MOVIE\n";
        return false;
        
    } catch (\Throwable $th) {
        echo "❌ Error occurred: " . $th->getMessage() . "\n";
        return false;
    }
}

// Test with some sample data from dashboard
echo "🎬 TESTING MUNOWATCH SERIES DETECTION PATTERN 🎬\n";
echo "================================================\n\n";

// First, let's get some actual data from the dashboard
$userId = '169464';
$dashboardUrl = "https://munowatch.org/api/dashboard/v2/{$userId}";

$jwtToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';

$headers = [
    'Authorization: Bearer ' . $jwtToken,
    'X-Api-Key: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0',
    'User-Agent: okhttp/4.9.0',
    'Content-Type: application/json',
    'Accept: application/json'
];

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'timeout' => 15
    ]
]);

echo "📥 Fetching dashboard data...\n";
$response = @file_get_contents($dashboardUrl, false, $context);

if ($response === false) {
    echo "❌ Failed to fetch dashboard data\n";
    exit(1);
}

$dashboardData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Failed to parse dashboard JSON\n";
    exit(1);
}

echo "✅ Dashboard data received\n\n";

// Test series detection on first few movies from dashboard
$testCount = 0;
$seriesFound = 0;
$moviesFound = 0;

foreach ($dashboardData['dashboard'] as $category) {
    if (!isset($category['movies']) || !is_array($category['movies'])) {
        continue;
    }
    
    echo "📂 Testing category: " . ($category['category'] ?? 'Unknown') . "\n";
    
    foreach ($category['movies'] as $movie) {
        if ($testCount >= 10) break 2; // Test only first 10 items
        
        $testCount++;
        $title = $movie['video_title'] ?? $movie['title'] ?? 'Unknown';
        $seriesCode = $movie['series_code'] ?? $movie['seriesCode'] ?? '';
        $showId = $movie['id'] ?? $movie['vid'] ?? null;
        
        echo "\n🎬 Test #{$testCount}: {$title}\n";
        echo "   Show ID: {$showId}\n";
        echo "   Series Code: {$seriesCode}\n";
        
        if (empty($seriesCode) || empty($showId)) {
            echo "   ❌ Missing series_code or show ID - treating as MOVIE\n";
            $moviesFound++;
            continue;
        }
        
        $isSeries = checkEpisodesExist($showId, $seriesCode);
        
        if ($isSeries) {
            $seriesFound++;
        } else {
            $moviesFound++;
        }
        
        echo "   " . ($isSeries ? "📺 SERIES" : "🎭 MOVIE") . "\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎯 SERIES DETECTION RESULTS:\n";
echo "   Total tested: {$testCount}\n";
echo "   Series found: {$seriesFound}\n";
echo "   Movies found: {$moviesFound}\n";
echo "   Success rate: " . ($testCount > 0 ? round(($seriesFound + $moviesFound) / $testCount * 100, 1) : 0) . "%\n";

if ($seriesFound > 0) {
    echo "\n🎉 SUCCESS! Series detection is working!\n";
    echo "   The Flutter app pattern successfully identifies series.\n";
    echo "   Ready to integrate into the Laravel crawler.\n";
} else {
    echo "\n⚠️  No series detected in test sample.\n";
    echo "   This could mean:\n";
    echo "   - The test sample contains only movies\n";
    echo "   - Series detection needs refinement\n";
    echo "   - API endpoints or authentication may have issues\n";
}