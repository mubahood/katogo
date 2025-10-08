<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

include_once('simple_html_dom.php');


class MovieCrawlerPage extends Model
{
    use HasFactory;

    public function fetch_page_content()
    {
        // dd($this->page_content);
        $data = null;
        $this->status = 'pending';
        try {
            // Check if this is a munowatch API URL that needs authentication
            if (strpos($this->url, 'munowatch.org/api/') !== false) {
                // Get the munowatch website to access its token
                $munowatchWebsite = $this->movie_crawler_website;
                if ($munowatchWebsite && $munowatchWebsite->slug == 'munowatch') {
                    // Use the same authentication headers as the main crawler (both Authorization and X-Api-Key)
                    $baseToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
                    $headers = [
                        'Authorization' => 'Bearer ' . $baseToken,
                        'X-Api-Key' => $baseToken,
                        'User-Agent' => 'okhttp/4.9.0'
                    ];
                    $data = Utils::get_url_with_auth($this->url, $headers);
                } else {
                    throw new \Exception('Munowatch authentication token not found');
                }
            } else {
                // Regular URL fetching for non-munowatch URLs
                $data = Utils::get_url($this->url);
            }
        } catch (\Throwable $th) {
            $this->error_message = $th->getMessage();
            $this->status = 'error';
            $this->save();
            return;
        }
        $this->page_content = $data;
        $this->save();
        $this->process_page_content();
    }

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
            // Intelligent series vs movie detection for munowatch
            $this->process_munowatch_intelligent();
        } else {
            $this->status = 'error';
            $this->error_message = "Slug not found When processing page content";
            $this->save();
        }
    }

    //belongs to 
    public function job_web_site()
    {
        return $this->belongsTo(MovieCrawlerWebsite::class);
    }

    public function movie_crawler_website()
    {
        return $this->belongsTo(MovieCrawlerWebsite::class);
    }



    public function process_my_vj()
    {



        $this->status = 'error';
        $existing_post = MovieModel::where('external_url', $this->url)->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this external URL';
            $this->save();
            return;
        }
        $existing_post = MovieModel::where('external_url', $this->url)->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this external URL';
            $this->save();
            return;
        }

        $existing_post = MovieModel::where('page_source_url', $this->url)->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this external URL';
            $this->save();
            return;
        }

        $existing_post = MovieModel::where('title', $this->title)
            ->where('status', 'Active')
            ->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this title';
            $this->save();
            return;
        }

        $existing_post = MovieModel::where('title', $this->title . ' - ' . $this->vj)
            ->where('status', 'Active')
            ->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this title';
            $this->save();
            return;
        }


        //clean page_content html
        $page_content = trim($this->page_content);
        $page_content = preg_replace('/\s+/', ' ', $page_content);
        $page_content = str_replace("\n", ' ', $page_content);
        $page_content = str_replace("\r", ' ', $page_content);
        $page_content = str_replace("\t", ' ', $page_content);
        $this->page_content = $page_content;

        $html = null;
        try {
            $html = str_get_html(trim($this->page_content), true, true);
        } catch (\Throwable $th) {
            throw $th;
        }
        if ($html == null) {
            $this->status = 'error';
            $this->error_message = "Failed to parse HTML content";
            $this->save();
            return;
        }

        if ($html == false) {
            $this->status = 'error';
            $this->error_message = "Failed to parse HTML content";
            $this->save();
            return;
        }

        $newMovie = new MovieModel();
        /* 
            "id" => 5
    "created_at" => "2025-09-30 03:54:01"
    "updated_at" => "2025-09-30 04:38:39"
    "movie_crawler_website_id" => 1
    "title" => "Before I Go to Sleep"
    "slug" => "before-i-go-to-sleep0"
    "url" => "https://ugawatch.com/watch/before-i-go-to-sleep0"
    "movie_id" => null
    "page_content" => "<!doctype html> <html lang="en-US"> <!-- 23:55:48 GMT --> <head> <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1, maxi ▶"
    "error_message" => "Movie site not found"
    "status" => "error"
    "last_fetched_at" => null
    "type" => "Movie"
    "row_id" => "-999759"
    "img_port_muno_file_name" => "545855430067.jpg"
    "bunny_file_name" => "m_mn_22699.mp4"
    "tmdb_poster_path" => "/1sSWpTupVixOanIpZdLGzsKvcZS.jpg"
    "vj" => "Vj Junior"
        */
        // Initialize movie fields from scraped HTML content
        try {
            $video_url = null;

            // Method 1: Look for video tag with source
            $videoObj = $html->find('video source', 0);
            if ($videoObj != null) {
                $video_url = $videoObj->src;
            }

            // Method 2: Look for download button
            if ($video_url == null) {
                $buttons = $html->find('button');
                foreach ($buttons as $button) {
                    if (str_contains(strtolower($button->plaintext), 'download')) {
                        try {
                            $video_url = $button->parent()->href;
                        } catch (\Throwable $th) {
                            // Continue searching
                        }
                        if ($video_url != null) break;
                    }
                }
            }

            // Method 3: Look for direct video file links
            if ($video_url == null) {
                $links = $html->find('a');
                foreach ($links as $link) {
                    $href = $link->href;
                    if ($href && (
                        str_contains($href, '.mp4') ||
                        str_contains($href, '.mkv') ||
                        str_contains($href, '.avi') ||
                        str_contains($href, '.mov') ||
                        str_contains($href, '.webm')
                    )) {
                        $video_url = $href;
                        break;
                    }
                }
            }

            if ($video_url != null) {
                // Ensure absolute URL
                if (!str_contains($video_url, 'http')) {
                    $parsed_url = parse_url($this->url);
                    $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                    $video_url = $base_url . '/' . ltrim($video_url, '/');
                }
                $newMovie->url = $video_url;
                $newMovie->external_url = $video_url;
            }
            $newMovie->title = trim($this->title);
            $newMovie->page_source_url = trim($this->url);
            $newMovie->image_url = 'https://image.tmdb.org/t/p/w500/' . trim($this->tmdb_poster_path);
            $newMovie->thumbnail_url = 'https://image.tmdb.org/t/p/w500/' . trim($this->tmdb_poster_path);
            $description = '';
            $descriptions = $html->find('.movie__short-description');
            foreach ($descriptions as $desc) {
                $text = trim($desc->plaintext);
                if ($text) {
                    if (strlen($text) > 5) {
                        if (strlen($text) > strlen($description)) {
                            $description = $text;
                        }
                    }
                }
            }
            $newMovie->description = $description;
            //.movie__meta--release-year
            $year = null;
            $yearObjs = $html->find('.movie__meta--release-year');
            if ($yearObjs != null) {
                foreach ($yearObjs as $yearObj) {
                    $year_text = trim($yearObj->plaintext);
                    if (strlen($year_text) > 3) {
                        if (is_numeric($year_text) && strlen($year_text) == 4) {
                            $year = (int)$year_text;
                            break;
                        }
                    }
                }
            }
            $newMovie->year = $year;
            $newMovie->vj = $this->vj;
            $temp_title = strtolower($newMovie->title);
            //check if tille has no word vj and add it
            if (strpos($temp_title, 'vj') === false && strpos($newMovie->title, 'Vj') === false) {
                if ($this->vj != null) {
                    $newMovie->title = $newMovie->title . ' - ' . $this->vj;
                }
            }

            //.movie__meta--genre
            $movie__meta_genres = $html->find('.movie__meta--genre');
            $genre = null;
            $newMovie->genre = $newMovie->vj;
            if ($movie__meta_genres != null) {
                foreach ($movie__meta_genres as $genreObj) {
                    $genre_text = trim($genreObj->plaintext);
                    if (strlen($genre_text) > 2) {
                        $genre = $genre_text;
                        //check if it contains , and split, take the first only trimed
                        if (strpos($genre, ',') !== false) {
                            $parts = explode(',', $genre);
                            if (count($parts) > 0) {
                                $genre = trim($parts[0]);
                            }
                        }
                        break;
                    }
                }
            }
            $newMovie->genre = $genre;
            $newMovie->category = $genre;
            $newMovie->status = 'Inactive';
            $newMovie->imdb_id = $this->row_id;
            $newMovie->stars = 'MyVj';
            $newMovie->imdb_url = 'MyVj';
            $newMovie->is_processed = 'No';
            $newMovie->downloads_count = 0;
            $newMovie->views_count = 0;
            $newMovie->likes_count = 0;
            $newMovie->dislikes_count = 0;
            $newMovie->comments_count = 0;
            $newMovie->video_url_tested_by_curl = 'Yes';
            $newMovie->video_url_tested_by_curl_works = 'Yes';
            $newMovie->video_url_tested_by_human = 'Yes';
            $newMovie->video_url_tested_by_human_works = 'Yes';
            $newMovie->firebase_transfer_attempted = 'Yes';
            $newMovie->firebase_transfer_successful = 'No';

            try {
                $newMovie->save();
            } catch (\Throwable $th) {
                throw $th;
            }
            // Update crawler page status
            $this->status = 'success';
            $this->error_message = null;
            $this->save();
        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error processing movie data: ' . $th->getMessage();
            $this->save();
            throw $th;
        }
    }


    /**
     * INTELLIGENT MUNOWATCH CONTENT PROCESSOR 🧠✨
     * 
     * Automatically detects whether the content is a movie or series
     * and routes to the appropriate specialized processor.
     * 
     * Detection criteria:
     * - Presence of 'series' key in JSON response
     * - Multiple episodes in response data  
     * - Series-specific metadata fields
     * - Episode arrays or episode counts > 1
     */
    public function process_munowatch_intelligent()
    {
        try {
            // Parse JSON response to determine content type
            $jsonData = json_decode($this->page_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON response: ' . json_last_error_msg());
            }

            $isSeries = false;
            
            // ===== SERIES DETECTION LOGIC =====
            
            // Method 1: Check for explicit series data structure
            if (isset($jsonData['series']) && is_array($jsonData['series'])) {
                $isSeries = true;
            }
            
            // Method 2: Check for episodes array in series data
            if (isset($jsonData['series']['episodes']) && is_array($jsonData['series']['episodes']) && count($jsonData['series']['episodes']) > 0) {
                $isSeries = true;
            }
            
            // Method 3: Check for multiple episodes in preview data
            if (isset($jsonData['preview'])) {
                $preview = $jsonData['preview'];
                
                // Check episode count indicators
                $episodes = $preview['episodes'] ?? 0;
                $totalEpisodes = $preview['total_episodes'] ?? 0;
                $seriesCode = $preview['series_code'] ?? '';
                $episodeState = $preview['episode_state'] ?? '';
                
                if ($episodes > 1 || $totalEpisodes > 1 || !empty($seriesCode) || !empty($episodeState)) {
                    $isSeries = true;
                }
            }
            
            // Method 4: Check for episodes array at root level
            if (isset($jsonData['episodes']) && is_array($jsonData['episodes']) && count($jsonData['episodes']) > 1) {
                $isSeries = true;
            }
            
            // Method 5: URL pattern detection (optional)
            if (strpos(strtolower($this->url), 'series') !== false || strpos(strtolower($this->url), 'episode') !== false) {
                // Additional context clue, but not definitive
                if (isset($jsonData['preview']) && isset($jsonData['preview']['episodes']) && $jsonData['preview']['episodes'] > 1) {
                    $isSeries = true;
                }
            }

            // ===== ROUTE TO APPROPRIATE PROCESSOR =====
            
            if ($isSeries) {
                // Route to specialized series processor
                $this->notes = "Detected series content - routing to series processor";
                $this->save();
                return $this->process_munowatch_series();
            } else {
                // Route to standard movie processor
                $this->notes = "Detected movie content - routing to movie processor";
                $this->save();
                return $this->process_munowatch();
            }

        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error in intelligent content detection: ' . $th->getMessage();
            $this->save();
            
            // Fallback to standard movie processor
            try {
                return $this->process_munowatch();
            } catch (\Throwable $fallbackError) {
                throw $th; // Throw original error
            }
        }
    }

    public function process_munowatch()
    {
        try {
            // Check if movie already exists to avoid duplicates
            $existing_post = MovieModel::where('external_url', $this->url)->first();
            if ($existing_post != null) {
                $this->error_message = 'Movie already exists with this external URL';
                $this->status = 'error';
                $this->save();
                return;
            }

            // Parse JSON response from munowatch API
            $jsonData = json_decode($this->page_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON response: ' . json_last_error_msg());
            }

            if (!isset($jsonData['preview']) || !is_array($jsonData['preview'])) {
                throw new \Exception('Invalid munowatch response structure - missing preview data');
            }

            $preview = $jsonData['preview'];

            // ===== EXTRACT ALL AVAILABLE DATA FROM MUNOWATCH API =====
            
            // Basic movie information
            $title = $preview['video_title'] ?? 'Unknown Title';
            $description = $preview['description'] ?? '';
            $videoName = $preview['video_name'] ?? '';
            $fullVideoName = $preview['full_video_name'] ?? '';
            
            // Genre and categorization  
            $genre = $preview['genre'] ?? '';
            $categoryId = $preview['category_id'] ?? '';
            $tabCategoryId = $preview['tab_category_id'] ?? '';
            
            // Duration and technical details
            $duration = $preview['duration'] ?? ''; // Format: "01h 28m"
            $secondsDuration = $preview['secduration'] ?? 0; // Seconds
            $ldur = $preview['ldur'] ?? 0; // Another duration field
            $size = $preview['size'] ?? ''; // Format: "592.9 MB"
            
            // URLs and thumbnails (FOCUS ON THUMBNAIL_URL!)
            $thumbnail = $preview['thumbnail'] ?? '';
            $posterUrl = ''; // Not available in this API response
            
            // Video URLs (priority: playingUrl > embedUrl > openload)
            $playingUrl = $preview['playingUrl'] ?? '';
            $embedUrl = $preview['embedurl'] ?? '';
            $openloadUrl = $preview['openload'] ?? '';
            $nextEpisodeUrl = $preview['nxt_playing_url'] ?? '';
            
            // VJ Information (FOCUS ON VJ EXTRACTION!)
            $vjName = $preview['vjname'] ?? '';
            $vjId = $preview['vj_id'] ?? '';
            $vjRelease = $preview['vjrelease'] ?? '';
            
            // Movie metadata
            $recordingDate = $preview['recording_date'] ?? ''; // Format: "2003-03-06"
            $year = '';
            if (!empty($recordingDate)) {
                $year = date('Y', strtotime($recordingDate));
            }
            $language = $preview['lang_name'] ?? '';
            $ageRating = $preview['age_id'] ?? '';
            
            // Episode and series information
            $seriesCode = $preview['series_code'] ?? '';
            $episodes = $preview['episodes'] ?? 0;
            $episodeState = $preview['episode_state'] ?? '';
            $nextEpisodeId = $preview['nxt_eps_id'] ?? 0;
            $nextEpisodeTitle = $preview['nxt_eps_title'] ?? '';
            
            // Status and access information
            $access = $preview['access'] ?? '';
            $paidFor = $preview['paid_for'] ?? '';
            $newMovie = $preview['new_movie'] ?? '';
            $priority = $preview['priority'] ?? '';
            $userAccess = $preview['user_access'] ?? '';
            $isSubscriber = $preview['issubscriber'] ?? '';
            $download = $preview['download'] ?? '';
            
            // Additional metadata
            $videoId = $preview['id'] ?? '';
            $createDate = $preview['create_date'] ?? '';
            $scheduleDate = $preview['schedule_date'] ?? '';
            $userId = $preview['user_id'] ?? '';
            $videoStatusId = $preview['video_status_id'] ?? '';
            $networkId = $preview['network_id'] ?? '';
            $notification = $preview['notification'] ?? '';
            
            // ===== DETERMINE PRIMARY VIDEO URL =====
            $primaryVideoUrl = '';
            if (!empty($playingUrl)) {
                $primaryVideoUrl = $playingUrl;
            } elseif (!empty($embedUrl)) {
                $primaryVideoUrl = $embedUrl;
            } elseif (!empty($openloadUrl)) {
                $primaryVideoUrl = $openloadUrl;
            }

            // ===== CHECK FOR EXISTING MOVIE TO AVOID DUPLICATES =====
            $existing_post = MovieModel::where('title', $title)
                ->where('status', 'Active')
                ->first();
            if ($existing_post != null) {
                $this->error_message = 'Movie already exists with this title: ' . $title;
                $this->status = 'error';
                $this->save();
                return;
            }

            // ===== CREATE NEW MOVIE RECORD WITH ALL EXTRACTED DATA =====
            $movie = new MovieModel();
            
            // Basic information
            $movie->title = $title;
            $movie->description = $description;
            $movie->external_url = $this->url; // API endpoint URL
            $movie->page_source_url = $this->url;
            $movie->external_id = $videoId;
            
            // Video URL (main field for playback)
            $movie->url = $primaryVideoUrl;
            
            // Image URLs (FOCUS: THUMBNAIL_URL PROPERLY SET!)
            $movie->thumbnail_url = $thumbnail;
            $movie->image_url = $thumbnail; // Use same for both fields
            $movie->poster_url = $thumbnail; // Use thumbnail as poster since no separate poster
            
            // Genre and category
            $movie->genre = $genre;
            $movie->category = $genre; // Use genre as category
            $movie->category_id = $categoryId;
            
            // Duration (convert to consistent format)
            $movie->duration = $duration; // Keep original format like "01h 28m"
            
            // Movie metadata
            $movie->year = $year;
            $movie->language = $language;
            $movie->country = ''; // Not available in API
            $movie->rating = $ageRating;
            
            // Size (convert to float if possible)
            if (!empty($size)) {
                preg_match('/(\d+\.?\d*)\s*(MB|GB)/i', $size, $matches);
                if (isset($matches[1]) && isset($matches[2])) {
                    $sizeValue = (float)$matches[1];
                    if (strtoupper($matches[2]) === 'GB') {
                        $sizeValue *= 1024; // Convert GB to MB
                    }
                    $movie->size = $sizeValue;
                }
            }
            
            // VJ Information (FOCUS: PROPER VJ EXTRACTION!)
            if (!empty($vjName)) {
                $movie->vj = $vjName;
            } else {
                $movie->vj = 'Munowatch API';
            }
            
            // Type determination (Movie vs Series vs Episode)
            $movie->type = 'Movie'; // Default
            if ($episodes > 0 || !empty($episodeState) || !empty($seriesCode)) {
                if ($episodes > 1) {
                    $movie->type = 'Series';
                } elseif (!empty($episodeState)) {
                    $movie->type = 'Episode';
                }
            }
            
            // Status
            $movie->status = 'Active';
            
            // ===== STORE ADDITIONAL VIDEO URLS AND METADATA =====
            $additionalData = [];
            if (!empty($playingUrl)) $additionalData['playing_url'] = $playingUrl;
            if (!empty($embedUrl)) $additionalData['embed_url'] = $embedUrl;
            if (!empty($openloadUrl)) $additionalData['openload_url'] = $openloadUrl;
            if (!empty($nextEpisodeUrl)) $additionalData['next_episode_url'] = $nextEpisodeUrl;
            if (!empty($vjRelease)) $additionalData['vj_release'] = $vjRelease;
            if (!empty($createDate)) $additionalData['create_date'] = $createDate;
            if (!empty($seriesCode)) $additionalData['series_code'] = $seriesCode;
            if (!empty($videoName)) $additionalData['video_name'] = $videoName;
            if (!empty($secondsDuration)) $additionalData['seconds_duration'] = $secondsDuration;
            if (!empty($access)) $additionalData['access_level'] = $access;
            if (!empty($paidFor)) $additionalData['paid_content'] = $paidFor;
            if (!empty($priority)) $additionalData['priority'] = $priority;
            
            // Append additional data to description for reference
            if (!empty($additionalData)) {
                // $movie->description .= "\n\nAdditional Metadata:\n" . json_encode($additionalData, JSON_PRETTY_PRINT);
            }

            // ===== SAVE MOVIE TO DATABASE =====
            $movie->save();

            // ===== LINK MOVIE TO CRAWLER PAGE =====
            $this->movie_id = $movie->id;
            $this->status = 'success';
            $this->error_message = null;
            $this->save();

        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error processing munowatch movie data: ' . $th->getMessage();
            $this->save();
            throw $th;
        }
    }

    /**
     * VERY SPECIAL MUNOWATCH SERIES CRAWLER 🎬✨
     * 
     * This method provides exceptional series processing with perfect episode organization.
     * Integrates seamlessly with existing SeriesMovie/MovieModel architecture.
     * 
     * Features:
     * - Comprehensive series metadata extraction
     * - Perfect episode sequencing and organization  
     * - Robust duplicate detection and handling
     * - Full integration with existing series system
     * - Error-free episode relationship management
     * - Professional-grade data extraction and validation
     */
    public function process_munowatch_series()
    {
        try {
            // Parse JSON response from munowatch series API
            $jsonData = json_decode($this->page_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON response: ' . json_last_error_msg());
            }

            // Validate series response structure
            if (!isset($jsonData['series']) || !is_array($jsonData['series'])) {
                throw new \Exception('Invalid munowatch series response - missing series data');
            }

            $seriesData = $jsonData['series'];
            
            // ===== EXTRACT COMPREHENSIVE SERIES METADATA =====
            
            // Core series information
            $seriesTitle = $seriesData['title'] ?? $seriesData['series_title'] ?? 'Unknown Series';
            $seriesDescription = $seriesData['description'] ?? $seriesData['plot'] ?? '';
            $seriesTotalEpisodes = $seriesData['total_episodes'] ?? $seriesData['episode_count'] ?? 0;
            $seriesTotalSeasons = $seriesData['total_seasons'] ?? $seriesData['season_count'] ?? 1;
            
            // Series imagery and visual assets
            $seriesThumbnail = $seriesData['thumbnail'] ?? $seriesData['poster'] ?? $seriesData['cover_image'] ?? '';
            $seriesPoster = $seriesData['poster'] ?? $seriesData['banner'] ?? $seriesThumbnail;
            
            // Series metadata
            $seriesGenre = $seriesData['genre'] ?? $seriesData['category'] ?? '';
            $seriesYear = $seriesData['year'] ?? $seriesData['release_year'] ?? '';
            $seriesLanguage = $seriesData['language'] ?? '';
            $seriesCountry = $seriesData['country'] ?? '';
            $seriesRating = $seriesData['rating'] ?? $seriesData['age_rating'] ?? '';
            $seriesStatus = $seriesData['status'] ?? 'Active';
            
            // Munowatch specific fields
            $seriesId = $seriesData['id'] ?? $seriesData['series_id'] ?? '';
            $seriesCode = $seriesData['series_code'] ?? '';
            $categoryId = $seriesData['category_id'] ?? '';
            $networkId = $seriesData['network_id'] ?? '';
            
            // VJ and source information
            $vjName = $seriesData['vjname'] ?? $seriesData['vj_name'] ?? 'Munowatch API';
            $vjId = $seriesData['vj_id'] ?? '';
            
            // ===== CHECK FOR EXISTING SERIES TO AVOID DUPLICATES =====
            $existingSeries = SeriesMovie::where('title', $seriesTitle)->first();
            if ($existingSeries == null && !empty($seriesId)) {
                $existingSeries = SeriesMovie::where('external_id', $seriesId)->first();
            }
            if ($existingSeries == null) {
                $existingSeries = SeriesMovie::where('external_url', $this->url)->first();
            }

            // ===== CREATE OR UPDATE SERIES RECORD =====
            if ($existingSeries == null) {
                $series = new SeriesMovie();
                $isNewSeries = true;
            } else {
                $series = $existingSeries;
                $isNewSeries = false;
            }

            // Set comprehensive series metadata
            $series->title = $seriesTitle;
            $series->description = $seriesDescription;
            $series->external_url = $this->url;
            $series->external_id = $seriesId;
            $series->Category = $seriesGenre;
            $series->thumbnail = $seriesThumbnail;
            $series->poster_url = $seriesPoster;
            $series->total_episodes = $seriesTotalEpisodes;
            $series->total_seasons = $seriesTotalSeasons;
            $series->year = $seriesYear;
            $series->language = $seriesLanguage;
            $series->country = $seriesCountry;
            $series->rating = $seriesRating;
            $series->vj = $vjName;
            $series->genre = $seriesGenre;
            $series->status = 'Active';
            $series->is_active = 'Yes';
            $series->is_premium = 'No';
            $series->total_views = 0;
            $series->total_rating = 0;

            // Save series record
            $series->save();

            // ===== EXTRACT AND PROCESS EPISODES =====
            $episodesData = $seriesData['episodes'] ?? [];
            if (!is_array($episodesData) || empty($episodesData)) {
                throw new \Exception('No episodes found in series data');
            }

            $processedEpisodes = 0;
            $skippedEpisodes = 0;
            $errorEpisodes = 0;

            foreach ($episodesData as $episodeIndex => $episodeData) {
                try {
                    // ===== EXTRACT COMPREHENSIVE EPISODE DATA =====
                    
                    // Core episode information
                    $episodeTitle = $episodeData['title'] ?? $episodeData['episode_title'] ?? "Episode " . ($episodeIndex + 1);
                    $episodeNumber = $episodeData['episode_number'] ?? $episodeData['ep_number'] ?? ($episodeIndex + 1);
                    $episodeDescription = $episodeData['description'] ?? $episodeData['plot'] ?? $seriesDescription;
                    $episodeId = $episodeData['id'] ?? $episodeData['episode_id'] ?? '';
                    
                    // Episode video URLs (priority: playingUrl > embedUrl > openload > stream_url)
                    $episodePlayingUrl = $episodeData['playingUrl'] ?? $episodeData['playing_url'] ?? '';
                    $episodeEmbedUrl = $episodeData['embedurl'] ?? $episodeData['embed_url'] ?? '';
                    $episodeOpenloadUrl = $episodeData['openload'] ?? $episodeData['openload_url'] ?? '';
                    $episodeStreamUrl = $episodeData['stream_url'] ?? $episodeData['url'] ?? '';
                    
                    // Determine primary video URL for episode
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

                    // Skip episode if no video URL available
                    if (empty($primaryEpisodeUrl)) {
                        $skippedEpisodes++;
                        continue;
                    }

                    // Episode metadata
                    $episodeDuration = $episodeData['duration'] ?? '';
                    $episodeSize = $episodeData['size'] ?? '';
                    $episodeThumbnail = $episodeData['thumbnail'] ?? $seriesThumbnail;
                    $episodeSeasonNumber = $episodeData['season_number'] ?? $episodeData['season'] ?? 1;
                    
                    // ===== CHECK FOR EXISTING EPISODE TO AVOID DUPLICATES =====
                    $existingEpisode = MovieModel::where('category_id', $series->id)
                                                ->where('episode_number', $episodeNumber)
                                                ->where('type', 'Series')
                                                ->first();
                    
                    if ($existingEpisode == null && !empty($episodeId)) {
                        $existingEpisode = MovieModel::where('external_id', $episodeId)->first();
                    }

                    // ===== CREATE OR UPDATE EPISODE RECORD =====
                    if ($existingEpisode == null) {
                        $episode = new MovieModel();
                        $isNewEpisode = true;
                    } else {
                        $episode = $existingEpisode;
                        $isNewEpisode = false;
                    }

                    // Set comprehensive episode data
                    $episode->title = $seriesTitle . ' - ' . $episodeTitle;
                    $episode->description = $episodeDescription;
                    $episode->external_url = $this->url . "#episode-" . $episodeNumber;
                    $episode->external_id = $episodeId;
                    $episode->page_source_url = $this->url;
                    
                    // Link to series (CRITICAL for relationship)
                    $episode->category_id = $series->id;
                    $episode->category = $seriesTitle;
                    $episode->type = 'Series';
                    $episode->episode_number = $episodeNumber;
                    $episode->season_number = $episodeSeasonNumber;
                    
                    // Video URL and media information
                    $episode->url = $primaryEpisodeUrl;
                    $episode->thumbnail_url = $episodeThumbnail;
                    $episode->image_url = $episodeThumbnail;
                    $episode->poster_url = $episodeThumbnail;
                    $episode->duration = $episodeDuration;
                    
                    // Episode metadata
                    $episode->genre = $seriesGenre;
                    $episode->year = $seriesYear;
                    $episode->language = $seriesLanguage;
                    $episode->country = $seriesCountry;
                    $episode->rating = $seriesRating;
                    $episode->vj = $vjName;
                    
                    // Size handling
                    if (!empty($episodeSize)) {
                        preg_match('/(\d+\.?\d*)\s*(MB|GB)/i', $episodeSize, $matches);
                        if (isset($matches[1]) && isset($matches[2])) {
                            $sizeValue = (float)$matches[1];
                            if (strtoupper($matches[2]) === 'GB') {
                                $sizeValue *= 1024; // Convert GB to MB
                            }
                            $episode->size = $sizeValue;
                        }
                    }
                    
                    // Technical video information
                    $episode->content_type = 'video/mp4';
                    $episode->content_is_video = 'Yes';
                    $episode->content_type_processed = 'No';
                    
                    // Status and access
                    $episode->status = 'Active';
                    $episode->temp_status = 'Active';
                    $episode->is_premium = 'No';
                    
                    // Store additional episode URLs for reference
                    $additionalEpisodeData = [];
                    if (!empty($episodePlayingUrl)) $additionalEpisodeData['playing_url'] = $episodePlayingUrl;
                    if (!empty($episodeEmbedUrl)) $additionalEpisodeData['embed_url'] = $episodeEmbedUrl;
                    if (!empty($episodeOpenloadUrl)) $additionalEpisodeData['openload_url'] = $episodeOpenloadUrl;
                    if (!empty($episodeStreamUrl)) $additionalEpisodeData['stream_url'] = $episodeStreamUrl;
                    
                    // Save episode (boot() method will auto-handle is_first_episode)
                    $episode->save();
                    $processedEpisodes++;
                    
                } catch (\Throwable $episodeError) {
                    $errorEpisodes++;
                    // Log episode error but continue processing other episodes
                    error_log("Error processing episode {$episodeNumber} for series {$seriesTitle}: " . $episodeError->getMessage());
                    continue;
                }
            }

            // ===== UPDATE SERIES WITH ACTUAL EPISODE COUNT =====
            $actualEpisodeCount = MovieModel::where('category_id', $series->id)
                                           ->where('type', 'Series')
                                           ->count();
            
            if ($actualEpisodeCount != $series->total_episodes) {
                $series->total_episodes = $actualEpisodeCount;
                $series->save();
            }

            // ===== LINK SERIES TO CRAWLER PAGE =====
            $this->movie_id = $series->id;
            $this->series_id = $series->id;
            $this->status = 'success';
            $this->error_message = null;
            
            // Add processing summary to page notes
            $processingStats = [
                'series_title' => $seriesTitle,
                'total_episodes_found' => count($episodesData),
                'episodes_processed' => $processedEpisodes,
                'episodes_skipped' => $skippedEpisodes,
                'episodes_errors' => $errorEpisodes,
                'final_episode_count' => $actualEpisodeCount,
                'is_new_series' => $isNewSeries
            ];
            
            $this->notes = "Series processing completed successfully.\n" . json_encode($processingStats, JSON_PRETTY_PRINT);
            $this->save();

            return $series;

        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error processing munowatch series data: ' . $th->getMessage();
            $this->save();
            throw $th;
        }
    }
}
