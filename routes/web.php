<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MainController;
use App\Models\Gen;
use App\Models\MovieCrawlerPage;
use App\Models\MovieCrawlerWebsite;
use App\Models\MovieModel;
use App\Models\MovieView;
use App\Models\SeriesMovie;
use App\Models\TrendingNotification;
use App\Models\Utils;
use Carbon\Carbon;
use Dflydev\DotAccessData\Util;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
/* 

- here are more informationf for you to understand how we clone records 
- we basically have and enpint in web router called (crawler), this is visited once to fetch all pages from the movie site
- we have 3 main levels of fetching movies records 
- 1. Registering a MovieCrawlerWebsite and give it all basic details like url, name, logo, base domain etc. you can check in the database and understand more. this how we shall create one for munowatch and document very well. make sure you save all information that we shall need
- 2. get_next_page_content function gets content of each page and saves in movie_crawler_pages table . so, since our munowatch is an api based site with json ... you have to keep that in mind
- get_next_page_content calls process_pages that processes each page and saves all movie links in movie_models table
- 3. fetch_pages_content function fetches each movie link from movie_models table and get all details of the movie and saves in movie_models table
- so basically you have to make sure all these 3 levels are working well for munowatch and document very well
- I might have not explained everything properly, scan the entire codebase and understand very well. 
- document everything very well. focus on how we shall implement the crawler for munowatch
- do this very very carefully because this is the most important part of the entire project and make sure you understand everything very well
- focus only on the crawler part, ignore everything else for now.

- here are some important points to note
- first read the above implementation plan document everything very well
- now go ahead and plan very well and start implementing the crawler for munowatch
- register the website first, do the next strategic tasks on this very important task
- make sure you document everything very well, every step you take
- make sure you dont skip a thing
- test each and everything that you make and make sure it actually works and saves in database
- for things that need modification, make sure you modify very well and test. for example our http client has some static based things like header, you can make a new dynmic one for munowatch.
- be very caretive, make sure you dont skip anything, 
- make sure you understand the meaning of a movie and the meaning of a series and how we handle them in the system 
- make sure for series are handled very well, because they are a bit complex. 
- avoide duplicates at all cost, for movies that are not active dont conside them as duplicates
- by default, make munowatch movies as active, because they are all active on the site. do this process very well.
- make sure the crawler works very well and fetches all movies and series very well
- and make sure it saves in the database very well and will be continue fetching new movies and series as they are added on the site
- make sure the crawler is efficient and not suspecious to the site
- make sure you document everything very well
- make sure you dont skip any command that i have given you here
- make sure you understand everything very well
*/

/**
 * Main crawler endpoint - handles all website crawling including munowatch series
 */
Route::get('crawler', function () {
    //set unlimited time
    set_time_limit(600); // 10 minutes
    ini_set('memory_limit', '512M'); // 512 MB
    try {
        Utils::fetch_pages();
    } catch (\Throwable $th) {
        echo "Failed to fetch pages because " . $th->getMessage();
    }

    try {
        Utils::fetch_pages_content();
    } catch (\Throwable $th) {
        echo "Failed to fetch page contents because " . $th->getMessage();
    }

    die("success");
});

/**
 * PRODUCTION MUNOWATCH SERIES CRAWLER ENDPOINT 🎬
 * 
 * Production endpoint for crawling munowatch series content.
 * Integrates with the existing 3-level crawler architecture.
 * Focuses specifically on series content from munowatch API.
 */
Route::get('munowatch-series-crawler', function () {
    set_time_limit(600); // 10 minutes
    ini_set('memory_limit', '512M'); // 512 MB
    
    try {
        // Get munowatch website configuration
        $munowatchWebsite = MovieCrawlerWebsite::where('slug', MovieCrawlerWebsite::MUNOWATCH)->first();
        if (!$munowatchWebsite || $munowatchWebsite->status !== 'Active') {
            throw new Exception('Munowatch website not configured or inactive');
        }
        
        echo "🚀 Starting Munowatch Series Crawler...\n";
        echo "=====================================\n\n";
        
        // Show current configuration
        $currentCategory = \App\Models\MunowatchMovieCategory::find($munowatchWebsite->current_munowatch_category_id);
        echo "📋 Current Category: " . ($currentCategory ? $currentCategory->category_name : 'Unknown') . "\n";
        echo "📋 API Endpoint Type: " . ($currentCategory ? $currentCategory->api_endpoint_type : 'Unknown') . "\n\n";
        
        // Step 1: Fetch pages (Level 1 - Website → Pages)
        echo "📥 Level 1: Fetching series pages...\n";
        $munowatchWebsite->get_next_page_content();
        echo "✅ Pages fetched successfully\n\n";
        
        // Step 2: Process page content (Level 2 - Pages → Content)  
        echo "🔍 Level 2: Processing page content...\n";
        Utils::fetch_pages_content();
        echo "✅ Content processed successfully\n\n";
        
        // Step 3: Report detailed results
        echo "📊 Crawler Results:\n";
        echo "==================\n";
        
        $pendingPages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatchWebsite->id)
                                       ->where('status', 'pending')
                                       ->count();
        
        $successPages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatchWebsite->id)
                                       ->where('status', 'success')
                                       ->count();
        
        $recentSeries = SeriesMovie::where('created_at', '>=', Carbon::now()->subHour())
                                 ->count();
        
        $recentMovies = \App\Models\MovieModel::where('created_at', '>=', Carbon::now()->subHour())
                                             ->count();
        
        $recentSeriesEpisodes = \App\Models\MovieModel::where('type', 'Series')
                                                      ->where('created_at', '>=', Carbon::now()->subHour())
                                                      ->count();
        
        echo "Pending Pages: $pendingPages\n";
        echo "Processed Pages: $successPages\n";  
        echo "New Series Created: $recentSeries\n";
        echo "New Movies Created: $recentMovies\n";
        echo "New Series Episodes: $recentSeriesEpisodes\n\n";
        
        // Debug information if no series found
        if ($recentSeries == 0 && $recentSeriesEpisodes == 0 && $recentMovies > 0) {
            echo "⚠️  DEBUG INFO: Only movies detected, no series\n";
            echo "💡 This may indicate:\n";
            echo "   - Current category contains mostly movies\n";
            echo "   - Series detection logic needs refinement\n";
            echo "   - API response format has changed\n\n";
            
            // Show sample of recent content
            $recentContent = \App\Models\MovieModel::where('created_at', '>=', Carbon::now()->subHour())
                                                   ->orderBy('id', 'desc')
                                                   ->limit(3)
                                                   ->get(['id', 'title', 'type']);
            
            echo "📋 Recent Content Sample:\n";
            foreach ($recentContent as $item) {
                echo "  - ID: {$item->id} | Type: {$item->type} | Title: " . substr($item->title, 0, 50) . "...\n";
            }
            echo "\n";
        }
        
        echo "🎯 Munowatch Series Crawler Completed Successfully!\n";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        Log::error('Munowatch series crawler failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

Route::get('migrate', function () {
    // Artisan::call('migrate');
    //do run laravel migration command
    Artisan::call('migrate', ['--force' => true]);
    //returning the output
    return Artisan::output();
});


// Basic Authentication Routes
Route::get('/login', function () {
    return redirect('/'); // Redirect to landing page with login form
})->name('login');

Route::post('/logout', function () {
    auth()->logout();
    return redirect('/');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Landing Site Routes
|--------------------------------------------------------------------------
*/

// Landing page (homepage)
Route::get('/', function () {
    $loggedInUser = Admin::user();
    if ($loggedInUser != null) {
        return redirect(admin_url('/dashboard'));
    }
    return app(LandingController::class)->index();
})->name('landing.index');


Route::get('set-all-movies-to-no', function () {
    $data = [
        'status' => 'Inactive',
        'is_processed' => 'No',
        'downloaded_from_google' => 'No',
        'uploaded_to_from_google' => 'No',
        'plays_on_google' => 'No',
        'downloaded_to_new_server' => 'No',
        'content_type_processed' => 'No',
        'video_is_downloaded_to_server' => 'No',
        'video_url_tested_by_curl' => 'No',
        'video_url_tested_by_curl_works' => 'No',
        'video_url_tested_by_human' => 'No',
        'video_url_tested_by_human_works' => 'No',
        'firebase_transfer_attempted' => 'No',
        'firebase_transfer_transfer_in_progress' => 'No',
        'firebase_transfer_successful' => 'No',
        'firebase_video_tested_by_curl' => 'No',
        'firebase_video_tested_by_curl_works' => 'No',
        'firebase_video_tested_by_human' => 'No',
        'firebase_video_tested_by_human_works' => 'No',
    ];
    DB::table('movie_models')->update($data);
    dd('Done');
});
// Static pages
Route::get('/about', [LandingController::class, 'about'])->name('landing.about');
Route::get('/features', [LandingController::class, 'features'])->name('landing.features');

// Support pages
Route::get('/support', [LandingController::class, 'support'])->name('landing.support');
Route::get('/faq', [LandingController::class, 'faq'])->name('landing.faq');

// Contact pages
Route::get('/contact', [LandingController::class, 'contact'])->name('landing.contact');
Route::post('/contact', [LandingController::class, 'contactSubmit'])->name('landing.contact.submit');

// Legal pages
Route::get('/privacy-policy', [LandingController::class, 'privacyPolicy'])->name('landing.privacy-policy');
Route::get('/terms-of-service', [LandingController::class, 'termsOfService'])->name('landing.terms-of-service');
Route::get('/eula', [LandingController::class, 'eula'])->name('landing.eula');

// Account management page (requires authentication)
Route::get('/account', function () {
    return view('account');
})->middleware('auth')->name('account.index');

// Temporary test routes for account system
Route::get('/test-account-apis', function () {
    $user = \App\Models\User::first();
    if (!$user) {
        $user = \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    auth()->login($user);
    $controller = new \App\Http\Controllers\DynamicCrudController();

    try {
        $request = new \Illuminate\Http\Request();
        $dashboardResponse = $controller->get_account_dashboard($request);
        $dashboard = json_decode($dashboardResponse->getContent(), true);

        return response()->json([
            'message' => 'Account API Test Results',
            'dashboard_status' => $dashboard['success'] ? 'PASS' : 'FAIL',
            'user' => $user->only(['id', 'name', 'email']),
            'timestamp' => now()->toISOString()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'API Test Failed',
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::get('/test-frontend', function () {
    return view('account');
});

/*
|--------------------------------------------------------------------------
| Firebase Video Streaming Routes
|--------------------------------------------------------------------------
*/

// Permanent video streaming route
Route::get('/video/{filename}', function ($filename) {
    // Validate filename for security
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(mp4|mov|avi|mkv)$/', $filename)) {
        return abort(404, 'Invalid video file');
    }

    $firebasePath = "movies/{$filename}";

    // Generate fresh signed URL (24 hours)
    $result = Utils::getFirebaseDownloadUrl($firebasePath, 24);

    if ($result['success']) {
        // Redirect to Firebase URL - this makes the URL appear permanent to users
        return redirect($result['url']);
    }

    return abort(404, 'Video not found');
})->name('video.stream');

// Get permanent public URL for a video
Route::get('/video/{filename}/permanent', function ($filename) {
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(mp4|mov|avi|mkv)$/', $filename)) {
        return abort(404, 'Invalid video file');
    }

    $firebasePath = "movies/{$filename}";
    $result = Utils::getFirebasePermanentUrl($firebasePath);

    if ($result['success']) {
        return response()->json([
            'success' => true,
            'permanent_url' => $result['url'],
            'expires' => $result['expires']
        ]);
    }

    return response()->json([
        'success' => false,
        'error' => $result['error']
    ], 404);
})->name('video.permanent');

/*
|--------------------------------------------------------------------------
| Movie URL Testing and Firebase Transfer Routes 
| curl -s https://katogo.schooldynamics.ug/admin/movies/test-firebase-urls
|--------------------------------------------------------------------------
*/

// Route 1: Production-Ready URL Testing Endpoint
Route::get('/admin/movies/test-urls', function (Request $request) {
    set_time_limit(999300); // 5 minutes for extensive processing

    try {
        // Input validation and sanitization
        $limit = (int) $request->get('limit', 20);
        $type = $request->get('type'); // Optional: 'Movie' or 'Series'

        // Validate limit range
        if ($limit < 1 || $limit > 100) {
            return response()->json([
                'success' => false,
                'error' => 'Limit must be between 1 and 100',
                'provided_limit' => $limit
            ], 400);
        }

        // Validate type if provided
        if ($type && !in_array($type, ['Movie', 'Series'])) {
            return response()->json([
                'success' => false,
                'error' => 'Type must be either "Movie" or "Series"',
                'provided_type' => $type
            ], 400);
        }

        // Get movies with optional type filtering
        $query = \App\Models\MovieModel::whereNotIn('video_url_tested_by_curl', ['Yes'])
            ->orderBy('id', 'asc')
            ->limit($limit);

        if ($type) {
            $query->where('type', $type);
        }

        $movies = $query->get();

        if ($movies->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No movies found that need URL testing',
                'total_tested' => 0,
                'working' => 0,
                'broken' => 0,
                'errors' => 0,
                'results' => []
            ]);
        }

        $results = [
            'total_tested' => 0,
            'working' => 0,
            'broken' => 0,
            'errors' => 0,
            'results' => [],
            'processing_info' => [
                'started_at' => now()->toISOString(),
                'limit_requested' => $limit,
                'type_filter' => $type ?: 'All',
                'movies_found' => $movies->count()
            ]
        ];

        foreach ($movies as $movie) {
            $results['total_tested']++;
            $movieResult = [
                'id' => $movie->id,
                'title' => $movie->title,
                'type' => $movie->type,
                'url' => $movie->url,
                'external_url' => $movie->external_url,
            ];

            try {
                $result = $movie->testExternalVideoUrl();

                if ($result === 'Yes') {
                    $results['working']++;
                    $movieResult['status'] = 'success';
                    $movieResult['works'] = 'Yes';
                } else {
                    $results['broken']++;
                    $movieResult['status'] = 'failed';
                    $movieResult['works'] = 'No';
                }

                // Get fresh data for content type
                $fresh = $movie->fresh();
                $movieResult['content_type'] = $fresh->content_type;
                $movieResult['content_is_video'] = $fresh->content_is_video;
                $movieResult['tested_at'] = $fresh->updated_at;
            } catch (\Exception $e) {
                $results['errors']++;
                $movieResult['status'] = 'error';
                $movieResult['works'] = 'Error';
                $movieResult['error_message'] = $e->getMessage();

                // Log error for debugging
                Log::error("URL Testing Error for Movie {$movie->id}: " . $e->getMessage());
            }

            $results['results'][] = $movieResult;
        }

        $results['processing_info']['completed_at'] = now()->toISOString();
        $results['processing_info']['duration_seconds'] = now()->diffInSeconds($results['processing_info']['started_at']);
        $results['success'] = true;

        return response()->json($results);
    } catch (\Exception $e) {
        Log::error("URL Testing Endpoint Error: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => 'Internal server error during URL testing',
            'message' => $e->getMessage()
        ], 500);
    }
})->name('admin.movies.test-urls');

//curl --request GET https://katogo.schooldynamics.ug/katogo/admin/movies/transfer-firebase
// Route 2: Production-Ready Firebase Transfer Endpoint with Type Support
Route::get('/admin/movies/transfer-firebase', function (Request $request) {
    set_time_limit(999900); // 15 minutes for large video transfers

    try {
        // Input validation and sanitization
        $limit = (int) $request->get('limit', 5);
        $type = $request->get('type'); // REQUIRED: 'Movie' or 'Series'

        if ($type == null || strlen($type) < 3) {
            $type = 'Movie';
        }

        // Validate required type parameter
        if (!$type || !in_array($type, ['Movie', 'Series'])) {
            return response()->json([
                'success' => false,
                'error' => 'Type parameter is required and must be either "Movie" or "Series"',
                'provided_type' => $type,
                'usage' => 'Add ?type=Movie or ?type=Series to the URL'
            ], 400);
        }

        // Validate limit range for safety
        if ($limit < 1 || $limit > 10) {
            return response()->json([
                'success' => false,
                'error' => 'Limit must be between 1 and 10 for safety during transfers',
                'provided_limit' => $limit
            ], 400);
        }

        // Get movies that need Firebase transfer with type filtering
        $query = \App\Models\MovieModel::where('video_url_tested_by_curl_works', 'Yes')
            ->where('type', $type)
            ->whereNotIn('firebase_transfer_successful', ['Yes'])
            ->orderBy('id', 'asc')
            ->limit($limit);

        //override
       /*  $movies = MovieModel::where([
            'firebase_transfer_attempted' => 'No',
            'stars' => 'MyVj', 
        ])->orderBy('id', 'asc')
            ->limit(10)
            ->get(); */

        //if empty, use original query
        /* if ($movies->count() == 0) {
        } */
        $movies = $query->get();

        if ($movies->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => "No {$type} movies found that need Firebase transfer",
                'total_processed' => 0,
                'successful_transfers' => 0,
                'failed_transfers' => 0,
                'skipped' => 0,
                'results' => []
            ]);
        }

        $results = [
            'total_processed' => 0,
            'successful_transfers' => 0,
            'failed_transfers' => 0,
            'skipped' => 0,
            'results' => [],
            'processing_info' => [
                'started_at' => now()->toISOString(),
                'limit_requested' => $limit,
                'type_filter' => $type,
                'movies_found' => $movies->count()
            ]
        ];

        foreach ($movies as $movie) {
            $results['total_processed']++;
            $movieResult = [
                'id' => $movie->id,
                'title' => $movie->title,
                'type' => $movie->type,
                'original_url' => $movie->url,
            ];

            try {
                // Check if already exists in Firebase first
                if ($movie->checkFirebaseExists()) {
                    $movie->firebase_transfer_successful = 'Yes';
                    $movie->firebase_transfer_attempted = 'Yes';
                    $movie->save();

                    $results['skipped']++;
                    $movieResult['status'] = 'skipped';
                    $movieResult['message'] = 'Already exists in Firebase';
                    $movieResult['firebase_url'] = $movie->firebase_video_url;
                    $movieResult['firebase_path'] = $movie->firebase_transfer_path;
                } else {
                    // Perform the transfer
                    $transferResult = $movie->transferToFirebase();

                    if ($transferResult['success']) {
                        $results['successful_transfers']++;
                        $movieResult['status'] = 'success';
                        $movieResult['message'] = $transferResult['message'];
                        $movieResult['firebase_url'] = $transferResult['firebase_url'];
                        $movieResult['firebase_path'] = $transferResult['firebase_path'] ?? null;
                        $movieResult['file_size'] = $transferResult['file_size'] ?? null;
                    } else {
                        $results['failed_transfers']++;
                        $movieResult['status'] = 'failed';
                        $movieResult['error'] = $transferResult['error'];
                        $movieResult['message'] = $transferResult['message'] ?? 'Transfer failed';
                    }
                }

                $movieResult['processed_at'] = now()->toISOString();
            } catch (\Exception $e) {
                $results['failed_transfers']++;
                $movieResult['status'] = 'error';
                $movieResult['error'] = $e->getMessage();
                $movieResult['message'] = 'Exception during transfer process';

                // Log critical transfer errors
                Log::error("Firebase Transfer Error for {$type} Movie {$movie->id}: " . $e->getMessage());

                // Update movie status to reflect error
                try {
                    $movie->firebase_transfer_attempted = 'Yes';
                    $movie->firebase_transfer_failure_reason = substr($e->getMessage(), 0, 500);
                    $movie->save();
                } catch (\Exception $saveError) {
                    Log::error("Failed to save error status for Movie {$movie->id}: " . $saveError->getMessage());
                }
            }

            $results['results'][] = $movieResult;
        }

        $results['processing_info']['completed_at'] = now()->toISOString();
        $results['processing_info']['duration_seconds'] = now()->diffInSeconds($results['processing_info']['started_at']);
        $results['success'] = true;

        return response()->json($results);
    } catch (\Exception $e) {
        Log::error("Firebase Transfer Endpoint Error: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => 'Internal server error during Firebase transfer',
            'message' => $e->getMessage()
        ], 500);
    }
})->name('admin.movies.transfer-firebase');

// curl --request GET https://katogo.schooldynamics.ug/katogo/admin/movies/transfer-firebase
// Route 3: Production-Ready Firebase URL Testing Endpoint
Route::get('/admin/movies/test-firebase-urls', function (Request $request) {
    set_time_limit(999300); // 5 minutes for URL testing

    try {
        // Input validation and sanitization
        $limit = (int) $request->get('limit', 20);
        $type = $request->get('type'); // Optional: 'Movie' or 'Series'

        // Validate limit range
        if ($limit < 1 || $limit > 50) {
            return response()->json([
                'success' => false,
                'error' => 'Limit must be between 1 and 50',
                'provided_limit' => $limit
            ], 400);
        }

        // Validate type if provided
        if ($type && !in_array($type, ['Movie', 'Series'])) {
            return response()->json([
                'success' => false,
                'error' => 'Type must be either "Movie" or "Series"',
                'provided_type' => $type
            ], 400);
        }

        // Get movies that need Firebase URL testing
        $query = \App\Models\MovieModel::where('firebase_transfer_successful', 'Yes')
            ->whereNotIn('firebase_video_tested_by_curl', ['Yes'])
            ->orderBy('id', 'asc')
            ->limit($limit);

        if ($type) {
            $query->where('type', $type);
        }

        $movies = $query->get();

        if ($movies->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No movies found that need Firebase URL testing',
                'total_tested' => 0,
                'working' => 0,
                'broken' => 0,
                'errors' => 0,
                'results' => []
            ]);
        }

        $results = [
            'total_tested' => 0,
            'working' => 0,
            'broken' => 0,
            'errors' => 0,
            'auto_activated' => 0,
            'results' => [],
            'processing_info' => [
                'started_at' => now()->toISOString(),
                'limit_requested' => $limit,
                'type_filter' => $type ?: 'All',
                'movies_found' => $movies->count()
            ]
        ];

        foreach ($movies as $movie) {
            $results['total_tested']++;
            $movieResult = [
                'id' => $movie->id,
                'title' => $movie->title,
                'type' => $movie->type,
                'firebase_url' => $movie->firebase_video_url,
                'firebase_path' => $movie->firebase_transfer_path,
            ];

            try {
                $result = $movie->testFirebaseVideoUrl();

                // Get fresh data to check for auto-activation
                $fresh = $movie->fresh();
                $wasActivated = ($fresh->status == 'Active' && $result === 'Yes');

                if ($result === 'Yes') {
                    $results['working']++;
                    $movieResult['status'] = 'success';
                    $movieResult['works'] = 'Yes';

                    if ($wasActivated) {
                        $results['auto_activated']++;
                        $movieResult['auto_activated'] = true;
                        $movieResult['message'] = 'Firebase URL working and movie auto-activated';
                    } else {
                        $movieResult['auto_activated'] = false;
                        $movieResult['message'] = 'Firebase URL working';
                    }
                } else {
                    $results['broken']++;
                    $movieResult['status'] = 'failed';
                    $movieResult['works'] = 'No';
                    $movieResult['auto_activated'] = false;
                    $movieResult['message'] = 'Firebase URL not accessible';
                }

                $movieResult['content_type'] = $fresh->content_type;
                $movieResult['current_status'] = $fresh->status;
                $movieResult['tested_at'] = $fresh->updated_at;
            } catch (\Exception $e) {
                $results['errors']++;
                $movieResult['status'] = 'error';
                $movieResult['works'] = 'Error';
                $movieResult['auto_activated'] = false;
                $movieResult['error_message'] = $e->getMessage();

                // Log Firebase testing errors
                Log::error("Firebase URL Testing Error for Movie {$movie->id}: " . $e->getMessage());
            }

            $results['results'][] = $movieResult;
        }

        $results['processing_info']['completed_at'] = now()->toISOString();
        $results['processing_info']['duration_seconds'] = now()->diffInSeconds($results['processing_info']['started_at']);
        $results['success'] = true;

        return response()->json($results);
    } catch (\Exception $e) {
        Log::error("Firebase URL Testing Endpoint Error: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => 'Internal server error during Firebase URL testing',
            'message' => $e->getMessage()
        ], 500);
    }
})->name('admin.movies.test-firebase-urls');

// Route 4: Comprehensive Dashboard with Production-Level Statistics
Route::get('/admin/movies/dashboard', function (Request $request) {
    set_time_limit(999120); // Extended time for comprehensive analysis

    try {
        // 1. BASIC MOVIE STATISTICS
        $total_movies = \App\Models\MovieModel::count();
        $movies_count = \App\Models\MovieModel::where('type', 'Movie')->count();
        $series_count = \App\Models\MovieModel::where('type', 'Series')->count();

        // 2. STATUS BREAKDOWN
        $status_stats = [
            'active' => \App\Models\MovieModel::where('status', 'Active')->count(),
            'inactive' => \App\Models\MovieModel::where('status', 'Inactive')->count(),
        ];

        // 3. URL TESTING PIPELINE STATISTICS
        $url_testing_stats = [
            'not_tested' => \App\Models\MovieModel::whereNotIn('video_url_tested_by_curl', ['Yes'])->count(),
            'tested_total' => \App\Models\MovieModel::where('video_url_tested_by_curl', 'Yes')->count(),
            'working_urls' => \App\Models\MovieModel::where('video_url_tested_by_curl_works', 'Yes')->count(),
            'broken_urls' => \App\Models\MovieModel::where('video_url_tested_by_curl', 'Yes')
                ->where('video_url_tested_by_curl_works', 'No')->count(),
            'human_tested' => \App\Models\MovieModel::where('video_url_tested_by_human', 'Yes')->count(),
            'human_verified_working' => \App\Models\MovieModel::where('video_url_tested_by_human_works', 'Yes')->count(),
        ];

        // 4. FIREBASE TRANSFER PIPELINE STATISTICS
        $firebase_transfer_stats = [
            'ready_for_transfer' => \App\Models\MovieModel::where('video_url_tested_by_curl_works', 'Yes')
                ->whereNotIn('firebase_transfer_successful', ['Yes'])->count(),
            'transfer_attempted' => \App\Models\MovieModel::where('firebase_transfer_attempted', 'Yes')->count(),
            'transfer_in_progress' => \App\Models\MovieModel::where('firebase_transfer_transfer_in_progress', 'Yes')->count(),
            'transfer_successful' => \App\Models\MovieModel::where('firebase_transfer_successful', 'Yes')->count(),
            'transfer_failed' => \App\Models\MovieModel::where('firebase_transfer_attempted', 'Yes')
                ->where('firebase_transfer_successful', '!=', 'Yes')->count(),
        ];

        // 5. FIREBASE URL TESTING STATISTICS
        $firebase_url_stats = [
            'need_testing' => \App\Models\MovieModel::where('firebase_transfer_successful', 'Yes')
                ->whereNotIn('firebase_video_tested_by_curl', ['Yes'])->count(),
            'tested_total' => \App\Models\MovieModel::where('firebase_video_tested_by_curl', 'Yes')->count(),
            'working_firebase_urls' => \App\Models\MovieModel::where('firebase_video_tested_by_curl_works', 'Yes')->count(),
            'broken_firebase_urls' => \App\Models\MovieModel::where('firebase_video_tested_by_curl', 'Yes')
                ->where('firebase_video_tested_by_curl_works', 'No')->count(),
            'human_firebase_tested' => \App\Models\MovieModel::where('firebase_video_tested_by_human', 'Yes')->count(),
        ];

        // 6. CONTENT TYPE ANALYSIS
        $content_stats = [
            'content_processed' => \App\Models\MovieModel::where('content_type_processed', 'Yes')->count(),
            'content_not_processed' => \App\Models\MovieModel::where('content_type_processed', '!=', 'Yes')->count(),
            'confirmed_videos' => \App\Models\MovieModel::where('content_is_video', 'Yes')->count(),
            'non_videos' => \App\Models\MovieModel::where('content_is_video', 'No')->count(),
        ];

        // 7. CATEGORY AND TYPE BREAKDOWN
        $type_breakdown = [
            'movies' => [
                'total' => $movies_count,
                'active' => \App\Models\MovieModel::where('type', 'Movie')->where('status', 'Active')->count(),
                'firebase_ready' => \App\Models\MovieModel::where('type', 'Movie')->where('firebase_video_tested_by_curl_works', 'Yes')->count(),
            ],
            'series' => [
                'total' => $series_count,
                'active' => \App\Models\MovieModel::where('type', 'Series')->where('status', 'Active')->count(),
                'firebase_ready' => \App\Models\MovieModel::where('type', 'Series')->where('firebase_video_tested_by_curl_works', 'Yes')->count(),
            ]
        ];

        // 8. ERROR TRACKING AND ANALYTICS
        $error_stats = [
            'movies_with_errors' => \App\Models\MovieModel::whereNotNull('error_message')->count(),
            'firebase_transfer_errors' => \App\Models\MovieModel::whereNotNull('firebase_transfer_failure_reason')->count(),
            'download_errors' => \App\Models\MovieModel::where('video_is_downloaded_to_server_status', 'error')->count(),
        ];

        // 9. PROCESSING PIPELINE EFFICIENCY
        $pipeline_stats = [
            'complete_pipeline' => \App\Models\MovieModel::where('video_url_tested_by_curl_works', 'Yes')
                ->where('firebase_transfer_successful', 'Yes')
                ->where('firebase_video_tested_by_curl_works', 'Yes')
                ->where('status', 'Active')->count(),
            'stuck_at_url_testing' => \App\Models\MovieModel::whereNotIn('video_url_tested_by_curl', ['Yes'])->count(),
            'stuck_at_firebase_transfer' => \App\Models\MovieModel::where('video_url_tested_by_curl_works', 'Yes')
                ->where('firebase_transfer_successful', '!=', 'Yes')->count(),
            'stuck_at_firebase_testing' => \App\Models\MovieModel::where('firebase_transfer_successful', 'Yes')
                ->whereNotIn('firebase_video_tested_by_curl', ['Yes'])->count(),
        ];

        // 10. PERFORMANCE METRICS
        $performance_stats = [
            'success_rate_url_testing' => $url_testing_stats['tested_total'] > 0 ?
                round(($url_testing_stats['working_urls'] / $url_testing_stats['tested_total']) * 100, 2) : 0,
            'success_rate_firebase_transfer' => $firebase_transfer_stats['transfer_attempted'] > 0 ?
                round(($firebase_transfer_stats['transfer_successful'] / $firebase_transfer_stats['transfer_attempted']) * 100, 2) : 0,
            'success_rate_firebase_urls' => $firebase_url_stats['tested_total'] > 0 ?
                round(($firebase_url_stats['working_firebase_urls'] / $firebase_url_stats['tested_total']) * 100, 2) : 0,
            'overall_pipeline_completion' => $total_movies > 0 ?
                round(($pipeline_stats['complete_pipeline'] / $total_movies) * 100, 2) : 0,
        ];

        // 11. RECENT ACTIVITY (Last 24 hours)
        $recent_stats = [
            'urls_tested_today' => \App\Models\MovieModel::where('video_url_tested_by_curl', 'Yes')
                ->where('updated_at', '>=', now()->subDay())->count(),
            'firebase_transfers_today' => \App\Models\MovieModel::where('firebase_transfer_successful', 'Yes')
                ->where('updated_at', '>=', now()->subDay())->count(),
            'activated_today' => \App\Models\MovieModel::where('status', 'Active')
                ->where('updated_at', '>=', now()->subDay())->count(),
        ];

        // 12. NEXT ACTIONS NEEDED
        $action_items = [
            'ready_for_url_testing' => $pipeline_stats['stuck_at_url_testing'],
            'ready_for_firebase_transfer' => $pipeline_stats['stuck_at_firebase_transfer'],
            'ready_for_firebase_testing' => $pipeline_stats['stuck_at_firebase_testing'],
        ];

        return response()->json([
            'success' => true,
            'generated_at' => now()->toISOString(),
            'summary' => [
                'total_movies' => $total_movies,
                'pipeline_completion_rate' => $performance_stats['overall_pipeline_completion'] . '%',
                'active_movies' => $status_stats['active'],
                'production_ready' => $pipeline_stats['complete_pipeline']
            ],
            'breakdown' => [
                'basic_stats' => [
                    'total_movies' => $total_movies,
                    'movies' => $movies_count,
                    'series' => $series_count,
                ],
                'status_distribution' => $status_stats,
                'url_testing_pipeline' => $url_testing_stats,
                'firebase_transfer_pipeline' => $firebase_transfer_stats,
                'firebase_url_testing' => $firebase_url_stats,
                'content_analysis' => $content_stats,
                'type_breakdown' => $type_breakdown,
                'error_tracking' => $error_stats,
                'pipeline_efficiency' => $pipeline_stats,
                'performance_metrics' => $performance_stats,
                'recent_activity' => $recent_stats,
                'action_items' => $action_items,
            ],
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Dashboard generation failed: ' . $e->getMessage(),
        ], 500);
    }
})->name('admin.movies.dashboard');

/*
|--------------------------------------------------------------------------
| API and Admin Routes (existing)
|--------------------------------------------------------------------------
*/


Route::get('check-ffmpeg', function (Request $request) {
    // Path to the FFmpeg binary.
    // On cPanel, it might be in a common system path, or sometimes hosts provide a specific path.
    // If 'ffmpeg' isn't found, you might need to try common paths like '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg',
    // or check with your hosting provider for the exact path.
    $ffmpegBinary = 'ffmpeg'; // Start by trying the common system path alias

    // Use Symfony Process component (already included in Laravel) for safer execution.
    // This avoids direct shell_exec/exec which can be risky and harder to debug.
    $process = new Process([$ffmpegBinary, '-version']);
    $process->setTimeout(10); // Set a timeout to prevent hanging

    try {
        $process->run();

        // Executes the command and returns the exit code, throws an exception on error
        if (!$process->isSuccessful()) {
            // FFmpeg command failed, likely because it's not found or not executable.
            // Capture error output for debugging.
            $errorMessage = "FFmpeg command failed or not found: " . $process->getErrorOutput();
            return response()->json([
                'status' => 'error',
                'message' => $errorMessage,
                'is_ffmpeg_installed' => false
            ], 200); // Use 200 as it's a successful response to the check, even if FFmpeg isn't there.
        }

        // If successful, FFmpeg is installed. Get the version output.
        $output = $process->getOutput();
        $versionLine = '';
        if (preg_match('/ffmpeg version (\S+)/', $output, $matches)) {
            $versionLine = 'FFmpeg version: ' . $matches[1];
        } else {
            $versionLine = 'FFmpeg found, but version could not be parsed. Full output: ' . substr($output, 0, 200) . '...';
        }

        return response()->json([
            'status' => 'success',
            'message' => 'FFmpeg is installed and executable.',
            'is_ffmpeg_installed' => true,
            'version_info' => $versionLine,
            'full_output_snippet' => substr($output, 0, 500) . '...' // Include a snippet for more context
        ]);
    } catch (ProcessFailedException $exception) {
        // This catches exceptions if the process itself couldn't be run (e.g., command not found).
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to run FFmpeg command. It might not be installed or the path is incorrect. Error: ' . $exception->getMessage(),
            'is_ffmpeg_installed' => false
        ], 200); // Still 200 for a successful response to the check request.
    } catch (\Exception $e) {
        // Catch any other unexpected exceptions
        return response()->json([
            'status' => 'error',
            'message' => 'An unexpected error occurred: ' . $e->getMessage(),
            'is_ffmpeg_installed' => false
        ], 500);
    }
});
Route::get('process-views', function (Request $request) {
    $views = MovieView::all();
    foreach ($views as $key => $v) {
        if ($v->movie == null) {
            echo "<br>Movie not found : => " . $v->movie_model_id;
            continue;
        }
        $v->update_views();
        echo $v->movie->views_time_count . " Secs<br>";
        continue;
    }

    die();
});
Route::get('send-notifications', function (Request $request) {

    try {
        $trending =  TrendingNotification::getTendingMovie();
    } catch (\Throwable $th) {
        //throw $th;
        echo $th->getMessage();
        die();
    }
    if ($trending == null) {
        echo 'No trending movie found';
        die();
    }
    $movie = $trending;
    if ($movie == null) {
        echo 'No movie found';
        die();
    }
    echo 'Movie found<br>';
    echo $movie->id . ' - ' . $movie->title . '<br>';
    echo $movie->url . '<br>';
    echo '<img src="' . $movie->thumbnail_url . '" width="100" height="100" alt=""><br>';
    echo 'Sending notification...<br>';
    die();
});
Route::get('fix-serries-movies', function (Request $request) {
    //where url like namzentertainment


    if (isset($_GET['id'])) {
        $id = $request->get('id');

        $series = SeriesMovie::where('id', $id)
            ->get();
    } else {



        $series = SeriesMovie::where('external_url', 'like', '%namzentertainment%')
            ->where(['is_active' => 'No'])
            ->orderBy('id', 'asc')
            ->limit(1000000)
            ->get();
    }

    //set limited time
    ini_set('memory_limit', '256M');
    ini_set('max_execution_time', '300');
    ini_set('max_input_time', '300');
    ini_set('upload_max_filesize', '50M');
    ini_set('post_max_size', '50M');
    foreach ($series as $key => $ser) {

        $my_html = null;
        $url = $ser->external_url;

        try {
            $my_html = Utils::get_url_2($url);
        } catch (\Throwable $th) {
            //throw $th;
            echo $th->getMessage();
            echo "<hr>";
            continue;
        }

        if ($my_html == null) {
            echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - not found<br>';
            $ser->is_active = 'Failed';
            $ser->description .=  ' - Episodes ARE NULL';
            $ser->save();
            continue;
        }


        $html = str_get_html($my_html);
        //.details__title


        $episodes = [];
        $mCustomScrollbar = $html->find('.accordion__list', 0);
        if ($mCustomScrollbar == null) {
            echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - not found IS NOT SERIES<br>';
            $ser->description .=  ' - Episodes ARE NULL';
            $ser->is_active = 'Failed';
            $ser->save();
            continue;
        }

        if ($mCustomScrollbar != null) {
            $links = $mCustomScrollbar->find('tr');


            if ($links != null) {
                $count = 0;
                foreach ($links as $key => $value) {
                    $tds = $value->find('td');
                    if ($tds == null) {
                        continue;
                    }
                    $td = $value->find('td', 0);
                    if ($td == null) {
                        continue;
                    }
                    $data_target = $value->getAttribute('data-target');
                    $ep_name = trim($td->plaintext);
                    $ep_url = $data_target;
                    $ep['title'] = $ep_name;
                    $ep['url'] = $ep_url;
                    $splits = explode(' ', $ep_name);
                    $count++;
                    $num = $count;
                    foreach ($splits as $key => $value) {
                        $_num = trim($value);
                        if (is_numeric($_num)) {
                            $num = $value;
                        }
                    }
                    $ep['number'] = $num;
                    $episodes[] = $ep;
                }
            }
        }

        if ($episodes == null) {
            echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - not found IS NOT SERIES<br>';
            $ser->is_active = 'Failed';
            $ser->description .= ' - Episodes ARE NULL';
            $ser->save();
            continue;
        }
        if (count($episodes) == 0) {
            echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - not found IS NOT SERIES<br>';
            $ser->is_active = 'Failed';
            $ser->description .=  ' - Episodes not found';
            $ser->save();
            continue;
        }


        $imgObj = $html->find('.card__cover img', 0);
        if ($imgObj != null) {
            $img_url = $imgObj->getAttribute('src');
            if ($img_url != null) {
                //if not contain http
                $img_url = trim($img_url);
                if (strpos($img_url, 'http') === false) {
                    $img_url = 'https://namzentertainment.com/' . $img_url;
                }
                // $ser->thumbnail = $img_url;
            }
        }




        $serie = $ser;
        if ($episodes != null && count($episodes) > 0) {



            foreach ($episodes as $key => $value) {
                $ep_url = $value['url'];
                $ep_title = $value['title'];

                $ep = MovieModel::where([
                    'external_url' => $ep_url
                ])->first();

                if ($ep == null) {
                    $ep = MovieModel::where([
                        'url' => $ep_url
                    ])->first();
                }
                $isEdit = false;
                if ($ep != null) {
                    $isEdit = true;
                } else {
                    $isEdit = false;
                }

                if ($ep == null) {
                    $ep = new MovieModel();
                }

                $ep->title = $serie->title . ' - ' . $ep_title;
                $ep->external_url = $ep_url;
                $ep->url = $ep_url;
                $ep->category_id = $serie->id;
                $ep->category_id = $serie->id;
                $ep->category = $serie->title;
                $ep->description = $serie->description;
                $ep->thumbnail_url = $serie->thumbnail;
                $ep->content_type = 'video/mp4';
                $ep->content_is_video = 'Yes';
                $ep->content_type_processed = 'No';
                $ep->content_type_processed_time = null;
                $ep->type = 'Series';
                $ep->is_premium = 'No';
                if (isset($value['number'])) {
                    $ep->episode_number = $value['number'];
                    $ep->country = $value['number'];
                }


                if ($isEdit) {
                    echo ' - edit - ';
                } else {
                    echo ' - new - ';
                }
                //save
                try {
                    $ep->save();
                    echo $ep->id . ' - saved - ===> ' . $ep->title . "<br>";
                    echo '<a href="' . $ep->url . '" target="_blank">Watch Video => ' . $ep->url . '</a><br>';
                } catch (\Throwable $th) {
                    echo ' - error - ';
                    echo '<br>';
                    echo '<pre>';
                    print_r($th);
                    echo '</pre>';
                }
            }
        }

        echo "<hr>";
        //echo done
        echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - done<br>';
        $ser->is_active = 'Yes';
        $ser->description .=  ' - Episodes found';
        $ser->save();
        echo '<img src="' . $ser->thumbnail . '" width="100" height="100" alt="">';
        echo "<hr>";

        continue;
    }
    dd($series);
});

// Fix munowatch series episodes - specialized for munowatch API pattern
Route::get('fix-munowatch-series', function (Request $request) {
    // Set execution limits for processing multiple episodes
    ini_set('memory_limit', '512M');
    ini_set('max_execution_time', '600');
    ini_set('max_input_time', '600');
    ini_set('upload_max_filesize', '100M');
    ini_set('post_max_size', '100M');
    
    // Get series ID from request
    if (!isset($_GET['id'])) {
        echo '<h1>Error: No series ID provided</h1>';
        return;
    }
    
    $seriesId = $request->get('id');
    $series = \App\Models\SeriesMovie::find($seriesId);
    
    if (!$series) {
        echo '<h1>Error: Series not found with ID: ' . $seriesId . '</h1>';
        return;
    }
    
    echo '<h1>🎬 MUNOWATCH SERIES EPISODE FIXER 🎬</h1>';
    echo '<h2>Processing: ' . htmlspecialchars($series->title) . '</h2>';
    echo '<p>Series ID: ' . $series->id . '</p>';
    echo '<p>External URL: ' . htmlspecialchars($series->external_url) . '</p>';
    echo '<hr>';
    
    try {
        // Create a MovieCrawlerPage instance to use our existing munowatch logic
        require_once app_path('Models/MovieCrawlerPage.php');
        
        $crawler = new \App\Models\MovieCrawlerPage();
        $crawler->url = $series->external_url;
        $crawler->page_content = ''; // Will be fetched by our method
        
        // ===== PHASE 1: FETCH MUNOWATCH API DATA =====
        echo '<h3>📥 Phase 1: Fetching Munowatch API Data</h3>';
        
        // Extract videoId and userId from external URL pattern
        // URL format: https://munowatch.org/api/preview/v2/userId/videoId
        $userId = null;
        $videoId = null;
        
        if (preg_match('/preview\/v2\/(\d+)\/(\d+)/', $series->external_url, $matches)) {
            $userId = $matches[1];
            $videoId = $matches[2];
            echo '<p>✅ Extracted from URL - User ID: ' . $userId . ', Video ID: ' . $videoId . '</p>';
        } else {
            echo '<p style="color: red;">❌ Could not extract user ID and video ID from URL</p>';
            echo '<p>Expected format: https://munowatch.org/api/preview/v2/userId/videoId</p>';
            echo '<p>Actual URL: ' . htmlspecialchars($series->external_url) . '</p>';
            return;
        }
        
        // Fetch movie/show details using Flutter app pattern: preview/v2/{videoId}/{userId}
        $previewUrl = "https://munowatch.org/api/preview/v2/{$videoId}/{$userId}";
        echo '<p>📡 Preview API URL: ' . $previewUrl . '</p>';
        
        $apiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
        
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'X-Api-Key: ' . $apiKey,
            'User-Agent: okhttp/4.9.0',
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $previewUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$apiResponse) {
            echo '<p style="color: red;">❌ Preview API request failed. HTTP Code: ' . $httpCode . '</p>';
            
            // Handle specific error cases
            if ($httpCode === 404) {
                echo '<div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0;">';
                echo '<h4>🔍 Video Not Found (404 Error)</h4>';
                echo '<p><strong>Issue:</strong> Video ID ' . $videoId . ' does not exist in the munowatch database.</p>';
                echo '<p><strong>Possible Solutions:</strong></p>';
                echo '<ol>';
                echo '<li><strong>Check the Video ID:</strong> Verify that the video ID ' . $videoId . ' is correct</li>';
                echo '<li><strong>Find Valid Video ID:</strong> Browse munowatch.org to find a valid series and copy its video ID</li>';
                echo '<li><strong>Test with Known Working ID:</strong> Try with a different video ID from an existing series</li>';
                echo '</ol>';
                echo '<p><strong>URL Format Expected:</strong> https://munowatch.org/api/preview/v2/{userId}/{videoId}</p>';
                echo '<p><strong>Current URL:</strong> ' . htmlspecialchars($series->external_url) . '</p>';
                echo '</div>';
                
                // Try to suggest alternative approach
                echo '<div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 10px 0;">';
                echo '<h4>💡 Alternative Approach</h4>';
                echo '<p>Since this video ID doesn\'t exist, you could:</p>';
                echo '<ol>';
                echo '<li>Update the external_url field with a valid munowatch preview URL</li>';
                echo '<li>Or find the correct video ID for this series</li>';
                echo '</ol>';
                echo '<p><strong>To find valid video IDs:</strong> Visit munowatch.org, browse series, and check the URLs</p>';
                echo '</div>';
            } else {
                echo '<p>Please check the URL format and try again.</p>';
            }
            return;
        }
        
        echo '<p>✅ Preview API data received successfully</p>';
        
        // ===== PHASE 2: PROCESS PREVIEW API RESPONSE =====
        echo '<h3>🔍 Phase 2: Processing Preview API Response</h3>';
        
        $previewData = json_decode($apiResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo '<p style="color: red;">❌ Failed to parse preview JSON response</p>';
            return;
        }
        
        // Check if API returned an error message (even with 200 status)
        if (isset($previewData['msg']) && strpos($previewData['msg'], 'not found') !== false) {
            echo '<div style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0;">';
            echo '<h4>❌ Video Not Found in Database</h4>';
            echo '<p><strong>API Message:</strong> ' . htmlspecialchars($previewData['msg']) . '</p>';
            echo '<p><strong>Video ID:</strong> ' . $videoId . '</p>';
            echo '<p><strong>User ID:</strong> ' . $userId . '</p>';
            echo '<br>';
            echo '<p><strong>What this means:</strong> The video ID doesn\'t exist in munowatch\'s database.</p>';
            echo '<p><strong>How to fix:</strong></p>';
            echo '<ol>';
            echo '<li>Find a valid series on munowatch.org</li>';
            echo '<li>Copy the correct video ID from its URL</li>';
            echo '<li>Update this series\' external_url with the correct video ID</li>';
            echo '</ol>';
            echo '</div>';
            return;
        }
        
        // Extract preview data following Flutter app pattern
        $movieDetail = null;
        if (isset($previewData['preview'])) {
            $movieDetail = $previewData['preview'];
            echo '<p>✅ Found preview data</p>';
        } else {
            echo '<p style="color: red;">❌ No preview data found in response</p>';
            echo '<p>Available keys: ' . implode(', ', array_keys($previewData)) . '</p>';
            return;
        }
        
        // Extract series code (critical for episodes API)
        $seriesCode = $movieDetail['series_code'] ?? '';
        $showTitle = $movieDetail['video_title'] ?? $series->title;
        
        echo '<p>📺 Show Title: ' . htmlspecialchars($showTitle) . '</p>';
        echo '<p>🔖 Series Code: ' . htmlspecialchars($seriesCode) . '</p>';
        
        if (empty($seriesCode)) {
            echo '<p style="color: red;">❌ No series code found - this might not be a series</p>';
            return;
        }
        
        // ===== PHASE 3: FETCH EPISODES USING FLUTTER APP PATTERN =====
        echo '<h3>📺 Phase 3: Fetching Episodes Data</h3>';
        
        // Use the videoId from URL as showId for episodes API
        $showId = $videoId;
        
        echo '<p>🎥 Show ID: ' . $showId . '</p>';
        echo '<p>🔖 Series Code: ' . htmlspecialchars($seriesCode) . '</p>';
        
        // Fetch episodes using the exact Flutter app pattern: episodes/range/{showId}/{seriesCode}/{seasonNumber}
        $episodesUrl = "https://munowatch.org/api/episodes/range/{$showId}/{$seriesCode}/1";
        echo '<p>📡 Episodes API URL: ' . $episodesUrl . '</p>';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $episodesUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $episodesResponse = curl_exec($ch);
        $episodesHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($episodesHttpCode !== 200 || !$episodesResponse) {
            echo '<p style="color: red;">❌ Episodes API request failed. HTTP Code: ' . $episodesHttpCode . '</p>';
            return;
        }
        
        $episodesData = json_decode($episodesResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo '<p style="color: red;">❌ Failed to parse episodes JSON response</p>';
            return;
        }
        
        // Check if API returned error (following Flutter app pattern)
        if (is_array($episodesData) && isset($episodesData['error']) && $episodesData['error'] === true) {
            echo '<p style="color: red;">❌ Episodes API returned error: ' . ($episodesData['msg'] ?? 'Unknown error') . '</p>';
            return;
        }
        
        // Episodes data should be an array of episode ranges
        $episodeRanges = [];
        if (is_array($episodesData)) {
            $episodeRanges = $episodesData;
        }
        
        if (empty($episodeRanges)) {
            echo '<p style="color: red;">❌ No episode ranges found - this might not be a series</p>';
            return;
        }
        
        echo '<p>✅ Found ' . count($episodeRanges) . ' episode ranges</p>';
        
        // Expand episode ranges into individual episodes (following Flutter app logic)
        $episodes = [];
        foreach ($episodeRanges as $rangeData) {
            echo '<div style="margin: 10px 0; padding: 5px; border: 1px solid #ddd;">';
            echo '<p>📼 Range: ' . htmlspecialchars($rangeData['eps'] ?? 'Unknown') . ' (Video IDs: ' . htmlspecialchars($rangeData['eps_range'] ?? 'No range') . ')</p>';
            
            // Parse episode range following Flutter app EpisodeRange.expand() logic
            $eps = trim($rangeData['eps'] ?? '');
            $epsRange = $rangeData['eps_range'] ?? '';
            
            if (!empty($eps) && !empty($epsRange)) {
                // Parse episode numbers (e.g., "1- 20" or " 21- 23")
                $epsParts = array_map('trim', explode('-', $eps));
                
                if (count($epsParts) === 2) {
                    $startEp = (int)$epsParts[0];
                    $endEp = (int)$epsParts[1];
                    
                    // Parse video IDs (e.g., "59705__59724")
                    $rangeParts = explode('__', $epsRange);
                    
                    if (count($rangeParts) === 2) {
                        $startId = (int)$rangeParts[0];
                        $endId = (int)$rangeParts[1];
                        
                        // Calculate counts
                        $episodeCount = $endEp - $startEp + 1;
                        
                        echo '<p>→ Episodes: ' . $startEp . ' to ' . $endEp . ' (' . $episodeCount . ' episodes)</p>';
                        echo '<p>→ Video IDs: ' . $startId . ' to ' . $endId . '</p>';
                        
                        // Generate individual episodes following Flutter app pattern
                        for ($i = 0; $i < $episodeCount; $i++) {
                            $episodeNumber = $startEp + $i;
                            $videoId = $startId + $i;
                            
                            $episodes[] = [
                                'number' => $episodeNumber,
                                'video_id' => $videoId,
                                'title' => 'Episode ' . $episodeNumber,
                                'description' => $rangeData['description'] ?? '',
                                'thumbnail' => $rangeData['thumbnail'] ?? '',
                                'duration' => $rangeData['duration'] ?? '',
                                'range_data' => $rangeData // Keep original range data for reference
                            ];
                        }
                        
                        echo '<p>→ Generated ' . $episodeCount . ' episodes</p>';
                    } else {
                        echo '<p style="color: orange;">⚠️ Invalid video ID range format: ' . htmlspecialchars($epsRange) . '</p>';
                    }
                } else {
                    echo '<p style="color: orange;">⚠️ Invalid episode number format: ' . htmlspecialchars($eps) . '</p>';
                }
            } else {
                echo '<p style="color: orange;">⚠️ Missing episode or video ID range data</p>';
            }
            
            echo '</div>';
        }
        
        // ===== PHASE 4: PROCESS AND SAVE EPISODES =====
        echo '<h3>💾 Phase 4: Processing Episodes</h3>';
        echo '<hr>';
        
        $processedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        
        foreach ($episodes as $index => $episodeData) {
            $episodeNumber = $episodeData['number'];
            $videoId = $episodeData['video_id']; // Use the video ID from range expansion
            
            try {
                echo '<div style="margin: 10px 0; padding: 10px; border: 1px solid #ddd;">';
                echo '<h4>Episode ' . $episodeNumber . ': ' . htmlspecialchars($episodeData['title']) . '</h4>';
                echo '<p>🎥 Video ID: ' . $videoId . '</p>';
                
                // For munowatch, we need to construct the episode URL using the video ID
                // Following Flutter app pattern, each episode is a separate video
                $episodeTitle = $episodeData['title'];
                $episodeDescription = $episodeData['description'] ?: $movieDetail['description'] ?? '';
                
                // Create episode URL - this would be the preview URL for this specific video
                $episodePreviewUrl = "https://munowatch.org/api/preview/v2/{$videoId}/{$userId}";
                
                echo '<p>🔗 Episode Preview URL: ' . htmlspecialchars($episodePreviewUrl) . '</p>';
                
                // For now, we'll use the episode preview URL as the primary URL
                // In a real implementation, you might want to fetch the actual video URL
                $primaryEpisodeUrl = $episodePreviewUrl;
                
                echo '<p>🎬 Primary URL: ' . htmlspecialchars($primaryEpisodeUrl) . '</p>';
                
                // Check for existing episode
                $existingEpisode = \App\Models\MovieModel::where('category_id', $series->id)
                                           ->where('episode_number', $episodeNumber)
                                           ->where('type', 'Series')
                                           ->first();
                
                // Generate episode external ID using video ID
                $episodeId = (string)$videoId;
                
                if (!$existingEpisode) {
                    $existingEpisode = \App\Models\MovieModel::where('external_id', $episodeId)->first();
                }
                
                $isNew = ($existingEpisode === null);
                
                if ($isNew) {
                    $episode = new \App\Models\MovieModel();
                    echo '<p style="color: green;">✅ Creating new episode</p>';
                } else {
                    $episode = $existingEpisode;
                    echo '<p style="color: blue;">🔄 Updating existing episode (ID: ' . $episode->id . ')</p>';
                }
                
                // Set episode data following the existing pattern
                $episode->title = $showTitle . ' - ' . $episodeTitle;
                $episode->description = $episodeDescription;
                $episode->external_url = $episodePreviewUrl;
                $episode->external_id = $episodeId;
                $episode->page_source_url = $series->external_url;
                
                // Critical relationship linking
                $episode->category_id = $series->id;
                $episode->category = $showTitle;
                $episode->type = 'Series';
                $episode->episode_number = $episodeNumber;
                $episode->season_number = 1; // Default to season 1
                
                // Video and media information
                $episode->url = $primaryEpisodeUrl;
                $episode->thumbnail_url = $episodeData['thumbnail'] ?: $movieDetail['image_url'] ?? $series->thumbnail;
                $episode->image_url = $episodeData['thumbnail'] ?: $movieDetail['image_url'] ?? $series->thumbnail;
                $episode->poster_url = $episodeData['thumbnail'] ?: $movieDetail['image_url'] ?? $series->thumbnail;
                $episode->duration = $episodeData['duration'] ?: $movieDetail['duration'] ?? '';
                
                // Inherit series metadata
                $episode->genre = $series->Category;
                $episode->year = $series->year;
                $episode->language = $series->language ?? 'English';
                $episode->country = $series->country ?? 'Uganda';
                $episode->rating = $series->rating ?? '';
                $episode->vj = $series->vj ?? '';
                
                // Technical metadata
                $episode->content_type = 'video/mp4';
                $episode->content_is_video = 'Yes';
                $episode->content_type_processed = 'No';
                
                // Status and access
                $episode->status = 'Active';
                $episode->temp_status = 'Active';
                $episode->is_premium = 'No';
                
                // Save episode (MovieModel boot() will automatically set is_first_episode)
                $episode->save();
                
                echo '<p>✅ Episode saved successfully (ID: ' . $episode->id . ')</p>';
                echo '<p>🏷️ First Episode: ' . ($episode->is_first_episode === 'Yes' ? 'YES' : 'No') . '</p>';
                
                $processedCount++;
                echo '</div>';
                
            } catch (\Exception $e) {
                echo '<p style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
                $errorCount++;
                continue;
            }
        }
        
        // ===== PHASE 5: UPDATE SERIES WITH FINAL STATS =====
        echo '<hr>';
        echo '<h3>📊 Final Summary</h3>';
        
        // Count actual episodes created
        $finalEpisodeCount = \App\Models\MovieModel::where('category_id', $series->id)
                                     ->where('type', 'Series')
                                     ->count();
        
        // Update series with episode count
        $series->total_episodes = $finalEpisodeCount;
        $series->is_active = 'Yes';
        $series->description .= " - Episodes processed on " . date('Y-m-d H:i:s');
        $series->save();
        
        echo '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px;">';
        echo '<h4>📈 Processing Results:</h4>';
        echo '<ul>';
        echo '<li><strong>Series:</strong> ' . htmlspecialchars($showTitle) . '</li>';
        echo '<li><strong>Episode Ranges Found:</strong> ' . count($episodeRanges) . '</li>';
        echo '<li><strong>Episodes Processed:</strong> ' . $processedCount . '</li>';
        echo '<li><strong>Episodes Skipped:</strong> ' . $skippedCount . '</li>';
        echo '<li><strong>Episodes Errors:</strong> ' . $errorCount . '</li>';
        echo '<li><strong>Final Episode Count:</strong> ' . $finalEpisodeCount . '</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<hr>';
        echo '<h3>🎬 Episodes Created:</h3>';
        
        $createdEpisodes = \App\Models\MovieModel::where('category_id', $series->id)
                                   ->where('type', 'Series')
                                   ->orderBy('episode_number', 'asc')
                                   ->get();
        
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
        echo '<tr><th>Episode #</th><th>Title</th><th>First Episode</th><th>Status</th><th>Video URL</th></tr>';
        
        foreach ($createdEpisodes as $ep) {
            echo '<tr>';
            echo '<td>' . $ep->episode_number . '</td>';
            echo '<td>' . htmlspecialchars($ep->title) . '</td>';
            echo '<td>' . ($ep->is_first_episode === 'Yes' ? '✅ YES' : '❌ No') . '</td>';
            echo '<td>' . $ep->status . '</td>';
            echo '<td><a href="' . htmlspecialchars($ep->url) . '" target="_blank">Watch</a></td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        echo '<hr>';
        echo '<div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        echo '<h3>✅ Munowatch Series Fix Completed Successfully!</h3>';
        echo '<p>All episodes have been processed and saved with proper relationships and episode numbering.</p>';
        echo '<p><strong>Note:</strong> The first episode has been automatically flagged with is_first_episode = "Yes"</p>';
        echo '</div>';
        
    } catch (\Exception $e) {
        echo '<div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        echo '<h3>❌ Error Processing Series</h3>';
        echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>Please check the series data and try again.</p>';
        echo '</div>';
    }
});

Route::get('process-movies', function (Request $request) {
    //https://movies.ug/videos/Leighton%20Meester-The%20Weekend%20Away%20(2022).mp4

    //set unlimited time
    ini_set('memory_limit', '512M');
    ini_set('max_execution_time', '600');
    ini_set('max_input_time', '600');
    ini_set('upload_max_filesize', '100M');
    ini_set('post_max_size', '100M');
    ini_set('max_input_vars', '10000');
    //get movies that does not have http in url

    /*     MovieModel::where('type','Movie')
        ->update(['content_type_processed'=>'No']); */

    $movies = MovieModel::where('url', 'like', '%movies.ug%')
        ->orderBy('id', 'asc')
        ->limit(10000)
        ->get();
    $x = 0;
    echo "<h1>Movies (" . $movies->count() . ")</h1>";

    foreach ($movies as $key => $movie) {
        $url = $movie->url;
        $segs = explode('/', $url);
        if (in_array('movies.ug', $segs)) {
            $movie->status = 'Inactive';
            $movie->content_type_processed = 'Yes';
            echo "<br>Movie not found : => " . $movie->id . " - " . $movie->title;
            $movie->save();
            continue;
        }
        continue;
        if (!in_array('https:', $segs)) {
            $movie->status = 'Inactive';
            $movie->content_type_processed = 'Yes';
            $movie->save();
            echo "<br>Movie not found : => " . $movie->id . " - " . $movie->title;
            continue;
        }
        echo "<hr> $x. ";

        $movie->verify_movie();
        if ($movie  == null) {
            continue;
        }
        $movie = MovieModel::find($movie->id);


        if ($movie  == null) {
            continue;
        }
        //echo irl
        echo $movie->id . ' - ' . $movie->title . " : <a target='_blank' href='" . $movie->url . "'>" . $movie->url . "</a><br>";
        //if has not http
        //check if  is content_is_video and display colour button
        if ($movie->content_is_video == 'Yes') {
            echo "<br><span style='color:green'>IS_VIDEO</span><br>";
            $x++;
        } else {
            echo "<span style='color:red'>NOT_VIDEO</span><br>";
            //delete movie
            // $movie->delete();
            $movie->satus = 'Inactive';
            $movie->save();
            echo "<br>deleted movie";
        }

        echo "<hr>";
        continue;
        //        $this->content_type_processed_time = Carbon::now();
        $last_time = $movie->content_type_processed_time;
        $last_time = Carbon::parse($last_time);
        $now = Carbon::now();
        $diff = $last_time->diffInMinutes($now);
        //if less than 5 minutes, continue
        if ($diff < 100) {
            echo $movie->id . ' - ' . $movie->title . " : " . $movie->url . ' |||SKIP|||<br>';
            continue;
        }
        //chek
        if ($movie->content_is_video == 'Yes' && str_contains($url, 'http')) {
            echo $movie->id . ' - ' . $movie->title . " : " . $movie->url . ' |||IS_ALREADY_VIDEO|||<br>';
            continue;
        }
        echo $movie->id . ' - ' . $movie->title . " : " . $movie->url . '>>>>>CHECKING<<======<br>';

        $m = $movie->verify_movie();
        if ($m  == null) {
            echo $movie->id . ' - ' . $movie->title . " : " . $movie->url . '>>>>>NOT_VIDEO DELETED<<======<br>';
            continue;
        }
        //ECHO URL
        $url = $m->url;
        //if has not http
        if (!str_contains($url, 'http')) {
            $url = 'https://movies.ug/' . $url;
        }

        //check content_is_video and display colour button
        if ($m->content_is_video == 'Yes') {
            echo "<span style='color:green'>IS_VIDEO</span>";
        } else {
            echo "<span style='color:red'>NOT_VIDEO</span>";
        }

        echo "<a target='_blank' href='" . $url . "'>" . $url . "</a><br>";
    }
    dd('process-movies');
});
Route::get('process-series', function (Request $request) {
    $series = SeriesMovie::where([])
        ->orderBy('id', 'asc')
        ->limit(500)
        ->get();

    //set unlimited time
    ini_set('memory_limit', -1);

    ini_set('max_execution_time', -1);
    ini_set('max_input_time', -1);
    ini_set('upload_max_filesize', -1);
    ini_set('post_max_size', -1);
    ini_set('max_input_vars', -1);


    foreach ($series as $key => $ser) {
        $other_with_external_url = SeriesMovie::where([
            'external_url' => $ser->external_url,
        ])
            ->where('id', '!=', $ser->id)
            ->get();

        if ($other_with_external_url->count()  > 0) {
            foreach ($other_with_external_url as $key => $other) {
                $eps = MovieModel::where([
                    'category_id' => $other->id,
                ])
                    ->update([
                        'category_id' => $ser->id,
                    ]);
                $other->delete();
            }
        }
        $other_with_external_bu_title = SeriesMovie::where([
            'title' => $ser->title,
        ])
            ->where('id', '!=', $ser->id)
            ->get();
        if ($other_with_external_bu_title->count()  > 0) {
            foreach ($other_with_external_bu_title as $key => $other) {
                $eps = MovieModel::where([
                    'category_id' => $other->id,
                ])
                    ->update([
                        'category_id' => $ser->id,
                    ]);
                $other->delete();
            }
        }


        foreach (
            MovieModel::where([
                'category_id' => $ser->id,
            ])
                ->get() as $key => $episode
        ) {
            $episode_number = (int) $episode->episode_number;
            if ($episode_number == 0) {
                $country = (int) $episode->country;
                if ($country > 0) {
                    $episode->episode_number = $country;
                    $episode->save();
                }
            }
        }

        $episodes = MovieModel::where([
            'category_id' => $ser->id,
        ])
            ->orderBy('episode_number', 'asc')
            ->get();
        $first_episode_found = false;
        $ser->is_active = 'No';
        $ser->save();
        foreach ($episodes as $key => $episode) {
            if ($episode->episode_number != 1) {
                continue;
            }
            $episode->is_first_episode = 'Yes';
            $episode->save();
            echo $episode->id . '. - first episode found for ==>  ' . $episode->title . '<br>';
            $ser->is_active = 'Yes';
            $ser->save();
            $first_episode_found = true;
            break;
        }
        if ($first_episode_found == false) {
            echo  $ser->id . '. |||||No first episode||||| found for ==>  ' . $ser->title . '<br>';
        }
    }
    /* 
 
   "id" => 1
    "created_at" => "2024-03-12 14:06:31"
    "updated_at" => "2024-03-12 15:36:38"
    "title" => "Feng Ku The Master of Kung Fu"
    "Category" => "Action"
    "description" => "<p>Huang Fei-Hung, famous Chinese boxer, teaches his martial arts at Pao Chih Lin Institute, in Canton. Gordon is a European businessman, dealing in import and  ▶"
    "thumbnail" => "images/MV5BYzZhZjE5NDgtNDk2OS00ZGNkLWFjYjktNmY1ZmZhY2VjZjBlXkEyXkFqcGdeQXVyOTMzMDk1NTY@._V1_ (1).jpg"
    "total_seasons" => 3
    "total_episodes" => 10
    "total_views" => 249
    "total_rating" => 4
    "is_active" => "No"
    "external_url" => null
    "is_premium" => "No"*/

    dd($series);
});
Route::get('remove-dupes', function (Request $request) {

    $max = 100000;
    $recs =  MovieModel::where([
        'plays_on_google' => 'dupes',
    ])
        ->orderBy('id', 'desc')
        ->limit($max)
        ->get();


    //set unlimited time
    ini_set('memory_limit', -1);

    ini_set('max_execution_time', -1);
    ini_set('max_input_time', -1);
    ini_set('upload_max_filesize', -1);
    ini_set('post_max_size', -1);
    ini_set('max_input_vars', -1);

    $i = 0;

    foreach ($recs as $key => $rec) {
        if ($i > $max) {
            break;
        }
        $i++;
        if ($i > $max) {
            break;
        }
        $otherMovies = MovieModel::where([
            'url' => $rec->url
        ])
            ->where('id', '!=', $rec->id)
            ->get();
        if ($otherMovies->count() == 0) {
            die("<hr>");
            echo $i . '. NOT DUPE for : ' . $rec->title . '<br>';
            $rec->plays_on_google = 'Yes';
            die("<hr>");
            $rec->save();
            continue;
        }

        $otherMovies = MovieModel::where([
            'url' => $rec->url
        ])
            ->get();
        echo "<hr>";
        foreach ($otherMovies as $key => $dp) {
            if ($rec->id == $dp->id) {
                continue;
            }
            echo $dp->delete();
            echo $dp->id . '. ' . $dp->title . ' ===> ' . $dp->url . '<br>';
            //display thumbnaildd 
            echo '<img src="' . $dp->thumbnail_url . '" width="100" height="100" alt="">';
            echo '<br>';
        }
        continue;

        die("<br>");

        echo $i . 'dupes for ' . $rec->title . '<br>';
    }

    die('remove-dupes');

    dd('remove-dupes');
});
Route::get('manifest', function (Request $request) {
    $apiController = new ApiController();
    $apiController->manifest($request);
});
Route::get('play', function (Request $request) {
    $moviemodel = MovieModel::find($request->id);
    if ($moviemodel == null) {
        return die('Movie not found');
    }
    $newUrl = url('storage/' . $moviemodel->new_server_path);
    //html player for new and old links
    $html = '<video width="320" height="240" controls>
                <source src="' . $moviemodel->url . '" type="video/mp4">
                Your browser does not support the video tag. 
            </video>';
    $html .= '<br><video width="320" height="240" controls>
                <source src="' . $newUrl . '" type="video/mp4">
                Your browser does not support the video tag.
            </video>';
    echo $html;
});
Route::get('download-to-new-server-get-images', function () {
    Utils::get_remote_movies_links_4_get_images();
    die("get_remote_movies_links_4_get_images");
});
Route::get('download-to-new-server-namzentertainment', function () {
    Utils::get_remote_movies_links_namzentertainment();
    die('download-to-new-namzentertainment');
});

Route::get('download-to-new-server', function () {
    //8019

    // return  view('test');

    Utils::get_remote_movies_links_4();
    die('download-to-new-server');
    // Utils::get_remote_movies_links_3();

    dd('download-to-new-server');
    //increase the memory limit
    ini_set('memory_limit', -1);
    //increase the execution time
    ini_set('max_execution_time', -1);
    //increase the time limit
    set_time_limit(0);
    //increase the time limit
    ignore_user_abort(true);
    //die("time to download");


    $movies = MovieModel::where([
        'uploaded_to_from_google' => 'Yes',
        'downloaded_to_new_server' => 'No',
    ])
        ->orderBy('id', 'asc')
        ->limit(100)
        ->get();
    if (isset($_GET['reset'])) {
        MovieModel::where([
            'uploaded_to_from_google' => 'Yes',
        ])->update([
            'downloaded_to_new_server' => 'No',
        ]);
    }
    /* 
            $table->string('downloaded_to_new_server')->default('No');
            $table->text('new_server_path')->nullable();
            server_fail_reason
*/

    $i = 0;
    foreach ($movies as $key => $value) {
        $url = $value->url;

        $filename = time() . '-' . rand(1000000, 10000000) . '-' . rand(1000000, 10000000) . '.mp4';
        $path = public_path('storage/files/' . $filename);
        if (file_exists($path)) {
            $value->downloaded_to_new_server = 'Yes';
            $value->save();
            continue;
        }

        try {
            if ($i > 10) {
                break;
            }
            $i++;
            if (Utils::is_localhost_server()) {
                echo 'localhost server';
                die();
            }

            $value->downloaded_to_new_server = 'Yes';
            $value->new_server_path = 'files/' . $filename;
            $value->save();
            $new_link = url('storage/' . $value->new_server_path);
            echo 'downloaded to ' . $new_link . '<hr>';
            //check if directtoryy exists

            try {
                $file = file_get_contents($url);
                file_put_contents($path, $file);
                echo '<h1>Downloaded: ' . $url . '</h1>';
            } catch (\Throwable $th) {
                echo 'failed to download ' . $url . '<br>';
                echo $th->getMessage();
                die();
            }

            $d_exists = '';
            if (!file_exists(public_path('storage/files'))) {
                $d_exists = 'does not exist';
                mkdir(public_path('storage/files'));
            } else {
                $d_exists = 'exists';
            }
            echo 'directory ' . $d_exists . '<br>';

            //html player for new and old links
            $html = '<video width="100" height="120" controls>
                <source src="' . $value->url . '" type="video/mp4">
                Your browser does not support the video tag. 
            </video>';
            $html .= '<br><video width="100" height="120" controls>
                <source src="' . $new_link . '" type="video/mp4">
                Your browser does not support the video tag. 
            </video>';
            echo $html;
        } catch (\Throwable $th) {
            $value->downloaded_to_new_server = 'Failed';
            $value->server_fail_reason = $th->getMessage();
            $value->save();
            echo 'failed to download ' . $url . '<br>';
            echo $th->getMessage();
        }
    }
});

Route::get('sync-with-google', function () {
    Utils::download_movies_from_google();
});
Route::get('/gen-form', function () {
    die(Gen::find($_GET['id'])->make_forms());
})->name("gen-form");


Route::get('generate-class', [MainController::class, 'generate_class']);
Route::get('/gen', function () {
    die(Gen::find($_GET['id'])->do_get());
})->name("register");

Route::post('/africa', function () {
    $m = new \App\Models\AfricaTalkingResponse();
    $m->sessionId = request()->get('sessionId');
    $m->status = request()->get('status');
    $m->phoneNumber = request()->get('phoneNumber');
    $m->errorMessage = request()->get('errorMessage');
    $m->post = json_encode($_POST);
    $m->get = json_encode($_GET);
    try {
        $m->save();
    } catch (\Throwable $th) {
        //throw $th;
    }

    //change response to xml
    header('Content-type: text/plain');

    echo '<Response>
            <Play url="https://www2.cs.uic.edu/~i101/SoundFiles/gettysburg10.wav"/>
    </Response>';
    die();
});
Route::get('/make-tsv', function () {
    $exists = [];
    foreach (
        MovieModel::where([
            'uploaded_to_from_google' => 'No',
        ])->get() as $key => $value
    ) {

        //check if not contain ranslatedfilms.com and continue
        if (!(strpos($value->external_url, 'ranslatedfilms.com') !== false)) {
            continue;
        }
        $exists[] = $value->external_url;
        continue;
        //check if file exists
        // $value->url = 'videos/test.mp4';
        if ($value->url == null) continue;
        if (strlen($value->url) < 5) continue;
        $path = public_path('storage/' . $value->url);
        if (!file_exists($path)) {
            echo $value->title . ' - does not exist<br>';
            continue;
        }
        //echo $value->title . ' - do exists<br>';
        $exists[] = url('storage/' . $value->url);
    }

    //create a tsv file
    $path = public_path('storage/movies-1.tsv');
    $file = fopen($path, 'w');
    //add TsvHttpData-1.0 on top of the tsv file content
    fputcsv($file, [
        'TsvHttpData-1.0'
    ], "\t");

    //put only data in $exists
    foreach ($exists as $key => $value) {
        fputcsv($file, [
            $value
        ], "\t");
    }
    fclose($file);
    //download the file link echo
    echo '<a href="' . url('storage/movies-1.tsv') . '">Download</a>';
    die();
});
Route::get('/down', function () {
    Utils::system_boot();
});

// Test Firebase Storage connection
Route::get('/test-firebase-connection', function () {
    try {
        // Test Firebase connection
        $storage = app('firebase.storage');
        $bucket = $storage->getBucket();

        return response()->json([
            'success' => true,
            'message' => 'Firebase Storage connection successful!',
            'bucket_name' => $bucket->name(),
            'project_id' => config('firebase.project_id')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Test video upload to Firebase
Route::get('/test-firebase-upload', function () {
    try {
        // Test with a small sample video
        $result = \App\Models\Utils::uploadVideoToFirebase(
            'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4',
            'test_video_' . time(),
            'test_uploads'
        );

        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});
