<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\TransferProcessController;
use App\Models\Gen;
use App\Models\MovieCrawlerPage;
use App\Models\MovieCrawlerWebsite;
use App\Models\MovieModel;
use App\Models\MoviePic;
use App\Models\MovieView;
use App\Models\SeriesMovie;
use App\Models\SubscriptionTransaction;
use App\Models\TrendingNotification;
use App\Models\User;
use App\Models\Utils;
use App\Services\SubscriptionPesapalService;
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
| Protected Processing Routes
|--------------------------------------------------------------------------
| These routes require ?key=PROCESSING_ROUTE_KEY in the URL.
| Example: /process-episodes-new?key=your_secret_key
*/
Route::middleware('processing.auth')->group(function () {

Route::get('process-episodes-new', function (Request $request) {
    $episodePages = MovieCrawlerPage::where('is_episode', 'Yes')
        ->where('is_muno', 'Yes')
        ->where('episodes_data_fetched', 'No')
        ->limit(10)
        ->get();
    $episodePages = MovieCrawlerPage::where('id', 2968)->get();
    foreach ($episodePages as $episodePage) {
        // Process each episode page
        try {
            echo "<hr>";
            $episodePage->process_munowatch_intelligent();
            $episodePage->refresh();
            echo "Processed episode page ID " . $episodePage->id . "<br>";
            echo "Episode title: " . $episodePage->title . "<br>";
            $seriesMovie = SeriesMovie::find($episodePage->series_id);
            if ($seriesMovie == null) {
                echo "Series movie not found for episode page ID " . $episodePage->id . "<br>";
                //log
                Log::error("Series movie not found for episode page ID " . $episodePage->id);
                $episodePage->episodes_data_fetched = 'Error';
                $episodePage->error_message = 'Series movie not found';
                $episodePage->save();
                continue;
            }
            echo "Series title: " . $seriesMovie->title . "<br>";
            //display thumbs of series
            echo '<img src="' . htmlspecialchars($seriesMovie->thumbnail) . '" alt="Series Thumbnail" style="max-width:200px;max-height:300px;"><br>';
            $episodePage->episodes_data_fetched = 'Success';
            $episodePage->save();
            continue;
        } catch (\Exception $e) {
            $episodePage->episodes_data_fetched = 'Error';
            $episodePage->error_message = $e->getMessage();
            $episodePage->save();
            echo "Error processing episode page ID " . $episodePage->id . ": " . $e->getMessage() . "<br>";
            //log
            Log::error("Error processing episode page ID " . $episodePage->id . ": " . $e->getMessage());
            continue;
        }
    }
});
Route::get('process-series-new', function (Request $request) {

    //set unlimited time
    set_time_limit(300);
    ini_set("memory_limit", "256M");

    $seriesPages = MovieCrawlerPage::where('is_episode', 'No')
        ->where('is_muno', 'Yes')
        ->where('type', 'Series')
        ->where('muno_series_processed', 'No')
        ->orderBy('id', 'asc')
        ->limit(10)
        ->get();

    $page_number = null;
    //check of is in. request
    if ($request->has('page')) {
        $page_number = $request->get('page');
        $page_number = (int) $page_number;
        $seriesPages = MovieCrawlerPage::where('id', $page_number)->get();
    }

    $reset = $request->get('reset', 'no');
    if ($reset === 'yes') {
        $total = MovieCrawlerPage::where('is_episode', 'No')
            ->where('is_muno', 'Yes')
            ->where('type', 'Series')
            ->update([
                'muno_series_processed' => 'No',
            ]);
        return "Reset completed for " . $total . " series pages.";
    }
    foreach ($seriesPages as $seriesPage) {

        echo "<hr>";
        try {
            MovieCrawlerPage::generate_series_episodes($seriesPage);
            $seriesPage->refresh();
            $seriesMovie = SeriesMovie::find($seriesPage->series_id);
            if ($seriesMovie == null) {
                echo "Series movie not found for series page ID " . $seriesPage->id . "<br>";
                //log
                Log::error("Series movie not found for series page ID " . $seriesPage->id);
                $seriesPage->muno_series_processed = 'Error';
                $seriesPage->muno_message = 'Series movie not found';
                $seriesPage->save();
                continue;
            }
            echo "Processed series page ID " . $seriesPage->id . "<br>";
            echo "Series title: " . $seriesMovie->title . "<br>";
            $pages = MovieCrawlerPage::where('series_id', $seriesPage->series_id)
                ->get();
            //display thumbs of series
            echo '<img src="' . htmlspecialchars($seriesMovie->thumbnail) . '" alt="Series Thumbnail" style="max-width:200px;max-height:300px;"><br>';
            continue;
        } catch (\Exception $e) {
            $seriesPage->muno_series_processed = 'Error';
            $seriesPage->muno_message = $e->getMessage();
            $seriesPage->error_message = $e->getMessage();
            $seriesPage->save();
            echo "Error processing series page ID " . $seriesPage->id . ": " . $e->getMessage() . "<br>";
            //log
            Log::error("Error processing series page ID " . $seriesPage->id . ": " . $e->getMessage());
            continue;
        }
    }
    die();
});
/**
 * 🎬 OPTIMIZED MUNOWATCH PAGES PROCESSOR
 * 
 * This endpoint processes pending Munowatch crawler pages and creates movie records.
 * Efficiently handles batch processing with intelligent duplicate detection.
 * 
 * Features:
 * - SQL-based query optimization for better performance
 * - Smart duplicate detection (checks if movie already exists and is active)
 * - Batch processing with configurable limits
 * - Professional UI with progress tracking
 * - Comprehensive error handling and logging
 * - Transaction-safe processing per page
 * - Real-time statistics and reporting
 * 
 * Query Parameters:
 * - limit: Number of pages to process per run (default: 10, max: 50)
 * - skip_active: Skip pages where movie already exists and is active (default: yes)
 * 
 * Usage: /process-muno-movies-pages?limit=20&skip_active=yes
 */
Route::get('process-muno-movies-pages', function (Request $request) {
    //set tmer
    set_time_limit(300); // 8+ hours for extensive processing
    ini_set("memory_limit", "256M");

    // $url = 'https://munowatch.org/api/preview/v2/4920/3664';
    // $page = MovieCrawlerPage::where('url', $url)->first();
    //next pending page for munowatch
    $page = MovieCrawlerPage::where('movie_crawler_website_id', 2)
        ->where('status', 'Pending')
        ->where('is_muno', 'Yes')
        ->orderBy('id', 'asc')
        ->first();

    if ($page->page_content == null || strlen(trim($page->page_content)) < 10) {
        echo "Fetching page content...<br>";
        $page->fetch_page_content(false);
    } else {
        echo "Page content already exists.<br>";
    }


    if ($page->page_content != null && strlen(trim($page->page_content)) > 10) {
        echo "Processing page content...<br>";
        $page->process_munowatch_intelligent();
    } else {
        echo "Failed to fetch page content.<br>";
        dd($page);
    }
    $page->refresh();


    //if type is movie,
    if ($page->type === 'Movie') {
        $movie = MovieModel::find($page->movie_id);
        if (!$movie) {
            echo "Movie not found.<br>";
            dd($page);
        }
        //ECHO THE MOVIE DETAILS
        echo "Movie Details:<br>";
        echo "ID: " . $movie->id . "<br>";
        echo "Title: " . $movie->title . "<br>";
        //img
        echo "<img src='" . $movie->thumbnail_url . "' alt='Movie Thumbnail' /><br>";
    } else if ($page->type === 'Series') {
        $series = SeriesMovie::find($page->series_id);
        if (!$series) {
            echo "Series not found.<br>";
            dd($page);
        }
        //ECHO THE SERIES DETAILS
        echo "Series Details:<br>";
        echo "ID: " . $series->id . "<br>";
        echo "Title: " . $series->title . "<br>";
        //img
        echo "<img src='" . $series->thumbnail . "' alt='Series Thumbnail' /><br>";
    } else {
        echo "Unknown type: " . $page->type . "<br>";
    }
    // dd($page);

    // die(); 

    // $baseToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
    // $headers = [
    //     'Authorization' => 'Bearer ' . $baseToken,
    //     'X-Api-Key' => $baseToken,
    //     'User-Agent' => 'okhttp/4.9.0'
    // ];
    // $data = Utils::get_url_with_auth($url, $headers);
    // dd($data);


    set_time_limit(300); // 8+ hours for extensive processing
    ini_set("memory_limit", "256M");

    $startTime = microtime(true);
    $limit = min((int) $request->get('limit', 20), 50); // Max 50 for safety
    $skipActive = $request->get('skip_active', 'yes') === 'yes';

    // Initialize statistics
    $stats = [
        'total_processed' => 0,
        'movies_created' => 0,
        'already_active' => 0,
        'processing_failed' => 0,
        'page_not_success' => 0,
        'errors' => 0,
    ];

    // HTML output with styling
    echo '<html><head><title>Munowatch Pages Processor</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; margin: 5px 0; border-left: 4px solid #28a745; background: #d4edda; border-radius: 4px; }
        .warning { color: #ffc107; padding: 10px; margin: 5px 0; border-left: 4px solid #ffc107; background: #fff3cd; border-radius: 4px; }
        .error { color: #dc3545; padding: 10px; margin: 5px 0; border-left: 4px solid #dc3545; background: #f8d7da; border-radius: 4px; }
        .info { color: #17a2b8; padding: 10px; margin: 5px 0; border-left: 4px solid #17a2b8; background: #d1ecf1; border-radius: 4px; }
        .page-card { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .page-id { font-weight: bold; color: #007bff; font-size: 16px; }
        .movie-thumb { max-width: 80px; max-height: 120px; border-radius: 4px; margin: 10px 0; }
        .progress { background: #e9ecef; height: 30px; border-radius: 5px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #28a745; height: 100%; line-height: 30px; color: white; text-align: center; transition: width 0.3s; }
    </style></head><body>';

    echo '<div class="header">';
    echo '<h1>🎬 Munowatch Pages Processor</h1>';
    echo '<p>Intelligent Movie Creation Engine</p>';
    echo '</div>';

    try {
        // Get pending pages count using SQL
        $totalPending = DB::table('movie_crawler_pages')
            ->where('movie_crawler_website_id', 2)
            ->where('status', 'Pending')
            ->where('is_muno', 'Yes')
            ->count();

        echo '<div class="stats">';
        echo '<p><strong>📊 Processing Configuration:</strong></p>';
        echo '<p>Total Pending Pages: <strong>' . number_format($totalPending) . '</strong></p>';
        echo '<p>Batch Size: <strong>' . $limit . '</strong></p>';
        echo '<p>Skip Active Movies: <strong>' . ($skipActive ? 'Yes' : 'No') . '</strong></p>';
        echo '</div>';

        if ($totalPending === 0) {
            echo '<div class="success">';
            echo '<h3>✅ No Pending Pages</h3>';
            echo '<p>All Munowatch pages have been processed.</p>';
            echo '</div>';
            echo '</body></html>';
            return;
        }

        // Get pending pages using SQL
        $pendingPages = DB::table('movie_crawler_pages')
            ->select('id', 'movie_id', 'url', 'title', 'slug')
            ->where('movie_crawler_website_id', 2)
            ->where('status', 'Pending')
            ->where('is_muno', 'Yes')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($pendingPages->isEmpty()) {
            echo '<div class="info">No pages to process in this batch.</div>';
            echo '</body></html>';
            return;
        }

        echo '<h2>🚀 Processing ' . count($pendingPages) . ' Pages</h2>';
        echo '<hr>';

        foreach ($pendingPages as $pageData) {
            $stats['total_processed']++;

            try {
                // Load the full model for processing
                $pendMuno = MovieCrawlerPage::find($pageData->id);

                if (!$pendMuno) {
                    echo '<div class="error">❌ Page ID ' . $pageData->id . ' not found</div>';
                    //but mark the page as failed
                    $stats['errors']++;
                    $pendMuno->status = 'failed';
                    $pendMuno->save();
                    continue;
                }

                // Check if movie already exists and is active
                if ($skipActive && $pendMuno->movie_id != null && is_numeric($pendMuno->movie_id)) {
                    // Use SQL for faster lookup
                    $existingMovie = DB::table('movie_models')
                        ->select('id', 'status', 'title', 'thumbnail_url')
                        ->where('id', $pendMuno->movie_id)
                        ->first();

                    if (!$existingMovie) {
                        $existingMovie = DB::table('movie_models')
                            ->select('id', 'status', 'title', 'thumbnail_url')
                            ->where('external_id', $pendMuno->movie_id)
                            ->first();
                    }

                    if ($existingMovie && $existingMovie->status === 'Active') {
                        $stats['already_active']++;

                        echo '<div class="page-card warning">';
                        echo '<span class="page-id">[SKIP - ACTIVE] Page ID: ' . $pendMuno->id . '</span><br>';
                        echo '<strong>Movie ID:</strong> ' . $existingMovie->id . '<br>';
                        echo '<strong>Title:</strong> ' . htmlspecialchars($existingMovie->title ?? 'N/A') . '<br>';
                        if (!empty($existingMovie->thumbnail_url)) {
                            echo '<img src="' . htmlspecialchars($existingMovie->thumbnail_url) . '" class="movie-thumb" onerror="this.style.display=\'none\'" /><br>';
                        }
                        echo '<em>Movie already exists and is active</em>';
                        echo '</div>';
                        //but mark the page as successful
                        $pendMuno->status = 'success';
                        $pendMuno->save();
                        continue;
                    }
                }

                // Process the page
                echo '<div class="page-card info">';
                echo '<span class="page-id">[PROCESSING] Page ID: ' . $pendMuno->id . '</span><br>';
                echo '<strong>URL:</strong> ' . htmlspecialchars(substr($pendMuno->url ?? '', 0, 80)) . '...<br>';
                flush();

                DB::beginTransaction();
                try {
                    $pendMuno->process_munowatch_intelligent();
                    DB::commit();

                    // Refresh page status
                    $pendMuno->refresh();

                    echo "<pre>";
                    print_r($pendMuno->toArray());
                    echo "</pre>";

                    if ($pendMuno->status !== 'success') {
                        $stats['page_not_success']++;
                        echo '<em style="color: #ffc107;">⚠️ Processing completed but status is: ' . $pendMuno->status . '</em><br>';
                        echo '</div>';
                        $pendMuno->status = 'failed';
                        $pendMuno->save();
                        continue;
                    }

                    // Check if movie was created
                    $createdMovie = DB::table('movie_models')
                        ->select('id', 'title', 'thumbnail_url', 'status', 'type')
                        ->where('external_url', $pendMuno->url)
                        ->first();

                    if ($createdMovie) {
                        $stats['movies_created']++;

                        echo '<em style="color: #28a745;">✅ Processing successful!</em><br>';
                        echo '</div>';

                        echo '<div class="page-card success">';
                        echo '<span class="page-id">[✅ MOVIE CREATED] Movie ID: ' . $createdMovie->id . '</span><br>';
                        echo '<strong>Title:</strong> ' . htmlspecialchars($createdMovie->title ?? 'Untitled') . '<br>';
                        echo '<strong>Type:</strong> ' . ($createdMovie->type ?? 'Movie') . '<br>';
                        echo '<strong>Status:</strong> ' . ($createdMovie->status ?? 'Unknown') . '<br>';
                        if (!empty($createdMovie->thumbnail_url)) {
                            echo '<img src="' . htmlspecialchars($createdMovie->thumbnail_url) . '" class="movie-thumb" onerror="this.style.display=\'none\'" />';
                        }
                        echo '</div>';
                    } else {
                        echo '<em style="color: #ffc107;">⚠️ Page processed but no movie found</em><br>';
                        echo '</div>';
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $pendMuno->status = 'failed';
                    $pendMuno->save();
                    // DB::rollBack();
                    throw $e;
                }

                // Progress indicator
                $progress = ($stats['total_processed'] / count($pendingPages)) * 100;
                echo '<div class="progress"><div class="progress-bar" style="width: ' . $progress . '%">'
                    . round($progress, 1) . '% Complete</div></div>';
            } catch (\Throwable $th) {
                $stats['errors']++;

                echo '<div class="page-card error">';
                echo '<span class="page-id">[❌ ERROR] Page ID: ' . $pageData->id . '</span><br>';
                echo '<strong>Error:</strong> ' . htmlspecialchars($th->getMessage()) . '<br>';
                echo '<em>Check logs for full details</em>';
                echo '</div>';

                Log::error('Error processing MovieCrawlerPage', [
                    'page_id' => $pageData->id,
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
            }
        }

        // Final Statistics
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        echo '<hr>';
        echo '<div class="stats">';
        echo '<h2>📊 Processing Complete</h2>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Processed:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['total_processed']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Movies Created:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #28a745;">' . number_format($stats['movies_created']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Already Active (Skipped):</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #ffc107;">' . number_format($stats['already_active']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Page Not Success:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #ffc107;">' . number_format($stats['page_not_success']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Errors:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #dc3545;">' . number_format($stats['errors']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Processing Time:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $duration . ' seconds</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Remaining Pages:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($totalPending - $stats['total_processed']) . '</td></tr>';
        echo '</table>';

        // Calculate success rate
        if ($stats['total_processed'] > 0) {
            $successRate = ($stats['movies_created'] / $stats['total_processed']) * 100;
            echo '<p style="margin-top: 15px;">Success Rate: <strong>' . round($successRate, 2) . '%</strong></p>';
        }

        echo '</div>';

        $remainingPages = $totalPending - $stats['total_processed'];
        if ($remainingPages > 0) {
            echo '<div class="info">';
            echo '<h3>🔄 More Pages Available</h3>';
            echo '<p>There are still ' . number_format($remainingPages) . ' pending pages to process.</p>';
            echo '<p><a href="' . $request->url() . '?limit=' . $limit . '&skip_active=' . ($skipActive ? 'yes' : 'no') . '" ';
            echo 'style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">▶️ Process Next Batch</a></p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<h3>🎉 All Done!</h3>';
            echo '<p>All pending pages have been processed successfully.</p>';
            echo '</div>';
        }
    } catch (\Exception $e) {
        echo '<div class="error">';
        echo '<h3>❌ Critical Error</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
        Log::error('Munowatch pages processor critical error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    echo '</body></html>';
});

/**
 * 🎬 OPTIMIZED MUNOWATCH MOVIES CRAWLER
 * 
 * This endpoint generates Munowatch movie crawler pages in bulk.
 * Efficiently creates crawler records for movie IDs that don't exist yet.
 * 
 * Features:
 * - Pure SQL-based for maximum performance
 * - Bulk insert with batch processing (500 records at a time)
 * - Automatic range detection from last processed movie
 * - Skips existing pages using efficient SQL queries
 * - Progress tracking and detailed reporting
 * - Transaction-safe operations
 * 
 * Query Parameters:
 * - generate_pages: Set to 'yes' to start generating pages (required)
 * - batch_size: Number of movies to check per batch (default: 5000, max: 10000)
 * - user_id: Munowatch user ID (default: 3664)
 * 
 * Usage: /munowatch-movies-crawler?generate_pages=yes&batch_size=5000
 */
Route::get('munowatch-movies-crawler', function (Request $request) {
    set_time_limit(1200); // 20 minutes
    ini_set("memory_limit", "256M");

    $startTime = microtime(true);
    $user_id = (int) $request->get('user_id', 3664);
    $batch_size = min((int) $request->get('batch_size', 5000), 10000);
    $max_movie_id = 60106; // Munowatch maximum movie ID
    $generate_pages = $request->get('generate_pages', 'no') === 'yes';

    // Initialize statistics
    $stats = [
        'pages_created' => 0,
        'pages_skipped' => 0,
        'errors' => 0,
        'total_checked' => 0,
    ];

    // HTML output with styling
    echo '<html><head><title>Munowatch Movies Crawler</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; margin: 5px 0; border-left: 4px solid #28a745; background: #d4edda; }
        .warning { color: #ffc107; padding: 10px; margin: 5px 0; border-left: 4px solid #ffc107; background: #fff3cd; }
        .error { color: #dc3545; padding: 10px; margin: 5px 0; border-left: 4px solid #dc3545; background: #f8d7da; }
        .info { color: #17a2b8; padding: 10px; margin: 5px 0; border-left: 4px solid #17a2b8; background: #d1ecf1; }
        .progress { background: #e9ecef; height: 30px; border-radius: 5px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #28a745; height: 100%; line-height: 30px; color: white; text-align: center; transition: width 0.3s; }
    </style></head><body>';

    echo '<div class="header">';
    echo '<h1>🎬 Munowatch Movies Crawler</h1>';
    echo '<p>SQL-Optimized Bulk Page Generator</p>';
    echo '</div>';

    try {
        // Get the last processed movie ID using efficient SQL with proper numeric ordering
        // Since movie_id is stored as string, we need to cast it to integer for proper ordering
        $lastProcessedMovie = DB::table('movie_crawler_pages')
            ->select('movie_id')
            ->where('movie_crawler_website_id', 2)
            ->where('is_muno', 'Yes')
            ->whereNotNull('movie_id')
            ->where('movie_id', '!=', '')
            ->where('movie_id', 'REGEXP', '^[0-9]+$') // Only numeric IDs
            ->orderByRaw('CAST(movie_id AS UNSIGNED) DESC') // Cast to integer for proper numeric ordering
            ->first();

        $min_movie_id = 4005; // Default starting ID
        if ($lastProcessedMovie && is_numeric($lastProcessedMovie->movie_id)) {
            $min_movie_id = (int) $lastProcessedMovie->movie_id + 1;
        }

        // Debug output
        echo '<div class="info">';
        echo '<p><strong>🔍 Debug Info:</strong></p>';
        echo '<p>Last Processed Movie ID: <strong>' . ($lastProcessedMovie ? $lastProcessedMovie->movie_id : 'None') . '</strong></p>';
        echo '<p>Next Starting ID: <strong>' . number_format($min_movie_id) . '</strong></p>';
        echo '</div>';

        // Calculate range for this batch
        $max_movie_id_batch = min($min_movie_id + $batch_size - 1, $max_movie_id);

        echo '<div class="stats">';
        echo '<p><strong>📊 Crawler Configuration:</strong></p>';
        echo '<p>User ID: <strong>' . $user_id . '</strong></p>';
        echo '<p>Movie ID Range: <strong>' . number_format($min_movie_id) . ' - ' . number_format($max_movie_id_batch) . '</strong></p>';
        echo '<p>Batch Size: <strong>' . number_format($max_movie_id_batch - $min_movie_id + 1) . '</strong></p>';
        echo '<p>Max Movie ID: <strong>' . number_format($max_movie_id) . '</strong></p>';
        echo '<p>Mode: <strong>' . ($generate_pages ? 'GENERATE (Creating Pages)' : 'PREVIEW (Dry Run)') . '</strong></p>';
        echo '</div>';

        if (!$generate_pages) {
            echo '<div class="warning">';
            echo '<h3>⚠️ Preview Mode</h3>';
            echo '<p>Add <code>?generate_pages=yes</code> to start generating pages.</p>';
            echo '<p><a href="' . $request->url() . '?generate_pages=yes&batch_size=' . $batch_size . '&user_id=' . $user_id . '" ';
            echo 'style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">▶️ Start Generating Pages</a></p>';
            echo '</div>';
            echo '</body></html>';
            return;
        }

        if ($min_movie_id > $max_movie_id) {
            echo '<div class="success">';
            echo '<h3>🎉 All Movies Processed!</h3>';
            echo '<p>All movie IDs up to ' . number_format($max_movie_id) . ' have been generated.</p>';
            echo '</div>';
            echo '</body></html>';
            return;
        }

        echo '<h2>🚀 Generating Pages...</h2>';
        echo '<hr>';

        // Get all existing URLs in this range using efficient SQL
        $existingMovieIds = DB::table('movie_crawler_pages')
            ->where('movie_crawler_website_id', 2)
            ->where('is_muno', 'Yes')
            ->whereBetween('movie_id', [$min_movie_id, $max_movie_id_batch])
            ->pluck('movie_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->toArray();

        $existingMovieIdsSet = array_flip($existingMovieIds);

        echo '<div class="info">';
        echo '<p>Found ' . count($existingMovieIds) . ' existing pages in this range.</p>';
        echo '</div>';

        //set time to process each movie
        set_time_limit(3000); // 50 minutes

        // Prepare bulk insert data
        $bulkInsertData = [];
        $now = now();
        $min_movie_id = 4005;
        $max_movie_id_batch = 60106;
        for ($i = $min_movie_id; $i <= $max_movie_id_batch; $i++) {
            $stats['total_checked']++;

            // Skip if already exists
            if (isset($existingMovieIdsSet[$i])) {
                $stats['pages_skipped']++;
                continue;
            }

            $url = "https://munowatch.org/api/preview/v2/$i/" . $user_id;

            $bulkInsertData[] = [
                'movie_crawler_website_id' => 2,
                'title' => '',
                'slug' => (string) $i,
                'movie_id' => (string) $i,
                'url' => $url,
                'status' => 'Pending',
                'is_generated' => 'Yes',
                'is_muno' => 'Yes',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $stats['pages_created']++;

            // Insert in chunks of 500 to avoid memory issues
            if (count($bulkInsertData) >= 500) {
                DB::table('movie_crawler_pages')->insert($bulkInsertData);
                echo '<div class="success">✅ Inserted ' . count($bulkInsertData) . ' pages (IDs: ' . ($i - count($bulkInsertData) + 1) . ' - ' . $i . ')</div>';

                $progress = (($i - $min_movie_id + 1) / ($max_movie_id_batch - $min_movie_id + 1)) * 100;
                echo '<div class="progress"><div class="progress-bar" style="width: ' . $progress . '%">'
                    . round($progress, 1) . '% Complete</div></div>';

                flush();
                $bulkInsertData = [];
            }
        }

        // Insert remaining records
        if (!empty($bulkInsertData)) {
            DB::table('movie_crawler_pages')->insert($bulkInsertData);
            echo '<div class="success">✅ Inserted final ' . count($bulkInsertData) . ' pages</div>';
        }

        // Final Statistics
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        // Check remaining movies
        $remainingMovies = $max_movie_id - $max_movie_id_batch;

        echo '<hr>';
        echo '<div class="stats">';
        echo '<h2>📊 Generation Complete</h2>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Checked:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['total_checked']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Pages Created:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #28a745;">' . number_format($stats['pages_created']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Pages Skipped (Existing):</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #ffc107;">' . number_format($stats['pages_skipped']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Processing Time:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $duration . ' seconds</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Speed:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['total_checked'] / max($duration, 0.001)) . ' movies/sec</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Remaining Movies:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format(max(0, $remainingMovies)) . '</td></tr>';
        echo '</table>';
        echo '</div>';

        if ($remainingMovies > 0) {
            echo '<div class="info">';
            echo '<h3>🔄 Continue Processing</h3>';
            echo '<p>There are still ' . number_format($remainingMovies) . ' movies to process.</p>';
            echo '<p><a href="' . $request->url() . '?generate_pages=yes&batch_size=' . $batch_size . '&user_id=' . $user_id . '" ';
            echo 'style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">▶️ Process Next Batch</a></p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<h3>🎉 All Done!</h3>';
            echo '<p>All Munowatch movies up to ID ' . number_format($max_movie_id) . ' have been generated successfully.</p>';
            echo '</div>';
        }
    } catch (\Exception $e) {
        echo '<div class="error">';
        echo '<h3>❌ Critical Error</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
        Log::error('Munowatch crawler critical error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    echo '</body></html>';
});


Route::get('logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);

// Video Transfer Routes
Route::get('transfer/process/{id}', [TransferProcessController::class, 'show'])->name('transfer.process');
Route::post('transfer/start/{id}', [TransferProcessController::class, 'start'])->name('transfer.start');
Route::get('transfer/status/{id}', [TransferProcessController::class, 'status'])->name('transfer.status');

/**
 * 🔄 OPTIMIZED IMAGE URL REPLACEMENT SYSTEM
 * 
 * This endpoint replaces movie image URLs from movie_pics table back into movie_models table.
 * Uses the 'director' field as a processing flag: '-' = pending, '--' = processed.
 * 
 * Features:
 * - Pure SQL-based for maximum performance
 * - Transaction-safe with proper error handling
 * - Batch processing to prevent memory issues
 * - Progress tracking and detailed reporting
 * - Automatic reset capability when processing is complete
 * 
 * Query Parameters:
 * - limit: Number of movies to process per run (default: 100, max: 500)
 * - reset: Set to 'yes' to reset all movies for reprocessing
 * - dry_run: Set to 'yes' to preview without updating
 * 
 * Usage: /replace-images?limit=100&dry_run=no
 */
Route::get('replace-images', function (Request $r) {
    set_time_limit(600); // 10 minutes
    ini_set("memory_limit", "256M");

    $startTime = microtime(true);
    $dryRun = $r->get('dry_run', 'no') === 'yes';
    $limit = (int) $r->get('limit', 1500); // Max 500 for safety
    $reset = $r->get('reset', 'no') === 'yes';

    // Initialize statistics
    $stats = [
        'total_processed' => 0,
        'images_replaced' => 0,
        'no_image_found' => 0,
        'errors' => 0,
    ];

    // HTML output with styling
    echo '<html><head><title>Image URL Replacement System</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; margin: 5px 0; border-left: 4px solid #28a745; background: #d4edda; }
        .warning { color: #ffc107; padding: 10px; margin: 5px 0; border-left: 4px solid #ffc107; background: #fff3cd; }
        .error { color: #dc3545; padding: 10px; margin: 5px 0; border-left: 4px solid #dc3545; background: #f8d7da; }
        .info { color: #17a2b8; padding: 10px; margin: 5px 0; border-left: 4px solid #17a2b8; background: #d1ecf1; }
        .movie-card { background: white; padding: 12px; margin: 8px 0; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .movie-id { font-weight: bold; color: #007bff; }
        .movie-title { color: #333; font-size: 14px; margin: 5px 0; }
        .progress { background: #e9ecef; height: 30px; border-radius: 5px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #28a745; height: 100%; line-height: 30px; color: white; text-align: center; transition: width 0.3s; }
    </style></head><body>';

    echo '<div class="header">';
    echo '<h1>🔄 Image URL Replacement System</h1>';
    echo '<p>SQL-Optimized Processing Engine</p>';
    echo '</div>';

    try {
        // RESET LOGIC: Reset all movies for reprocessing
        if ($reset) {
            echo '<div class="warning"><strong>⚠️ RESET MODE ACTIVATED</strong></div>';
            $resetCount = DB::table('movie_models')
                ->update(['director' => '-']);
            echo '<div class="success">✅ Reset complete! Marked ' . number_format($resetCount) . ' movies for reprocessing.</div>';
            echo '<div class="info">💡 Refresh this page without reset=yes to start processing.</div>';
            echo '</body></html>';
            return;
        }

        // Check if processing is needed
        $pendingCount = DB::table('movie_models')
            ->where('director', '-')
            ->count();

        if ($pendingCount < 5) {
            // Auto-reset when less than 5 pending
            echo '<div class="warning"><strong>⚠️ AUTO-RESET TRIGGERED</strong></div>';
            echo '<div class="info">Less than 5 movies pending. Resetting all movies for reprocessing...</div>';
            $resetCount = DB::table('movie_models')
                ->update(['director' => '-']);
            echo '<div class="success">✅ Reset complete! Marked ' . number_format($resetCount) . ' movies for reprocessing.</div>';
            echo '<div class="info">💡 <a href="' . $r->url() . '">Click here to start processing</a></div>';
            echo '</body></html>';
            return;
        }

        echo '<div class="stats">';
        echo '<p><strong>📊 Processing Configuration:</strong></p>';
        echo '<p>Pending Movies: <strong>' . number_format($pendingCount) . '</strong></p>';
        echo '<p>Batch Size: <strong>' . number_format($limit) . '</strong></p>';
        echo '<p>Mode: <strong>' . ($dryRun ? 'DRY RUN (Preview Only)' : 'LIVE (Will Update Database)') . '</strong></p>';
        echo '</div>';

        // Get movies to process using efficient SQL with LEFT JOIN
        $movies = DB::table('movie_models as m')
            ->select('m.id', 'm.title', 'm.image_url as current_image_url', 'mp.pic_url')
            ->leftJoin('movie_pics as mp', 'm.id', '=', 'mp.movie_id')
            ->where('m.director', '-')
            ->orderBy('m.id', 'asc')
            ->limit($limit)
            ->get();

        if ($movies->isEmpty()) {
            echo '<div class="info">';
            echo '<h3>✅ No Movies to Process</h3>';
            echo '<p>All movies have been processed.</p>';
            echo '</div>';
            echo '</body></html>';
            return;
        }

        echo '<h2>🔍 Processing ' . number_format($movies->count()) . ' Movies</h2>';
        echo '<hr>';

        foreach ($movies as $movie) {
            $stats['total_processed']++;

            DB::beginTransaction();
            try {
                // Case 1: No image found in movie_pics
                if (empty($movie->pic_url)) {
                    $stats['no_image_found']++;

                    if (!$dryRun) {
                        DB::table('movie_models')
                            ->where('id', $movie->id)
                            ->update([
                                'director' => '--', // Mark as processed even without image
                                'updated_at' => now()
                            ]);
                    }

                    echo '<div class="movie-card warning">';
                    echo '<span class="movie-id">[NO IMAGE] ID: ' . $movie->id . '</span><br>';
                    echo '<span class="movie-title">' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                    echo '<em>No image found in movie_pics table - marked as processed</em>';
                    echo ($dryRun ? '<br><em>(DRY RUN - Not updated)</em>' : '');
                    echo '</div>';

                    DB::commit();
                    continue;
                }

                // Case 2: Image found - replace URL
                $stats['images_replaced']++;

                if (!$dryRun) {
                    DB::table('movie_models')
                        ->where('id', $movie->id)
                        ->update([
                            'director' => '--',
                            'image_url' => $movie->pic_url,
                            'thumbnail_url' => $movie->pic_url,
                            'updated_at' => now()
                        ]);
                }

                echo '<div class="movie-card success">';
                echo '<span class="movie-id">[✅ REPLACED] ID: ' . $movie->id . '</span><br>';
                echo '<span class="movie-title">' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                echo '<em>Old URL: ' . htmlspecialchars(substr($movie->current_image_url ?? 'N/A', 0, 60)) . '...</em><br>';
                echo '<em>New URL: ' . htmlspecialchars(substr($movie->pic_url, 0, 60)) . '...</em>';
                echo ($dryRun ? '<br><em>(DRY RUN - Not updated)</em>' : '');
                echo '</div>';

                DB::commit();

                // Progress indicator every 25 movies
                if ($stats['total_processed'] % 25 === 0) {
                    $progress = ($stats['total_processed'] / $movies->count()) * 100;
                    echo '<div class="progress"><div class="progress-bar" style="width: ' . $progress . '%">'
                        . round($progress, 1) . '% Complete</div></div>';
                    flush();
                }
            } catch (\Exception $e) {
                // DB::rollBack();
                $stats['errors']++;

                echo '<div class="movie-card error">';
                echo '<span class="movie-id">[❌ ERROR] ID: ' . $movie->id . '</span><br>';
                echo '<span class="movie-title">' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                echo '<span style="color: #666;">Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
                echo '</div>';

                Log::error('Image replacement error', [
                    'movie_id' => $movie->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Final Statistics
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        // Check remaining movies
        $remainingCount = DB::table('movie_models')
            ->where('director', '-')
            ->count();

        echo '<hr>';
        echo '<div class="stats">';
        echo '<h2>📊 Processing Complete</h2>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Processed:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['total_processed']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Images Replaced:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #28a745;">' . number_format($stats['images_replaced']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>No Image Found:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #ffc107;">' . number_format($stats['no_image_found']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Errors:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['errors']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Processing Time:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $duration . ' seconds</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Remaining Movies:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($remainingCount) . '</td></tr>';
        echo '</table>';

        // Calculate success rate
        if ($stats['total_processed'] > 0) {
            $successRate = ($stats['images_replaced'] / $stats['total_processed']) * 100;
            echo '<p style="margin-top: 15px;">Success Rate: <strong>' . round($successRate, 2) . '%</strong></p>';
        }

        echo '</div>';

        if ($remainingCount > 0) {
            echo '<div class="info">';
            echo '<h3>🔄 More Movies Available</h3>';
            echo '<p>There are still ' . number_format($remainingCount) . ' movies to process.</p>';
            echo '<p><a href="' . $r->url() . '?limit=' . $limit . '" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">▶️ Process Next Batch</a></p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<h3>🎉 Batch Complete!</h3>';
            echo '<p>This batch has been processed successfully.</p>';
            echo '<p>The system will auto-reset when less than 5 movies remain.</p>';
            echo '</div>';
        }
    } catch (\Exception $e) {
        // DB::rollBack();
        echo '<div class="error">';
        echo '<h3>❌ Critical Error</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
        Log::error('Image replacement critical error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    echo '</body></html>';
});

/**
 * 🖼️ OPTIMIZED IMAGE MIGRATION SYSTEM
 * 
 * This endpoint migrates movie images from movie_models table to movie_pics table.
 * It efficiently processes movies that don't have entries in movie_pics table yet.
 * 
 * Features:
 * - Pure SQL-based for maximum performance
 * - Transaction-safe with proper error handling
 * - Batch processing to prevent memory issues
 * - Validates image URLs before insertion
 * - Progress tracking and detailed reporting
 * - Skips movies that already have images
 * 
 * Query Parameters:
 * - limit: Number of movies to process per run (default: 500, max: 2000)
 * - dry_run: Set to 'yes' to preview without inserting
 * 
 * Usage: /fix-images?limit=500&dry_run=no
 */
Route::get('fix-images', function (Request $r) {
    $pic = MoviePic::where('movie_id', 78)->first();
    //display pic_url
    echo '<img src="' . e($pic->pic_url) . '" alt="Movie Picture">';
    $movie = MovieModel::find(78);
    echo '<h2>' . e($movie->title) . '</h2>';
    echo '<p>Image URL: ' . e($movie->thumbnail_url) . '</p>';
    echo '<img src="' . e($movie->thumbnail_url) . '" alt="Thumbnail">';
    die();
    // dd($pic);
    // die("done processing");
    set_time_limit(600); // 10 minutes
    ini_set("memory_limit", "256M");

    $startTime = microtime(true);
    $dryRun = $r->get('dry_run', 'no') === 'yes';
    $limit = $r->get('limit', 1000); // Max 2000 for safety

    // Initialize statistics
    $stats = [
        'total_processed' => 0,
        'images_added' => 0,
        'already_exists' => 0,
        'no_valid_image' => 0,
        'errors' => 0,
        'used_image_url' => 0,
        'used_thumbnail_url' => 0,
    ];

    // HTML output with styling
    echo '<html><head><title>Image Migration System</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; margin: 5px 0; border-left: 4px solid #28a745; background: #d4edda; }
        .warning { color: #ffc107; padding: 10px; margin: 5px 0; border-left: 4px solid #ffc107; background: #fff3cd; }
        .error { color: #dc3545; padding: 10px; margin: 5px 0; border-left: 4px solid #dc3545; background: #f8d7da; }
        .info { color: #17a2b8; padding: 10px; margin: 5px 0; border-left: 4px solid #17a2b8; background: #d1ecf1; }
        .movie-card { background: white; padding: 12px; margin: 8px 0; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .movie-id { font-weight: bold; color: #007bff; }
        .movie-title { color: #333; font-size: 14px; margin: 5px 0; }
        .movie-image { max-width: 100px; max-height: 150px; border-radius: 4px; margin-top: 5px; }
        .progress { background: #e9ecef; height: 30px; border-radius: 5px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #28a745; height: 100%; line-height: 30px; color: white; text-align: center; transition: width 0.3s; }
    </style></head><body>';

    echo '<div class="header">';
    echo '<h1>🖼️ Image Migration System</h1>';
    echo '<p>SQL-Optimized Processing Engine</p>';
    echo '</div>';

    try {
        echo '<div class="stats">';
        echo '<p><strong>📊 Processing Configuration:</strong></p>';
        echo '<p>Batch Size: <strong>' . number_format($limit) . '</strong></p>';
        echo '<p>Mode: <strong>' . ($dryRun ? 'DRY RUN (Preview Only)' : 'LIVE (Will Insert Images)') . '</strong></p>';
        echo '</div>';

        // Get movies that don't have images yet using efficient LEFT JOIN
        $movies = DB::table('movie_models as m')
            ->select('m.id', 'm.title', 'm.image_url', 'm.thumbnail_url')
            ->leftJoin('movie_pics as mp', 'm.id', '=', 'mp.movie_id')
            ->whereNull('mp.id') // Only get movies without movie_pics entry
            ->orderBy('m.id', 'asc')
            ->limit($limit)
            ->get();

        if ($movies->isEmpty()) {
            echo '<div class="info">';
            echo '<h3>✅ No Movies to Process</h3>';
            echo '<p>All movies already have images in the movie_pics table.</p>';
            echo '</div>';
            echo '</body></html>';
            return;
        }

        echo '<h2>🔍 Processing ' . number_format($movies->count()) . ' Movies</h2>';
        echo '<hr>';

        foreach ($movies as $movie) {
            $stats['total_processed']++;

            DB::beginTransaction();
            try {
                // Double-check in case of race condition
                $existingCheck = DB::table('movie_pics')
                    ->where('movie_id', $movie->id)
                    ->exists();

                if ($existingCheck) {
                    $stats['already_exists']++;

                    echo '<div class="movie-card warning">';
                    echo '<span class="movie-id">[SKIP - EXISTS] ID: ' . $movie->id . '</span><br>';
                    echo '<span class="movie-title">' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span>';
                    echo '</div>';

                    DB::commit();
                    continue;
                }

                // Determine which URL to use (image_url first, then thumbnail_url)
                $imageUrl = null;
                $urlSource = null;

                if (!empty($movie->image_url) && strlen($movie->image_url) >= 10) {
                    $imageUrl = $movie->image_url;
                    $urlSource = 'image_url';
                    $stats['used_image_url']++;
                } elseif (!empty($movie->thumbnail_url) && strlen($movie->thumbnail_url) >= 10) {
                    $imageUrl = $movie->thumbnail_url;
                    $urlSource = 'thumbnail_url';
                    $stats['used_thumbnail_url']++;
                }

                // No valid image URL found
                if ($imageUrl === null) {
                    $stats['no_valid_image']++;

                    echo '<div class="movie-card warning">';
                    echo '<span class="movie-id">[NO IMAGE] ID: ' . $movie->id . '</span><br>';
                    echo '<span class="movie-title">' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                    echo '<em>No valid image URL found</em>';
                    echo '</div>';

                    DB::commit();
                    continue;
                }

                // Insert image record
                if (!$dryRun) {
                    DB::table('movie_pics')->insert([
                        'movie_id' => $movie->id,
                        'pic_url' => $imageUrl,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $stats['images_added']++;

                echo '<div class="movie-card success">';
                echo '<span class="movie-id">[✅ ADDED] ID: ' . $movie->id . '</span><br>';
                echo '<span class="movie-title">' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                echo '<em>Source: ' . $urlSource . '</em><br>';
                // echo '<img src="' . htmlspecialchars($imageUrl) . '" class="movie-image" onerror="this.style.display=\'none\'" />';
                echo ($dryRun ? '<br><em>(DRY RUN - Not inserted)</em>' : '');
                echo '</div>';

                DB::commit();

                // Progress indicator every 50 movies
                if ($stats['total_processed'] % 50 === 0) {
                    $progress = ($stats['total_processed'] / $movies->count()) * 100;
                    echo '<div class="progress"><div class="progress-bar" style="width: ' . $progress . '%">'
                        . round($progress, 1) . '% Complete</div></div>';
                    flush();
                }
            } catch (\Exception $e) {
                // DB::rollBack();
                $stats['errors']++;

                echo '<div class="movie-card error">';
                echo '<span class="movie-id">[❌ ERROR] ID: ' . $movie->id . '</span><br>';
                echo '<span class="movie-title">' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                echo '<span style="color: #666;">Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
                echo '</div>';

                Log::error('Image migration error', [
                    'movie_id' => $movie->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Final Statistics
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        // Check remaining movies
        $remainingCount = DB::table('movie_models as m')
            ->leftJoin('movie_pics as mp', 'm.id', '=', 'mp.movie_id')
            ->whereNull('mp.id')
            ->count();

        echo '<hr>';
        echo '<div class="stats">';
        echo '<h2>📊 Processing Complete</h2>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Processed:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['total_processed']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Images Added:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #28a745;">' . number_format($stats['images_added']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Already Exists:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #ffc107;">' . number_format($stats['already_exists']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>No Valid Image:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #ffc107;">' . number_format($stats['no_valid_image']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Errors:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['errors']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Processing Time:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $duration . ' seconds</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Remaining Movies:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($remainingCount) . '</td></tr>';
        echo '</table>';

        echo '<h3>📸 Image Source Breakdown:</h3>';
        echo '<ul>';
        echo '<li>From image_url: <strong>' . number_format($stats['used_image_url']) . '</strong></li>';
        echo '<li>From thumbnail_url: <strong>' . number_format($stats['used_thumbnail_url']) . '</strong></li>';
        echo '</ul>';

        // Calculate success rate
        if ($stats['total_processed'] > 0) {
            $successRate = ($stats['images_added'] / $stats['total_processed']) * 100;
            echo '<p style="margin-top: 15px;">Success Rate: <strong>' . round($successRate, 2) . '%</strong></p>';
        }

        echo '</div>';

        if ($remainingCount > 0) {
            echo '<div class="info">';
            echo '<h3>🔄 More Movies Available</h3>';
            echo '<p>There are still ' . number_format($remainingCount) . ' movies without images.</p>';
            echo '<p><a href="' . $r->url() . '?limit=' . $limit . '" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">▶️ Process Next Batch</a></p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<h3>🎉 All Done!</h3>';
            echo '<p>All movies have been processed successfully.</p>';
            echo '</div>';
        }
    } catch (\Exception $e) {
        // DB::rollBack();
        echo '<div class="error">';
        echo '<h3>❌ Critical Error</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
        Log::error('Image migration critical error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    echo '</body></html>';
});

Route::get('reverse-firebase', function (Request $r) {
    set_time_limit(600); // 10 minutes
    ini_set("memory_limit", "256M");

    $startTime = microtime(true);
    $dryRun = $r->get('dry_run', 'no') === 'yes';
    $limit = min((int) $r->get('limit', 100), 500); // Max 500 for safety

    // Initialize statistics
    $stats = [
        'total_processed' => 0,
        'still_google' => 0,
        'invalid_urls' => 0,
        'successfully_reversed' => 0,
        'errors' => 0,
    ];

    // HTML output with styling
    echo '<html><head><title>Firebase Reversal System</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; margin: 5px 0; border-left: 4px solid #28a745; background: #d4edda; }
        .warning { color: #ffc107; padding: 10px; margin: 5px 0; border-left: 4px solid #ffc107; background: #fff3cd; }
        .error { color: #dc3545; padding: 10px; margin: 5px 0; border-left: 4px solid #dc3545; background: #f8d7da; }
        .info { color: #17a2b8; padding: 10px; margin: 5px 0; border-left: 4px solid #17a2b8; background: #d1ecf1; }
        .movie-card { background: white; padding: 12px; margin: 8px 0; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .movie-id { font-weight: bold; color: #007bff; }
        .movie-title { color: #333; font-size: 14px; }
        .movie-url { color: #666; font-size: 12px; font-family: monospace; word-break: break-all; }
        .progress { background: #e9ecef; height: 30px; border-radius: 5px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #28a745; height: 100%; line-height: 30px; color: white; text-align: center; transition: width 0.3s; }
    </style></head><body>';

    echo '<div class="header">';
    echo '<h1>🔄 Firebase Reversal System</h1>';
    echo '<p>SQL-Optimized Processing Engine</p>';
    echo '</div>';

    try {
        echo '<div class="stats">';
        echo '<p><strong>📊 Processing Configuration:</strong></p>';
        echo '<p>Batch Size: <strong>' . number_format($limit) . '</strong></p>';
        echo '<p>Mode: <strong>' . ($dryRun ? 'DRY RUN (Preview Only)' : 'LIVE (Will Update Database)') . '</strong></p>';
        echo '</div>';

        // Get movies using direct SQL for efficiency
        $movies = DB::table('movie_models')
            ->select('id', 'title', 'url', 'external_url', 'old_video_url', 'firebase_transfer_successful')
            ->where('firebase_transfer_successful', 'Yes')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($movies->isEmpty()) {
            echo '<div class="info">';
            echo '<h3>✅ No Movies to Process</h3>';
            echo '<p>No movies found with firebase_transfer_successful = "Yes"</p>';
            echo '</div>';
            echo '</body></html>';
            return;
        }

        echo '<h2>🔍 Processing ' . number_format($movies->count()) . ' Movies</h2>';
        echo '<hr>';

        foreach ($movies as $movie) {
            $stats['total_processed']++;

            DB::beginTransaction();
            try {
                // Determine which URL to check
                $urlToCheck = $movie->external_url;
                $isGoogleHosted = Utils::isHostedOnGoogle($urlToCheck);

                // If external_url is on Google, try old_video_url
                if ($isGoogleHosted && !empty($movie->old_video_url)) {
                    $urlToCheck = $movie->old_video_url;
                    $isGoogleHosted = Utils::isHostedOnGoogle($urlToCheck);
                }

                // Case 1: URL is still hosted on Google (can't reverse)
                if ($isGoogleHosted) {
                    $stats['still_google']++;

                    if (!$dryRun) {
                        DB::table('movie_models')
                            ->where('id', $movie->id)
                            ->update([
                                'firebase_transfer_successful' => '
                                ',
                                'firebase_transfer_failure_reason' => 'Still hosted on Google services',
                                'status' => 'Inactive',
                                'updated_at' => now()
                            ]);
                    }

                    echo '<div class="movie-card error">';
                    echo '<span class="movie-id">[STILL GOOGLE] ID: ' . $movie->id . '</span><br>';
                    echo '<span class="movie-title">Title: ' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                    echo '<span class="movie-url">URL: ' . htmlspecialchars($urlToCheck) . '</span>';
                    echo ($dryRun ? '<br><em>(DRY RUN - Not updated)</em>' : '<br><em>Marked as FIX-FAIL</em>');
                    echo '</div>';

                    DB::commit();
                    continue;
                }

                // Case 2: Validate if URL is a valid video URL
                $isValidVideoUrl = Utils::isPossiblyVideoUrl($urlToCheck, true);

                if (!$isValidVideoUrl) {
                    $stats['invalid_urls']++;

                    if (!$dryRun) {
                        DB::table('movie_models')
                            ->where('id', $movie->id)
                            ->update([
                                'firebase_transfer_successful' => 'FIX-FAIL',
                                'status' => 'Inactive',
                                'firebase_transfer_failure_reason' => 'URL is not a valid video URL during reverse check',
                                'updated_at' => now()
                            ]);
                    }

                    echo '<div class="movie-card error">';
                    echo '<span class="movie-id">[INVALID URL] ID: ' . $movie->id . '</span><br>';
                    echo '<span class="movie-title">Title: ' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                    echo '<span class="movie-url">URL: ' . htmlspecialchars($urlToCheck) . '</span>';
                    echo ($dryRun ? '<br><em>(DRY RUN - Not updated)</em>' : '<br><em>Marked as FIX-FAIL</em>');
                    echo '</div>';

                    DB::commit();
                    continue;
                }

                // Case 3: Valid non-Google URL - reverse the Firebase transfer
                $stats['successfully_reversed']++;

                if (!$dryRun) {
                    DB::table('movie_models')
                        ->where('id', $movie->id)
                        ->update([
                            'firebase_transfer_successful' => 'FIX-PASS',
                            'firebase_transfer_failure_reason' => null,
                            'url' => $urlToCheck,
                            'old_video_url' => $movie->url, // Save current URL as old
                            'updated_at' => now()
                        ]);
                }

                echo '<div class="movie-card success">';
                echo '<span class="movie-id">[✅ REVERSED] ID: ' . $movie->id . '</span><br>';
                echo '<span class="movie-title">Title: ' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                echo '<span class="movie-url">New URL: ' . htmlspecialchars($urlToCheck) . '</span><br>';
                echo '<span class="movie-url">Old URL: ' . htmlspecialchars($movie->url ?? 'N/A') . '</span>';
                echo ($dryRun ? '<br><em>(DRY RUN - Not updated)</em>' : '<br><em>Successfully reversed</em>');
                echo '</div>';

                DB::commit();

                // Progress indicator every 25 movies
                if ($stats['total_processed'] % 25 === 0) {
                    $progress = ($stats['total_processed'] / $movies->count()) * 100;
                    echo '<div class="progress"><div class="progress-bar" style="width: ' . $progress . '%">'
                        . round($progress, 1) . '% Complete</div></div>';
                    flush();
                }
            } catch (\Exception $e) {
                // DB::rollBack();
                $stats['errors']++;

                echo '<div class="movie-card error">';
                echo '<span class="movie-id">[❌ ERROR] ID: ' . $movie->id . '</span><br>';
                echo '<span class="movie-title">Title: ' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 80)) . '</span><br>';
                echo '<span class="movie-url">Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
                echo '</div>';

                Log::error('Firebase reversal error', [
                    'movie_id' => $movie->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Final Statistics
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        echo '<hr>';
        echo '<div class="stats">';
        echo '<h2>📊 Processing Complete</h2>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Processed:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['total_processed']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Successfully Reversed:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #28a745;">' . number_format($stats['successfully_reversed']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Still on Google:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #ffc107;">' . number_format($stats['still_google']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Invalid URLs:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #dc3545;">' . number_format($stats['invalid_urls']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Errors:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['errors']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Processing Time:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $duration . ' seconds</td></tr>';
        echo '</table>';

        // Calculate success rate
        if ($stats['total_processed'] > 0) {
            $successRate = ($stats['successfully_reversed'] / $stats['total_processed']) * 100;
            echo '<p style="margin-top: 15px;">Success Rate: <strong>' . round($successRate, 2) . '%</strong></p>';
        }

        echo '</div>';

        // Check if there are more movies to process
        $remainingCount = DB::table('movie_models')
            ->where('firebase_transfer_successful', 'Yes')
            ->count();

        if ($remainingCount > 0) {
            echo '<div class="info">';
            echo '<h3>🔄 More Movies Available</h3>';
            echo '<p>There are still ' . number_format($remainingCount) . ' movies with firebase_transfer_successful = "Yes"</p>';
            echo '<p><a href="' . $r->url() . '?limit=' . $limit . '" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">▶️ Process Next Batch</a></p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<h3>🎉 All Done!</h3>';
            echo '<p>All eligible movies have been processed.</p>';
            echo '</div>';
        }
    } catch (\Exception $e) {
        // DB::rollBack();
        echo '<div class="error">';
        echo '<h3>❌ Critical Error</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
        Log::error('Firebase reversal critical error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    echo '</body></html>';
});

/**
 * 🎯 OPTIMIZED SQL-BASED DUPLICATE REMOVAL SYSTEM
 * 
 * This endpoint efficiently finds and removes duplicate movies using pure SQL queries.
 * Duplicates are identified by: title, munowatch_id, external_url, or url.
 * The system keeps the oldest record (lowest ID) and removes all duplicates.
 * 
 * Features:
 * - Pure SQL-based for maximum performance and reliability
 * - Transaction-safe with proper error handling
 * - Batch processing to prevent memory issues
 * - Progress tracking and detailed reporting
 * - Automatic reset capability when processing is complete
 * 
 * Query Parameters:
 * - limit: Number of movies to process per run (default: 500, max: 2000)
 * - reset: Set to 'yes' to reset all movies for reprocessing
 * - dry_run: Set to 'yes' to preview without deleting
 * 
 * Usage: /process-duplicates?limit=500&dry_run=no
 */
Route::get('process-duplicates', function (Request $r) {
    // return;
    set_time_limit(6000); // 10 minutes
    ini_set("memory_limit", "256M"); // 1GB for safety

    $startTime = microtime(true);
    $dryRun = $r->get('dry_run', 'no') === 'yes';
    $limit = min((int) $r->get('limit', 500), 2000); // Max 2000 for safety
    $reset = $r->get('reset', 'no') === 'yes';

    // Initialize statistics
    $stats = [
        'total_processed' => 0,
        'duplicates_found' => 0,
        'duplicates_deleted' => 0,
        'no_duplicates' => 0,
        'errors' => 0,
        'by_title' => 0,
        'by_munowatch_id' => 0,
        'by_external_url' => 0,
        'by_url' => 0,
    ];

    echo '<html><head><title>Duplicate Movie Removal</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; margin: 5px 0; border-left: 4px solid #28a745; background: #d4edda; }
        .warning { color: #ffc107; padding: 10px; margin: 5px 0; border-left: 4px solid #ffc107; background: #fff3cd; }
        .error { color: #dc3545; padding: 10px; margin: 5px 0; border-left: 4px solid #dc3545; background: #f8d7da; }
        .info { color: #17a2b8; padding: 10px; margin: 5px 0; border-left: 4px solid #17a2b8; background: #d1ecf1; }
        .progress { background: #e9ecef; height: 30px; border-radius: 5px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #28a745; height: 100%; line-height: 30px; color: white; text-align: center; transition: width 0.3s; }
    </style></head><body>';

    echo '<div class="header">';
    echo '<h1>🎯 Duplicate Movie Removal System</h1>';
    echo '<p>SQL-Optimized Processing Engine</p>';
    echo '</div>';

    try {
        // RESET LOGIC: Reset all movies for reprocessing
        if ($reset) {
            echo '<div class="warning"><strong>⚠️ RESET MODE ACTIVATED</strong></div>';
            $resetCount = DB::table('movie_models')
                ->where('type', 'Movie')
                ->update(['actor' => '--']);
            echo '<div class="success">✅ Reset complete! Marked ' . number_format($resetCount) . ' movies for reprocessing.</div>';
            echo '<div class="info">💡 Refresh this page without reset=yes to start processing.</div>';
            echo '</body></html>';
            return;
        }

        // Check if processing is needed
        $unprocessedCount = DB::table('movie_models')
            ->where('type', 'Movie')
            ->where('actor', '--')
            ->count();

        if ($unprocessedCount < 5) {
            echo '<div class="info"><strong>✅ Processing Complete!</strong></div>';
            echo '<div class="stats">';
            echo '<p>All movies have been processed for duplicates.</p>';
            echo '<p>To reprocess all movies, visit: <a href="?reset=yes">Reset & Reprocess All</a></p>';
            echo '</div>';
            echo '</body></html>';
            return;
        }

        echo '<div class="stats">';
        echo '<p><strong>📊 Processing Status:</strong></p>';
        echo '<p>Unprocessed Movies: <strong>' . number_format($unprocessedCount) . '</strong></p>';
        echo '<p>Batch Size: <strong>' . number_format($limit) . '</strong></p>';
        echo '<p>Mode: <strong>' . ($dryRun ? 'DRY RUN (Preview Only)' : 'LIVE (Will Delete Duplicates)') . '</strong></p>';
        echo '</div>';

        // Get movies to process using SQL for efficiency
        $moviesToProcess = DB::table('movie_models')
            ->select('id', 'title', 'munowatch_id', 'external_url', 'url')
            ->where('type', 'Movie')
            ->where('actor', '--')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($moviesToProcess->isEmpty()) {
            echo '<div class="warning">⚠️ No movies to process in this batch.</div>';
            echo '</body></html>';
            return;
        }

        echo '<h2>🔍 Processing ' . number_format($moviesToProcess->count()) . ' Movies</h2>';
        echo '<hr>';

        // Process each movie using pure SQL
        foreach ($moviesToProcess as $movie) {
            $stats['total_processed']++;

            DB::beginTransaction();
            try {
                // Build SQL query to find ALL duplicates in one go using UNION
                // This is much more efficient than multiple queries
                $duplicateIds = DB::table('movie_models as m')
                    ->select('m.id')
                    ->where('m.type', 'Movie')
                    ->where('m.id', '!=', $movie->id)
                    ->where(function ($query) use ($movie) {
                        // Match by title
                        if (!empty($movie->title)) {
                            $query->orWhere('m.title', $movie->title);
                        }
                        // Match by munowatch_id
                        if (!empty($movie->munowatch_id) && strlen($movie->munowatch_id) > 1) {
                            $query->orWhere('m.munowatch_id', $movie->munowatch_id);
                        }
                        // Match by external_url
                        if (!empty($movie->external_url) && strlen($movie->external_url) > 10) {
                            $query->orWhere('m.external_url', $movie->external_url);
                        }
                        // Match by url
                        if (!empty($movie->url) && strlen($movie->url) > 10) {
                            $query->orWhere('m.url', $movie->url);
                        }
                    })
                    ->pluck('id')
                    ->toArray();

                if (empty($duplicateIds)) {
                    // No duplicates found - mark as processed
                    if (!$dryRun) {
                        DB::table('movie_models')
                            ->where('id', $movie->id)
                            ->update(['actor' => '-']);
                    }

                    $stats['no_duplicates']++;
                    echo '<div class="success">✅ ID: ' . $movie->id . ' - "' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 60)) . '" - No duplicates</div>';
                } else {
                    // Duplicates found - get details for reporting
                    $duplicateDetails = DB::table('movie_models')
                        ->select('id', 'title', 'munowatch_id', 'external_url', 'url')
                        ->whereIn('id', $duplicateIds)
                        ->get();

                    // Categorize duplicates for statistics
                    foreach ($duplicateDetails as $dup) {
                        if (!empty($movie->title) && $dup->title === $movie->title) {
                            $stats['by_title']++;
                        }
                        if (!empty($movie->munowatch_id) && $dup->munowatch_id === $movie->munowatch_id) {
                            $stats['by_munowatch_id']++;
                        }
                        if (!empty($movie->external_url) && $dup->external_url === $movie->external_url) {
                            $stats['by_external_url']++;
                        }
                        if (!empty($movie->url) && $dup->url === $movie->url) {
                            $stats['by_url']++;
                        }
                    }

                    $stats['duplicates_found'] += count($duplicateIds);

                    if (!$dryRun) {
                        // Delete duplicates in a single query
                        $deleted = DB::table('movie_models')
                            ->whereIn('id', $duplicateIds)
                            ->delete();

                        $stats['duplicates_deleted'] += $deleted;

                        // Mark original as processed
                        DB::table('movie_models')
                            ->where('id', $movie->id)
                            ->update(['actor' => '-']);
                    }

                    echo '<div class="error">🗑️ ID: ' . $movie->id . ' - "' . htmlspecialchars(substr($movie->title ?? 'Untitled', 0, 60)) . '"';
                    echo '<br>&nbsp;&nbsp;&nbsp;&nbsp;Found ' . count($duplicateIds) . ' duplicate(s): [' . implode(', ', $duplicateIds) . ']';
                    echo ($dryRun ? ' <strong>(DRY RUN - Not deleted)</strong>' : ' <strong>✅ Deleted</strong>');
                    echo '</div>';
                }

                DB::commit();

                // Progress indicator every 50 movies
                if ($stats['total_processed'] % 50 === 0) {
                    $progress = ($stats['total_processed'] / $moviesToProcess->count()) * 100;
                    echo '<div class="progress"><div class="progress-bar" style="width: ' . $progress . '%">'
                        . round($progress, 1) . '% Complete</div></div>';
                    flush();
                }
            } catch (\Exception $e) {
                // DB::rollBack();
                $stats['errors']++;
                echo '<div class="error">❌ Error processing ID: ' . $movie->id . ' - ' . htmlspecialchars($e->getMessage()) . '</div>';
                Log::error('Duplicate processing error', [
                    'movie_id' => $movie->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Final Statistics
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $remainingCount = DB::table('movie_models')
            ->where('type', 'Movie')
            ->where('actor', '--')
            ->count();

        echo '<hr>';
        echo '<div class="stats">';
        echo '<h2>📊 Processing Complete</h2>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Processed:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['total_processed']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Movies with No Duplicates:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #28a745;">' . number_format($stats['no_duplicates']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Duplicates Found:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #dc3545;">' . number_format($stats['duplicates_found']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Duplicates Deleted:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd; color: #dc3545;">' . number_format($stats['duplicates_deleted']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Errors:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($stats['errors']) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Processing Time:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $duration . ' seconds</td></tr>';
        echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Remaining to Process:</strong></td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . number_format($remainingCount) . '</td></tr>';
        echo '</table>';

        echo '<h3>🔍 Duplicate Detection Breakdown:</h3>';
        echo '<ul>';
        echo '<li>By Title: <strong>' . number_format($stats['by_title']) . '</strong></li>';
        echo '<li>By Munowatch ID: <strong>' . number_format($stats['by_munowatch_id']) . '</strong></li>';
        echo '<li>By External URL: <strong>' . number_format($stats['by_external_url']) . '</strong></li>';
        echo '<li>By URL: <strong>' . number_format($stats['by_url']) . '</strong></li>';
        echo '</ul>';
        echo '</div>';

        if ($remainingCount > 0) {
            echo '<div class="info">';
            echo '<h3>🔄 Continue Processing</h3>';
            echo '<p>There are still ' . number_format($remainingCount) . ' movies to process.</p>';
            echo '<p><a href="' . $r->url() . '?limit=' . $limit . '" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">▶️ Process Next Batch</a></p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<h3>🎉 All Done!</h3>';
            echo '<p>All movies have been processed successfully.</p>';
            echo '</div>';
        }
    } catch (\Exception $e) {
        // DB::rollBack();
        echo '<div class="error">';
        echo '<h3>❌ Critical Error</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
        Log::error('Duplicate processing critical error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    echo '</body></html>';
});


Route::get('process-muno-series', function (Request $r) {

    $id = $r->get('id');
    $id = (int) $id;

    if ($id  > 0) {
        //process serises
        $pages = MovieCrawlerPage::where([
            'id' => $id,
            'is_muno' => 'Yes',
        ])
            ->orderBy('id', 'asc')
            ->limit(20)
            ->get();

        $pendMuno = $pages->first();
        if ($pendMuno == null) {
            die("No page found with ID: {$id}");
        }
        try {
            $pendMuno->process_munowatch_intelligent();
            $page_content = MovieCrawlerPage::find($pendMuno->id);
            if ($page_content == null) {
                echo "Failed to reload page content after processing.<br>";
                die();
            }

            //display $pendMuno
            echo "<pre>";
            print_r($page_content->toArray());
            echo "</pre>";

            if ($page_content->type == 'Series') {
                $series = SeriesMovie::find($page_content->series_id);
                if ($series == null) {
                    echo "Series not found for series_id: {$page_content->series_id}<br>";
                    die();
                } else {
                    echo "Series found: {$series->id} - {$series->title}. total episodes: {$series->total_episodes}<br>";
                    //display Thumbnail
                    echo '<img src="' . $series->thumbnail . '" /><br>';
                    die("Done processing series ID: {$pendMuno->id}");
                }
            }


            $movie = MovieModel::where('munowatch_id', $pendMuno->muno_id)->first();
            if ($movie == null) {
                echo "Movie not found for munowatch_id: {$pendMuno->muno_id}<br>";
                die();
            }
            echo "Page {$pendMuno->id}. {$movie->title} - successfully<hr>";
            //ttile
            echo "Title: {$movie->title}<br>";
            //thumbnail
            echo '<img src="' . $movie->thumbnail . '" /><br>';
            die("Done processing page ID: {$pendMuno->id}");
        } catch (\Throwable $th) {
            echo "Failed to process page {$pendMuno->id} because " . $th->getMessage() . "<br>";
            Log::error("Failed to process page {$pendMuno->id} because " . $th->getMessage());
            die();
        }
    } else {
        //process serises
        $pages = MovieCrawlerPage::where([
            'muno_series_processed' => 'No',
            'is_muno' => 'Yes',
            'status' => 'Pending',
        ])
            ->orderBy('id', 'asc')
            ->limit(20)
            ->get();
    }


    //set time limit
    set_time_limit(6000); // 10 minutes
    ini_set("memory_limit", "256M"); // 512 MB 

    foreach ($pages as $key => $page) {
        try {
            $page->process_munowatch_intelligent();
            echo "Page {$page->id} processed successfully.<br>";
            $newPage = MovieCrawlerPage::find($page->id);
            if ($newPage == null) {
                echo "Failed to reload page after processing {$page->id}.<br>";
                continue;
            }
            echo "<pre>";
            print_r($newPage->toArray());
            echo "</pre>";
            if ($newPage->type == 'Series') {
                $series = SeriesMovie::find($newPage->series_id);
                if ($series == null) {
                    echo "Series not found for series_id: {$newPage->series_id}<br>";
                    continue;
                } else {
                    echo "Series found: {$series->id} - {$series->title}. total episodes: {$series->total_episodes}<br>";
                    //display Thumbnail
                    echo '<img src="' . $series->thumbnail . '" /><br>';
                    continue;
                }
            } else {
                $movie = MovieModel::where('id', $page->movie_id)->first();
                if ($movie == null) {
                    echo "Movie not found for munowatch_id: {$page->muno_id}<br>";
                    continue;
                }
                echo "Movie found: {$movie->id} - {$movie->title}<br>";
                //display Thumbnail
                echo '<img src="' . $movie->thumbnail_url . '" /><br>';
                continue;
            }
        } catch (\Throwable $th) {
            throw $th;
            $page->muno_series_success = 'No';
            $page->save();
            echo "Failed to process page {$page->id} because " . $th->getMessage() . "<br>";
            Log::error("Failed to process page {$page->id} because " . $th->getMessage());
        }
    }
});
Route::get('send-trendings', function () {

    $now = Carbon::now();
    //time of day
    $hour = (int) $now->format('H');
    $day_time = '';
    if ($hour >= 5 && $hour < 12) {
        $day_time = 'morning';
    } elseif ($hour >= 12 && $hour < 17) {
        $day_time = 'afternoon';
    } elseif ($hour >= 17 && $hour < 21) {
        $day_time = 'evening';
    } else {
        $day_time = 'night';
    }



    TrendingNotification::getTrendingMovie();
    die("done");
});
Route::get('process-payments', function () {
    //set timer
    $startTime = microtime(true);
    set_time_limit(900); // 15 minutes
    $pendingPayments = SubscriptionTransaction::whereNotIn('status', ['Completed',])
        ->where('created_at', '>=', Carbon::now()->subHours(24 * 3)) // only check last 72 hours
        ->orderBy('id', 'desc')
        ->limit(30)
        ->get();
    //set time limit
    set_time_limit(900); // 15 minutes
    $pesapalService = new SubscriptionPesapalService();
    // $pendingPayments = SubscriptionTransaction::where('id', 82)->get();
    foreach ($pendingPayments as $key => $pay) {
        if ($pay->status == 'Completed') {
            continue;
        }
        $number_of_times_checked = (int) $pay->number_of_times_checked;
        if ($number_of_times_checked > 20) {
            //mark as failed
            $pay->status = 'Failed';
            $pay->refund_reason = 'Payment not completed after multiple checks.';
            $pay->save();
            continue;
        }
        try {
            $created_time_date = Carbon::parse($pay->created_at);
            $time_diff_in_hours = $created_time_date->diffInHours(Carbon::now());
            echo "DATE: {$created_time_date}, HOURS DIFF: {$time_diff_in_hours}<br>";
            $pay->check_payment_status();
            $statusColor = $pay->status == 'Completed' ? 'green' : ($pay->status == 'Failed' ? 'red' : 'orange');
            echo "<div style='padding: 10px; margin: 5px 0; border-left: 4px solid {$statusColor}; background-color: #f8f9fa;'>";
            echo "<strong>Payment #{$pay->id}</strong><br>";
            echo "Status: <span style='color: {$statusColor}; font-weight: bold;'>{$pay->status}</span><br>";
            echo "Times Checked: {$pay->number_of_times_checked}<br>";
            echo "</div>";
        } catch (\Throwable $th) {
            echo "<div style='padding: 10px; margin: 5px 0; border-left: 4px solid red; background-color: #fff3cd;'>";
            echo "<strong style='color: red;'>Error checking payment #{$pay->id}:</strong><br>";
            echo htmlspecialchars($th->getMessage());
            echo "</div>";
        }
    }
});


Route::get('fix-pics', function () {

    return;
    //set timer
    set_time_limit(300); // 5 minutes for extensive processing
    ini_set("memory_limit", "256M"); // 512 MB
    $movies = MovieModel::where([])
        ->orderBy('id', 'desc')
        ->limit(2000)
        ->get();

    foreach ($movies as $movie) {
        $page = MovieCrawlerPage::where('url', $movie->external_url)->first();

        if ($page == null) {
            echo "No crawler page found for movie {$movie->id} - {$movie->title}<br>";
            continue;
        }

        $data = json_decode($page->page_content);
        if ($data == null) {
            echo "No page content for movie {$movie->id} - {$movie->title}<br>";
            continue;
        }

        if (!isset($data->preview) || $data->preview == null) {
            echo "No preview data for movie {$movie->id} - {$movie->title}<br>";
            continue;
        }

        //if thumb is not the same as in the api, update it 
        if ($movie->thumbnail_url != $data->preview->thumbnail) {
            $movie->thumbnail_url = $data->preview->thumbnail;
            $movie->image_url = $data->preview->poster_url;
            $movie->save();
            echo "Updated movie {$movie->id} - {$movie->title}<br>";
        } else {
            echo "No update needed for movie {$movie->id} - {$movie->title}<br>";
            //curem img url
            //echo "PHOTO: {$movie->image_url}<br>";
            //echo '<img src="' . $movie->image_url . '" width="100" /><hr>';
        }
    }
});

Route::get('process-muno-movies', function () {
    //set time limit
    set_time_limit(600); // 10 minutes
    ini_set("memory_limit", "256M"); // 512 MB

    $latestMovies = MovieModel::where('muno_processed', 'No')
        ->where('id', '>', 21308)
        ->orderBy('id', 'asc')
        ->limit(40)
        ->get();
    //    $latestMovies = MovieModel::where('id', 21344)->get();
    foreach ($latestMovies as $movie) {
        // continue;
        try {
            MovieModel::process_munowatch($movie);
            // $movie->muno_success = 'Yes';
            // $movie->muno_processed = 'Yes';
            // $movie->save();
            echo "{$movie->id}. {$movie->title} - successfully<br>";
        } catch (\Throwable $th) {
            echo "Failed to process movie {$movie->id} because " . $th->getMessage() . "<br>";
            Log::error("Failed to process movie {$movie->id} because " . $th->getMessage());
        }
    }

    echo "<hr>All done<hr>";
    //now pages aboive 1899
    $crawlerPages = MovieCrawlerPage::where('status', 'success')
        ->where('muno_processed', 'No')
        ->where('id', '>', 1899)
        ->orderBy('id', 'asc')
        ->limit(30)
        ->get();

    foreach ($crawlerPages as $page) {
        try {
            $page->process_page_content(false);
            echo "Page {$page->id}. {$page->title} - successfully<br>";
        } catch (\Throwable $th) {
            throw $th;
            echo "Failed to process page {$page->id} because " . $th->getMessage() . "<br>";
            Log::error("Failed to process page {$page->id} because " . $th->getMessage());
        }
    }
});


Route::get('crawler', function (Request $request) {
    set_time_limit(300);
    ini_set("memory_limit", "256M");

    $startTime   = microtime(true);
    $timeBudget  = 240; // seconds — safely under MAMP's 300s FastCGI timeout
    $batchSize   = min((int) $request->get('batch', 20), 100);
    $userId      = $request->get('uid', '169464');
    $mode        = $request->get('mode', 'auto'); // auto | discover | process
    $autoChain   = (bool) $request->get('chain', 1);  // auto-redirect to next batch

    $stats = [
        'pages_discovered' => 0,
        'pages_skipped'    => 0,
        'pages_processed'  => 0,
        'movies_created'   => 0,
        'series_detected'  => 0,
        'fetch_errors'     => 0,
        'process_errors'   => 0,
        'time_limited'     => false,
    ];

    $munowatchWebsite = MovieCrawlerWebsite::where('slug', MovieCrawlerWebsite::MUNOWATCH)->first();
    if (!$munowatchWebsite) {
        return response()->json(['error' => 'Munowatch website not found'], 404);
    }

    // Count pending before starting
    $pendingBefore = DB::table('movie_crawler_pages')
        ->where('movie_crawler_website_id', $munowatchWebsite->id)
        ->where('status', 'pending')
        ->where('is_muno', 'Yes')
        ->count();

    // ── Decide what to do ──
    $doDiscover = ($mode === 'discover' || ($mode === 'auto' && $pendingBefore === 0));
    $doProcess  = ($mode === 'process' || $mode === 'auto');

    $baseToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
    $headers = [
        'Authorization' => 'Bearer ' . $baseToken,
        'X-Api-Key'     => $baseToken,
        'User-Agent'    => 'okhttp/4.9.0',
    ];

    $log = []; // structured log entries for output

    // ════════════════════════════════════════════
    // PHASE 1 — Discover new movies from dashboard
    // ════════════════════════════════════════════
    if ($doDiscover) {
        try {
            $dashboardUrl  = "https://munowatch.org/api/dashboard/v2/{$userId}";
            $dashboardJson = Utils::get_url_with_auth($dashboardUrl, $headers);
            $dashboard     = @json_decode($dashboardJson, true);

            if (!is_array($dashboard) || !isset($dashboard['dashboard'])) {
                throw new \Exception('Invalid dashboard response — missing "dashboard" key');
            }

            // Extract all unique movies by vid
            $allMovies = [];
            foreach ($dashboard['dashboard'] as $cat) {
                if (!isset($cat['movies']) || !is_array($cat['movies'])) continue;
                foreach ($cat['movies'] as $movie) {
                    $vid = $movie['vid'] ?? $movie['id'] ?? null;
                    if ($vid && !isset($allMovies[$vid])) {
                        $allMovies[$vid] = $movie;
                    }
                }
            }

            $log[] = ['type' => 'info', 'msg' => 'Dashboard: ' . count($allMovies) . ' unique movies across ' . count($dashboard['dashboard']) . ' categories'];

            // Find which vids already exist
            $existingVids = DB::table('movie_crawler_pages')
                ->where('movie_crawler_website_id', $munowatchWebsite->id)
                ->where('is_muno', 'Yes')
                ->whereIn('slug', array_map('strval', array_keys($allMovies)))
                ->pluck('slug')
                ->flip()
                ->toArray();

            $newPages = [];
            $now = now();
            foreach ($allMovies as $vid => $movieObj) {
                if (isset($existingVids[(string)$vid])) {
                    $stats['pages_skipped']++;
                    continue;
                }
                $newPages[] = [
                    'movie_crawler_website_id' => $munowatchWebsite->id,
                    'title'          => $movieObj['title'] ?? '',
                    'slug'           => (string) $vid,
                    'movie_id'       => null,
                    'url'            => "https://munowatch.org/api/preview/v2/{$vid}/{$userId}",
                    'status'         => 'pending',
                    'is_muno'        => 'Yes',
                    'is_generated'   => 'Yes',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
                $stats['pages_discovered']++;
            }

            if (!empty($newPages)) {
                foreach (array_chunk($newPages, 200) as $chunk) {
                    DB::table('movie_crawler_pages')->insert($chunk);
                }
                $log[] = ['type' => 'ok', 'msg' => 'Created ' . $stats['pages_discovered'] . ' new pages, skipped ' . $stats['pages_skipped'] . ' existing'];
            } else {
                $log[] = ['type' => 'info', 'msg' => 'No new movies — all ' . $stats['pages_skipped'] . ' already known'];
            }

            $munowatchWebsite->update([
                'last_fetched_at' => $now,
                'fetch_status'    => 'success',
                'error_message'   => null,
            ]);
        } catch (\Throwable $e) {
            $log[] = ['type' => 'err', 'msg' => 'Dashboard error: ' . $e->getMessage()];
            Log::error('Crawler discover failed', ['error' => $e->getMessage()]);
        }
    }

    // ════════════════════════════════════════════
    // PHASE 2 — Process pending pages (time-budgeted)
    // ════════════════════════════════════════════
    if ($doProcess) {
        $pendingPages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatchWebsite->id)
            ->where('status', 'pending')
            ->where('is_muno', 'Yes')
            ->orderBy('id', 'desc')
            ->limit($batchSize)
            ->get();

        if ($pendingPages->isEmpty()) {
            $log[] = ['type' => 'info', 'msg' => 'No pending pages to process'];
        }

        foreach ($pendingPages as $page) {
            // ── Time-budget check ──
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $timeBudget) {
                $stats['time_limited'] = true;
                $log[] = ['type' => 'warn', 'msg' => 'Time budget reached (' . round($elapsed, 1) . 's) — stopping to avoid timeout'];
                break;
            }

            try {
                // Fetch page content if missing
                if (empty($page->page_content) || strlen(trim($page->page_content)) < 10) {
                    $page->fetch_page_content(false);
                    $page->refresh();
                }

                if (empty($page->page_content) || strlen(trim($page->page_content)) < 10) {
                    $page->status = 'error';
                    $page->error_message = 'Empty API response';
                    $page->save();
                    $stats['fetch_errors']++;
                    $log[] = ['type' => 'err', 'msg' => '#' . $page->id . ' (' . $page->slug . ') — empty response'];
                    continue;
                }

                // Process content
                $page->process_munowatch_intelligent();
                $page->refresh();

                $stats['pages_processed']++;

                if ($page->status === 'error') {
                    $stats['process_errors']++;
                    $log[] = ['type' => 'warn', 'msg' => '#' . $page->id . ' — ' . substr($page->error_message ?? 'unknown', 0, 120)];
                } elseif ($page->type === 'Series') {
                    $stats['series_detected']++;
                    $log[] = ['type' => 'series', 'msg' => '#' . $page->id . ' — Series: ' . ($page->title ?: 'Untitled')];
                } else {
                    $stats['movies_created']++;
                    $thumb = null;
                    $movieRecord = MovieModel::where('external_url', $page->url)->first();
                    if ($movieRecord && !empty($movieRecord->thumbnail_url)) {
                        $thumb = $movieRecord->thumbnail_url;
                    }
                    $log[] = ['type' => 'ok', 'msg' => '#' . $page->id . ' — ' . ($page->title ?: 'Untitled'), 'thumb' => $thumb];
                }
            } catch (\Throwable $e) {
                $stats['process_errors']++;
                $page->status = 'error';
                $page->error_message = substr($e->getMessage(), 0, 500);
                $page->save();
                $log[] = ['type' => 'err', 'msg' => '#' . $page->id . ' exception: ' . substr($e->getMessage(), 0, 120)];
                Log::error('Crawler page error', ['page_id' => $page->id, 'error' => $e->getMessage()]);
            }
        }
    }

    // ════════════════════════════════════════════
    // PHASE 3 — Output
    // ════════════════════════════════════════════
    $duration = round(microtime(true) - $startTime, 2);

    $totalPending = DB::table('movie_crawler_pages')
        ->where('movie_crawler_website_id', $munowatchWebsite->id)
        ->where('status', 'pending')
        ->where('is_muno', 'Yes')
        ->count();

    $totalProcessed = DB::table('movie_crawler_pages')
        ->where('movie_crawler_website_id', $munowatchWebsite->id)
        ->where('is_muno', 'Yes')
        ->where('status', '!=', 'pending')
        ->count();

    // Auto-chain: redirect to next batch if there are more pending pages
    $nextUrl = url('crawler') . '?batch=' . $batchSize . '&uid=' . urlencode($userId) . '&mode=process&chain=1';
    $shouldChain = $autoChain && $totalPending > 0 && $stats['pages_processed'] > 0;

    // ── Build HTML ──
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Munowatch Crawler</title>';
    if ($shouldChain) {
        echo '<meta http-equiv="refresh" content="2;url=' . htmlspecialchars($nextUrl) . '">';
    }
    echo '<style>
        body{font-family:system-ui,sans-serif;margin:20px;background:#0f0f1e;color:#ccc}
        h1{color:#ff9800}h2{color:#eee;margin-top:24px}
        .card{background:#1a1a2e;padding:14px;margin:8px 0;border-radius:6px;border-left:4px solid #333}
        .ok{border-left-color:#4caf50;color:#a5d6a7}.err{border-left-color:#f44336;color:#ef9a9a}
        .warn{border-left-color:#ff9800;color:#ffe082}.info{border-left-color:#2196f3;color:#90caf9}
        .series{border-left-color:#9c27b0;color:#ce93d8}
        table{width:100%;border-collapse:collapse;margin:12px 0}
        td{padding:8px 12px;border-bottom:1px solid #2a2a3e}
        td:first-child{color:#999;width:200px}td:last-child{font-weight:600}
        .thumb{max-height:80px;border-radius:4px;margin:4px 6px 4px 0;vertical-align:middle}
        a{color:#ff9800}.progress{background:#1a1a2e;border-radius:4px;overflow:hidden;height:6px;margin:8px 0}
        .progress-bar{height:100%;background:linear-gradient(90deg,#ff9800,#4caf50);transition:width .3s}
        .controls{display:flex;gap:12px;flex-wrap:wrap;margin:16px 0}
        .btn{display:inline-block;padding:10px 20px;background:#ff9800;color:#000;border-radius:6px;text-decoration:none;font-weight:600}
        .btn:hover{background:#ffa726}.btn.stop{background:#f44336;color:#fff}
    </style></head><body>';
    echo '<h1>🎬 Munowatch Crawler</h1>';

    // Progress bar
    $total = $totalPending + $totalProcessed;
    $pct = $total > 0 ? round(($totalProcessed / $total) * 100) : 100;
    echo '<div class="progress"><div class="progress-bar" style="width:' . $pct . '%"></div></div>';
    echo '<div style="text-align:center;color:#666;font-size:13px">' . number_format($totalProcessed) . ' / ' . number_format($total) . ' processed (' . $pct . '%)</div>';

    // Log entries
    echo '<h2>Activity Log</h2>';
    foreach ($log as $entry) {
        $cls = $entry['type'];
        $thumb = '';
        if (!empty($entry['thumb'])) {
            $thumb = '<img class="thumb" src="' . htmlspecialchars($entry['thumb']) . '">';
        }
        $icon = match($entry['type']) {
            'ok'     => '🎬',
            'err'    => '❌',
            'warn'   => '⚠️',
            'series' => '📺',
            default  => 'ℹ️',
        };
        echo '<div class="card ' . $cls . '">' . $thumb . $icon . ' ' . htmlspecialchars($entry['msg']) . '</div>';
    }

    // Stats table
    echo '<h2>📊 Summary</h2><table>';
    echo '<tr><td>New pages discovered</td><td>' . $stats['pages_discovered'] . '</td></tr>';
    echo '<tr><td>Pages skipped (existing)</td><td>' . $stats['pages_skipped'] . '</td></tr>';
    echo '<tr><td>Pages processed this batch</td><td>' . $stats['pages_processed'] . '</td></tr>';
    echo '<tr><td>Movies created</td><td style="color:#4caf50">' . $stats['movies_created'] . '</td></tr>';
    echo '<tr><td>Series detected</td><td style="color:#9c27b0">' . $stats['series_detected'] . '</td></tr>';
    echo '<tr><td>Fetch errors</td><td style="color:#f44336">' . $stats['fetch_errors'] . '</td></tr>';
    echo '<tr><td>Process errors</td><td style="color:#f44336">' . $stats['process_errors'] . '</td></tr>';
    echo '<tr><td>Still pending</td><td>' . number_format($totalPending) . '</td></tr>';
    echo '<tr><td>Duration</td><td>' . $duration . 's</td></tr>';
    echo '</table>';

    // Controls
    echo '<div class="controls">';
    if ($totalPending > 0) {
        if ($shouldChain) {
            echo '<div class="card warn">⏳ Auto-continuing in 2s... (' . number_format($totalPending) . ' pending)</div>';
            $stopUrl = url('crawler') . '?batch=' . $batchSize . '&uid=' . urlencode($userId) . '&mode=process&chain=0';
            echo '<a class="btn stop" href="' . htmlspecialchars($stopUrl) . '">⏹ Stop auto-chain</a>';
        }
        echo '<a class="btn" href="' . htmlspecialchars($nextUrl) . '">▶️ Process next batch</a>';
    } else {
        echo '<div class="card ok">🎉 All caught up — no pending pages!</div>';
    }
    $discoverUrl = url('crawler') . '?mode=discover&uid=' . urlencode($userId);
    echo '<a class="btn" href="' . htmlspecialchars($discoverUrl) . '">🔍 Re-discover from dashboard</a>';
    echo '</div>';

    echo '</body></html>';
});


//munowatch-movies-crawler

/**
 * PRODUCTION MUNOWATCH SERIES CRAWLER ENDPOINT 🎬
 * 
 * Production endpoint for crawling munowatch series content.
 * Integrates with the existing 3-level crawler architecture.
 * Focuses specifically on series content from munowatch API.
 */
Route::get('munowatch-series-crawler', function () {
    set_time_limit(600); // 10 minutes
    ini_set("memory_limit", "256M"); // 512 MB

    try {
        // Get munowatch website configuration
        $munowatchWebsite = MovieCrawlerWebsite::where('slug', MovieCrawlerWebsite::MUNOWATCH)->first();
        if (!$munowatchWebsite || $munowatchWebsite->status !== 'Active') {
            throw new Exception('Munowatch website not configured or inactive');
        }

        echo "🚀 Starting Munowatch Series Crawler...<br>";
        echo "=====================================<br>";


        // Show current configuration
        $currentCategory = \App\Models\MunowatchMovieCategory::find($munowatchWebsite->current_munowatch_category_id);
        echo "📋 Current Category: " . ($currentCategory ? $currentCategory->category_name : 'Unknown') . "<br>";
        echo "📋 API Endpoint Type: " . ($currentCategory ? $currentCategory->api_endpoint_type : 'Unknown') . "<br><br>";

        // Step 1: Fetch pages (Level 1 - Website → Pages)
        echo "📥 Level 1: Fetching series pages...<br>";
        $munowatchWebsite->get_next_page_content();
        echo "✅ Pages fetched successfully<br><br>";

        // Step 2: Process page content (Level 2 - Pages → Content)  
        echo "🔍 Level 2: Processing page content...<br>";
        Utils::fetch_pages_content();
        echo "✅ Content processed successfully<br><br>";

        // Step 3: Report detailed results
        echo "📊 Crawler Results:<br>";
        echo "==================<br>";

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

        echo "Pending Pages: $pendingPages<br>";
        echo "Processed Pages: $successPages<br>";
        echo "New Series Created: $recentSeries<br>";
        echo "New Movies Created: $recentMovies<br>";
        echo "New Series Episodes: $recentSeriesEpisodes<br><br>";

        // Debug information if no series found
        if ($recentSeries == 0 && $recentSeriesEpisodes == 0 && $recentMovies > 0) {
            echo "⚠️  DEBUG INFO: Only movies detected, no series<br>";
            echo "💡 This may indicate:<br>";
            echo "   - Current category contains mostly movies<br>";
            echo "   - Series detection logic needs refinement<br>";
            echo "   - API response format has changed<br><br>";

            // Show sample of recent content
            $recentContent = \App\Models\MovieModel::where('created_at', '>=', Carbon::now()->subHour())
                ->orderBy('id', 'desc')
                ->limit(3)
                ->get(['id', 'title', 'type']);

            echo "📋 Recent Content Sample:<br>";
            foreach ($recentContent as $item) {
                echo "  - ID: {$item->id} | Type: {$item->type} | Title: " . substr($item->title, 0, 50) . "...\n";
            }
            echo "\n";
        }

        echo "🎯 Munowatch Series Crawler Completed Successfully!<br>";
    } catch (Exception $e) {
        throw $e;
        echo "❌ Error: " . $e->getMessage() . "<br>";
        Log::error('Munowatch series crawler failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

Route::get('my-migrate', function () {
    // Artisan::call('migrate');
    //do run laravel migration command
    Artisan::call('migrate', ['--force' => true]);
    //returning the output
    return Artisan::output();
});
Route::get('migrate', function () {
    // Artisan::call('migrate');
    //do run laravel migration command
    Artisan::call('migrate', ['--force' => true]);
    //returning the output
    return Artisan::output();
});

}); // End of processing.auth middleware group

// Basic Authentication Routes
Route::get('/login', function () {
    return redirect('/'); // Redirect to landing page with login form
})->name('login');

Route::post('/logout', function () {
    auth()->logout();
    return redirect('/');
})->name('logout');

// Dating crawler routes also protected
Route::middleware('processing.auth')->group(function () {

/*
|--------------------------------------------------------------------------
| Uganda Hot Girls Dating Site Crawler Routes
|--------------------------------------------------------------------------
*/

/**
 * Crawl Uganda Hot Girls pages to discover user profile URLs
 * 
 * This route triggers the page discovery process which:
 * - Crawls the homepage for profile links
 * - Extracts city page links
 * - Crawls each city page with pagination
 * - Stores discovered profile URLs in movie_crawler_pages table
 * 
 * Usage: GET /crawl-dating-pages
 */
Route::get('crawl-dating-pages', function () {
    set_time_limit(300);
    ini_set("memory_limit", "256M");

    try {
        // Get the Uganda Hot Girls website record
        $website = MovieCrawlerWebsite::where('slug', MovieCrawlerWebsite::UGANDAHOTGIRLS)->first();
        
        if (!$website) {
            return response()->json([
                'success' => false,
                'error' => 'Uganda Hot Girls website not found in database. Please run the seeder first: php artisan db:seed --class=UgandaHotGirlsCrawlerSeeder'
            ], 404);
        }

        echo "<h2>🚀 Starting Uganda Hot Girls Page Crawling</h2>";
        echo "<p><em>Processing cities in batches to optimize resources. Adjust limit with ?max_cities parameter (default: 5)</em></p>";
        echo "<hr>";
        flush();
        
        // Get max cities from request (default 5)
        $maxCities = request()->get('max_cities', 5);
        $maxCities = min($maxCities, 20); // Cap at 20 cities per run
        
        // Start crawling
        $stats = $website->crawl_ugandahotgirls_pages($maxCities);
        
        echo "<hr>";
        echo "<h2>✅ Crawling Complete!</h2>";
        echo "<h3>Statistics:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>Metric</th><th>Count</th></tr>";
        echo "<tr><td>Cities Processed</td><td><strong>" . ($stats['cities_processed'] ?? 0) . "</strong></td></tr>";
        echo "<tr><td>Pages Crawled</td><td>" . $stats['pages_crawled'] . "</td></tr>";
        echo "<tr><td>Profiles Discovered</td><td><strong style='color: blue;'>" . $stats['profiles_discovered'] . "</strong></td></tr>";
        echo "<tr><td>New Profiles</td><td><strong style='color: green;'>" . $stats['profiles_new'] . "</strong></td></tr>";
        echo "<tr><td>Duplicate Profiles</td><td>" . $stats['profiles_duplicate'] . "</td></tr>";
        echo "<tr><td>Errors</td><td><strong style='color: red;'>" . count($stats['errors']) . "</strong></td></tr>";
        echo "</table>";

        if (!empty($stats['errors'])) {
            echo "<h3>Errors:</h3>";
            echo "<ul>";
            foreach ($stats['errors'] as $error) {
                echo "<li style='color: red;'>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        }

        echo "<hr>";
        echo "<h3>Next Steps:</h3>";
        echo "<p>Visit <a href='/extract-dating-users' style='color: blue; text-decoration: underline;'>/extract-dating-users</a> to extract user details from discovered profiles</p>";

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);

    } catch (\Exception $e) {
        echo "<h2 style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

/**
 * Extract user details from discovered profile pages
 * 
 * This route processes the stored profile URLs and:
 * - Fetches each profile page content
 * - Extracts user details (name, age, location, phone, photos, etc.)
 * - Stores user data in the users table
 * - Prevents duplicates by checking phone number and URL
 * 
 * Usage: GET /extract-dating-users
 * Optional params:
 * - limit: Number of profiles to process (default: 10)
 * - page_id: Process specific page by ID
 */
Route::get('extract-dating-users', function (Request $request) {
    set_time_limit(300);
    ini_set("memory_limit", "256M");

    try {
        $limit = $request->get('limit', 10);
        $pageId = $request->get('page_id');

        // Get the Uganda Hot Girls website record
        $website = MovieCrawlerWebsite::where('slug', MovieCrawlerWebsite::UGANDAHOTGIRLS)->first();
        
        if (!$website) {
            return response()->json([
                'success' => false,
                'error' => 'Uganda Hot Girls website not found in database'
            ], 404);
        }

        echo "<h2>🔍 Starting User Profile Extraction</h2>";
        echo "<hr>";

        // Get pending profile pages
        $query = MovieCrawlerPage::where('movie_crawler_website_id', $website->id)
            ->where('type', 'User Profile');

        if ($pageId) {
            $query->where('id', $pageId);
            echo "<p>Processing specific page ID: {$pageId}</p>";
        } else {
            $query->whereIn('status', ['pending', 'error'])
                ->limit($limit);
            echo "<p>Processing up to {$limit} profiles...</p>";
        }

        $pages = $query->get();

        if ($pages->isEmpty()) {
            echo "<p style='color: orange;'>⚠️ No pending profiles found to process</p>";
            echo "<p>Run <a href='/crawl-dating-pages' style='color: blue;'>/crawl-dating-pages</a> first to discover profile URLs</p>";
            return;
        }

        echo "<p>Found {$pages->count()} profiles to process</p>";
        echo "<hr>";

        $stats = [
            'processed' => 0,
            'success' => 0,
            'duplicate' => 0,
            'errors' => 0,
            'error_messages' => []
        ];

        foreach ($pages as $page) {
            echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
            echo "<strong>Processing:</strong> " . htmlspecialchars($page->url) . "<br>";
            
            try {
                // Fetch page content if not already fetched
                if (empty($page->page_content)) {
                    echo "Fetching page content...<br>";
                    $page->fetch_page_content(false);
                    $page->refresh();
                }

                // Process the page
                echo "Extracting user data...<br>";
                $page->process_page_content();
                $page->refresh();

                $stats['processed']++;

                if ($page->status == 'success') {
                    $stats['success']++;
                    echo "<span style='color: green;'>✅ Success!</span><br>";
                } elseif ($page->status == 'duplicate') {
                    $stats['duplicate']++;
                    echo "<span style='color: orange;'>⚠️ Duplicate: " . htmlspecialchars($page->error_message) . "</span><br>";
                } else {
                    $stats['errors']++;
                    $stats['error_messages'][] = $page->error_message;
                    echo "<span style='color: red;'>❌ Error: " . htmlspecialchars($page->error_message) . "</span><br>";
                }

            } catch (\Exception $e) {
                $stats['errors']++;
                $stats['error_messages'][] = $e->getMessage();
                echo "<span style='color: red;'>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</span><br>";
            }

            echo "</div>";
        }

        echo "<hr>";
        echo "<h2>📊 Extraction Summary</h2>";
        echo "<ul>";
        echo "<li><strong>Total Processed:</strong> " . $stats['processed'] . "</li>";
        echo "<li><strong>Successful:</strong> <span style='color: green;'>" . $stats['success'] . "</span></li>";
        echo "<li><strong>Duplicates:</strong> <span style='color: orange;'>" . $stats['duplicate'] . "</span></li>";
        echo "<li><strong>Errors:</strong> <span style='color: red;'>" . $stats['errors'] . "</span></li>";
        echo "</ul>";

        if (!empty($stats['error_messages'])) {
            echo "<h3>Error Details:</h3>";
            echo "<ul>";
            foreach (array_unique($stats['error_messages']) as $error) {
                echo "<li style='color: red;'>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        }

        echo "<hr>";
        echo "<p>To process more profiles, visit <a href='/extract-dating-users?limit=50' style='color: blue;'>/extract-dating-users?limit=50</a></p>";

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);

    } catch (\Exception $e) {
        echo "<h2 style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

/**
 * Process a single user profile by page ID
 * 
 * This route processes a specific profile page:
 * - Fetches the profile page content
 * - Extracts user details
 * - Stores user data in the database
 * 
 * Usage: GET /process-dating-profile/{page_id}
 */
Route::get('process-dating-profile/{page_id}', function ($pageId) {
    set_time_limit(300);
    
    try {
        $page = MovieCrawlerPage::find($pageId);
        
        if (!$page) {
            echo "<h2 style='color: red;'>❌ Error: Profile page not found</h2>";
            return;
        }

        // Verify this is a dating profile
        if ($page->type !== 'User Profile') {
            echo "<h2 style='color: red;'>❌ Error: This page is not a user profile</h2>";
            return;
        }

        echo "<h2>🔍 Processing User Profile</h2>";
        echo "<hr>";
        echo "<p><strong>Page ID:</strong> {$page->id}</p>";
        echo "<p><strong>URL:</strong> " . htmlspecialchars($page->url) . "</p>";
        echo "<p><strong>Status Before:</strong> <span style='color: orange;'>{$page->status}</span></p>";
        echo "<hr>";

        // Fetch page content if not already fetched
        if (empty($page->page_content)) {
            echo "<p>📡 Fetching page content...</p>";
            flush();
            $page->fetch_page_content(false);
            $page->refresh();
            echo "<p>✅ Page content fetched</p>";
            flush();
        } else {
            echo "<p>✅ Page content already available</p>";
            flush();
        }

        // Process the page
        echo "<p>🔄 Extracting user data...</p>";
        flush();
        $page->process_page_content();
        $page->refresh();

        echo "<hr>";
        echo "<h3>📊 Processing Result</h3>";
        echo "<p><strong>Status After:</strong> ";
        
        if ($page->status == 'success') {
            echo "<span style='color: green; font-weight: bold;'>✅ SUCCESS</span></p>";
            echo "<p><strong>Message:</strong> User profile extracted successfully</p>";
            echo "<div style='padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin-top: 10px;'>";
            echo "<h4 style='color: #155724; margin: 0;'>✅ User Created Successfully!</h4>";
            echo "<p style='margin: 5px 0 0 0;'>The user profile has been extracted and saved to the database.</p>";
            echo "</div>";
        } elseif ($page->status == 'duplicate') {
            echo "<span style='color: orange; font-weight: bold;'>⚠️ DUPLICATE</span></p>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($page->error_message ?? 'User already exists') . "</p>";
            echo "<div style='padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; margin-top: 10px;'>";
            echo "<h4 style='color: #856404; margin: 0;'>⚠️ Duplicate Detected</h4>";
            echo "<p style='margin: 5px 0 0 0;'>This user profile already exists in the database.</p>";
            echo "</div>";
        } else {
            echo "<span style='color: red; font-weight: bold;'>❌ ERROR</span></p>";
            echo "<p><strong>Error Message:</strong> " . htmlspecialchars($page->error_message ?? 'Unknown error') . "</p>";
            echo "<div style='padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin-top: 10px;'>";
            echo "<h4 style='color: #721c24; margin: 0;'>❌ Processing Failed</h4>";
            echo "<p style='margin: 5px 0 0 0;'>" . htmlspecialchars($page->error_message ?? 'Unknown error') . "</p>";
            echo "</div>";
        }

        echo "<hr>";
        echo "<p><a href='javascript:window.close()' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Close Window</a> ";
        echo "<a href='/admin/movie-crawler-pages' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Back to List</a></p>";

    } catch (\Exception $e) {
        Log::error('Error processing dating profile', [
            'page_id' => $pageId,
            'error' => $e->getMessage()
        ]);

        echo "<h2 style='color: red;'>❌ Exception Error</h2>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<details style='margin-top: 20px;'>";
        echo "<summary style='cursor: pointer; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6;'>Show Trace</summary>";
        echo "<pre style='padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; overflow: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</details>";
    }
});

});

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


// REMOVED: Dangerous nuclear route that sets ALL movies to Inactive
// Route 'set-all-movies-to-no' has been disabled to prevent accidental mass deactivation

// SEO-optimized public pages
Route::get('/movies', [LandingController::class, 'movies'])->name('landing.movies');
Route::get('/series', [LandingController::class, 'series'])->name('landing.series');
Route::get('/movie/{id}', [LandingController::class, 'movieDetail'])->name('landing.movie.detail');
Route::get('/sitemap.xml', [LandingController::class, 'sitemap'])->name('landing.sitemap');

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

// App Download Landing Page
Route::get('/app', function () {
    return view('landing.app-download');
})->name('landing.app-download');

// Page visit tracking (landing page analytics)
Route::post('/api/track-visit', [\App\Http\Controllers\PageVisitController::class, 'store']);
Route::post('/api/track-event', [\App\Http\Controllers\PageVisitController::class, 'event']);

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
    return; //disable for now
    set_time_limit(300); // 5 minutes for extensive processing

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
    return; //disable for now
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
    return response()->json(['success' => true]);
    set_time_limit(300); // 5 minutes for URL testing

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
/**
 * IMPROVED NOTIFICATION ENDPOINT 📱
 * 
 * Enhanced notification system with proper rate limiting and user feedback.
 * Prevents spam notifications by implementing time-based controls and user preferences.
 */
Route::get('send-notifications', function (Request $request) {
    // Set proper execution limits
    set_time_limit(300); // 5 minutes max
    ini_set('memory_limit', '256M');

    $startTime = \Carbon\Carbon::now();
    $results = [
        'success' => false,
        'message' => '',
        'start_time' => $startTime->toISOString(),
        'trending_data' => null,
        'notification_results' => null,
        'statistics' => null,
        'errors' => []
    ];

    try {
        echo '<h1>🔔 UGFLIX Notification System</h1>';
        echo '<h2>📊 Trending Movie Notification Process</h2>';
        echo '<p><strong>Started:</strong> ' . $startTime->format('Y-m-d H:i:s') . '</p>';
        echo '<hr>';

        // Step 1: Get notification statistics before sending
        echo '<h3>📈 Pre-Send Statistics</h3>';
        $preStats = \App\Services\NotificationService::getNotificationStats();
        echo '<ul>';
        echo '<li><strong>Total Users:</strong> ' . $preStats['total_users'] . '</li>';
        echo '<li><strong>Users with Notifications Enabled:</strong> ' . $preStats['users_with_notifications_enabled'] . '</li>';
        echo '<li><strong>Users Already Notified Today:</strong> ' . $preStats['users_notified_today'] . '</li>';
        echo '<li><strong>Total Notifications Sent Today:</strong> ' . $preStats['total_notifications_sent_today'] . '</li>';
        echo '<li><strong>Users at Daily Limit:</strong> ' . $preStats['users_at_daily_limit'] . '</li>';
        echo '</ul>';
        echo '<hr>';

        // Step 2: Get trending movie
        echo '<h3>🎬 Finding Trending Movie</h3>';
        $trending = \App\Models\TrendingNotification::getTrendingMovie();

        if ($trending == null) {
            $results['success'] = false;
            $results['message'] = 'No trending movie found';
            $results['errors'][] = 'No suitable movie available for trending notification';

            echo '<div style="color: red; font-weight: bold;">❌ No trending movie found</div>';
            echo '<p>This could be because:</p>';
            echo '<ul>';
            echo '<li>No active movies in the database</li>';
            echo '<li>All movies have already been marked as trending</li>';
            echo '<li>No movies meet the minimum view time criteria</li>';
            echo '</ul>';

            \Illuminate\Support\Facades\Log::warning('No trending movie found for notification', [
                'timestamp' => $startTime->toISOString(),
                'statistics' => $preStats
            ]);

            return response()->json($results, 404);
        }

        $results['trending_data'] = [
            'id' => $trending->id,
            'title' => $trending->title,
            'type' => $trending->type,
            'url' => $trending->url,
            'thumbnail_url' => $trending->thumbnail_url,
            'views_count' => $trending->views_count,
            'views_time_count' => $trending->views_time_count
        ];

        echo '<div style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0;">';
        echo '<h4>✅ Trending Movie Found</h4>';
        echo '<p><strong>ID:</strong> ' . $trending->id . '</p>';
        echo '<p><strong>Title:</strong> ' . htmlspecialchars($trending->title) . '</p>';
        echo '<p><strong>Type:</strong> ' . $trending->type . '</p>';
        echo '<p><strong>Views:</strong> ' . number_format($trending->views_count ?? 0) . '</p>';
        echo '<p><strong>Watch Time:</strong> ' . gmdate("H:i:s", $trending->views_time_count ?? 0) . '</p>';
        echo '<p><strong>URL:</strong> <a href="' . htmlspecialchars($trending->url) . '" target="_blank">' . htmlspecialchars($trending->url) . '</a></p>';

        if ($trending->thumbnail_url) {
            echo '<p><strong>Thumbnail:</strong></p>';
            echo '<img src="' . htmlspecialchars($trending->thumbnail_url) . '" width="200" height="150" style="border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);" alt="Movie Thumbnail">';
        }
        echo '</div>';

        // Step 3: Get post-send statistics  
        echo '<h3>📊 Post-Process Statistics</h3>';
        $postStats = \App\Services\NotificationService::getNotificationStats();
        echo '<ul>';
        echo '<li><strong>Total Users:</strong> ' . $postStats['total_users'] . '</li>';
        echo '<li><strong>Users with Notifications Enabled:</strong> ' . $postStats['users_with_notifications_enabled'] . '</li>';
        echo '<li><strong>Users Notified Today:</strong> ' . $postStats['users_notified_today'] . '</li>';
        echo '<li><strong>Total Notifications Sent Today:</strong> ' . $postStats['total_notifications_sent_today'] . '</li>';
        echo '<li><strong>Users at Daily Limit:</strong> ' . $postStats['users_at_daily_limit'] . '</li>';
        echo '</ul>';

        $results['statistics'] = [
            'pre_send' => $preStats,
            'post_send' => $postStats,
            'notifications_sent_this_run' => $postStats['total_notifications_sent_today'] - $preStats['total_notifications_sent_today']
        ];

        // Step 4: Summary
        $endTime = \Carbon\Carbon::now();
        $duration = $startTime->diffInSeconds($endTime);

        echo '<hr>';
        echo '<h3>✅ Process Complete</h3>';
        echo '<div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 10px 0;">';
        echo '<p><strong>Duration:</strong> ' . $duration . ' seconds</p>';
        echo '<p><strong>Notifications Sent This Run:</strong> ' . $results['statistics']['notifications_sent_this_run'] . '</p>';
        echo '<p><strong>Movie:</strong> ' . htmlspecialchars($trending->title) . '</p>';
        echo '<p><strong>Status:</strong> Notification process completed successfully</p>';
        echo '</div>';

        $results['success'] = true;
        $results['message'] = 'Notification process completed successfully';
        $results['end_time'] = $endTime->toISOString();
        $results['duration_seconds'] = $duration;


        // Return JSON for API consumers
        if ($request->expectsJson()) {
            return response()->json($results);
        }
    } catch (\Throwable $th) {
        $endTime = \Carbon\Carbon::now();
        $duration = $startTime->diffInSeconds($endTime);

        $results['success'] = false;
        $results['message'] = 'Notification process failed: ' . $th->getMessage();
        $results['end_time'] = $endTime->toISOString();
        $results['duration_seconds'] = $duration;
        $results['errors'][] = $th->getMessage();

        echo '<hr>';
        echo '<div style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0;">';
        echo '<h3 style="color: #721c24;">❌ Error Occurred</h3>';
        echo '<p><strong>Error:</strong> ' . htmlspecialchars($th->getMessage()) . '</p>';
        echo '<p><strong>Duration:</strong> ' . $duration . ' seconds</p>';
        echo '<p><strong>Time:</strong> ' . $endTime->format('Y-m-d H:i:s') . '</p>';
        echo '</div>';

        \Illuminate\Support\Facades\Log::error('Trending notification process failed', [
            'error' => $th->getMessage(),
            'trace' => $th->getTraceAsString(),
            'duration_seconds' => $duration
        ]);

        if ($request->expectsJson()) {
            return response()->json($results, 500);
        }
    }
});

/**
 * NOTIFICATION STATISTICS ENDPOINT 📊
 * 
 * Get detailed statistics about the notification system for monitoring and debugging.
 */
Route::get('notification-stats', function (Request $request) {
    try {
        $stats = \App\Services\NotificationService::getNotificationStats();

        // Get current day time using a simple method since we can't make the method public easily
        $now = \Carbon\Carbon::now();
        if ($now->hour >= 6 && $now->hour < 12) {
            $currentDayTime = 'morning';
        } elseif ($now->hour >= 12 && $now->hour < 18) {
            $currentDayTime = 'afternoon';
        } elseif ($now->hour >= 18 && $now->hour < 24) {
            $currentDayTime = 'evening';
        } else {
            $currentDayTime = 'night';
        }

        // Get recent trending notifications
        $recentTrending = \App\Models\TrendingNotification::with('movie')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($trending) {
                return [
                    'id' => $trending->id,
                    'day_time' => $trending->day_time,
                    'title' => $trending->title,
                    'is_sent' => $trending->is_sent,
                    'sent_time' => $trending->sent_time,
                    'created_at' => $trending->created_at,
                    'movie_id' => $trending->movie_model_id
                ];
            });

        // Get users who received notifications today
        $today = \Carbon\Carbon::today();
        $notifiedUsers = \App\Models\User::whereDate('last_trending_notification_date', $today)
            ->where('trending_notifications_today', '>', 0)
            ->orderBy('last_trending_notification_sent', 'desc')
            ->limit(10)
            ->get(['id', 'email', 'name', 'last_trending_notification_sent', 'last_trending_notification_period', 'trending_notifications_today'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'last_notification_sent' => $user->last_trending_notification_sent,
                    'last_notification_period' => $user->last_trending_notification_period,
                    'notifications_today' => $user->trending_notifications_today
                ];
            });

        $response = [
            'success' => true,
            'current_time' => \Carbon\Carbon::now()->toISOString(),
            'current_day_time' => $currentDayTime,
            'statistics' => $stats,
            'recent_trending_notifications' => $recentTrending,
            'recent_notified_users' => $notifiedUsers,
            'notification_periods' => [
                'morning' => '06:00 - 11:59',
                'afternoon' => '12:00 - 17:59',
                'evening' => '18:00 - 23:59',
                'night' => '00:00 - 05:59'
            ]
        ];

        if ($request->expectsJson()) {
            return response()->json($response);
        }

        // HTML output for browser viewing
        echo '<h1>📊 UGFLIX Notification System Statistics</h1>';
        echo '<p><strong>Current Time:</strong> ' . \Carbon\Carbon::now()->format('Y-m-d H:i:s') . '</p>';
        echo '<p><strong>Current Period:</strong> ' . ucfirst($currentDayTime) . '</p>';
        echo '<hr>';

        echo '<h2>📈 System Statistics</h2>';
        echo '<ul>';
        foreach ($stats as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            echo '<li><strong>' . $label . ':</strong> ' . number_format($value) . '</li>';
        }
        echo '</ul>';

        echo '<h2>🎬 Recent Trending Notifications</h2>';
        echo '<table border="1" style="border-collapse: collapse; width: 100%; margin: 10px 0;">';
        echo '<tr style="background-color: #f8f9fa;">';
        echo '<th style="padding: 10px;">ID</th><th style="padding: 10px;">Period</th><th style="padding: 10px;">Title</th><th style="padding: 10px;">Sent</th><th style="padding: 10px;">Sent Time</th>';
        echo '</tr>';
        foreach ($recentTrending as $trending) {
            echo '<tr>';
            echo '<td style="padding: 8px;">' . $trending['id'] . '</td>';
            echo '<td style="padding: 8px;">' . ucfirst($trending['day_time']) . '</td>';
            echo '<td style="padding: 8px;">' . htmlspecialchars($trending['title'] ?? 'N/A') . '</td>';
            echo '<td style="padding: 8px;">' . ($trending['is_sent'] === 'Yes' ? '✅' : '❌') . '</td>';
            echo '<td style="padding: 8px;">' . ($trending['sent_time'] ? \Carbon\Carbon::parse($trending['sent_time'])->format('M j H:i') : 'Not sent') . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        echo '<h2>👥 Recently Notified Users</h2>';
        echo '<table border="1" style="border-collapse: collapse; width: 100%; margin: 10px 0;">';
        echo '<tr style="background-color: #f8f9fa;">';
        echo '<th style="padding: 10px;">Email</th><th style="padding: 10px;">Last Period</th><th style="padding: 10px;">Today Count</th><th style="padding: 10px;">Last Notification</th>';
        echo '</tr>';
        foreach ($notifiedUsers as $user) {
            echo '<tr>';
            echo '<td style="padding: 8px;">' . htmlspecialchars($user['email']) . '</td>';
            echo '<td style="padding: 8px;">' . ucfirst($user['last_notification_period'] ?? 'N/A') . '</td>';
            echo '<td style="padding: 8px;">' . $user['notifications_today'] . '</td>';
            echo '<td style="padding: 8px;">' . ($user['last_notification_sent'] ? \Carbon\Carbon::parse($user['last_notification_sent'])->format('M j H:i') : 'Never') . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        return;
    } catch (\Exception $e) {
        $error = [
            'success' => false,
            'error' => $e->getMessage()
        ];

        if ($request->expectsJson()) {
            return response()->json($error, 500);
        }

        echo '<h1 style="color: red;">❌ Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
});

/**
 * DEBUG NOTIFICATION SYSTEM 🐛
 * 
 * Debug the notification system step by step
 */
Route::get('debug-notifications', function (Request $request) {
    echo '<h1>🐛 Notification Debug</h1>';

    try {
        $now = \Carbon\Carbon::now();
        $dayTime = $now->hour >= 6 && $now->hour < 12 ? 'morning' : ($now->hour >= 12 && $now->hour < 18 ? 'afternoon' : ($now->hour >= 18 && $now->hour < 24 ? 'evening' : 'night'));

        echo "<p><strong>Current Time:</strong> {$now}</p>";
        echo "<p><strong>Day Time:</strong> {$dayTime}</p>";
        echo "<hr>";

        // Check if notification already sent for this period
        $existingTrending = \App\Models\TrendingNotification::whereDate('created_at', \Carbon\Carbon::today())
            ->where('day_time', $dayTime)
            ->where('is_sent', 'Yes')
            ->first();

        echo "<h3>Step 1: Check if already sent</h3>";
        echo "<p>Existing sent notification: " . ($existingTrending ? "YES (ID: {$existingTrending->id})" : "NO") . "</p>";

        if ($existingTrending) {
            echo "<p><strong>Movie:</strong> {$existingTrending->title}</p>";
            echo "<p><strong>Sent at:</strong> {$existingTrending->sent_time}</p>";
            echo "<hr>";
            echo "<h3>✅ Notification already sent for this period</h3>";
            return;
        }

        // Get or create trending record
        $trending = \App\Models\TrendingNotification::whereDate('created_at', \Carbon\Carbon::today())
            ->where('day_time', $dayTime)
            ->first();

        echo "<h3>Step 2: Get/Create trending record</h3>";
        echo "<p>Trending record exists: " . ($trending ? "YES (ID: {$trending->id})" : "NO") . "</p>";

        if (!$trending) {
            echo "<p>Creating new trending record...</p>";
            $trending = new \App\Models\TrendingNotification();
            $trending->day_time = $dayTime;
            $trending->created_at = $now;
            $trending->updated_at = $now;
            $trending->is_sent = 'No';
            $trending->save();
            echo "<p>Created with ID: {$trending->id}</p>";
        }

        echo "<p><strong>Movie assigned:</strong> " . ($trending->movie_model_id ? "YES (ID: {$trending->movie_model_id})" : "NO") . "</p>";
        echo "<p><strong>Is sent:</strong> {$trending->is_sent}</p>";

        // Test notification eligibility for a few users
        echo "<h3>Step 3: Check user eligibility</h3>";
        $users = \App\Models\User::limit(5)->get();

        foreach ($users as $user) {
            $canReceive = \App\Services\NotificationService::canReceiveTrendingNotification($user, $dayTime);
            echo "<p><strong>User {$user->id} ({$user->email}):</strong> " . ($canReceive ? "✅ ELIGIBLE" : "❌ NOT ELIGIBLE") . "</p>";

            if (!$canReceive) {
                // Get reason
                if ($user->push_notifications === 'No') {
                    echo "  - Reason: Push notifications disabled<br>";
                } elseif ($user->notification_preferences === 'No') {
                    echo "  - Reason: Notification preferences disabled<br>";
                } elseif ($user->trending_notifications_today >= ($user->max_trending_notifications_per_day ?? 4)) {
                    echo "  - Reason: Daily limit reached ({$user->trending_notifications_today}/{$user->max_trending_notifications_per_day})<br>";
                } elseif (
                    $user->last_trending_notification_period === $dayTime &&
                    $user->last_trending_notification_date &&
                    \Carbon\Carbon::parse($user->last_trending_notification_date)->isSameDay(\Carbon\Carbon::today())
                ) {
                    echo "  - Reason: Already notified this period<br>";
                } elseif ($user->last_trending_notification_sent) {
                    $lastTime = \Carbon\Carbon::parse($user->last_trending_notification_sent);
                    $hoursDiff = $lastTime->diffInHours(\Carbon\Carbon::now());
                    if ($hoursDiff < 3) {
                        echo "  - Reason: Minimum time gap not met ({$hoursDiff} hours < 3)<br>";
                    }
                }
            }
        }

        echo "<hr>";
        echo "<h3>Step 4: Statistics</h3>";
        $stats = \App\Services\NotificationService::getNotificationStats();
        foreach ($stats as $key => $value) {
            echo "<p><strong>" . ucwords(str_replace('_', ' ', $key)) . ":</strong> {$value}</p>";
        }
    } catch (\Exception $e) {
        echo "<h3 style='color: red;'>❌ Error: {$e->getMessage()}</h3>";
        echo "<pre>{$e->getTraceAsString()}</pre>";
    }
});

/**
 * TEST SINGLE NOTIFICATION 🧪
 * 
 * Send notification to just one user for testing
 */
Route::get('test-single-notification', function (Request $request) {
    try {
        echo '<h1>🧪 Test Single Notification</h1>';

        // Get first user
        $user = \App\Models\User::first();
        if (!$user) {
            echo '<p style="color: red;">❌ No users found</p>';
            return;
        }

        echo "<p><strong>Test User:</strong> {$user->email}</p>";

        $dayTime = 'night'; // Current time

        // Check eligibility
        $canReceive = \App\Services\NotificationService::canReceiveTrendingNotification($user, $dayTime);
        echo "<p><strong>Can Receive:</strong> " . ($canReceive ? "✅ YES" : "❌ NO") . "</p>";

        if (!$canReceive) {
            echo '<p style="color: orange;">User is not eligible for notifications right now.</p>';
            return;
        }

        // Create test notification data
        $notificationData = [
            'title' => 'TEST: UGFLIX Night Trending Movie - Test Movie',
            'body' => 'This is a test notification from the improved notification system.',
            'image' => 'https://katogo.schooldynamics.ug/logo.png',
            'url' => 'https://katogo.schooldynamics.ug',
            'type' => 'Movie',
            'movie_id' => 12345,
            'is_trending' => 'Yes',
            'data' => [
                'movie_id' => 12345,
                'is_trending' => 'Yes',
                'type' => 'Movie',
                'url' => 'https://katogo.schooldynamics.ug',
                'image_url' => 'https://katogo.schooldynamics.ug/logo.png',
            ],
        ];

        echo '<h3>📤 Sending Test Notification</h3>';

        try {
            // Send notification to single user
            \App\Models\Utils::sendNotificationToUser($user, $notificationData);

            // Update user tracking manually for testing
            \Illuminate\Support\Facades\DB::table('admin_users')
                ->where('id', $user->id)
                ->update([
                    'last_trending_notification_sent' => \Carbon\Carbon::now(),
                    'last_trending_notification_period' => $dayTime,
                    'last_trending_notification_date' => \Carbon\Carbon::today()->format('Y-m-d'),
                    'trending_notifications_today' => \Illuminate\Support\Facades\DB::raw('COALESCE(trending_notifications_today, 0) + 1'),
                    'updated_at' => \Carbon\Carbon::now()
                ]);

            echo '<div style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0;">';
            echo '<h4>✅ Test Notification Sent Successfully</h4>';
            echo '<p><strong>User:</strong> ' . $user->email . '</p>';
            echo '<p><strong>Time:</strong> ' . \Carbon\Carbon::now()->format('Y-m-d H:i:s') . '</p>';
            echo '<p><strong>Period:</strong> ' . $dayTime . '</p>';
            echo '</div>';
        } catch (\Exception $e) {
            echo '<div style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0;">';
            echo '<h4 style="color: #721c24;">❌ Test Failed</h4>';
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
    } catch (\Exception $e) {
        echo '<h3 style="color: red;">❌ Test Error: ' . htmlspecialchars($e->getMessage()) . '</h3>';
    }
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
    ini_set("memory_limit", "256M");
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

    // ===== CHECK IF THIS IS A MUNOWATCH SERIES =====
    if ($series->is_muno !== 'Yes' || !$series->external_url || strpos($series->external_url, 'munowatch.org') === false) {
        echo '<h1>🎬 MUNOWATCH SERIES EPISODE FIXER 🎬</h1>';
        echo '<h2>❌ ERROR: Not a Munowatch Series</h2>';
        echo '<p><strong>Series:</strong> ' . htmlspecialchars($series->title) . '</p>';
        echo '<p><strong>Series ID:</strong> ' . $series->id . '</p>';
        echo '<p><strong>External URL:</strong> ' . htmlspecialchars($series->external_url) . '</p>';
        echo '<p><strong>Is Muno:</strong> ' . ($series->is_muno ?? 'NULL') . '</p>';
        echo '<p><strong>Munowatch ID:</strong> ' . ($series->munowatch_id ?? 'NULL') . '</p>';
        echo '<hr>';
        echo '<p style="color: red; font-weight: bold;">This fixer only works with series that have:</p>';
        echo '<ul>';
        echo '<li>is_muno = "Yes"</li>';
        echo '<li>external_url containing "munowatch.org"</li>';
        echo '<li>Proper munowatch API URL format</li>';
        echo '</ul>';
        echo '<p><strong>Suggestion:</strong> Use the regular series processor for non-munowatch content.</p>';
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

        // Fetch movie/show details using Flutter app pattern: preview/v2/{userId}/{videoId}
        $previewUrl = "https://munowatch.org/api/preview/v2/{$userId}/{$videoId}";
        echo '<p>📡 Preview API URL: ' . $previewUrl . '</p>';

        try {
            // Use MunowatchAuthService for automatic token refresh
            $apiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
            $xApiKey = 'Api-munowatch-2024';

            echo '<p>🔑 Using automatic token refresh authentication...</p>';

            $apiResponse = \App\Services\MunowatchAuthService::callApiWithAutoRefresh(
                $previewUrl,
                $apiKey,
                $xApiKey,
                'GET',
                [],
                3
            );

            $httpCode = 200; // If we get here, the call was successful
            echo '<p>✅ API call successful with auto-refresh support</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ Preview API request failed with auto-refresh: ' . $e->getMessage() . '</p>';
            $apiResponse = false;
            $httpCode = 0;
        }

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

        try {
            echo '<p>🔑 Fetching episodes with auto-refresh authentication...</p>';

            $episodesResponse = \App\Services\MunowatchAuthService::callApiWithAutoRefresh(
                $episodesUrl,
                $apiKey,
                $xApiKey,
                'GET',
                [],
                3
            );

            $episodesHttpCode = 200; // If we get here, the call was successful
            echo '<p>✅ Episodes API call successful</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ Episodes API request failed with auto-refresh: ' . $e->getMessage() . '</p>';
            $episodesResponse = false;
            $episodesHttpCode = 0;
        }

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
            echo '<p>📼 Range: ' . htmlspecialchars($rangeData['eps'] ?? 'Unknown') . ' (' . htmlspecialchars($rangeData['eps_range'] ?? 'No range') . ')</p>';

            // Parse episode range following Flutter app EpisodeRange.expand() logic
            $epsRange = $rangeData['eps_range'] ?? '';
            $rangeEpisodes = [];

            if (!empty($epsRange)) {
                // Parse range like "1-10" or "1,2,3" or single number "5"
                if (strpos($epsRange, '-') !== false) {
                    // Handle range like "1-10"
                    $parts = explode('-', $epsRange);
                    if (count($parts) === 2) {
                        $start = (int)trim($parts[0]);
                        $end = (int)trim($parts[1]);
                        for ($i = $start; $i <= $end; $i++) {
                            $rangeEpisodes[] = $i;
                        }
                    }
                } elseif (strpos($epsRange, ',') !== false) {
                    // Handle comma-separated like "1,2,3"
                    $parts = explode(',', $epsRange);
                    foreach ($parts as $part) {
                        $episodeNum = (int)trim($part);
                        if ($episodeNum > 0) {
                            $rangeEpisodes[] = $episodeNum;
                        }
                    }
                } else {
                    // Single episode number
                    $episodeNum = (int)trim($epsRange);
                    if ($episodeNum > 0) {
                        $rangeEpisodes[] = $episodeNum;
                    }
                }
            }

            echo '<p>→ Expanded to episodes: ' . implode(', ', $rangeEpisodes) . '</p>';

            // Create episode data for each number in the range
            foreach ($rangeEpisodes as $episodeNumber) {
                $episodes[] = [
                    'number' => $episodeNumber,
                    'title' => ($rangeData['eps'] ?? 'Episode') . ' ' . $episodeNumber,
                    'description' => $rangeData['description'] ?? $movieDetail['description'] ?? '',
                    'thumbnail' => $rangeData['thumbnail'] ?? $movieDetail['image_url'] ?? '',
                    'duration' => $rangeData['duration'] ?? $movieDetail['duration'] ?? '',
                    'playing_url' => $rangeData['playing_url'] ?? '',
                    'embed_url' => $rangeData['embed_url'] ?? '',
                    'openload_url' => $rangeData['openload_url'] ?? '',
                    'stream_url' => $rangeData['stream_url'] ?? '',
                    'range_data' => $rangeData // Keep original range data for reference
                ];
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
            $episodeNumber = $index + 1; // 1-based numbering

            try {
                echo '<div style="margin: 10px 0; padding: 10px; border: 1px solid #ddd;">';
                echo '<h4>Episode ' . $episodeNumber . ': ' . htmlspecialchars($episodeData['name'] ?? 'Unknown') . '</h4>';

                // Extract episode URLs using our munowatch pattern
                $episodeId = $episodeData['id'] ?? '';
                $episodeTitle = $episodeData['name'] ?? 'Episode ' . $episodeNumber;
                $episodeDescription = $episodeData['description'] ?? $series->description;

                // Get video URLs from episode data
                $episodePlayingUrl = $episodeData['playing_url'] ?? '';
                $episodeEmbedUrl = $episodeData['embed_url'] ?? '';
                $episodeOpenloadUrl = $episodeData['openload_url'] ?? '';
                $episodeStreamUrl = $episodeData['stream_url'] ?? '';

                // Determine primary video URL
                $primaryEpisodeUrl = '';
                if (!empty($episodePlayingUrl)) {
                    $primaryEpisodeUrl = $episodePlayingUrl;
                } elseif (!empty($episodeEmbedUrl)) {
                    $primaryEpisodeUrl = $episodeEmbedUrl;
                } elseif (!empty($episodeOpenloadUrl)) {
                    $primaryEpisodeUrl = $episodeOpenloadUrl;
                } elseif (!empty($episodeStreamUrl)) {
                    $primaryEpisodeUrl = $episodeStreamUrl;
                }

                if (empty($primaryEpisodeUrl)) {
                    echo '<p style="color: orange;">⚠️ No video URL found - will create crawler page for individual fetching</p>';
                    //DUMP IT
                    echo '<div style="background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;">';
                    echo '<h4>Debug Info:</h4>';
                    echo '<pre>' . htmlspecialchars(print_r($episodeData, true)) . '</pre>';
                    echo '<p><strong>Episode Number:</strong> ' . $episodeNumber . '</p>';
                    echo '<p><strong>Episode Title:</strong> ' . htmlspecialchars($episodeTitle) . '</p>';
                    echo '<p><strong>Episode Description:</strong> ' . htmlspecialchars($episodeDescription) . '</p>';
                    echo '<p><strong>Episode ID:</strong> ' . htmlspecialchars($episodeId) . '</p>';
                    echo '<p><strong>All URLs:</strong></p>';
                    echo '<ul>';
                    echo '<li>Playing URL: ' . htmlspecialchars($episodePlayingUrl) . '</li>';
                    echo '<li>Embed URL: ' . htmlspecialchars($episodeEmbedUrl) . '</li>';
                    echo '<li>Openload URL: ' . htmlspecialchars($episodeOpenloadUrl) . '</li>';
                    echo '<li>Stream URL: ' . htmlspecialchars($episodeStreamUrl) . '</li>';
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<p>🎬 Video URL: ' . htmlspecialchars($primaryEpisodeUrl) . '</p>';
                }

                // Check for existing episode as MovieCrawlerPage
                $existingEpisodePage = \App\Models\MovieCrawlerPage::where('url', 'like', "%preview/v2/{$episodeData['number']}/%")
                    ->where('type', 'Series')
                    ->first();

                if (!$existingEpisodePage) {
                    // Try by munowatch_id if number is available
                    $episodeNumber = $episodeData['number'] ?? '';
                    if (!empty($episodeNumber)) {
                        $existingEpisodePage = \App\Models\MovieCrawlerPage::where('munowatch_id', $episodeNumber)
                            ->where('type', 'Series')
                            ->first();
                    }
                }

                $isNew = ($existingEpisodePage === null);

                if ($isNew) {
                    $episodePage = new \App\Models\MovieCrawlerPage();
                    echo '<p style="color: green;">✅ Creating new episode crawler page</p>';
                } else {
                    $episodePage = $existingEpisodePage;
                    echo '<p style="color: blue;">🔄 Updating existing episode page (ID: ' . $episodePage->id . ')</p>';
                }

                // Create proper munowatch episode URL using the episode number
                $episodeVideoId = $episodeData['number'] ?? '';
                $episodeUrl = "https://munowatch.org/api/preview/v2/{$episodeVideoId}/{$videoId}";

                // Get the munowatch website for proper relationship
                $munowatchWebsite = \App\Models\MovieCrawlerWebsite::where('slug', 'munowatch')->first();
                if (!$munowatchWebsite) {
                    throw new \Exception('Munowatch website not found in crawler websites');
                }

                // Set episode page data following munowatch pattern
                $episodePage->url = $episodeUrl;
                $episodePage->movie_crawler_website_id = $munowatchWebsite->id;
                $episodePage->title = $series->title . ' - Episode ' . $episodeNumber;
                $episodePage->status = 'pending'; // Will be processed later
                $episodePage->slug = (string)$episodeVideoId;
                $episodePage->type = 'Series'; // Mark as Series episode

                // Set series relationship
                $episodePage->series_id = $series->id;
                $episodePage->row_id = $episodeVideoId;

                // Set munowatch identification fields
                $episodePage->is_muno = 'Yes';
                $episodePage->muno_processed = 'No';
                $episodePage->munowatch_id = $episodeVideoId;

                // Set VJ information
                $episodePage->vj = 'Munowatch API';

                // Additional metadata
                $episodePage->notes = "Episode {$episodeNumber} of series: {$series->title}";

                // Save episode page
                $episodePage->save();

                echo '<p>✅ Episode crawler page saved successfully (ID: ' . $episodePage->id . ')</p>';
                echo '<p>🔗 Episode URL: ' . $episodeUrl . '</p>';
                echo '<p>📺 Series ID: ' . $series->id . '</p>';
                echo '<p>🎬 Munowatch ID: ' . $episodeVideoId . '</p>';

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

        // Count actual episode pages created
        $finalEpisodeCount = \App\Models\MovieCrawlerPage::where('series_id', $series->id)
            ->where('type', 'Series')
            ->where('is_muno', 'Yes')
            ->count();

        // Update series with episode count
        $series->total_episodes = $finalEpisodeCount;
        $series->is_active = 'Yes';
        $series->description .= " - Episode pages created on " . date('Y-m-d H:i:s');
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
        echo '<h3>🎬 Episode Pages Created:</h3>';

        $createdEpisodePages = \App\Models\MovieCrawlerPage::where('series_id', $series->id)
            ->where('type', 'Series')
            ->where('is_muno', 'Yes')
            ->orderBy('created_at', 'asc')
            ->get();

        echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
        echo '<tr><th>Page ID</th><th>Title</th><th>Status</th><th>Munowatch ID</th><th>Episode URL</th></tr>';

        foreach ($createdEpisodePages as $ep) {
            echo '<tr>';
            echo '<td>' . $ep->id . '</td>';
            echo '<td>' . htmlspecialchars($ep->title) . '</td>';
            echo '<td>' . ucfirst($ep->status) . '</td>';
            echo '<td>' . htmlspecialchars($ep->munowatch_id ?? 'N/A') . '</td>';
            echo '<td><a href="' . htmlspecialchars($ep->url) . '" target="_blank">View API</a></td>';
            echo '</tr>';
        }

        echo '</table>';

        echo '<hr>';
        echo '<div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        echo '<h3>✅ Munowatch Series Episode Pages Created Successfully!</h3>';
        echo '<p>All episode pages have been created as MovieCrawlerPage records and are ready for individual processing.</p>';
        echo '<p><strong>Next Steps:</strong></p>';
        echo '<ul>';
        echo '<li>Episode pages are marked as "pending" and will be processed by the main crawler</li>';
        echo '<li>Each page has the correct munowatch API URL for individual fetching</li>';
        echo '<li>Pages are marked with is_muno="Yes" and proper series relationships</li>';
        echo '</ul>';
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
    ini_set("memory_limit", "256M");
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
    ini_set("memory_limit", "256M");

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
    ini_set("memory_limit", "256M");

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
    ini_set("memory_limit", "256M");
    //increase the execution time
    ini_set('max_execution_time', -1);
    //increase the time limit
    set_time_limit(300);
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
    return;
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
    return;
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

// ============================================
// GAME MODULE SEEDER ENDPOINT
// ============================================
// Visit this URL ONCE to add the Game Module menu items to admin panel
// Safe to visit multiple times - will skip if already seeded
Route::get('/run-game-menu-seeder', function () {
    try {
        // Check if already seeded
        $existingParent = DB::table('admin_menu')
            ->where('title', 'Game Module')
            ->first();

        if ($existingParent) {
            return response()->json([
                'success' => true,
                'message' => 'Game Module menu already exists. No action needed.',
                'already_seeded' => true,
                'parent_menu_id' => $existingParent->id,
                'admin_urls' => [
                    'dashboard' => url('/admin/game-dashboard'),
                    'matatu_sessions' => url('/admin/game-sessions'),
                    'ludo_sessions' => url('/admin/ludo-sessions'),
                    'invitations' => url('/admin/game-invitations'),
                    'coin_transactions' => url('/admin/coin-transactions'),
                ],
            ]);
        }

        // Get the maximum order value to place our menu at the end
        $maxOrder = DB::table('admin_menu')->max('order') ?? 0;
        $baseOrder = $maxOrder + 1;

        // Create parent menu item: Game Module
        $parentId = DB::table('admin_menu')->insertGetId([
            'parent_id' => 0,
            'order' => $baseOrder,
            'title' => 'Game Module',
            'icon' => 'fa-gamepad',
            'uri' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create child menu items
        $menuItems = [
            ['title' => '📊 Dashboard', 'icon' => 'fa-dashboard', 'uri' => 'game-dashboard', 'order' => 1],
            ['title' => '🃏 Matatu Sessions', 'icon' => 'fa-th', 'uri' => 'game-sessions', 'order' => 2],
            ['title' => '🎲 Ludo Sessions', 'icon' => 'fa-circle-o', 'uri' => 'ludo-sessions', 'order' => 3],
            ['title' => '📨 Invitations', 'icon' => 'fa-envelope', 'uri' => 'game-invitations', 'order' => 4],
            ['title' => '🪙 Coin Transactions', 'icon' => 'fa-bitcoin', 'uri' => 'coin-transactions', 'order' => 5],
        ];

        $createdMenus = [];
        foreach ($menuItems as $item) {
            $menuId = DB::table('admin_menu')->insertGetId([
                'parent_id' => $parentId,
                'order' => $item['order'],
                'title' => $item['title'],
                'icon' => $item['icon'],
                'uri' => $item['uri'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $createdMenus[] = [
                'id' => $menuId,
                'title' => $item['title'],
                'uri' => $item['uri'],
            ];
        }

        // Clear the admin menu cache
        try {
            if (class_exists('\Encore\Admin\Auth\Database\Menu')) {
                \Encore\Admin\Auth\Database\Menu::flushCache();
            }
        } catch (\Exception $e) {
            // Cache clearing is optional
        }

        Log::info("Game Menu Seeder executed via web endpoint. Parent ID: {$parentId}");

        return response()->json([
            'success' => true,
            'message' => '🎉 Game Module menu created successfully!',
            'already_seeded' => false,
            'parent_menu_id' => $parentId,
            'created_menus' => $createdMenus,
            'admin_urls' => [
                'dashboard' => url('/admin/game-dashboard'),
                'matatu_sessions' => url('/admin/game-sessions'),
                'ludo_sessions' => url('/admin/ludo-sessions'),
                'invitations' => url('/admin/game-invitations'),
                'coin_transactions' => url('/admin/coin-transactions'),
            ],
            'instructions' => [
                '1. Go to your admin panel',
                '2. You should see "Game Module" in the sidebar menu',
                '3. Click to expand and access all game management features',
                '4. Visit /admin/game-dashboard for the full overview',
            ],
        ]);

    } catch (\Exception $e) {
        Log::error("Game Menu Seeder failed: " . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

// ──────────────────────────────────────────────────────────────────────────
// Health check endpoint — returns DB connection status + response time.
// Used by UptimeRobot or any monitoring tool (P13-02).
// Returns 200 OK when healthy, 503 when DB is unreachable.
// ──────────────────────────────────────────────────────────────────────────
Route::get('/health', function () {
    $start = microtime(true);
    $dbOk = false;
    $cacheOk = false;

    try { DB::statement('SELECT 1'); $dbOk = true; } catch (\Throwable $e) {}
    try {
        \Illuminate\Support\Facades\Cache::put('_hc', 'ok', 10);
        $cacheOk = \Illuminate\Support\Facades\Cache::get('_hc') === 'ok';
    } catch (\Throwable $e) {}

    $healthy = $dbOk && $cacheOk;
    return response()->json([
        'status'      => $healthy ? 'ok' : 'degraded',
        'db'          => $dbOk ? 'ok' : 'fail',
        'cache'       => $cacheOk ? 'ok' : 'fail',
        'response_ms' => round((microtime(true) - $start) * 1000, 1),
        'timestamp'   => now()->toISOString(),
    ], $healthy ? 200 : 503);
});
