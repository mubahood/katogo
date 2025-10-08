<?php
/**
 * DIRECT SERIES LOGIC TEST - Validates core functionality
 * Tests the series processing methods directly without web routes
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MovieCrawlerPage;
use App\Models\MovieCrawlerWebsite;
use App\Models\SeriesMovie;
use App\Models\MovieModel;

echo "🎬 DIRECT SERIES LOGIC VALIDATION TEST 🎬\n";
echo "==========================================\n\n";

try {
    // Test 1: Verify MovieCrawlerWebsite exists for MUNOWATCH
    echo "1️⃣  Testing MovieCrawlerWebsite Configuration...\n";
    
    $munowatchWebsite = MovieCrawlerWebsite::where('slug', MovieCrawlerWebsite::MUNOWATCH)->first();
    
    if (!$munowatchWebsite) {
        echo "   ⚠️  MUNOWATCH website not found. Creating test configuration...\n";
        
        $munowatchWebsite = new MovieCrawlerWebsite();
        $munowatchWebsite->name = 'Munowatch Test';
        $munowatchWebsite->slug = MovieCrawlerWebsite::MUNOWATCH;
        $munowatchWebsite->domain = 'api.munowatch.test';
        $munowatchWebsite->base_url = 'https://api.munowatch.test';
        $munowatchWebsite->status = 'Active';
        $munowatchWebsite->save();
        
        echo "   ✅ Created test MUNOWATCH website configuration\n";
    } else {
        echo "   ✅ MUNOWATCH website found: {$munowatchWebsite->name}\n";
    }
    
    // Test 2: Create test series data and validate processing
    echo "\n2️⃣  Testing Series Processing Logic...\n";
    
    $testSeriesJson = [
        'series' => [
            'title' => 'Detective Chronicles Test Series',
            'description' => 'A thrilling detective series for testing our exceptional crawler.',
            'thumbnail' => 'https://example.com/detective-poster.jpg',
            'total_episodes' => 3,
            'total_seasons' => 1,
            'genre' => 'Crime/Drama',
            'year' => '2024',
            'language' => 'English',
            'country' => 'USA',
            'rating' => 'TV-14',
            'id' => 'detective-test-789',
            'vj_name' => 'Test Detective VJ',
            'episodes' => [
                [
                    'id' => 'det-ep1',
                    'title' => 'The Missing Evidence',
                    'episode_number' => 1,
                    'description' => 'A crucial piece of evidence goes missing from the police station.',
                    'playingUrl' => 'https://example.com/detective-s1e1.mp4',
                    'embedurl' => 'https://example.com/embed/detective-s1e1',
                    'duration' => '43:30',
                    'size' => '845.2 MB',
                    'thumbnail' => 'https://example.com/det-s1e1-thumb.jpg'
                ],
                [
                    'id' => 'det-ep2',
                    'title' => 'Following the Trail',
                    'episode_number' => 2,
                    'description' => 'The detective follows a dangerous lead through the city.',
                    'playingUrl' => 'https://example.com/detective-s1e2.mp4',
                    'duration' => '41:15',
                    'size' => '782.8 MB',
                    'thumbnail' => 'https://example.com/det-s1e2-thumb.jpg'
                ],
                [
                    'id' => 'det-ep3',
                    'title' => 'The Final Confrontation',
                    'episode_number' => 3,
                    'description' => 'All mysteries are revealed in a thrilling conclusion.',
                    'playingUrl' => 'https://example.com/detective-s1e3.mp4',
                    'duration' => '47:45',
                    'size' => '920.1 MB',
                    'thumbnail' => 'https://example.com/det-s1e3-thumb.jpg'
                ]
            ]
        ]
    ];
    
    // Create test MovieCrawlerPage
    $testPage = new MovieCrawlerPage();
    $testPage->movie_crawler_website_id = $munowatchWebsite->id;
    $testPage->url = 'https://api.munowatch.test/series/detective-test-789';
    $testPage->page_content = json_encode($testSeriesJson);
    $testPage->status = 'pending';
    $testPage->save();
    
    echo "   ✅ Created test MovieCrawlerPage with series data\n";
    echo "   📊 Page ID: {$testPage->id}\n";
    echo "   🔗 Test URL: {$testPage->url}\n";
    
    // Test 3: Run intelligent processing
    echo "\n3️⃣  Testing Intelligent Content Detection...\n";
    
    $result = $testPage->process_munowatch_intelligent();
    
    echo "   📊 Processing Status: {$testPage->status}\n";
    echo "   📝 Processing Notes: {$testPage->notes}\n";
    
    if ($testPage->status === 'success') {
        echo "   ✅ Series processing completed successfully!\n";
        
        // Test 4: Validate created series
        echo "\n4️⃣  Validating Created Series...\n";
        
        if ($testPage->series_id) {
            $createdSeries = SeriesMovie::find($testPage->series_id);
            if ($createdSeries) {
                echo "   ✅ Series Created Successfully:\n";
                echo "      - ID: {$createdSeries->id}\n";
                echo "      - Title: {$createdSeries->title}\n";
                echo "      - Total Episodes: {$createdSeries->total_episodes}\n";
                echo "      - Genre: {$createdSeries->Category}\n";
                echo "      - Status: {$createdSeries->is_active}\n";
                echo "      - Thumbnail: {$createdSeries->thumbnail}\n";
                
                // Test 5: Validate created episodes
                echo "\n5️⃣  Validating Created Episodes...\n";
                
                $episodes = MovieModel::where('category_id', $createdSeries->id)
                                     ->where('type', 'Series')
                                     ->orderBy('episode_number')
                                     ->get();
                
                echo "   📊 Found {$episodes->count()} episodes\n";
                
                foreach ($episodes as $episode) {
                    echo "   ✅ Episode {$episode->episode_number}:\n";
                    echo "      - Title: {$episode->title}\n";
                    echo "      - Duration: {$episode->duration}\n";
                    echo "      - Status: {$episode->status}\n";
                    echo "      - First Episode: {$episode->is_first_episode}\n";
                    echo "      - Category ID: {$episode->category_id}\n";
                    echo "      - Video URL: {$episode->url}\n";
                    echo "\n";
                }
                
                // Test 6: Validate relationships
                echo "6️⃣  Testing Database Relationships...\n";
                
                $seriesEpisodes = $createdSeries->episodes;
                echo "   ✅ Series->episodes() relationship: {$seriesEpisodes->count()} episodes\n";
                
                $firstEpisode = $episodes->where('episode_number', 1)->first();
                if ($firstEpisode && $firstEpisode->is_first_episode === 'Yes') {
                    echo "   ✅ First episode flagging: CORRECT\n";
                } else {
                    echo "   ❌ First episode flagging: ERROR\n";
                }
                
                // Test 7: Validate episode count accuracy
                $actualCount = MovieModel::where('category_id', $createdSeries->id)->where('type', 'Series')->count();
                if ($actualCount == $createdSeries->total_episodes) {
                    echo "   ✅ Episode count accuracy: PERFECT ({$actualCount} episodes)\n";
                } else {
                    echo "   ❌ Episode count mismatch: Expected {$createdSeries->total_episodes}, found {$actualCount}\n";
                }
                
            } else {
                echo "   ❌ Created series not found in database!\n";
            }
        } else {
            echo "   ❌ No series_id set on MovieCrawlerPage!\n";
        }
        
    } else {
        echo "   ❌ Series processing failed!\n";
        echo "   🚨 Error: {$testPage->error_message}\n";
    }
    
    // Test 8: Test movie detection (should not be processed as series)
    echo "\n7️⃣  Testing Movie Detection (Should NOT be Series)...\n";
    
    $movieTestJson = [
        'preview' => [
            'video_title' => 'Standalone Action Movie',
            'description' => 'An action-packed standalone movie.',
            'playingUrl' => 'https://example.com/action-movie.mp4',
            'episodes' => 1,
            'genre' => 'Action',
            'duration' => '02h 15m'
        ]
    ];
    
    $movieTestPage = new MovieCrawlerPage();
    $movieTestPage->movie_crawler_website_id = $munowatchWebsite->id;
    $movieTestPage->url = 'https://api.munowatch.test/movie/action-movie-123';
    $movieTestPage->page_content = json_encode($movieTestJson);
    $movieTestPage->status = 'pending';
    $movieTestPage->save();
    
    $movieResult = $movieTestPage->process_munowatch_intelligent();
    
    echo "   📊 Movie Processing Status: {$movieTestPage->status}\n";
    echo "   📝 Movie Processing Notes: {$movieTestPage->notes}\n";
    
    if (strpos($movieTestPage->notes, 'movie content') !== false) {
        echo "   ✅ Movie detection: CORRECT (routed to movie processor)\n";
    } else {
        echo "   ❌ Movie detection: ERROR (incorrectly detected as series)\n";
    }
    
    echo "\n🎯 DIRECT LOGIC VALIDATION COMPLETED! 🎯\n";
    echo "=========================================\n\n";
    
    echo "📊 FINAL TEST RESULTS:\n";
    echo ($testPage->status === 'success' ? "✅" : "❌") . " Series Processing: " . strtoupper($testPage->status) . "\n";
    echo ($episodes->count() > 0 ? "✅" : "❌") . " Episode Creation: " . ($episodes->count() > 0 ? "SUCCESS" : "FAILED") . "\n";
    echo ($firstEpisode && $firstEpisode->is_first_episode === 'Yes' ? "✅" : "❌") . " First Episode Flag: " . ($firstEpisode && $firstEpisode->is_first_episode === 'Yes' ? "CORRECT" : "ERROR") . "\n";
    echo (strpos($movieTestPage->notes, 'movie content') !== false ? "✅" : "❌") . " Movie Detection: " . (strpos($movieTestPage->notes, 'movie content') !== false ? "CORRECT" : "ERROR") . "\n";
    
    echo "\n🏆 SERIES LOGIC VALIDATION: " . ($testPage->status === 'success' && $episodes->count() > 0 ? "PERFECT!" : "NEEDS ATTENTION") . "\n";
    
} catch (\Throwable $th) {
    echo "\n❌ FATAL ERROR DURING TESTING:\n";
    echo "Error: " . $th->getMessage() . "\n";
    echo "File: " . $th->getFile() . ":" . $th->getLine() . "\n";
    echo "\nStack Trace:\n" . $th->getTraceAsString() . "\n";
}

echo "\n💎 Direct logic validation completed!\n";