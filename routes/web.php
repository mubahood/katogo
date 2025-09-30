<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MainController;
use App\Models\Gen;
use App\Models\MovieCrawlerPage;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::get('crawler', function () {



    //set unlimited time
    set_time_limit(-1);
    //set unlimited memory
    ini_set('memory_limit', -1);
    try {
        Utils::fetch_pages();
    } catch (\Throwable $th) {
        echo "Failed to fetch pages because " . $th->getMessage();
    }

    try {

        Utils::fetch_pages_content();
    } catch (\Throwable $th) {
        echo "Failed to fetch page contents because " . $th->getMessage();
        //throw $th;
    }

    die("scucess");
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
        $movies = MovieModel::where([
            'firebase_transfer_attempted' => 'No',
            'imdb_url' => 'MyVj',
        ])->orderBy('id', 'asc')
            ->limit(10)
            ->get();

        //if empty, use original query
        if ($movies->count() == 0) {
            $movies = $query->get();
        }

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
    ini_set('memory_limit', -1);
    ini_set('max_execution_time', -1);
    ini_set('max_input_time', -1);
    ini_set('upload_max_filesize', -1);
    ini_set('post_max_size', -1);
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

Route::get('process-movies', function (Request $request) {
    //https://movies.ug/videos/Leighton%20Meester-The%20Weekend%20Away%20(2022).mp4

    //set unlimited time
    ini_set('memory_limit', -1);
    ini_set('max_execution_time', -1);
    ini_set('max_input_time', -1);
    ini_set('upload_max_filesize', -1);
    ini_set('post_max_size', -1);
    ini_set('max_input_vars', -1);
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
