<?php

namespace App\Models;

use App\Models\Utils;
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
    public static function fetchCategoriesFromDashboard($userId = '169464')
    {
        try {
            Log::info('Fetching munowatch categories from dashboard API', [
                'user_id' => $userId
            ]);

            // Call the dashboard API endpoint
            $dashboardUrl = "https://munowatch.org/api/dashboard/v2/{$userId}";
            $jwtToken = config('munowatch.jwt_token', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0');
            
            $response = Utils::call_munowatch_api(
                $dashboardUrl,
                $jwtToken, // Bearer token
                $jwtToken, // API key (same as bearer for munowatch)
                'GET'
            );

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
     */
    public function determineBestAPIEndpoint()
    {
        // Based on testing, only certain endpoints work:
        // - shows/g/{categoryId}/{userId}/{lastId} works for TV shows (category 5)
        // - search/{query}/{userId}/{lastId} works for search
        // - browse and list endpoints return 500 errors
        
        $categoryId = $this->munowatch_category_id;
        
        if ($categoryId == 5) {
            // TV Shows/Action category uses shows endpoint (confirmed working)
            $this->api_endpoint_type = self::ENDPOINT_SHOWS;
            $this->pagination_endpoint = "shows/g/{$categoryId}/{uid}/{lid}";
            $this->has_pagination = true;
        } else {
            // For other categories, use search with category-specific terms
            $this->api_endpoint_type = 'search';
            
            // Map category names to search terms
            $searchTerms = [
                1 => 'latest',        // My List
                3 => 'romance',       // Romance
                4 => 'latest',        // Latest on Munowatch
                6 => 'popular',       // You may also like
                8 => 'drama',         // Drama
                9 => 'sci-fi',        // Sci Fi
                10 => 'horror',       // Horror
                17 => 'latest',       // Continue watching
                18 => 'latest',       // Latest Uploads
                20 => 'popular',      // Favourites
                22 => 'popular',      // Most Liked
                23 => 'episodes',     // Last watched episodes
            ];
            
            $searchTerm = $searchTerms[$categoryId] ?? 'latest';
            $this->pagination_endpoint = "search/{$searchTerm}/{uid}/{lid}";
            $this->has_pagination = true;
            
            // Store search term for URL generation
            $this->api_parameters = ['search_term' => $searchTerm];
        }

        $this->save();
    }

    /**
     * Get the API URL for fetching movies from this category
     */
    public function getMoviesFetchURL($page = 1, $userId = '169464')
    {
        $baseUrl = 'https://munowatch.org/api/';
        $lastId = ($page - 1) * 20; // Assume 20 items per page, use lastId for pagination
        
        switch ($this->api_endpoint_type) {
            case self::ENDPOINT_SHOWS:
                return $baseUrl . str_replace(['{uid}', '{lid}'], [$userId, $lastId], $this->pagination_endpoint);
                
            case 'search':
                $searchTerm = $this->api_parameters['search_term'] ?? 'latest';
                return $baseUrl . "search/{$searchTerm}/{$userId}/{$lastId}";
                
            case self::ENDPOINT_BROWSE:
                // Keep for fallback, though it doesn't work
                return $baseUrl . $this->pagination_endpoint;
                
            case self::ENDPOINT_LIST:
                // Keep for fallback, though it doesn't work
                return $baseUrl . str_replace(['{uid}', '{lid}'], [$userId, $lastId], $this->pagination_endpoint);
                
            default:
                // Fallback to search
                return $baseUrl . "search/latest/{$userId}/{$lastId}";
        }
    }

    /**
     * Fetch movies for this category
     */
    public function fetchMovies($page = 1, $userId = '169464')
    {
        try {
            $url = $this->getMoviesFetchURL($page, $userId);
            $jwtToken = config('munowatch.jwt_token', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0');
            
            Log::info('Fetching movies for category', [
                'category_id' => $this->munowatch_category_id,
                'category_name' => $this->category_name,
                'page' => $page,
                'url' => $url
            ]);

            $response = Utils::call_munowatch_api(
                $url,
                $jwtToken, // Bearer token
                $jwtToken, // API key (same for munowatch)
                'GET'
            );

            $moviesData = json_decode($response, true);
            
            if (!$moviesData) {
                throw new \Exception('Invalid JSON response for category: ' . substr($response, 0, 200));
            }
            
            // Validate response structure
            if (!is_array($moviesData)) {
                throw new \Exception('Response is not an array: ' . gettype($moviesData));
            }
            
            // Log the successful response structure for debugging
            Log::info('Category movies fetched successfully', [
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
