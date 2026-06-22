<?php

namespace App\Models;

use App\Models\Utils;
use App\Services\MunowatchAuthService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * MunowatchMovieCategory Model
 * 
 * Manages dynamic movie categories fetched from munowatch dashboard API.
 * Unlike static categories, these are fetched from the dashboard endpoint first,
 * then used to fetch movies for each category using their specific IDs.
 * 
 * Process:
 * 1. Fetch dashboard (dashboard/v2/{userId}) to get dynamic categories
 * 2. Store each category with its ID and metadata
 * 3. Use category IDs to fetch movies from browse/{categoryId} or list endpoints
 */
class MunowatchMovieCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'munowatch_category_id',
        'category_name',
        'category_description',
        'api_endpoint_type',
        'api_parameters',
        'status',
        'is_dynamic',
        'sort_order',
        'total_movies_in_category',
        'movies_fetched',
        'last_fetched_from_dashboard_at',
        'last_movies_fetched_at',
        'sample_movies',
        'category_metadata',
        'has_pagination',
        'pagination_endpoint',
        'current_page',
        'max_pages',
        'last_error_message',
        'next_fetch_at',
        'fetch_frequency_hours'
    ];

    protected $casts = [
        'api_parameters' => 'array',
        'sample_movies' => 'array',
        'category_metadata' => 'array',
        'is_dynamic' => 'boolean',
        'has_pagination' => 'boolean',
        'last_fetched_from_dashboard_at' => 'datetime',
        'last_movies_fetched_at' => 'datetime',
        'next_fetch_at' => 'datetime'
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_FETCHING = 'fetching';
    const STATUS_FAILED = 'failed';

    // API endpoint types
    const ENDPOINT_DASHBOARD = 'dashboard';
    const ENDPOINT_BROWSE = 'browse';
    const ENDPOINT_SHOWS = 'shows';
    const ENDPOINT_LIST = 'list';

    /**
     * Scope: Get active categories only
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Get dynamic categories (fetched from dashboard)
     */
    public function scopeDynamic($query)
    {
        return $query->where('is_dynamic', true);
    }

    /**
     * Scope: Categories ready for movie fetching
     */
    public function scopeReadyForFetching($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                    ->where(function($q) {
                        $q->whereNull('next_fetch_at')
                          ->orWhere('next_fetch_at', '<=', Carbon::now());
                    });
    }

    /**
     * Scope: Recently updated categories (from dashboard)
     */
    public function scopeRecentlyFetched($query, $hours = 24)
    {
        return $query->where('last_fetched_from_dashboard_at', '>=', Carbon::now()->subHours($hours));
    }

    /**
     * Fetch categories from munowatch dashboard API
     * 
     * This method calls the dashboard/v2/{userId} endpoint to get dynamic categories
     * and updates the database with current category information.
     */
    public static function fetchCategoriesFromDashboard($userId = null)
    {
        try {
            // Always use the active session user_id — never the hardcoded stale ID
            if (empty($userId)) {
                $session  = MunowatchAuthService::getActiveSession();
                $userId   = $session['user_id'];
                $jwtToken = $session['app_jwt'];
                $sessionId = $session['session_id'];
            } else {
                $session  = MunowatchAuthService::getActiveSession();
                $jwtToken = $session['app_jwt'];
                $sessionId = $session['session_id'];
            }

            Log::info('Fetching munowatch categories from dashboard API', ['user_id' => $userId]);

            $dashboardUrl = "https://munowatch.org/api/dashboard/v2/{$userId}";

            $response = Utils::get_url_with_auth($dashboardUrl, [
                'Authorization' => 'Bearer ' . $jwtToken,
                'X-Api-Key'     => $jwtToken,
                'X-Session-Id'  => $sessionId,
                'User-Agent'    => 'okhttp/4.9.0',
            ]);

            $dashboardData = json_decode($response, true);
            
            if (!$dashboardData || !isset($dashboardData['dashboard'])) {
                throw new \Exception('Invalid dashboard response structure');
            }

            $categoriesCount = 0;
            $now = Carbon::now();

            foreach ($dashboardData['dashboard'] as $categoryData) {
                $categoryId = (int)$categoryData['id'];
                $categoryName = $categoryData['category'] ?? "Category {$categoryId}";
                $movies = $categoryData['movies'] ?? [];

                // Update or create category
                $category = self::updateOrCreate(
                    ['munowatch_category_id' => $categoryId],
                    [
                        'category_name' => $categoryName,
                        'total_movies_in_category' => count($movies),
                        'last_fetched_from_dashboard_at' => $now,
                        'sample_movies' => array_slice($movies, 0, 5), // Store first 5 movies as sample
                        'category_metadata' => [
                            'dashboard_position' => $categoriesCount,
                            'movies_in_dashboard' => count($movies),
                            'updated_from_dashboard' => $now->toISOString()
                        ],
                        'status' => self::STATUS_ACTIVE,
                        'is_dynamic' => true,
                        'api_endpoint_type' => self::ENDPOINT_DASHBOARD,
                        'next_fetch_at' => $now->addHours(6) // Check dashboard every 6 hours
                    ]
                );

                // Determine the best API endpoint for fetching more movies from this category
                $category->determineBestAPIEndpoint();
                
                $categoriesCount++;
            }

            Log::info('Successfully fetched munowatch categories from dashboard', [
                'categories_found' => $categoriesCount,
                'user_id' => $userId
            ]);

            return $categoriesCount;

        } catch (\Exception $e) {
            Log::error('Failed to fetch munowatch categories from dashboard', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            
            throw $e;
        }
    }

    /**
     * Determine the best API endpoint for fetching movies from this category
     * 
     * Based on Flutter app analysis, the app ONLY uses:
     * 1. Dashboard API to get initial 15 movies per category
     * 2. Shows endpoint for TV shows only (category 5)  
     * 3. Does NOT use browse/list endpoints directly for category fetching
     */
    public function determineBestAPIEndpoint()
    {
        $categoryId = $this->munowatch_category_id;
        
        if ($categoryId == 5) {
            // TV Shows/Action category uses shows endpoint (confirmed working)
            $this->api_endpoint_type = self::ENDPOINT_SHOWS;
            $this->pagination_endpoint = "shows/g/{$categoryId}/{uid}/{lid}";
            $this->has_pagination = true;
        } else {
            // All other categories use dashboard data only - no separate endpoint needed
            // The Flutter app gets movies for all categories from the dashboard API
            $this->api_endpoint_type = self::ENDPOINT_DASHBOARD;
            $this->pagination_endpoint = null; // Not needed for dashboard
            $this->has_pagination = false; // Dashboard gives fixed 15 movies per category
        }

        $this->save();
    }

    /**
     * Get the API URL for fetching movies from this category
     * 
     * Following the Flutter app pattern:
     * - Category 5 (TV Shows): uses shows endpoint 
     * - All other categories: use movies from dashboard data (no separate API call needed)
     */
    public function getMoviesFetchURL($page = 1, $userId = null)
    {
        if (empty($userId)) {
            try {
                $userId = MunowatchAuthService::getActiveSession()['user_id'];
            } catch (\Throwable) {
                $userId = 1062074;
            }
        }

        $baseUrl = 'https://munowatch.org/api/';

        switch ($this->api_endpoint_type) {
            case self::ENDPOINT_SHOWS:
                $lastId = ($page - 1) * 20;
                return $baseUrl . str_replace(['{uid}', '{lid}'], [$userId, $lastId], $this->pagination_endpoint);

            case self::ENDPOINT_DASHBOARD:
            default:
                return $baseUrl . "dashboard/v2/{$userId}";
        }
    }

    /**
     * Fetch movies for this category following Flutter app pattern
     * 
     * - For dashboard categories: returns the movies from the latest dashboard fetch
     * - For TV shows category: makes API call to shows endpoint
     */
    public function fetchMovies($page = 1, $userId = null)
    {
        try {
            $session   = MunowatchAuthService::getActiveSession();
            $jwtToken  = $session['app_jwt'];
            $sessionId = $session['session_id'];
            if (empty($userId)) {
                $userId = $session['user_id'];
            }
            
            Log::info('Fetching movies for category', [
                'category_id' => $this->munowatch_category_id,
                'category_name' => $this->category_name,
                'page' => $page,
                'endpoint_type' => $this->api_endpoint_type
            ]);

            if ($this->api_endpoint_type === self::ENDPOINT_DASHBOARD) {
                // For dashboard categories, return the movies from sample_movies
                $movies = $this->sample_movies ?? [];
                
                Log::info('Returning dashboard movies for category', [
                    'category_id' => $this->munowatch_category_id,
                    'category_name' => $this->category_name,
                    'movies_count' => count($movies)
                ]);
                
                // Update fetch statistics
                $this->update([
                    'last_movies_fetched_at' => Carbon::now(),
                    'current_page' => $page,
                    'status' => self::STATUS_ACTIVE,
                    'last_error_message' => null
                ]);
                
                return $movies; // Return array directly like Flutter app
                
            } else {
                // For TV shows and other special endpoints, make API call
                $url = $this->getMoviesFetchURL($page, $userId);

                $response = Utils::get_url_with_auth($url, [
                    'Authorization' => 'Bearer ' . $jwtToken,
                    'X-Api-Key'     => $jwtToken,
                    'X-Session-Id'  => $sessionId,
                    'User-Agent'    => 'okhttp/4.9.0',
                ]);

                $moviesData = json_decode($response, true);
                
                if (!$moviesData) {
                    throw new \Exception('Invalid JSON response for category: ' . substr($response, 0, 200));
                }
                
                // Validate response structure
                if (!is_array($moviesData)) {
                    throw new \Exception('Response is not an array: ' . gettype($moviesData));
                }
                
                // Log the successful response structure for debugging
                Log::info('Category movies fetched successfully from API', [
                    'category_id' => $this->munowatch_category_id,
                    'category_name' => $this->category_name,
                    'movies_count' => count($moviesData),
                    'response_sample' => array_slice($moviesData, 0, 2) // First 2 items for structure
                ]);

                // Update fetch statistics
                $this->update([
                    'last_movies_fetched_at' => Carbon::now(),
                    'current_page' => $page,
                    'status' => self::STATUS_ACTIVE,
                    'last_error_message' => null
                ]);

                return $moviesData;
            }

        } catch (\Exception $e) {
            $this->update([
                'status' => self::STATUS_FAILED,
                'last_error_message' => $e->getMessage(),
                'next_fetch_at' => Carbon::now()->addHour() // Retry in 1 hour
            ]);

            Log::error('Failed to fetch movies for category', [
                'category_id' => $this->munowatch_category_id,
                'category_name' => $this->category_name,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get next category for fetching movies
     */
    public static function getNextForMovieFetching()
    {
        return self::active()
                  ->readyForFetching()
                  ->orderBy('last_movies_fetched_at', 'asc')
                  ->orderBy('sort_order', 'asc')
                  ->first();
    }

    /**
     * Mark category as currently being fetched
     */
    public function startFetching()
    {
        $this->update([
            'status' => self::STATUS_FETCHING,
            'last_error_message' => null
        ]);
    }

    /**
     * Complete movie fetching for this category
     */
    public function completeFetching($moviesCount = 0)
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'movies_fetched' => $this->movies_fetched + $moviesCount,
            'next_fetch_at' => Carbon::now()->addHours($this->fetch_frequency_hours),
            'last_error_message' => null
        ]);
    }

    /**
     * Get display name with movie count
     */
    public function getDisplayNameAttribute()
    {
        return "{$this->category_name} (ID: {$this->munowatch_category_id}, {$this->total_movies_in_category} movies)";
    }

    /**
     * Check if categories are stale and need dashboard refresh
     */
    public static function needsDashboardRefresh($maxAgeHours = 6)
    {
        $latestFetch = self::max('last_fetched_from_dashboard_at');
        
        if (!$latestFetch) {
            return true; // No categories fetched yet
        }

        return Carbon::parse($latestFetch)->addHours($maxAgeHours)->isPast();
    }

    /**
     * Get relationship to crawler pages
     */
    public function crawlerPages()
    {
        return $this->hasMany(MovieCrawlerPage::class, 'munowatch_movie_category_id', 'id');
    }
}
