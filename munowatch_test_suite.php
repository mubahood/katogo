<?php

/**
 * Standalone Munowatch Integration Test Suite
 * 
 * Comprehensive test suite for munowatch crawler integration
 * Does not require PHPUnit - runs directly via PHP CLI
 * 
 * @author Katogo Development Team
 * @date 2025-10-08
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MovieCrawlerWebsite;
use App\Models\MovieCrawlerPage;
use App\Models\Utils;
use Carbon\Carbon;

class MunowatchStandaloneTests
{
    private $munowatchWebsite;
    private $testData;
    private $testsRun = 0;
    private $testsPassed = 0;
    private $testsFailed = 0;
    private $errors = [];
    
    public function __construct()
    {
        $this->initializeTestData();
        echo "🔥 MUNOWATCH STANDALONE TEST SUITE\n";
        echo "=================================\n";
        echo "Date: " . Carbon::now()->toDateTimeString() . "\n";
        echo "Environment: " . (app()->environment() ?? 'Unknown') . "\n\n";
    }
    
    /**
     * Initialize test data and mock responses
     */
    private function initializeTestData()
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
                    ]
                ],
                'total' => 2,
                'page' => 1
            ]),
            'expected_urls' => [
                'https://munowatch.com/movie/test-movie-1',
                'https://munowatch.com/movie/test-series-1'
            ]
        ];
    }
    
    /**
     * Assert helper method
     */
    private function assert($condition, $message, $testName = '')
    {
        $this->testsRun++;
        if ($condition) {
            $this->testsPassed++;
            echo "✅ {$testName} - {$message}\n";
            return true;
        } else {
            $this->testsFailed++;
            $error = "❌ {$testName} - FAILED: {$message}";
            echo $error . "\n";
            $this->errors[] = $error;
            return false;
        }
    }
    
    /**
     * Test 1: Database Operations
     */
    public function test1_DatabaseOperations()
    {
        echo "\n🧪 Test 1: Database Operations\n";
        echo "============================\n";
        
        try {
            // Test 1.1: Find munowatch website
            $this->munowatchWebsite = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
            $this->assert($this->munowatchWebsite !== null, "Munowatch website record exists", "1.1");
            
            if ($this->munowatchWebsite) {
                // Test 1.2: Verify required fields
                $this->assert(!empty($this->munowatchWebsite->name), "Name field not empty: '{$this->munowatchWebsite->name}'", "1.2");
                $this->assert(!empty($this->munowatchWebsite->url), "URL field not empty", "1.3");
                $this->assert($this->munowatchWebsite->token === 'munowatch123', "Token correct: '{$this->munowatchWebsite->token}'", "1.4");
                $this->assert($this->munowatchWebsite->email === 'Api-munowatch-2024', "API key correct: '{$this->munowatchWebsite->email}'", "1.5");
                
                // Test 1.3: Test update capability
                $original_status = $this->munowatchWebsite->status;
                $this->munowatchWebsite->last_tested_at = Carbon::now();
                $saved = $this->munowatchWebsite->save();
                $this->assert($saved, "Database update successful", "1.6");
            }
            
        } catch (Exception $e) {
            $this->assert(false, "Database operations: " . $e->getMessage(), "1.ERROR");
        }
    }
    
    /**
     * Test 2: HTTP Client Functionality
     */
    public function test2_HttpClientFunctionality()
    {
        echo "\n🧪 Test 2: HTTP Client Functionality\n";
        echo "===================================\n";
        
        try {
            // Test 2.1: get_munowatch_headers
            $headers = Utils::get_munowatch_headers('test-token', 'test-api-key');
            $this->assert(is_array($headers), "Headers returned as array", "2.1");
            $this->assert(isset($headers['Authorization']), "Authorization header exists", "2.2");
            $this->assert($headers['Authorization'] === 'Bearer test-token', "Authorization header correct", "2.3");
            $this->assert(isset($headers['X-Api-Key']), "X-Api-Key header exists", "2.4");
            $this->assert($headers['X-Api-Key'] === 'test-api-key', "X-Api-Key header correct", "2.5");
            
            // Test 2.2: get_url_with_auth with test endpoint
            try {
                $response = Utils::get_url_with_auth('https://httpbin.org/headers', ['X-Test' => 'munowatch']);
                $this->assert(!empty($response), "HTTP client returns response", "2.6");
                
                $json = json_decode($response, true);
                $hasHeaders = isset($json['headers']);
                $this->assert($hasHeaders, "Response contains headers section", "2.7");
            } catch (Exception $e) {
                $this->assert(false, "HTTP client test: " . substr($e->getMessage(), 0, 100), "2.6");
            }
            
            // Test 2.3: Error handling
            try {
                Utils::call_munowatch_api('https://invalid-nonexistent-domain.com/api', 'token', 'key');
                $this->assert(false, "Should throw exception for invalid URL", "2.8");
            } catch (Exception $e) {
                $this->assert(true, "Error handling works for invalid URLs", "2.8");
            }
            
        } catch (Exception $e) {
            $this->assert(false, "HTTP client functionality: " . $e->getMessage(), "2.ERROR");
        }
    }
    
    /**
     * Test 3: Model Extensions
     */
    public function test3_ModelExtensions()
    {
        echo "\n🧪 Test 3: Model Extensions\n";
        echo "=========================\n";
        
        try {
            // Test 3.1: MUNOWATCH constant
            $constant_exists = defined('App\Models\MovieCrawlerWebsite::MUNOWATCH');
            $this->assert($constant_exists, "MUNOWATCH constant defined", "3.1");
            
            if ($constant_exists) {
                $this->assert(MovieCrawlerWebsite::MUNOWATCH === 'munowatch', "MUNOWATCH constant value correct", "3.2");
            }
            
            if ($this->munowatchWebsite) {
                // Test 3.2: get_next_page_link method
                $original_page = $this->munowatchWebsite->page_number;
                $original_url = $this->munowatchWebsite->url;
                
                $this->munowatchWebsite->page_number = 5;
                $next_url = $this->munowatchWebsite->get_next_page_link();
                $this->assert(!empty($next_url), "get_next_page_link returns URL: " . substr($next_url, 0, 50), "3.3");
                $this->assert($this->munowatchWebsite->page_number === 6, "Page number incremented to {$this->munowatchWebsite->page_number}", "3.4");
                
                // Test 3.3: Category rotation
                $this->munowatchWebsite->page_number = 20;
                $this->munowatchWebsite->url = 'https://munowatch.com/api/list/p/1/3/{page}';
                $next_url = $this->munowatchWebsite->get_next_page_link();
                $rotated = strpos($next_url, '/p/2/') !== false;
                $this->assert($rotated, "Category rotation working (moved to category 2)", "3.5");
                $this->assert($this->munowatchWebsite->page_number === 1, "Page reset to 1 after rotation", "3.6");
                
                // Restore original values
                $this->munowatchWebsite->page_number = $original_page;
                $this->munowatchWebsite->url = $original_url;
                $this->munowatchWebsite->save();
            }
            
        } catch (Exception $e) {
            $this->assert(false, "Model extensions: " . $e->getMessage(), "3.ERROR");
        }
    }
    
    /**
     * Test 4: Page Processing & Data Saving
     */
    public function test4_PageProcessingAndDataSaving()
    {
        echo "\n🧪 Test 4: Page Processing & Data Saving\n";
        echo "=======================================\n";
        
        try {
            if (!$this->munowatchWebsite) {
                $this->assert(false, "Munowatch website not available for testing", "4.1");
                return;
            }
            
            // Clean up existing test data
            MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)
                           ->where('title', 'LIKE', 'Test %')
                           ->delete();
            
            // Test 4.1: Count initial pages
            $initial_count = MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)->count();
            $this->assert(true, "Initial page count: {$initial_count}", "4.1");
            
            // Test 4.2: Set mock response and process
            $this->munowatchWebsite->response_data = $this->testData['mock_json_response'];
            $this->munowatchWebsite->url = 'https://munowatch.com/api/list/p/1/3/{page}'; // Category 1 = Movies
            
            try {
                $this->munowatchWebsite->process_pages();
                $this->assert(true, "process_pages() executed without errors", "4.2");
            } catch (Exception $e) {
                $this->assert(false, "process_pages() failed: " . $e->getMessage(), "4.2");
                return;
            }
            
            // Test 4.3: Verify new pages created
            $final_count = MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)->count();
            $new_pages = $final_count - $initial_count;
            $this->assert($new_pages > 0, "Created {$new_pages} new pages", "4.3");
            
            // Test 4.4: Verify page data
            $test_pages = MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)
                                         ->where('title', 'LIKE', 'Test %')
                                         ->get();
            
            $this->assert($test_pages->count() > 0, "Found {$test_pages->count()} test pages", "4.4");
            
            foreach ($test_pages as $index => $page) {
                $this->assert(!empty($page->title), "Page {$index}: Title not empty ('{$page->title}')", "4.5." . $index);
                $this->assert(!empty($page->slug), "Page {$index}: Slug not empty ('{$page->slug}')", "4.6." . $index);
                $this->assert(!empty($page->url), "Page {$index}: URL not empty", "4.7." . $index);
                $this->assert($page->status === 'pending', "Page {$index}: Status is pending", "4.8." . $index);
                $this->assert(in_array($page->url, $this->testData['expected_urls']), "Page {$index}: URL format correct", "4.9." . $index);
            }
            
            // Test 4.5: Test duplicate prevention
            $before_duplicate = MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)->count();
            $this->munowatchWebsite->process_pages(); // Process again
            $after_duplicate = MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)->count();
            $this->assert($before_duplicate === $after_duplicate, "Duplicate prevention working", "4.10");
            
            // Test 4.6: Verify website statistics
            $this->munowatchWebsite->refresh();
            $this->assert($this->munowatchWebsite->fetch_status === 'success', "Fetch status is success", "4.11");
            $this->assert($this->munowatchWebsite->new_movies_found > 0, "New movies count updated: {$this->munowatchWebsite->new_movies_found}", "4.12");
            
        } catch (Exception $e) {
            $this->assert(false, "Page processing: " . $e->getMessage(), "4.ERROR");
        }
    }
    
    /**
     * Test 5: Database Integrity
     */
    public function test5_DatabaseIntegrity()
    {
        echo "\n🧪 Test 5: Database Integrity\n";
        echo "============================\n";
        
        try {
            if (!$this->munowatchWebsite) {
                $this->assert(false, "Munowatch website not available", "5.1");
                return;
            }
            
            // Test 5.1: Website record integrity
            $required_fields = ['id', 'name', 'url', 'slug', 'token', 'email', 'status'];
            foreach ($required_fields as $field) {
                $this->assert(!is_null($this->munowatchWebsite->$field), "Website field '{$field}' not null", "5.1.{$field}");
            }
            
            // Test 5.2: Page records integrity
            $pages = MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)->get();
            $this->assert($pages->count() > 0, "Found {$pages->count()} page records", "5.2");
            
            $valid_pages = 0;
            foreach ($pages as $page) {
                if (!empty($page->url) && !empty($page->title) && !is_null($page->status)) {
                    $valid_pages++;
                }
            }
            $this->assert($valid_pages === $pages->count(), "All {$valid_pages} pages have valid data", "5.3");
            
            // Test 5.3: Foreign key relationships
            $orphan_pages = MovieCrawlerPage::whereNotExists(function ($query) {
                $query->select('id')
                      ->from('movie_crawler_websites')
                      ->whereRaw('movie_crawler_websites.id = movie_crawler_pages.movie_crawler_website_id');
            })->count();
            $this->assert($orphan_pages === 0, "No orphan page records found", "5.4");
            
            // Test 5.4: URL format validation
            $test_pages = MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)
                                         ->where('title', 'LIKE', 'Test %')
                                         ->get();
            
            $valid_urls = 0;
            foreach ($test_pages as $page) {
                if (preg_match('/^https:\/\/munowatch\.com\/movie\//', $page->url)) {
                    $valid_urls++;
                }
            }
            $this->assert($valid_urls === $test_pages->count(), "All test page URLs have correct format", "5.5");
            
        } catch (Exception $e) {
            $this->assert(false, "Database integrity: " . $e->getMessage(), "5.ERROR");
        }
    }
    
    /**
     * Clean up test data
     */
    public function cleanupTestData()
    {
        try {
            $deleted = MovieCrawlerPage::where('title', 'LIKE', 'Test %')->delete();
            echo "🧹 Cleaned up {$deleted} test records\n";
        } catch (Exception $e) {
            echo "⚠ Warning: Could not clean up test data: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Generate final test report
     */
    public function generateReport()
    {
        echo "\n📊 COMPREHENSIVE TEST REPORT\n";
        echo "============================\n";
        echo "Total Tests Run: {$this->testsRun}\n";
        echo "Tests Passed: {$this->testsPassed}\n";
        echo "Tests Failed: {$this->testsFailed}\n";
        echo "Success Rate: " . round(($this->testsPassed / $this->testsRun) * 100, 2) . "%\n\n";
        
        if ($this->testsFailed > 0) {
            echo "❌ FAILED TESTS:\n";
            foreach ($this->errors as $error) {
                echo "  " . $error . "\n";
            }
            echo "\n";
        }
        
        // Database statistics
        if ($this->munowatchWebsite) {
            $page_count = MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)->count();
            $test_page_count = MovieCrawlerPage::where('movie_crawler_website_id', $this->munowatchWebsite->id)
                                              ->where('title', 'LIKE', 'Test %')
                                              ->count();
            
            echo "📈 DATABASE STATISTICS:\n";
            echo "• Munowatch Website ID: {$this->munowatchWebsite->id}\n";
            echo "• Website Status: {$this->munowatchWebsite->status}\n";
            echo "• Total Pages: {$page_count}\n";
            echo "• Test Pages Created: {$test_page_count}\n";
            echo "• Last Fetch Status: {$this->munowatchWebsite->fetch_status}\n";
            if ($this->munowatchWebsite->new_movies_found) {
                echo "• New Movies Found: {$this->munowatchWebsite->new_movies_found}\n";
            }
            echo "\n";
        }
        
        if ($this->testsFailed === 0) {
            echo "🎉 ALL TESTS PASSED!\n";
            echo "✅ MUNOWATCH INTEGRATION IS FULLY FUNCTIONAL\n";
            echo "🚀 READY FOR PRODUCTION USE\n\n";
        } else {
            echo "⚠ SOME TESTS FAILED - REVIEW REQUIRED\n\n";
        }
    }
    
    /**
     * Run all tests
     */
    public function runAllTests($cleanup = true)
    {
        $this->test1_DatabaseOperations();
        $this->test2_HttpClientFunctionality();
        $this->test3_ModelExtensions();
        $this->test4_PageProcessingAndDataSaving();
        $this->test5_DatabaseIntegrity();
        
        $this->generateReport();
        
        if ($cleanup) {
            $this->cleanupTestData();
        }
        
        return $this->testsFailed === 0;
    }
}

// Run the tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $testSuite = new MunowatchStandaloneTests();
        $success = $testSuite->runAllTests();
        exit($success ? 0 : 1);
    } catch (Exception $e) {
        echo "💥 CRITICAL ERROR: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
        exit(1);
    }
}