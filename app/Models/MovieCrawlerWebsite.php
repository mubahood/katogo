<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class MovieCrawlerWebsite extends Model
{
    use HasFactory;

    //boot
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($model) {
            if ($model->status != 'Active' && $model->status != 'Inactive') {
                $model->status = 'Active';
            }
        });
    }

    protected $fillable = [
        'name', 'base_url', 'current_page', 'last_page_url', 'fetch_status', 
        'last_fetched_at', 'next_fetch_at', 'response_data', 'error_message', 
        'status', 'current_munowatch_category_id'
    ];

    //constant for fetch status
    const MY_VJ = 'my-vj';
    const MUNOWATCH = 'munowatch';
    /**
     * Fetches and processes the next page content from the movie crawler website.
     * 
     * This method performs the following operations in sequence:
     * 1. Sets the fetch status to 'in_progress' and clears any previous error messages
     * 2. Updates the last_fetched_at timestamp to the current time
     * 3. Retrieves the next page URL by calling get_next_page_link()
     * 4. Attempts to fetch the HTML content from the last_page_url using Utils::get_url()
     * 5. If the fetch fails, updates status to 'failed', stores the error message, and rethrows the exception
     * 6. On success, updates fetch_status to 'success' and stores the HTML response in response_data
     * 7. Persists all changes to the database by calling save()
     * 8. Triggers page processing by calling process_pages()
     * 
     * @return void
     * @throws \Throwable When URL fetching fails, the exception is caught, logged, and rethrown
     */

    public function get_next_page_content()
    {
        $this->fetch_status = 'in_progress';
        $this->error_message = null;
        $this->fetch_status = 'in_progress';
        $this->last_fetched_at = Carbon::now();
        $this->get_next_page_link();
        try {
            if ($this->slug == self::MUNOWATCH) {
                // Use the correct headers format for munowatch API (same as Flutter app)
                $baseToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
                $headers = [
                    'Authorization' => 'Bearer ' . $baseToken,
                    'X-Api-Key' => $baseToken,
                    'User-Agent' => 'okhttp/4.9.0'
                ];
                $my_html = Utils::get_url_with_auth($this->last_page_url, $headers);
            } else {
                $my_html = Utils::get_url($this->last_page_url);
            }
        } catch (\Throwable $th) {
            $this->status = 'failed';
            $this->error_message = $th->getMessage();
            throw $th;
        }
        $this->fetch_status = 'success';
        $this->last_fetched_at = Carbon::now();
        $this->response_data = $my_html;

        $this->save();
        $this->process_pages();
    }



    /**
     * Generate the next page URL for crawling with enhanced error handling and validation
     * 
     * @return string|null Next page URL or null if unable to generate
     * @throws \Exception When invalid configuration is detected
     */
    public function get_next_page_link()
    {
        try {
            // Validate basic requirements
            if (empty($this->slug)) {
                throw new \Exception('Website slug is empty - cannot generate page link');
            }

            if (empty($this->url)) {
                throw new \Exception('Website URL template is empty - cannot generate page link');
            }

            if ($this->slug == self::MY_VJ) {
                return $this->handleMyVjPageLink();
            } elseif ($this->slug == self::MUNOWATCH) {
                return $this->handleMunowatchPageLink();
            } else {
                throw new \Exception("Unsupported website slug: {$this->slug}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate next page link', [
                'website_id' => $this->id,
                'slug' => $this->slug,
                'current_page' => $this->page_number,
                'url_template' => $this->url,
                'error' => $e->getMessage()
            ]);

            // Set error state
            $this->error_message = 'Page link generation failed: ' . $e->getMessage();
            $this->fetch_status = 'failed';

            throw $e;
        }
    }

    /**
     * Handle MY_VJ specific page link generation
     * 
     * @return string Next page URL
     */
    private function handleMyVjPageLink()
    {
        $page_number = (int)$this->page_number + 1;

        // Validate and adjust max_page if needed
        if ($this->max_page > 50) {
            $this->max_page = 49;
            Log::info('Adjusted max_page for MY_VJ website', [
                'website_id' => $this->id,
                'new_max_page' => $this->max_page
            ]);
        }

        if ($page_number > $this->max_page) {
            $page_number = 0;
            Log::info('MY_VJ page cycling - reset to 0', [
                'website_id' => $this->id,
                'max_page' => $this->max_page
            ]);
        }

        $this->page_number = $page_number;
        $this->last_page_url = $this->url . $page_number;

        return str_replace('{page_number}', $this->page_number, $this->url);
    }

    /**
     * Handle Munowatch specific page link generation using dynamic categories from dashboard API
     * 
     * Now uses MunowatchMovieCategory model for proper category rotation and management.
     * Categories are fetched dynamically from munowatch dashboard API with real category IDs.
     * 
     * @return string Next page URL
     * @throws \Exception When no categories available or API structure invalid
     */
    private function handleMunowatchPageLink()
    {
        // Ensure we have fresh dynamic categories from munowatch dashboard
        if (MunowatchMovieCategory::needsDashboardRefresh()) {
            try {
                MunowatchMovieCategory::fetchCategoriesFromDashboard();
                Log::info('Refreshed munowatch categories from dashboard');
            } catch (\Exception $e) {
                Log::warning('Failed to refresh munowatch categories', ['error' => $e->getMessage()]);
            }
        }

        // Get the next category to fetch movies from
        $category = MunowatchMovieCategory::getNextForMovieFetching();

        if (!$category) {
            Log::warning('No munowatch categories available for fetching movies');
            // Fallback to dashboard endpoint (safe and always works)
            return 'https://munowatch.org/api/dashboard/v2/169464';
        }

        // Mark category as being fetched
        $category->startFetching();

        // Store reference to current category for tracking
        $this->update(['current_munowatch_category_id' => $category->id]);

        // Get the API URL for fetching movies from this category
        $url = $category->getMoviesFetchURL();

        // Store URL for tracking
        $this->last_page_url = $url;
        $this->page_number = 1; // Start with page 1 for each category

        Log::info('Selected munowatch category for crawling', [
            'category_id' => $category->munowatch_category_id,
            'category_name' => $category->category_name,
            'endpoint_type' => $category->api_endpoint_type,
            'url' => $url,
            'total_movies' => $category->total_movies_in_category
        ]);

        return $url;
    }



    /** process_pages - Processes the fetched HTML response to extract movie page information.
     *
     * This method parses the HTML content stored in the `response_data` attribute,
     * extracts movie details, and creates new `MovieCrawlerPage` records for each
     * unique movie found. It handles JSON responses specifically for the 'my-vj' slug.
     * The method updates the website's fetch statistics and saves changes to the database.
     *
     * @throws \Exception If JSON decoding fails or if the response structure is invalid.
     * @return void
     */
    public function process_pages()
    {
        try {
            // Input validation
            if (empty($this->response_data)) {
                throw new \Exception('No response data available to process');
            }

            $jobLinks = [];
            $jobLinksNew = [];

            if ($this->slug == self::MY_VJ) {
                $this->processMYVJPages($jobLinksNew);
            } elseif ($this->slug == self::MUNOWATCH) {
                $this->processMunowatchPages($jobLinksNew);
            } else {
                throw new \Exception('Invalid slug when processing pages: ' . $this->slug);
            }

            // Update statistics and status
            $this->last_fetched_at = Carbon::now();
            $this->total_movies_found = count($jobLinks);
            $this->new_movies_found = count($jobLinksNew);
            $this->fetch_status = "success";
            $this->error_message = null;

            $this->save();

            Log::info('Successfully processed pages', [
                'website_id' => $this->id,
                'slug' => $this->slug,
                'new_movies_found' => $this->new_movies_found,
                'total_movies_found' => $this->total_movies_found
            ]);
        } catch (\Exception $e) {
            $this->fetch_status = 'failed';
            $this->error_message = $e->getMessage();
            $this->save();

            Log::error('Failed to process pages', [
                'website_id' => $this->id,
                'slug' => $this->slug,
                'error' => $e->getMessage(),
                'response_data_length' => strlen($this->response_data ?? '')
            ]);

            throw $e;
        }
    }

    /**
     * Process MY_VJ specific page data
     */
    private function processMYVJPages(&$jobLinksNew)
    {
        $jsonObject = null;
        try {
            $jsonObject = json_decode($this->response_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }
        } catch (\Exception $e) {
            throw new \Exception('Failed to decode MY_VJ JSON response: ' . $e->getMessage());
        }

        if ($jsonObject === null) {
            throw new \Exception('Failed to decode JSON response - null result');
        }

        if (!isset($jsonObject['movies']) || $jsonObject['movies'] === null) {
            throw new \Exception('No movies found in JSON response');
        }

        if (!is_array($jsonObject['movies'])) {
            throw new \Exception('Movies is not an array in JSON response');
        }

        foreach ($jsonObject['movies'] as $key => $movieObject) {
            try {
                $movieObject = (object) $movieObject; // Convert array to object for compatibility

                if (empty($movieObject->slug)) {
                    Log::warning('MY_VJ movie missing slug', ['key' => $key, 'title' => $movieObject->title ?? 'Unknown']);
                    continue;
                }

                $url1 = 'https://ugawatch.com/watch/' . $movieObject->slug;
                $url2 = 'https://myvj.net/watch/' . $movieObject->slug;

                // Check if page already exists
                $existingPage = MovieCrawlerPage::where('url', $url1)->first();
                if ($existingPage !== null) {
                    continue;
                }

                $existingPage = MovieCrawlerPage::where('url', $url2)->first();
                if ($existingPage !== null) {
                    continue;
                }

                // Create new page
                $page = new MovieCrawlerPage();
                $page->url = $url1;
                $page->movie_crawler_website_id = $this->id;
                $page->title = $movieObject->title ?? 'Unknown Title';
                $page->status = 'pending';
                $page->slug = $movieObject->slug;
                $page->movie_id = null;
                $page->page_content = null;
                $page->error_message = null;
                $page->last_fetched_at = null;

                // Determine type based on URL
                $isMovie = false;
                if (strpos($this->url, 'explore-movies') !== false) {
                    $isMovie = true;
                    $page->type = 'Movie';
                } else {
                    $page->type = 'Series';
                }

                $page->row_id = $movieObject->row_id ?? null;
                $page->img_port_muno_file_name = $movieObject->img_port_muno_file_name ?? null;
                $page->bunny_file_name = $movieObject->bunny_file_name ?? null;
                $page->tmdb_poster_path = $movieObject->tmdb_poster_path ?? null;
                $page->vj = $movieObject->vj ?? 'Unknown VJ';

                $page->save();
                $jobLinksNew[] = $url1;
            } catch (\Exception $e) {
                Log::error('Failed to process MY_VJ movie', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                    'slug' => $movieObject->slug ?? 'unknown'
                ]);
                // Continue processing other movies
                continue;
            }
        }
    }

    /**
     * Process Munowatch specific page data
     */
    private function processMunowatchPages(&$jobLinksNew)
    {
        $jsonObject = null;
        try {
            $jsonObject = json_decode($this->response_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }
        } catch (\Exception $e) {
            throw new \Exception('Failed to decode Munowatch JSON response: ' . $e->getMessage());
        }

        if ($jsonObject === null) {
            throw new \Exception('Failed to decode JSON response - null result');
        }

        // Handle both dashboard and list/browse response formats
        $moviesData = null;
        if (isset($jsonObject['dashboard']) && is_array($jsonObject['dashboard'])) {
            // Dashboard response format - extract movies from all categories
            $moviesData = [];
            foreach ($jsonObject['dashboard'] as $category) {
                if (isset($category['movies']) && is_array($category['movies'])) {
                    $moviesData = array_merge($moviesData, $category['movies']);
                }
            }
        } elseif (isset($jsonObject['data']) && is_array($jsonObject['data'])) {
            // List/browse response format
            $moviesData = $jsonObject['data'];
        } else {
            throw new \Exception('No valid data found in JSON response - expected dashboard or data key');
        }

        if (empty($moviesData)) {
            throw new \Exception('No movies found in response');
        }

        foreach ($moviesData as $key => $movieObject) {
            try {
                $movieObject = (object) $movieObject; // Convert array to object for compatibility

                // Dashboard response uses 'vid' instead of 'slug'
                $movieId = $movieObject->vid ?? $movieObject->id ?? null;
                if (empty($movieId)) {
                    Log::warning('Munowatch movie missing ID/VID', ['key' => $key, 'title' => $movieObject->title ?? 'Unknown']);
                    continue;
                }

                // Create URL based on munowatch API structure - preview endpoint for details
                $userId = '169464'; // Default user ID used by the API
                $url = "https://munowatch.org/api/preview/v2/{$movieId}/{$userId}";

                // Check if page already exists
                $existingPage = MovieCrawlerPage::where('url', $url)->first();
                if ($existingPage !== null) {
                    continue;
                }

                // Create new MovieCrawlerPage
                $page = new MovieCrawlerPage();
                $page->url = $url;
                $page->movie_crawler_website_id = $this->id;
                $page->title = $movieObject->title ?? 'Unknown Title';
                $page->status = 'pending';
                $page->slug = (string)$movieId; // Use movieId as slug
                $page->movie_id = null;
                $page->page_content = null;
                $page->error_message = null;
                $page->last_fetched_at = null;

                // Determine type based on category_id in current URL
                $current_category = 1;
                if (preg_match('/\/p\/(\d+)\//', $this->url, $matches)) {
                    $current_category = (int)$matches[1];
                }

                // Category mapping: 1=movie, 2=series, 3=korean, 4=animation
                switch ($current_category) {
                    case 1:
                        $page->type = 'Movie';
                        break;
                    case 2:
                        $page->type = 'Series';
                        break;
                    case 3:
                        $page->type = 'Movie'; // Korean movies/series
                        break;
                    case 4:
                        $page->type = 'Movie'; // Animation
                        break;
                    default:
                        $page->type = 'Movie';
                }

                // Store additional munowatch-specific fields
                $page->row_id = $movieObject->id ?? null;
                $page->img_port_muno_file_name = $movieObject->poster ?? null;
                $page->bunny_file_name = $movieObject->video_url ?? null;
                $page->tmdb_poster_path = $movieObject->poster_path ?? null;
                $page->vj = 'Munowatch API';

                $page->save();
                $jobLinksNew[] = $url;
            } catch (\Exception $e) {
                Log::error('Failed to process Munowatch movie', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                    'slug' => $movieObject->slug ?? 'unknown'
                ]);
                // Continue processing other movies
                continue;
            }
        }
    }
}
