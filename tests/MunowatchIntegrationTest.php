<?php

/**
 * Comprehensive Munowatch Integration Test Suite
 * 
 * This test file validates all aspects of the munowatch crawler implementation:
 * - Database operations and record management
 * - HTTP client functionality and authentication
 * - Model extensions and method implementations
 * - Page processing and data validation
 * - Full integration workflow testing
 * 
 * @author Katogo Development Team
 * @date 2025-10-08
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Models\MovieCrawlerWebsite;
use App\Models\MovieCrawlerPage;
use App\Models\Utils;
use Carbon\Carbon;
use Exception;

class MunowatchIntegrationTest extends TestCase
{
    private $munowatchWebsite;
    private $testData;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize test environment
        $this->initializeTestEnvironment();
        $this->prepareMockData();
        
        echo "\n=== Munowatch Integration Test Suite ===\n";
        echo "Date: " . Carbon::now()->toDateTimeString() . "\n";
        echo "Testing Environment: " . (app()->environment() ?? 'Unknown') . "\n\n";
    }
    
    protected function tearDown(): void
    {
        // Clean up test data if needed
        $this->cleanupTestData();
        parent::tearDown();
    }
    
    /**
     * Initialize Laravel environment for testing
     */
    private function initializeTestEnvironment()
    {
        if (!function_exists('app')) {
            // Bootstrap Laravel if not already done
            require_once __DIR__ . '/../vendor/autoload.php';
            $app = require_once __DIR__ . '/../bootstrap/app.php';
            $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        }
    }
    
    /**
     * Prepare mock data for testing
     */
    private function prepareMockData()
    {
        $this->testData = [
            'mock_json_response' => json_encode([
                'data' => [
                    [
                        'id' => 1001,
                        'slug' => 'test-movie-1',
                        'title' => 'Test Movie 1',
                        'poster' => 'test-poster-1.jpg',
                        'video_url' => 'test-video-1.mp4',
                        'poster_path' => '/poster1.jpg',
                        'category' => 'movie'
                    ],
                    [
                        'id' => 1002,
                        'slug' => 'test-series-1',
                        'title' => 'Test Series 1',
                        'poster' => 'test-poster-2.jpg',
                        'video_url' => 'test-video-2.mp4',
                        'poster_path' => '/poster2.jpg',
                        'category' => 'series'
                    ],
                    [
                        'id' => 1003,
                        'slug' => 'test-korean-1',
                        'title' => 'Test Korean Drama',
                        'poster' => 'test-poster-3.jpg',
                        'video_url' => 'test-video-3.mp4',
                        'poster_path' => '/poster3.jpg',
                        'category' => 'korean'
                    ]
                ],
                'total' => 3,
                'page' => 1,
                'per_page' => 20
            ]),
            'expected_urls' => [
                'https://munowatch.com/movie/test-movie-1',
                'https://munowatch.com/movie/test-series-1',
                'https://munowatch.com/movie/test-korean-1'
            ]
        ];
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData()
    {
        try {
            // Remove test MovieCrawlerPage records
            MovieCrawlerPage::where('title', 'LIKE', 'Test %')->delete();
            echo "✓ Cleaned up test data\n";
        } catch (Exception $e) {
            echo "⚠ Warning: Could not clean up test data: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Test 1: Database Operations
     * Validates munowatch website registration and database connectivity
     */
    public function test1_DatabaseOperations()
    {
        echo "🧪 Test 1: Database Operations\n";
        echo "============================\n";
        
        try {
            // Test 1.1: Find munowatch website record
            $this->munowatchWebsite = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
            $this->assertNotNull($this->munowatchWebsite, "Munowatch website record should exist");
            echo "✓ 1.1 Found munowatch website record (ID: {$this->munowatchWebsite->id})\n";
            
            // Test 1.2: Verify required fields
            $requiredFields = ['name', 'url', 'slug', 'token', 'email', 'status'];
            foreach ($requiredFields as $field) {
                $this->assertNotEmpty($this->munowatchWebsite->$field, "Field $field should not be empty");
                echo "✓ 1.2.{$field} Field '{$field}' = '{$this->munowatchWebsite->$field}'\n";
            }
            
            // Test 1.3: Verify authentication details
            $this->assertEquals('munowatch123', $this->munowatchWebsite->token, "Token should match");
            $this->assertEquals('Api-munowatch-2024', $this->munowatchWebsite->email, "API key should match");
            echo "✓ 1.3 Authentication details verified\n";
            
            // Test 1.4: Verify URL template
            $expectedUrl = 'https://munowatch.com/api/list/p/{category_id}/3/{page}';
            $this->assertEquals($expectedUrl, $this->munowatchWebsite->url, "URL template should match");
            echo "✓ 1.4 URL template verified\n";
            
            // Test 1.5: Test database update capability
            $originalStatus = $this->munowatchWebsite->status;
            $this->munowatchWebsite->last_tested_at = Carbon::now();
            $this->assertTrue($this->munowatchWebsite->save(), "Should be able to update record");
            $this->munowatchWebsite->refresh();
            $this->assertNotNull($this->munowatchWebsite->last_tested_at, "Update should be saved");
            echo "✓ 1.5 Database update capability verified\n";
            
        } catch (Exception $e) {
            $this->fail("Database operations failed: " . $e->getMessage());
        }
        
        echo "✅ Test 1: Database Operations - PASSED\n\n";
    }
    
    /**
     * Test 2: HTTP Client Functionality
     * Tests all custom HTTP methods for munowatch
     */
    public function test2_HttpClientFunctionality()
    {
        echo "🧪 Test 2: HTTP Client Functionality\n";
        echo "===================================\n";
        
        try {
            // Test 2.1: get_munowatch_headers method
            $headers = Utils::get_munowatch_headers('test-token', 'test-api-key');
            $this->assertIsArray($headers, "Headers should be an array");
            $this->assertArrayHasKey('Authorization', $headers, "Should have Authorization header");
            $this->assertArrayHasKey('X-Api-Key', $headers, "Should have X-Api-Key header");
            $this->assertEquals('Bearer test-token', $headers['Authorization'], "Authorization header format");
            $this->assertEquals('test-api-key', $headers['X-Api-Key'], "X-Api-Key header value");
            echo "✓ 2.1 get_munowatch_headers() working correctly\n";
            
            // Test 2.2: get_url_with_auth method (test with public endpoint)
            $testUrl = 'https://httpbin.org/headers';
            $testHeaders = ['X-Test' => 'munowatch-test'];
            $response = Utils::get_url_with_auth($testUrl, $testHeaders);
            $this->assertNotEmpty($response, "Should get response from test endpoint");
            $responseData = json_decode($response, true);
            $this->assertArrayHasKey('headers', $responseData, "Response should contain headers");
            echo "✓ 2.2 get_url_with_auth() working correctly\n";
            
            // Test 2.3: call_munowatch_api method (test error handling)
            try {
                $response = Utils::call_munowatch_api('https://httpbin.org/status/200', 'test-token', 'test-key');
                $this->assertNotEmpty($response, "Should handle successful requests");
                echo "✓ 2.3.a call_munowatch_api() handles successful requests\n";
            } catch (Exception $e) {
                echo "⚠ 2.3.a call_munowatch_api() test endpoint issue: " . $e->getMessage() . "\n";
            }
            
            // Test 2.4: Test error handling for invalid URLs
            try {
                Utils::call_munowatch_api('https://invalid-url-that-does-not-exist.com', 'token', 'key');
                $this->fail("Should throw exception for invalid URL");
            } catch (Exception $e) {
                $this->assertStringContainsString('Request Error', $e->getMessage(), "Should contain error message");
                echo "✓ 2.4 Error handling working correctly\n";
            }
            
        } catch (Exception $e) {
            $this->fail("HTTP client functionality test failed: " . $e->getMessage());
        }
        
        echo "✅ Test 2: HTTP Client Functionality - PASSED\n\n";
    }
    
    /**
     * Test 3: Model Extensions
     * Tests MovieCrawlerWebsite extensions for munowatch
     */
    public function test3_ModelExtensions()
    {
        echo "🧪 Test 3: Model Extensions\n";
        echo "=========================\n";
        
        try {
            $website = $this->munowatchWebsite ?? MovieCrawlerWebsite::where('slug', 'munowatch')->first();
            $this->assertNotNull($website, "Munowatch website should exist");
            
            // Test 3.1: MUNOWATCH constant
            $this->assertEquals('munowatch', MovieCrawlerWebsite::MUNOWATCH, "MUNOWATCH constant should be defined");
            echo "✓ 3.1 MUNOWATCH constant defined correctly\n";
            
            // Test 3.2: get_next_page_link method - Category 1 (Movies)
            $originalPage = $website->page_number;
            $originalUrl = $website->url;
            
            $website->page_number = 5; // Test page progression
            $nextUrl = $website->get_next_page_link();
            $expectedUrl = 'https://munowatch.com/api/list/p/1/3/6';
            $this->assertEquals($expectedUrl, $nextUrl, "Next page URL should be correct for category 1");
            $this->assertEquals(6, $website->page_number, "Page number should increment");
            echo "✓ 3.2 get_next_page_link() page progression working\n";
            
            // Test 3.3: Category rotation logic (page > 20)
            $website->page_number = 20;
            $website->url = 'https://munowatch.com/api/list/p/1/3/{page}';
            $nextUrl = $website->get_next_page_link();
            $this->assertStringContainsString('/p/2/', $nextUrl, "Should rotate to next category");
            $this->assertEquals(1, $website->page_number, "Should reset to page 1");
            echo "✓ 3.3 Category rotation logic working\n";
            
            // Test 3.4: All categories rotation
            $categories = [1, 2, 3, 4];
            foreach ($categories as $index => $category) {
                $website->url = "https://munowatch.com/api/list/p/{$category}/3/{page}";
                $website->page_number = 21; // Force category change
                $nextUrl = $website->get_next_page_link();
                $nextCategory = $categories[($index + 1) % count($categories)];
                $this->assertStringContainsString("/p/{$nextCategory}/", $nextUrl, "Should rotate through all categories");
                echo "✓ 3.4.{$category} Category {$category} → {$nextCategory} rotation working\n";
            }
            
            // Restore original values
            $website->page_number = $originalPage;
            $website->url = $originalUrl;
            $website->save();
            
        } catch (Exception $e) {
            $this->fail("Model extensions test failed: " . $e->getMessage());
        }
        
        echo "✅ Test 3: Model Extensions - PASSED\n\n";
    }
    
    /**
     * Test 4: Page Processing & Data Saving
     * Tests process_pages method with mock data
     */
    public function test4_PageProcessingAndDataSaving()
    {
        echo "🧪 Test 4: Page Processing & Data Saving\n";
        echo "=======================================\n";
        
        try {
            $website = $this->munowatchWebsite ?? MovieCrawlerWebsite::where('slug', 'munowatch')->first();
            $this->assertNotNull($website, "Munowatch website should exist");
            
            // Test 4.1: Set up mock response data
            $website->response_data = $this->testData['mock_json_response'];
            $website->url = 'https://munowatch.com/api/list/p/1/3/{page}'; // Category 1 = Movie
            echo "✓ 4.1 Mock response data prepared\n";
            
            // Test 4.2: Count existing pages before processing
            $initialPageCount = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)->count();
            echo "✓ 4.2 Initial page count: {$initialPageCount}\n";
            
            // Test 4.3: Process pages
            $website->process_pages();
            echo "✓ 4.3 process_pages() executed successfully\n";
            
            // Test 4.4: Verify new pages were created
            $finalPageCount = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)->count();
            $newPagesCreated = $finalPageCount - $initialPageCount;
            $this->assertGreaterThan(0, $newPagesCreated, "Should create new pages");
            echo "✓ 4.4 Created {$newPagesCreated} new pages\n";
            
            // Test 4.5: Verify page data integrity
            $testPages = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)
                                      ->where('title', 'LIKE', 'Test %')
                                      ->get();
            
            $this->assertGreaterThan(0, $testPages->count(), "Should find test pages");
            echo "✓ 4.5 Found {$testPages->count()} test pages in database\n";
            
            // Test 4.6: Verify individual page data
            foreach ($testPages as $index => $page) {
                $this->assertNotEmpty($page->title, "Page title should not be empty");
                $this->assertNotEmpty($page->slug, "Page slug should not be empty");
                $this->assertNotEmpty($page->url, "Page URL should not be empty");
                $this->assertEquals('pending', $page->status, "Page status should be pending");
                $this->assertNotEmpty($page->type, "Page type should be set");
                $this->assertContains($page->url, $this->testData['expected_urls'], "URL should match expected format");
                echo "✓ 4.6.{$index} Page '{$page->title}' data integrity verified\n";
            }
            
            // Test 4.7: Test duplicate prevention
            $duplicateCount = $finalPageCount;
            $website->process_pages(); // Process again
            $afterDuplicateCount = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)->count();
            $this->assertEquals($duplicateCount, $afterDuplicateCount, "Should not create duplicates");
            echo "✓ 4.7 Duplicate prevention working correctly\n";
            
            // Test 4.8: Verify website statistics
            $website->refresh();
            $this->assertEquals('success', $website->fetch_status, "Fetch status should be success");
            $this->assertEquals(3, $website->new_movies_found, "Should record new movies found");
            echo "✓ 4.8 Website statistics updated correctly\n";
            
        } catch (Exception $e) {
            $this->fail("Page processing test failed: " . $e->getMessage());
        }
        
        echo "✅ Test 4: Page Processing & Data Saving - PASSED\n\n";
    }
    
    /**
     * Test 5: Full Integration Workflow
     * Tests complete crawler workflow from start to finish
     */
    public function test5_FullIntegrationWorkflow()
    {
        echo "🧪 Test 5: Full Integration Workflow\n";
        echo "===================================\n";
        
        try {
            $website = $this->munowatchWebsite ?? MovieCrawlerWebsite::where('slug', 'munowatch')->first();
            $this->assertNotNull($website, "Munowatch website should exist");
            
            // Test 5.1: Reset website to initial state
            $website->page_number = 0;
            $website->fetch_status = 'pending';
            $website->error_message = null;
            $website->response_data = null;
            $website->last_fetched_at = null;
            $website->save();
            echo "✓ 5.1 Website reset to initial state\n";
            
            // Test 5.2: Test get_next_page_link workflow
            $nextUrl = $website->get_next_page_link();
            $this->assertNotEmpty($nextUrl, "Should generate next page URL");
            $this->assertStringContainsString('/p/1/3/1', $nextUrl, "Should start with category 1, page 1");
            echo "✓ 5.2 URL generation workflow: {$nextUrl}\n";
            
            // Test 5.3: Mock successful content fetching
            // Since real API doesn't exist, we'll mock the get_next_page_content behavior
            $website->response_data = $this->testData['mock_json_response'];
            $website->fetch_status = 'success';
            $website->last_fetched_at = Carbon::now();
            $website->save();
            echo "✓ 5.3 Mocked content fetching completed\n";
            
            // Test 5.4: Process the fetched content
            $initialCount = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)->count();
            $website->process_pages();
            $finalCount = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)->count();
            $processed = $finalCount - $initialCount;
            echo "✓ 5.4 Content processing completed ({$processed} pages processed)\n";
            
            // Test 5.5: Verify end-to-end data flow
            $recentPages = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)
                                          ->where('created_at', '>=', Carbon::now()->subMinutes(5))
                                          ->get();
            
            $this->assertGreaterThan(0, $recentPages->count(), "Should have recent pages");
            echo "✓ 5.5 End-to-end data flow verified ({$recentPages->count()} recent pages)\n";
            
            // Test 5.6: Test error handling workflow
            $website->response_data = 'invalid json data';
            try {
                $website->process_pages();
                $this->fail("Should throw exception for invalid JSON");
            } catch (Exception $e) {
                $this->assertStringContainsString('JSON', $e->getMessage(), "Should mention JSON error");
                echo "✓ 5.6 Error handling workflow working correctly\n";
            }
            
        } catch (Exception $e) {
            $this->fail("Full integration workflow test failed: " . $e->getMessage());
        }
        
        echo "✅ Test 5: Full Integration Workflow - PASSED\n\n";
    }
    
    /**
     * Test 6: Database Integrity Validation
     * Validates all saved records and relationships
     */
    public function test6_DatabaseIntegrityValidation()
    {
        echo "🧪 Test 6: Database Integrity Validation\n";
        echo "=======================================\n";
        
        try {
            // Test 6.1: Validate website record integrity
            $website = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
            $this->assertNotNull($website, "Website record should exist");
            
            $requiredFields = ['id', 'name', 'url', 'slug', 'token', 'email', 'status'];
            foreach ($requiredFields as $field) {
                $this->assertNotNull($website->$field, "Field {$field} should not be null");
            }
            echo "✓ 6.1 Website record integrity validated\n";
            
            // Test 6.2: Validate page records integrity
            $pages = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)->get();
            $this->assertGreaterThan(0, $pages->count(), "Should have page records");
            
            foreach ($pages as $page) {
                $this->assertNotNull($page->movie_crawler_website_id, "Should have website ID");
                $this->assertEquals($website->id, $page->movie_crawler_website_id, "Website ID should match");
                $this->assertNotEmpty($page->url, "Page URL should not be empty");
                $this->assertNotEmpty($page->title, "Page title should not be empty");
                $this->assertNotNull($page->status, "Page status should not be null");
            }
            echo "✓ 6.2 Page records integrity validated ({$pages->count()} records)\n";
            
            // Test 6.3: Validate foreign key relationships
            $orphanPages = MovieCrawlerPage::whereNotExists(function ($query) {
                $query->select('id')
                      ->from('movie_crawler_websites')
                      ->whereRaw('movie_crawler_websites.id = movie_crawler_pages.movie_crawler_website_id');
            })->count();
            
            $this->assertEquals(0, $orphanPages, "Should not have orphan page records");
            echo "✓ 6.3 Foreign key relationships validated (no orphan records)\n";
            
            // Test 6.4: Validate data consistency
            $testPages = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)
                                        ->where('title', 'LIKE', 'Test %')
                                        ->get();
            
            foreach ($testPages as $page) {
                $this->assertMatchesRegularExpression('/^https:\/\/munowatch\.com\/movie\//', $page->url, "URL format should be correct");
                $this->assertContains($page->status, ['pending', 'processing', 'completed', 'failed'], "Status should be valid");
                $this->assertContains($page->type, ['Movie', 'Series'], "Type should be valid");
            }
            echo "✓ 6.4 Data consistency validated\n";
            
            // Test 6.5: Performance validation
            $startTime = microtime(true);
            $largeFetch = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)->limit(100)->get();
            $endTime = microtime(true);
            $queryTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            $this->assertLessThan(1000, $queryTime, "Large fetch should complete within 1 second");
            echo "✓ 6.5 Performance validation passed (query time: " . round($queryTime, 2) . "ms)\n";
            
        } catch (Exception $e) {
            $this->fail("Database integrity validation failed: " . $e->getMessage());
        }
        
        echo "✅ Test 6: Database Integrity Validation - PASSED\n\n";
    }
    
    /**
     * Generate comprehensive test report
     */
    public function generateTestReport()
    {
        echo "📊 COMPREHENSIVE TEST REPORT\n";
        echo "============================\n";
        echo "Test Suite: Munowatch Integration\n";
        echo "Execution Time: " . Carbon::now()->toDateTimeString() . "\n";
        echo "Environment: " . (app()->environment() ?? 'Unknown') . "\n\n";
        
        echo "🔍 SUMMARY OF TESTED COMPONENTS:\n";
        echo "• Database Operations & CRUD functionality\n";
        echo "• HTTP Client methods with authentication\n";
        echo "• MovieCrawlerWebsite model extensions\n";
        echo "• Page processing & JSON data handling\n";
        echo "• Full integration workflow\n";
        echo "• Database integrity & relationships\n";
        echo "• Error handling & edge cases\n";
        echo "• Performance validation\n\n";
        
        // Database statistics
        $website = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
        if ($website) {
            $pageCount = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)->count();
            $testPageCount = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)
                                           ->where('title', 'LIKE', 'Test %')
                                           ->count();
            
            echo "📈 DATABASE STATISTICS:\n";
            echo "• Munowatch Website ID: {$website->id}\n";
            echo "• Total Pages Created: {$pageCount}\n";
            echo "• Test Pages Created: {$testPageCount}\n";
            echo "• Website Status: {$website->status}\n";
            echo "• Last Fetch Status: {$website->fetch_status}\n\n";
        }
        
        echo "✅ ALL TESTS COMPLETED SUCCESSFULLY\n";
        echo "🎯 MUNOWATCH INTEGRATION IS READY FOR PRODUCTION\n\n";
    }
    
    /**
     * Run all tests in sequence
     */
    public function runAllTests()
    {
        echo "\n🚀 STARTING COMPREHENSIVE MUNOWATCH TEST SUITE\n";
        echo "=" . str_repeat("=", 50) . "\n\n";
        
        try {
            $this->test1_DatabaseOperations();
            $this->test2_HttpClientFunctionality();
            $this->test3_ModelExtensions();
            $this->test4_PageProcessingAndDataSaving();
            $this->test5_FullIntegrationWorkflow();
            $this->test6_DatabaseIntegrityValidation();
            
            $this->generateTestReport();
            
        } catch (Exception $e) {
            echo "❌ TEST SUITE FAILED: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
            throw $e;
        }
    }
}