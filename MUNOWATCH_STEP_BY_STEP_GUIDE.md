# Munowatch Crawler Step-by-Step Implementation Guide

## Quick Implementation Steps

### STEP 1: Register Munowatch Website in Database

Execute this in your Laravel application or via phpMyAdmin:

```sql
INSERT INTO movie_crawler_websites (
    name, url, about, priority, page_number, max_page, status, slug, token, email, created_at, updated_at
) VALUES (
    'Munowatch API',
    'https://munowatch.com/api/list/p/{category_id}/3/{page}',
    '0',  -- Current category index (start with 0)
    1,    -- Priority
    0,    -- Starting page number
    1000, -- Max pages (will be adjusted dynamically)
    'Active',
    'munowatch',
    'munowatch123',           -- Bearer token
    'Api-munowatch-2024',     -- API key (stored in email field)
    NOW(),
    NOW()
);
```

### STEP 2: Modify MovieCrawlerWebsite.php

Add these constants and methods to `/app/Models/MovieCrawlerWebsite.php`:

```php
// Add this constant at the top with existing constants
const MUNOWATCH = 'munowatch';

// Modify the get_next_page_link() method to include munowatch case
public function get_next_page_link()
{
    if ($this->slug == self::MY_VJ) {
        $page_number = (int)$this->page_number + 1;
        if ($page_number > $this->max_page) {
            if ($this->max_page > 50) {
                $this->max_page = 49;
            }
            $page_number = 0;
        }
        $this->page_number = $page_number;
        $this->last_page_url = $this->url . $page_number;
        return str_replace('{page_number}', $this->page_number, $this->url);
    } elseif ($this->slug == self::MUNOWATCH) {
        return $this->get_munowatch_next_page();
    } else {
        throw new \Exception('Invalid slug');
    }
}

// Modify the process_pages() method to include munowatch case
public function process_pages()
{
    $html = str_get_html($this->response_data);
    $jobLinks = [];
    $jobLinksNew = [];
    
    if ($this->slug == self::MY_VJ) {
        // Existing ugawatch code...
        $jsonObject = null;
        try {
            $jsonObject = json_decode($this->response_data);
        } catch (\Throwable $th) {
            throw $th;
        }
        // ... rest of existing MY_VJ code
    } elseif ($this->slug == self::MUNOWATCH) {
        $this->process_munowatch_pages();
        return;
    } else {
        throw new \Exception('Invalid slug when processing pages');
    }
    
    // Existing completion code for MY_VJ...
    $this->last_fetched_at = Carbon::now();
    $this->total_movies_found = count($jobLinks);
    $this->new_movies_found = count($jobLinksNew);
    $this->fetch_status = "success";
    $this->error_message = null;
    try {
        $this->save();
    } catch (\Throwable $th) {
        throw $th;
    }
}

// Add these new methods at the end of the class

private function get_munowatch_next_page()
{
    // Category rotation logic - munowatch categories
    $categories = [1, 2, 3, 4, 5]; // Adjust based on actual munowatch categories
    $currentCategoryIndex = (int)($this->about ?? 0);
    $categoryId = $categories[$currentCategoryIndex];
    
    $page = (int)$this->page_number;
    
    // Build API URL
    $this->last_page_url = str_replace(
        ['{category_id}', '{page}'],
        [$categoryId, $page],
        $this->url
    );
    
    // Increment page for next fetch
    $this->page_number = $page + 1;
    
    // Rotate to next category if reached max pages per category
    if ($this->page_number > 50) { // Max 50 pages per category
        $this->page_number = 0;
        $nextCategoryIndex = ($currentCategoryIndex + 1) % count($categories);
        $this->about = (string)$nextCategoryIndex;
    }
    
    return $this->last_page_url;
}

private function process_munowatch_pages()
{
    try {
        // First, get the content with proper headers
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'X-Api-Key: ' . $this->email,
            'Content-Type: application/json'
        ];
        
        // Re-fetch with authentication headers
        $response = $this->fetchWithHeaders($this->last_page_url, $headers);
        $this->response_data = $response;
        
        $jsonObject = json_decode($response, true);
        
        if (!$jsonObject) {
            throw new \Exception('Failed to decode munowatch JSON response');
        }
        
        // Handle different response structures
        $movies = [];
        if (isset($jsonObject['data'])) {
            $movies = $jsonObject['data'];
        } elseif (is_array($jsonObject)) {
            $movies = $jsonObject;
        } else {
            throw new \Exception('Invalid munowatch API response structure');
        }
        
        $newMoviesCount = 0;
        
        foreach ($movies as $movieData) {
            // Check if movie already exists
            $existingPage = MovieCrawlerPage::where([
                'movie_crawler_website_id' => $this->id,
                'movie_id' => $movieData['id'] ?? null
            ])->first();
            
            if ($existingPage) {
                continue;
            }
            
            // Create movie page record
            $page = new MovieCrawlerPage();
            $page->movie_crawler_website_id = $this->id;
            $page->title = $movieData['video_title'] ?? $movieData['title'] ?? 'Unknown Title';
            $page->slug = 'munowatch-' . ($movieData['id'] ?? uniqid());
            $page->url = "https://munowatch.com/api/video/{$movieData['id']}/3";
            $page->movie_id = $movieData['id'] ?? null;
            $page->status = 'pending';
            $page->type = $this->determineMunowatchType($movieData);
            $page->row_id = $movieData['id'] ?? null;
            $page->img_port_muno_file_name = $movieData['thumbnail'] ?? null;
            $page->bunny_file_name = $movieData['playingUrl'] ?? null;
            $page->tmdb_poster_path = $movieData['thumbnail'] ?? null;
            $page->vj = $movieData['vjname'] ?? 'Munowatch';
            $page->save();
            
            $newMoviesCount++;
        }
        
        // Update statistics
        $this->new_movies_found = $newMoviesCount;
        $this->total_movies_found = count($movies);
        $this->fetch_status = 'success';
        $this->error_message = null;
        $this->last_fetched_at = Carbon::now();
        $this->save();
        
    } catch (\Exception $e) {
        $this->fetch_status = 'failed';
        $this->error_message = $e->getMessage();
        $this->last_fetched_at = Carbon::now();
        $this->save();
        throw $e;
    }
}

private function fetchWithHeaders($url, $headers)
{
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'Katogo-Crawler/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    
    curl_close($curl);
    
    if ($error) {
        throw new \Exception("cURL Error: $error");
    }
    
    if ($httpCode >= 400) {
        throw new \Exception("HTTP Error: $httpCode - URL: $url");
    }
    
    return $response;
}

private function determineMunowatchType($movieData)
{
    // Check if it's a series based on episodes or series_code
    if (isset($movieData['episodes']) && !empty($movieData['episodes'])) {
        return 'Series';
    }
    
    if (isset($movieData['series_code']) && !empty($movieData['series_code'])) {
        return 'Series';
    }
    
    // Check title for series indicators
    $title = strtolower($movieData['video_title'] ?? $movieData['title'] ?? '');
    if (strpos($title, 'episode') !== false || strpos($title, 'season') !== false) {
        return 'Series';
    }
    
    return 'Movie';
}
```

### STEP 3: Modify MovieCrawlerPage.php

Add munowatch processing to `/app/Models/MovieCrawlerPage.php`:

```php
// Modify the process_page_content() method to include munowatch case
public function process_page_content()
{
    if ($this->movie_crawler_website == null) {
        $this->status = 'error';
        $this->error_message = "Movie site not found";
        $this->save();
        return;
    }
    if ($this->page_content == null) {
        $this->status = 'error';
        $this->error_message = "Page content is empty";
        $this->save();
        return;
    }
    if ($this->movie_crawler_website->slug == MovieCrawlerWebsite::MY_VJ) {
        $this->process_my_vj();
    } elseif ($this->movie_crawler_website->slug == MovieCrawlerWebsite::MUNOWATCH) {
        $this->process_munowatch_movie();
    } else {
        $this->status = 'error';
        $this->error_message = "Slug not found When processing page content";
        $this->save();
    }
}

// Add this new method at the end of the class
private function process_munowatch_movie()
{
    try {
        // Check for existing movie
        $existing = MovieModel::where('external_url', $this->url)
            ->orWhere('imdb_id', $this->movie_id)
            ->orWhere('title', $this->title)
            ->first();
            
        if ($existing) {
            $this->status = 'skipped';
            $this->error_message = 'Movie already exists';
            $this->save();
            return;
        }
        
        // For munowatch, we need to fetch the detailed movie data
        $headers = [
            'Authorization: Bearer ' . $this->movie_crawler_website->token,
            'X-Api-Key: ' . $this->movie_crawler_website->email,
            'Content-Type: application/json'
        ];
        
        $movieResponse = $this->fetchWithHeaders($this->url, $headers);
        $this->page_content = $movieResponse;
        
        // Parse munowatch movie data
        $movieData = json_decode($movieResponse, true);
        
        if (!$movieData) {
            throw new \Exception('Invalid movie data received from API');
        }
        
        // Create new movie record
        $movie = new MovieModel();
        
        // Map munowatch fields to katogo fields
        $movie->title = $movieData['video_title'] ?? $this->title;
        $movie->description = $movieData['description'] ?? '';
        $movie->url = $movieData['playingUrl'] ?? $movieData['embedUrl'] ?? '';
        $movie->external_url = $this->url;
        $movie->thumbnail_url = $movieData['thumbnail'] ?? '';
        $movie->image_url = $movieData['thumbnail'] ?? '';
        $movie->page_source_url = $this->url;
        
        // Duration and timing
        $movie->duration = $movieData['duration'] ?? '';
        $movie->year = $this->extractYear($movieData['create_date'] ?? '');
        
        // Categories and metadata
        $movie->genre = $movieData['genre'] ?? 'General';
        $movie->category = $movieData['genre'] ?? 'General';
        $movie->category_id = $movieData['category_id'] ?? 1;
        
        // VJ and source information
        $movie->vj = $movieData['vjname'] ?? $this->vj ?? 'Munowatch';
        $movie->stars = 'Munowatch';
        $movie->imdb_id = $this->movie_id;
        $movie->imdb_url = 'Munowatch-' . $this->movie_id;
        
        // Status and access
        $movie->status = 'Inactive'; // Start as inactive, will be activated after testing
        $movie->is_premium = ($movieData['access'] === 'Premium') ? 'Yes' : 'No';
        
        // Technical fields
        $movie->type = $this->type;
        $movie->is_processed = 'No';
        $movie->content_type = 'video/mp4';
        $movie->content_is_video = 'Yes';
        $movie->content_type_processed = 'Yes';
        
        // Initialize counters
        $movie->downloads_count = 0;
        $movie->views_count = 0;
        $movie->likes_count = 0;
        $movie->dislikes_count = 0;
        $movie->comments_count = 0;
        
        // Testing status (will be tested later by the system)
        $movie->video_url_tested_by_curl = 'No';
        $movie->video_url_tested_by_curl_works = 'No';
        $movie->video_url_tested_by_human = 'No';
        $movie->video_url_tested_by_human_works = 'No';
        
        // Firebase fields
        $movie->firebase_transfer_attempted = 'No';
        $movie->firebase_transfer_successful = 'No';
        $movie->firebase_video_tested_by_curl = 'No';
        $movie->firebase_video_tested_by_curl_works = 'No';
        
        $movie->save();
        
        // Mark page as successfully processed
        $this->status = 'success';
        $this->error_message = null;
        $this->save();
        
    } catch (\Exception $e) {
        $this->status = 'error';
        $this->error_message = 'Error processing munowatch movie: ' . $e->getMessage();
        $this->save();
        throw $e;
    }
}

private function fetchWithHeaders($url, $headers)
{
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'Katogo-Crawler/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    
    curl_close($curl);
    
    if ($error) {
        throw new \Exception("cURL Error: $error");
    }
    
    if ($httpCode >= 400) {
        throw new \Exception("HTTP Error: $httpCode");
    }
    
    return $response;
}

private function extractYear($dateString)
{
    if (empty($dateString)) {
        return null;
    }
    
    $year = (int) substr($dateString, 0, 4);
    return ($year > 1900 && $year <= date('Y')) ? $year : null;
}
```

### STEP 4: Test the Implementation

#### 4.1 Manual Test - Check Database Registration
```sql
SELECT * FROM movie_crawler_websites WHERE slug = 'munowatch';
```

#### 4.2 Manual Test - Test URL Generation
```php
// In tinker or a test script
$munowatch = \App\Models\MovieCrawlerWebsite::where('slug', 'munowatch')->first();
echo $munowatch->get_next_page_link();
// Should output: https://munowatch.com/api/list/p/1/3/0
```

#### 4.3 Manual Test - Test Page Content Fetching
```php
$munowatch = \App\Models\MovieCrawlerWebsite::where('slug', 'munowatch')->first();
$munowatch->get_next_page_content();

// Check for any errors
echo $munowatch->error_message ?: 'Success';

// Check how many pages were created
$pages = \App\Models\MovieCrawlerPage::where('movie_crawler_website_id', $munowatch->id)->count();
echo "Created {$pages} movie pages";
```

#### 4.4 Run Full Crawler
Visit: `http://yourdomain.com/crawler`

This will execute both:
1. `Utils::fetch_pages()` - Creates movie pages from API
2. `Utils::fetch_pages_content()` - Processes pages into movies

### STEP 5: Monitor Progress

#### Check Crawler Status
```sql
-- Check website status
SELECT slug, fetch_status, error_message, new_movies_found, last_fetched_at 
FROM movie_crawler_websites 
WHERE slug = 'munowatch';

-- Check pending pages
SELECT COUNT(*) as pending_pages 
FROM movie_crawler_pages 
WHERE status = 'pending' 
AND movie_crawler_website_id = (SELECT id FROM movie_crawler_websites WHERE slug = 'munowatch');

-- Check created movies
SELECT COUNT(*) as total_movies 
FROM movie_models 
WHERE stars = 'Munowatch';
```

#### Debug Common Issues
```php
// Check last error for website
$munowatch = \App\Models\MovieCrawlerWebsite::where('slug', 'munowatch')->first();
echo "Status: " . $munowatch->fetch_status;
echo "Error: " . $munowatch->error_message;

// Check last page error
$lastPage = \App\Models\MovieCrawlerPage::where('movie_crawler_website_id', $munowatch->id)
    ->orderBy('id', 'desc')
    ->first();
echo "Page Status: " . $lastPage->status;
echo "Page Error: " . $lastPage->error_message;
```

## Key Points to Remember

### 1. Authentication
- Bearer token stored in `token` field
- API key stored in `email` field (reusing existing field)
- Both must be valid for munowatch API

### 2. Category Rotation
- Munowatch has multiple categories
- System rotates through categories to get all movies
- Current category index stored in `about` field

### 3. API Structure
- Level 1: List movies by category/page
- Level 2: Get individual movie details
- Both require authentication headers

### 4. Error Handling
- API might return 404/403 if credentials are wrong
- Network timeouts are handled with 30-second timeout
- Invalid JSON responses are caught and logged

### 5. Data Mapping
- Munowatch fields are mapped to existing katogo fields
- Some fields might be empty/null from API
- Default values are provided for missing data

### 6. Performance
- Process in small batches to avoid timeouts
- Uses existing crawler infrastructure
- Automatic retry on failures

This step-by-step guide should get munowatch crawler working with the existing katogo system using the proven 3-level architecture.