# Munowatch Crawler Implementation Guide

## Overview
This document provides comprehensive documentation for implementing a munowatch movie data crawler using the existing katogo crawler architecture. The implementation follows the proven 3-level fetching system used by the ugawatch integration.

## Table of Contents
1. [Crawler Architecture Overview](#crawler-architecture-overview)
2. [Database Structure](#database-structure)
3. [Current Implementation Analysis](#current-implementation-analysis)
4. [Munowatch API Structure](#munowatch-api-structure)
5. [Implementation Plan](#implementation-plan)
6. [Code Examples](#code-examples)
7. [Testing Guide](#testing-guide)
8. [Troubleshooting](#troubleshooting)

## Crawler Architecture Overview

The katogo crawler system uses a 3-level architecture for fetching movie data:

### Level 1: Website Registration (MovieCrawlerWebsite)
- **Purpose**: Register and configure the movie source website
- **Model**: `MovieCrawlerWebsite`
- **Table**: `movie_crawler_websites`
- **Key Fields**: url, name, slug, status, page_number, max_page

### Level 2: Page Discovery (get_next_page_content → process_pages)
- **Purpose**: Fetch page content and extract movie page links
- **Method**: `get_next_page_content()` calls `process_pages()`
- **Model**: `MovieCrawlerPage`
- **Table**: `movie_crawler_pages`
- **Process**: Fetches JSON/HTML content, extracts movie links, saves as pending pages

### Level 3: Movie Detail Extraction (fetch_pages_content)
- **Purpose**: Process individual movie pages and extract complete movie data
- **Method**: `fetch_page_content()` calls `process_page_content()`
- **Target**: `movie_models` table
- **Process**: Fetches individual movie details, saves to movie_models table

## Database Structure

### movie_crawler_websites Table
```sql
CREATE TABLE movie_crawler_websites (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    name TEXT NULL,                    -- "Munowatch API"
    url TEXT NULL,                     -- API base URL template
    about TEXT NULL,                   -- Description
    priority INT DEFAULT 1,           -- Processing priority
    last_fetched_at DATETIME NULL,    -- Last fetch timestamp
    page_number INT NULL,              -- Current page being processed
    total_movies_found INT NULL,       -- Total movies discovered
    new_movies_found INT NULL,         -- New movies in last fetch
    status VARCHAR(255) DEFAULT 'Active',  -- Active/Inactive
    fetch_status VARCHAR(255) NULL,    -- in_progress/success/failed
    failed_message TEXT NULL,          -- Error details
    response_data TEXT NULL,           -- Last API response
    slug VARCHAR(255) NULL,            -- Unique identifier (munowatch)
    last_page_url VARCHAR(255) NULL,   -- Last processed URL
    max_page VARCHAR(255) NULL,        -- Maximum pages available
    error_message TEXT NULL,           -- Current error message
    email TEXT NULL,                   -- API credentials (optional)
    password TEXT NULL,                -- API credentials (optional)
    token TEXT NULL,                   -- Bearer token
    token_expiry TEXT NULL             -- Token expiration
);
```

### movie_crawler_pages Table
```sql
CREATE TABLE movie_crawler_pages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    movie_crawler_website_id BIGINT,   -- Foreign key to websites
    title TEXT NULL,                   -- Movie title
    slug TEXT NULL,                    -- Movie slug/identifier
    url TEXT NULL,                     -- Detail page URL or API endpoint
    movie_id TEXT NULL,                -- External movie ID
    page_content LONGTEXT NULL,        -- Fetched content (JSON/HTML)
    error_message LONGTEXT NULL,       -- Processing errors
    status VARCHAR(255) DEFAULT 'Pending',  -- Pending/Success/Error
    last_fetched_at DATETIME NULL,     -- Processing timestamp
    type VARCHAR(255),                 -- Movie/Series
    row_id VARCHAR(255) NULL,          -- External row identifier
    img_port_muno_file_name TEXT NULL, -- Poster filename
    bunny_file_name TEXT NULL,         -- Video filename
    tmdb_poster_path TEXT NULL,        -- TMDb poster path
    vj TEXT NULL                       -- VJ/Source information
);
```

## Current Implementation Analysis

### Utils.php Core Methods

#### fetch_pages()
```php
static function fetch_pages()
{
    // 1. Find next active website to process
    $next_company = MovieCrawlerWebsite::where('status', 'Active')
        ->orderBy('last_fetched_at', 'asc')
        ->first();
    
    // 2. Call website's page content fetcher
    $next_company->get_next_page_content();
}
```

#### fetch_pages_content()
```php
static function fetch_pages_content()
{
    // 1. Find pending pages to process
    $pages = MovieCrawlerPage::where('status', 'pending')
        ->orderBy('id', 'desc')
        ->limit(10)
        ->get();
    
    // 2. Process each page to extract movie details
    foreach ($pages as $page) {
        $page->fetch_page_content();
    }
}
```

### MovieCrawlerWebsite Methods

#### get_next_page_content()
```php
public function get_next_page_content()
{
    // 1. Set processing status
    $this->fetch_status = 'in_progress';
    $this->last_fetched_at = Carbon::now();
    
    // 2. Generate next page URL
    $this->get_next_page_link();
    
    // 3. Fetch content from URL
    $my_html = Utils::get_url($this->last_page_url);
    
    // 4. Store response and trigger processing
    $this->response_data = $my_html;
    $this->save();
    $this->process_pages();
}
```

#### process_pages()
```php
public function process_pages()
{
    // 1. Parse response data (JSON for APIs)
    $jsonObject = json_decode($this->response_data);
    
    // 2. Extract movie list
    foreach ($jsonObject->movies as $movieObject) {
        // 3. Create MovieCrawlerPage records
        $page = new MovieCrawlerPage();
        $page->url = $detail_url;
        $page->title = $movieObject->title;
        $page->status = 'pending';
        $page->save();
    }
}
```

### MovieCrawlerPage Methods

#### fetch_page_content()
```php
public function fetch_page_content()
{
    // 1. Fetch individual movie details
    $data = Utils::get_url($this->url);
    
    // 2. Store content and trigger processing
    $this->page_content = $data;
    $this->save();
    $this->process_page_content();
}
```

#### process_page_content()
```php
public function process_page_content()
{
    // 1. Parse movie details from content
    // 2. Create MovieModel record
    $newMovie = new MovieModel();
    $newMovie->title = $this->title;
    $newMovie->url = $video_url;
    $newMovie->thumbnail_url = $poster_url;
    // ... other fields
    $newMovie->save();
    
    // 3. Mark page as processed
    $this->status = 'success';
    $this->save();
}
```

## Munowatch API Structure

Based on our previous analysis, munowatch uses these key endpoints:

### Authentication
- **Bearer Token**: `munowatch123`
- **API Key Header**: `X-Api-Key: Api-munowatch-2024`

### API Endpoints

#### Categories List
```
GET /api/categories/{userId}
Response: Array of category objects with id, name, description
```

#### Category Movies (Pagination)
```
GET /api/list/p/{categoryId}/{userId}/{lastId}
Parameters:
- categoryId: Category ID
- userId: User ID
- lastId: Last movie ID for pagination (0 for first page)

Response: Array of movie objects
```

#### Movie Detail
```
GET /api/video/{movieId}/{userId}
Response: Complete movie object with all metadata
```

### Munowatch Movie Data Structure
```json
{
  "id": 123,
  "video_title": "Movie Title",
  "full_video_name": "Full Movie Name",
  "description": "Movie description...",
  "thumbnail": "https://munowatch.com/thumbnails/movie.jpg",
  "playingUrl": "https://munowatch.com/videos/movie.mp4",
  "duration": "02:30:00",
  "genre": "Action",
  "vjname": "VJ Name",
  "category_id": 1,
  "create_date": "2024-01-01",
  "mstatus": "Active",
  "access": "Free"
}
```

## Implementation Plan

### Step 1: Register Munowatch Website

```php
// Database record for munowatch
$munowatch = new MovieCrawlerWebsite();
$munowatch->name = 'Munowatch API';
$munowatch->url = 'https://munowatch.com/api/list/p/{category_id}/3/{page}';
$munowatch->about = 'Munowatch movie streaming API integration';
$munowatch->priority = 1;
$munowatch->page_number = 0;
$munowatch->max_page = 1000; // Will be determined dynamically
$munowatch->status = 'Active';
$munowatch->slug = 'munowatch';
$munowatch->token = 'munowatch123'; // Bearer token
$munowatch->email = 'Api-munowatch-2024'; // Store API key in email field
$munowatch->save();
```

### Step 2: Extend MovieCrawlerWebsite Model

Add munowatch-specific methods to `MovieCrawlerWebsite.php`:

```php
const MUNOWATCH = 'munowatch';

public function get_next_page_link()
{
    if ($this->slug == self::MUNOWATCH) {
        return $this->get_munowatch_next_page();
    }
    // ... existing code
}

private function get_munowatch_next_page()
{
    // Get categories and iterate through them
    $categories = $this->getMunowatchCategories();
    
    // Implement pagination logic for each category
    $currentCategory = $this->getCurrentCategory();
    $page = (int)$this->page_number;
    
    $this->last_page_url = str_replace(
        ['{category_id}', '{page}'],
        [$currentCategory, $page],
        $this->url
    );
    
    return $this->last_page_url;
}

public function process_pages()
{
    if ($this->slug == self::MUNOWATCH) {
        $this->process_munowatch_pages();
        return;
    }
    // ... existing code
}

private function process_munowatch_pages()
{
    try {
        $jsonObject = json_decode($this->response_data, true);
        
        if (!$jsonObject || !isset($jsonObject['data'])) {
            throw new \Exception('Invalid munowatch API response');
        }
        
        $movies = $jsonObject['data'];
        $newMoviesCount = 0;
        
        foreach ($movies as $movieData) {
            // Check if movie already exists
            $existingPage = MovieCrawlerPage::where([
                'movie_crawler_website_id' => $this->id,
                'movie_id' => $movieData['id']
            ])->first();
            
            if ($existingPage) {
                continue;
            }
            
            // Create new page record
            $page = new MovieCrawlerPage();
            $page->movie_crawler_website_id = $this->id;
            $page->title = $movieData['video_title'];
            $page->slug = 'movie-' . $movieData['id'];
            $page->url = "https://munowatch.com/api/video/{$movieData['id']}/3";
            $page->movie_id = $movieData['id'];
            $page->status = 'pending';
            $page->type = 'Movie'; // Determine from API data
            $page->row_id = $movieData['id'];
            $page->save();
            
            $newMoviesCount++;
        }
        
        $this->new_movies_found = $newMoviesCount;
        $this->total_movies_found = count($movies);
        $this->fetch_status = 'success';
        $this->save();
        
    } catch (\Exception $e) {
        $this->fetch_status = 'failed';
        $this->error_message = $e->getMessage();
        $this->save();
        throw $e;
    }
}

private function getMunowatchCategories()
{
    $headers = [
        'Authorization: Bearer ' . $this->token,
        'X-Api-Key: ' . $this->email, // Using email field for API key
        'Content-Type: application/json'
    ];
    
    $categoriesUrl = 'https://munowatch.com/api/categories/3';
    $response = Utils::get_url_with_headers($categoriesUrl, $headers);
    
    return json_decode($response, true);
}
```

### Step 3: Extend MovieCrawlerPage Model

Add munowatch processing to `MovieCrawlerPage.php`:

```php
public function process_page_content()
{
    if ($this->movie_crawler_website->slug == MovieCrawlerWebsite::MUNOWATCH) {
        $this->process_munowatch_movie();
        return;
    }
    // ... existing code
}

private function process_munowatch_movie()
{
    try {
        // Check for existing movie
        $existing = MovieModel::where('external_url', $this->url)
            ->orWhere('imdb_id', $this->movie_id)
            ->first();
            
        if ($existing) {
            $this->status = 'skipped';
            $this->error_message = 'Movie already exists';
            $this->save();
            return;
        }
        
        // Parse munowatch movie data
        $movieData = json_decode($this->page_content, true);
        
        if (!$movieData) {
            throw new \Exception('Invalid movie data received');
        }
        
        // Create new movie record
        $movie = new MovieModel();
        
        // Map munowatch fields to katogo fields
        $movie->title = $movieData['video_title'] ?? $this->title;
        $movie->description = $movieData['description'] ?? '';
        $movie->url = $movieData['playingUrl'] ?? '';
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
        $movie->vj = $movieData['vjname'] ?? 'Munowatch';
        $movie->stars = 'Munowatch';
        $movie->imdb_id = $this->movie_id;
        $movie->imdb_url = 'Munowatch-' . $this->movie_id;
        
        // Status and access
        $movie->status = ($movieData['mstatus'] === 'Active') ? 'Active' : 'Inactive';
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
        
        // Testing status (mark as tested since it's from API)
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

private function extractYear($dateString)
{
    if (empty($dateString)) {
        return null;
    }
    
    $year = (int) substr($dateString, 0, 4);
    return ($year > 1900 && $year <= date('Y')) ? $year : null;
}
```

### Step 4: Extend Utils Class

Add HTTP header support to `Utils.php`:

```php
public static function get_url_with_headers($url, $headers = [])
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
```

## Code Examples

### Complete Implementation Files

#### 1. Database Seeder
Create `database/seeders/MunowatchCrawlerSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\MovieCrawlerWebsite;
use Illuminate\Database\Seeder;

class MunowatchCrawlerSeeder extends Seeder
{
    public function run()
    {
        MovieCrawlerWebsite::create([
            'name' => 'Munowatch API',
            'url' => 'https://munowatch.com/api/list/p/{category_id}/3/{page}',
            'about' => 'Munowatch movie streaming API integration for cloning movie data',
            'priority' => 1,
            'page_number' => 0,
            'max_page' => 1000,
            'status' => 'Active',
            'slug' => 'munowatch',
            'token' => 'munowatch123',
            'email' => 'Api-munowatch-2024',
            'last_fetched_at' => now(),
        ]);
    }
}
```

#### 2. Enhanced MovieCrawlerWebsite Model
Complete `app/Models/MovieCrawlerWebsite.php` additions:

```php
// Add to existing constants
const MUNOWATCH = 'munowatch';

// Add to get_next_page_link() method
public function get_next_page_link()
{
    if ($this->slug == self::MY_VJ) {
        // ... existing ugawatch code
    } elseif ($this->slug == self::MUNOWATCH) {
        return $this->get_munowatch_next_page();
    } else {
        throw new \Exception('Invalid slug');
    }
}

// Add to process_pages() method
public function process_pages()
{
    if ($this->slug == self::MY_VJ) {
        // ... existing ugawatch code
    } elseif ($this->slug == self::MUNOWATCH) {
        $this->process_munowatch_pages();
    } else {
        throw new \Exception('Invalid slug when processing pages');
    }
}

// Add munowatch-specific methods
private function get_munowatch_next_page()
{
    // Category rotation logic
    $categories = [1, 2, 3, 4, 5]; // Action, Comedy, Drama, Horror, Sci-Fi
    $currentCategoryIndex = $this->getCurrentCategoryIndex();
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
    
    // Rotate to next category if needed
    if ($this->page_number > 50) { // Max pages per category
        $this->page_number = 0;
        $this->rotateMunowatchCategory();
    }
    
    return $this->last_page_url;
}

private function getCurrentCategoryIndex()
{
    return (int)($this->about ?? 0); // Use about field to store current category index
}

private function rotateMunowatchCategory()
{
    $currentIndex = $this->getCurrentCategoryIndex();
    $nextIndex = ($currentIndex + 1) % 5; // 5 categories
    $this->about = (string)$nextIndex;
}

private function process_munowatch_pages()
{
    try {
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'X-Api-Key: ' . $this->email,
            'Content-Type: application/json'
        ];
        
        // Re-fetch with proper headers
        $response = Utils::get_url_with_headers($this->last_page_url, $headers);
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
            // Skip if movie already exists
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
        $this->last_fetched_at = now();
        $this->save();
        
    } catch (\Exception $e) {
        $this->fetch_status = 'failed';
        $this->error_message = $e->getMessage();
        $this->last_fetched_at = now();
        $this->save();
        throw $e;
    }
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
    
    return 'Movie';
}
```

## Testing Guide

### 1. Database Setup
```sql
-- Run migration
php artisan migrate

-- Run seeder
php artisan db:seed --class=MunowatchCrawlerSeeder

-- Verify website registration
SELECT * FROM movie_crawler_websites WHERE slug = 'munowatch';
```

### 2. Manual Testing
```php
// Test URL generation
$munowatch = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
$munowatch->get_next_page_link();
echo $munowatch->last_page_url;

// Test page content fetching
$munowatch->get_next_page_content();

// Check for created pages
$pages = MovieCrawlerPage::where('movie_crawler_website_id', $munowatch->id)
    ->where('status', 'pending')
    ->get();
echo "Created {$pages->count()} pages";
```

### 3. Crawler Route Testing
```bash
# Test full crawler execution
curl http://localhost/katogo/crawler

# Monitor progress
tail -f storage/logs/laravel.log
```

### 4. Movie Processing Testing
```php
// Process individual pages
$page = MovieCrawlerPage::where('status', 'pending')->first();
$page->fetch_page_content();

// Check created movies
$movies = MovieModel::where('stars', 'Munowatch')->get();
echo "Created {$movies->count()} movies";
```

## Troubleshooting

### Common Issues

#### 1. API Authentication Errors
**Problem**: 401/403 responses from munowatch API
**Solution**: Verify token and API key in database
```php
$munowatch = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
$munowatch->token = 'correct_bearer_token';
$munowatch->email = 'correct_api_key';
$munowatch->save();
```

#### 2. JSON Parsing Errors
**Problem**: Invalid JSON response
**Solution**: Add response validation
```php
private function validateMunowatchResponse($response)
{
    $decoded = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('JSON decode error: ' . json_last_error_msg());
    }
    
    return $decoded;
}
```

#### 3. Duplicate Movies
**Problem**: Same movies being created multiple times
**Solution**: Enhanced duplicate checking
```php
private function checkMunowatchDuplicate($movieData)
{
    return MovieModel::where('imdb_id', $movieData['id'])
        ->orWhere('title', $movieData['video_title'])
        ->orWhere('external_url', 'LIKE', '%' . $movieData['id'] . '%')
        ->exists();
}
```

#### 4. Memory/Timeout Issues
**Problem**: Scripts timing out during large imports
**Solution**: Batch processing
```php
// In Utils::fetch_pages_content()
$pages = MovieCrawlerPage::where('status', 'pending')
    ->where('movie_crawler_website_id', $munowatchId)
    ->orderBy('id', 'desc')
    ->limit(5) // Reduced batch size
    ->get();
```

### Error Monitoring
```php
// Add to MovieCrawlerWebsite::process_munowatch_pages()
Log::info('Munowatch processing started', [
    'website_id' => $this->id,
    'url' => $this->last_page_url,
    'page_number' => $this->page_number
]);

// Add to MovieCrawlerPage::process_munowatch_movie()
Log::info('Munowatch movie processed', [
    'page_id' => $this->id,
    'movie_title' => $movieData['video_title'] ?? 'Unknown',
    'status' => $this->status
]);
```

## Important Notes

### Field Mapping
Ensure proper mapping between munowatch and katogo fields:
- `video_title` → `title`
- `playingUrl` → `url`
- `thumbnail` → `thumbnail_url` & `image_url`
- `vjname` → `vj`
- `id` → `imdb_id`

### Category Management
Munowatch categories need to be mapped to katogo categories:
```php
private function mapMunowatchCategory($categoryId)
{
    $mapping = [
        1 => 'Action',
        2 => 'Comedy', 
        3 => 'Drama',
        4 => 'Horror',
        5 => 'Sci-Fi'
    ];
    
    return $mapping[$categoryId] ?? 'General';
}
```

### Performance Optimization
- Process movies in small batches (5-10 at a time)
- Add delays between API calls to avoid rate limiting
- Monitor memory usage during large imports
- Use database transactions for data consistency

### Data Validation
Always validate API responses before processing:
```php
private function validateMovieData($data)
{
    $required = ['id', 'video_title', 'playingUrl'];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new \Exception("Missing required field: $field");
        }
    }
}
```

This comprehensive guide provides everything needed to implement munowatch crawler integration following the proven 3-level architecture used by the existing ugawatch system.