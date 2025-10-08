<?php

echo "=== TESTING VIDEO LINK GENERATION ===\n\n";

// Based on Flutter app analysis, the preview API response should contain:
$expectedResponseStructure = [
    'preview' => [
        'id' => 35547,
        'video_title' => 'Sample Movie Title',
        'video_description' => 'Sample movie description',
        'genre' => 'Action',
        'duration' => '120 minutes',
        'poster_url' => 'https://example.com/poster.jpg',
        'playingUrl' => 'https://streaming-server.com/video/35547.m3u8',  // Main video URL
        'embedurl' => 'https://embed-server.com/35547',                   // Embed URL
        'openload' => 'https://openload-server.com/35547',               // Alternative source
        'nxt_playing_url' => 'https://streaming-server.com/video/35548.m3u8', // Next episode
        'category_id' => 1,
        'vj_name' => 'Sample VJ',
        'episodes' => [] // Episodes array for series
    ],
    'items' => [],
    'dashboard' => []
];

echo "Expected API Response Structure:\n";
echo json_encode($expectedResponseStructure, JSON_PRETTY_PRINT) . "\n\n";

echo "Key Video URL Fields that the app looks for:\n";
echo "1. playingUrl - Primary streaming URL (usually .m3u8 or .mp4)\n";
echo "2. embedurl - Embed player URL\n";
echo "3. openload - Alternative streaming source\n";
echo "4. nxt_playing_url - Next episode URL for series\n\n";

echo "Current Issue:\n";
echo "- The API token is expired (Feb 2024)\n";
echo "- When token is valid, the preview endpoint should return this structure\n";
echo "- The app extracts video URLs from these fields\n\n";

echo "URL Pattern Analysis:\n";
echo "Dashboard movies have 'playingurl' field (lowercase)\n";
echo "Preview detail has 'playingUrl' field (camelCase)\n";
echo "Both should contain the actual streaming URL\n\n";

// Let's look at how the current system processes this
echo "Current Laravel Processing:\n";
echo "1. MovieCrawlerWebsite creates pages with preview URLs\n";
echo "2. MovieCrawlerPage fetches the preview JSON\n";
echo "3. process_munowatch() should extract video URLs from response\n";
echo "4. Store in MovieModel for the application to use\n\n";

echo "Test URL Pattern:\n";
echo "https://munowatch.org/api/preview/v2/{videoId}/{userId}\n";
echo "Example: https://munowatch.org/api/preview/v2/35547/169464\n\n";

echo "🎯 Video link generation analysis complete!\n";