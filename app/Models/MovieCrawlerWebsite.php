<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class MovieCrawlerWebsite extends Model
{
    use HasFactory;

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
                $my_html = Utils::call_munowatch_api($this->last_page_url, $this->token, $this->email);
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
     * Handle Munowatch specific page link generation using category management system
     * 
     * Now uses MunowatchCategory model for proper category rotation and management.
     * Categories are fetched from database with their own crawling status and parameters.
     * 
     * @return string Next page URL
     * @throws \Exception When no categories available or API structure invalid
     */
    private function handleMunowatchPageLink()
    {
        // Get the next category for crawling using the new category system
        $category = MunowatchCategory::getNextForCrawling();
        
        if (!$category) {
            throw new \Exception('No munowatch categories available for crawling. Please seed categories first.');
        }
        
        // Start crawling this category if not already in progress
        if ($category->crawl_status !== MunowatchCategory::CRAWL_IN_PROGRESS) {
            $category->startCrawling();
        }
        
        // Get next page for this category
        $nextPage = $category->nextPage();
        $userId = $this->email; // User ID stored in email field (169464)
        
        // Generate API URL using the category's method
        $this->last_page_url = $category->getApiUrl($nextPage, $userId);
        $this->page_number = $nextPage;
        
        // Check if category has reached page limit (20 pages per category)
        if ($nextPage >= 20) {
            // Complete this category and reset for next crawling cycle
            $category->completeCrawling($category->new_videos_last_crawl);
            $category->resetCrawling();
            
            Log::info('Munowatch category completed, moving to next', [
                'website_id' => $this->id,
                'completed_category' => $category->name,
                'completed_category_id' => $category->munowatch_id,
                'pages_crawled' => $nextPage,
                'next_category_ready' => MunowatchCategory::getNextForCrawling()?->name ?? 'None'
            ]);
        }
        
        // Store current category info for tracking
        $this->setAttribute('current_munowatch_category_id', $category->id);
        
        Log::debug('Munowatch page generation with category system', [
            'website_id' => $this->id,
            'category_name' => $category->name,
            'category_id' => $category->munowatch_id,
            'page' => $nextPage,
            'url' => $this->last_page_url,
            'crawl_status' => $category->crawl_status
        ]);
        
        return $this->last_page_url;
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
        
        if (!isset($jsonObject['data']) || $jsonObject['data'] === null) {
            throw new \Exception('No data found in JSON response');
        }
        
        if (!is_array($jsonObject['data'])) {
            throw new \Exception('Data is not an array in JSON response');
        }
        
        foreach ($jsonObject['data'] as $key => $movieObject) {
            try {
                $movieObject = (object) $movieObject; // Convert array to object for compatibility
                
                if (empty($movieObject->slug)) {
                    Log::warning('Munowatch movie missing slug', ['key' => $key, 'title' => $movieObject->title ?? 'Unknown']);
                    continue;
                }

                // Create URL based on munowatch structure
                $url = 'https://munowatch.com/movie/' . $movieObject->slug;

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
                $page->slug = $movieObject->slug;
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
