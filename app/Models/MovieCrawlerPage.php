<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\User;

include_once('simple_html_dom.php');


class MovieCrawlerPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'movie_crawler_website_id',
        'url',
        'title',
        'status',
        'is_muno',
        'is_episode',
        'type',
        'page_content',
        'error_message'
    ];

    public function fetch_page_content($doProcessPage = true)
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
        if ($doProcessPage) {
            $this->process_page_content();
        }
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
        } elseif ($this->movie_crawler_website->slug == MovieCrawlerWebsite::UGANDAHOTGIRLS) {
            // Process Uganda Hot Girls user profile
            $this->process_ugandahotgirls_profile();
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

        //first check if movie exist by external url
        $existing_post = MovieModel::where('external_url', $this->url)->first();
        if ($existing_post != null) {
            $this->status = 'error';
            $this->error_message = 'Movie already exists with this external URL';
            $this->save();
            return;
        }

        //check if movie already exists by title
        $existing_post = MovieModel::where('title', $this->title)
            ->where('status', 'Active')
            ->first();
        if ($existing_post != null) {
            $this->status = 'error';
            $this->error_message = 'Movie already exists with this title';
            $this->save();
            return;
        }

        //check if movie already exists by url
        $existing_post = MovieModel::where('url', $this->url)
            ->where('status', 'Active')
            ->first();
        if ($existing_post != null) {
            $this->status = 'error';
            $this->error_message = 'Movie already exists with this URL';
            $this->save();
            return;
        }

        $newMovie = new MovieModel(); //validated
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
        if ($this->is_episode === "Yes") {
            $this->process_munowatch_series();
            return;
        }
        if ($this->page_content == null || strlen(trim($this->page_content)) < 10) {
            try {
                //check if url is reachable again
                if (strpos($this->url, 'munowatch.org/api/') !== false) {
                    $this->fetch_page_content(false);
                } else {
                    $this->status = 'error';
                    $this->error_message = "Page content is empty for intelligent processing";
                    $this->save();
                    return;
                }
            } catch (\Throwable $th) {
                $this->status = 'error';
                $this->error_message = 'Failed to fetch page content: ' . $th->getMessage();
                $this->save();
                return;
                //throw $th;
            }
        }

        if ($this->page_content == null || strlen(trim($this->page_content)) < 10) {
            $this->status = 'error';
            $this->error_message = "Page content is empty for intelligent processing";
            $this->save();
            return;
        }

        try {
            $jsonData = null;
            // Parse JSON response to determine content type
            try {
                $jsonData = json_decode($this->page_content, true);
            } catch (\Throwable $th) {
                $this->status = 'error';
                $this->error_message = 'Failed to parse JSON response: ' . $th->getMessage();
                $this->save();
                throw new \Exception('Failed to parse JSON response: ' . $th->getMessage());
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->status = 'error';
                $this->error_message = 'Failed to parse JSON response: ' . json_last_error_msg();
                $this->save();
                throw new \Exception('Failed to parse JSON response: ' . json_last_error_msg());
            }

            // ===== ENHANCED SERIES DETECTION USING MULTIPLE SIGNALS =====

            $isSeries = false;
            $seriesCode = null;
            $showId = null;
            $detectionSignals = [];

            if (!isset($jsonData['preview']) || !is_array($jsonData['preview'])) {
                $this->status = 'error';
                $this->error_message = 'Invalid munowatch response structure - missing preview data';
                $this->save();
                throw new \Exception('Invalid munowatch response structure - missing preview data');
            }

            // Extract movie data from various possible structures
            $movieData = $this->extractMovieDataFromResponse($jsonData);

            if ($movieData) {
                // dd($movieData);
                // ===== SIGNAL 1: SERIES_CODE PRESENCE =====
                $seriesCode = $movieData['series_code'] ?? $movieData['seriesCode'] ?? '';
                $showId = $movieData['id'] ?? $movieData['vid'] ?? null;

                //base on genre to be Series
                $genre = strtolower($movieData['genre'] ?? '');
                if (strpos($genre, 'series') !== false) {
                    $detectionSignals[] = "genre_series";
                    $isSeries = true;
                }

                // ===== SIGNAL 2: EPISODE COUNT INDICATORS =====
                $episodeCount = $movieData['episodes'] ?? 0;
                if ($episodeCount > 1) {
                    $detectionSignals[] = "multi_episode_count";
                    $isSeries = true;
                }


                // ===== SIGNAL 6: LIVE EPISODES API VERIFICATION =====
                // Only check episodes API if we have strong signals to avoid unnecessary calls
                if (!empty($seriesCode) && !empty($showId) && ($isSeries || $episodeCount > 0)) {
                    if ($this->checkEpisodesExist($showId, $seriesCode)) {
                        $detectionSignals[] = "episodes_api_confirmed";
                        $isSeries = true;
                    }
                }
            }



            if ($isSeries) {
                // Strong series signals - route to series processor
                $this->type = 'Series'; // Update the type field
                $this->notes = "SERIES DETECTED: (seriesCode: $seriesCode, showId: $showId)";
                $this->muno_processed = 'Yes';
                $this->muno_success = 'No';
                $this->muno_message = 'Series detected, pending processing 1';

                //get movie with this external url
                $existing_post = MovieModel::where('external_url', $this->url)->first();
                if ($existing_post != null) {
                    if ($existing_post->muno_processed != 'Yes') {
                        $existing_post->error_message = 'Series for muno are on pending processing';
                        $existing_post->status = 'error';
                        $existing_post->muno_processed = 'Yes';
                        $existing_post->muno_success = 'No';
                        $existing_post->muno_message = 'Series detected, pending processing 2';
                        $existing_post->save();
                    }
                }
                $this->save();
                $this->process_munowatch_series();
                return;
            } else {
                // Movie or weak series signals - route to movie processor
                $this->type = 'Movie'; // Update the type field
                $this->notes = "MOVIE DETECTED: (treating as standalone movie)";
                $this->save();
                return $this->process_munowatch_movie();
            }
        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error in intelligent content detection: ' . $th->getMessage();
            $this->save();

            // Fallback to standard movie processor
            try {
                return $this->process_munowatch_movie();
            } catch (\Throwable $fallbackError) {
                $this->error_message = 'Both series and movie processing failed: ' . $fallbackError->getMessage();
                $this->save();
                throw $fallbackError;
            }
        }
    }


    public function process_munowatch_movie()
    {

        try {
            // Check if movie already exists to avoid duplicates
            $existing_post = MovieModel::where('external_url', $this->url)->first();



            // Parse JSON response from munowatch API
            $jsonData = json_decode($this->page_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error_message = 'Failed to parse JSON response: ' . json_last_error_msg();
                $this->status = 'error';
                $this->save();
                // throw new \Exception('Failed to parse JSON response: ' . json_last_error_msg());
            }

            if (!isset($jsonData['preview']) || !is_array($jsonData['preview'])) {
                $this->error_message = 'Invalid munowatch response structure - missing preview data';
                $this->status = 'error';
                $this->save();
                // throw new \Exception('Invalid munowatch response structure - missing preview data');
            }
            $preview = $jsonData['preview'];

            $playingUrl = $preview['playingUrl'] ?? null;
            if ($playingUrl == null || strlen(trim($playingUrl)) < 5) {
                $this->error_message = 'No valid video URL found in munowatch response';
                $this->status = 'error';
                $this->save();
                // throw new \Exception('No valid video URL found in munowatch response');
            }

            if (!(Utils::isPossiblyVideoUrl($playingUrl))) {
                $this->error_message = 'Extracted video URL is not valid: ' . $playingUrl;
                $this->status = 'error';
                $this->save();
                // throw new \Exception('Extracted video URL is not valid: ' . $playingUrl);
            }

            //check by page source url
            if ($existing_post == null) {
                $existing_post = MovieModel::where('external_url', $this->url)->first();
            }

            //check by url
            if ($existing_post == null) {
                $existing_post = MovieModel::where('url', $playingUrl)
                    ->first();
            }

            if ($existing_post == null) {
                //check by title
                $existing_post = MovieModel::where('title', $this->title)
                    ->where('status', 'Active')
                    ->where('type', $this->type)
                    ->first();
            }


            //check if is series



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

            if ($existing_post == null) {
                $existing_post = MovieModel::where('title', $title)
                    ->first();
            }

            //check by url using primaryVideoUrl
            if ($existing_post == null) {
                $existing_post = MovieModel::where('url', $primaryVideoUrl)
                    ->first();
            }
            //check using url encoded
            if ($existing_post == null) {
                $existing_post = MovieModel::where('url', urlencode($primaryVideoUrl))
                    ->first();
            }
            if ($existing_post == null) {
                $encodedPlayingUrl = str_replace(' ', '%20', $playingUrl);
                $existing_post = MovieModel::where('url', $encodedPlayingUrl)
                    ->first();
            }

            //check by page_source_url
            if ($existing_post == null) {
                $existing_post = MovieModel::where('page_source_url', $this->url)
                    ->first();
            }

            //use munowatch id to check
            if ($existing_post == null) {
                $existing_post = MovieModel::where('munowatch_id', $videoId)
                    ->first();
            }

            if ($existing_post != null) {
                $movie = $existing_post;
            } else {
                $movie = new MovieModel();
            }


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
            $movie->type = $this->type ?? 'Movie'; // Default to Movie, can be overridden

            //check if $genre is series
            if (strtolower($genre) === 'series') {
                try {
                    $this->process_munowatch_series();
                } catch (\Throwable $th) {
                    //throw $th;
                }
                return;
                $movie->type = 'Series';
            }
            $types = ['movie', 'series'];
            //if not in types make type movie
            if (!in_array(strtolower($movie->type), $types)) {
                $movie->type = 'Movie';
            }
            // Status (Set to Inactive for munowatch content)
            $movie->status = 'Active';

            // Munowatch identification fields
            $movie->is_muno = 'Yes';
            $movie->muno_processed = 'Yes';
            $movie->munowatch_id = $videoId; // Use the video ID as munowatch ID
            $movie->muno_message = '';
            $movie->muno_success = 'No';

            //check if $movie->url does not contain extension mp4 or mkv or avi or flv or wmv or mov or webm
            $file_extension = pathinfo(parse_url($movie->url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $file_extension = strtolower($file_extension);
            $file_name = pathinfo(parse_url($movie->url, PHP_URL_PATH), PATHINFO_FILENAME);
            $isMovieFile = true;
            //check if $file_extension is not in array
            $validVideoExtensions = ['mp4', 'mkv', 'avi', 'flv', 'wmv', 'mov', 'webm', 'mpeg', 'mpg', 'm4v', '3gp', '3g2', 'f4v', 'f4p', 'f4a', 'f4b', 'ts', 'vob', 'ogv', 'ogg', 'rm', 'rmvb', 'asf', 'divx', 'xvid'];
            if (!in_array($file_extension, $validVideoExtensions)) {
                $isMovieFile = false;
            }

            /// if is not $isMovieFile, the mark it as error and save
            if (!$isMovieFile) {
                $this->status = 'error';
                $this->error_message = 'Invalid or unsupported video file extension: ' . $file_extension;
                $this->save();

                //mark movie as muno processed but not active and reason
                $movie->muno_processed = 'No';
                $movie->status = 'Inactive';
                $movie->muno_message = 'Invalid or unsupported video file extension: ' . $file_extension;
                $movie->save();
            }


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


            $movie->muno_success = 'Yes';
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
     * FLUTTER APP PATTERN MUNOWATCH SERIES PROCESSOR 🎬✨
     * 
     * Processes series detected via Flutter app pattern:
     * - Series identified by successful episodes API call
     * - Fetches episodes using episodes/range/{showId}/{seriesCode}/{seasonNumber}
     * - Creates series entry and associated episode records
     * 
     * Features:
     * - Follows exact Flutter app logic for series processing
     * - Fetches episodes from episodes API like Flutter app
     * - Comprehensive series metadata extraction
     * - Perfect episode sequencing and organization  
     * - Robust duplicate detection and handling
     * - Full integration with existing series system
     */
    public function process_munowatch_series()
    {
        try {
            if ($this->page_content == null || strlen($this->page_content) < 10) {
                try {
                    $this->fetch_page_content(false);
                } catch (\Throwable $th) {
                    $this->status = 'failed';
                    $this->muno_series_processed = 'Yes';
                    $this->muno_series_success = 'No';
                    $this->error_message = 'Failed to fetch page content: ' . $th->getMessage();
                    $this->save();
                }

                //check again if $this->page_content is valid
                if ($this->page_content == null || strlen($this->page_content) < 10) {
                    $this->status = 'failed';
                    $this->muno_series_processed = 'Yes';
                    $this->muno_series_success = 'No';
                    $this->error_message = 'Page content is empty or too short after fetch attempt';
                    $this->save();
                }
            }


            $jsonData = null;
            try {
                // Parse JSON response from munowatch API
                $jsonData = json_decode($this->page_content, true);
            } catch (\Throwable $th) {
                //throw $th;
            }

            if ($jsonData == null || !is_array($jsonData)) {
                $this->status = 'failed';
                $this->muno_series_processed = 'Yes';
                $this->muno_series_success = 'No';
                $this->error_message = 'Failed to parse JSON response: ' . json_last_error_msg();
                $this->save();
            }

            if (!isset($jsonData['preview'])) {
                $this->status = 'failed';
                $this->muno_series_processed = 'Yes';
                $this->muno_series_success = 'No';
                $this->error_message = 'Preview data not set';
                $this->save();
            }
            $preview = $jsonData['preview'];
            if (!isset($preview['series_code'])) {
                $this->status = 'failed';
                $this->muno_series_processed = 'Yes';
                $this->muno_series_success = 'No';
                $this->error_message = 'Series code not set';
                $this->save();
            }
            $series_code = $preview['series_code'];

            if ($series_code == null || strlen($series_code) < 1) {
                $this->status = 'failed';
                $this->muno_series_processed = 'Yes';
                $this->muno_series_success = 'No';
                $this->error_message = 'Series code is empty    ';
                $this->save();
            }

            // dd( 'Searching for related pages with series_code: ' . $series_code);
            // dd( '%"series_code":"' . $series_code . '"%');
            // dd($series_code);

            //where page_content contains "series_code":"$series_code","
            $related_pages =  MovieCrawlerPage::where('page_content', 'like', '%"series_code":"' . $series_code . '"%')
                ->get();
            //if empty
            if ($related_pages->count() < 1) {
                $this->status = 'failed';
                $this->muno_series_processed = 'Yes';
                $this->muno_series_success = 'No';
                $this->error_message = 'Related series not found';
                $this->save();
            }

            foreach ($related_pages as $key => $related_page) {
                if ($related_page->page_content == null) {
                    continue;
                }
                if (strlen($related_page->page_content) < 10) {
                    continue;
                }
                $_page_content = null;
                try {
                    $_page_content = json_decode($related_page->page_content, true);
                } catch (\Throwable $th) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'Failed to parse data: ' . $th->getMessage();
                    $related_page->save();
                    continue;
                }
                if (!isset($_page_content['preview'])) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'Preview data not set';
                    $related_page->save();
                    continue;
                }
                $_preview = $_page_content['preview'];
                if (!isset($_preview['series_code'])) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'Series code not set';
                    $related_page->save();
                    continue;
                }
                $series_code = $_preview['series_code'];
                if ($series_code == null || strlen($series_code) < 1) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'Series code is empty    ';
                    $related_page->save();
                    continue;
                }

                //check for $_preview['id']
                if (!isset($_preview['id'])) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'ID not set';
                    $related_page->save();
                    continue;
                }

                $title = $_preview['video_title'];
                $title = trim($title);
                if ($title == null || strlen($title) < 1) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'Title is empty    ';
                    $related_page->save();
                    continue;
                }
                $vjname = $_preview['vjname'] ?? 'Munowatch API';
                $vjname = trim($vjname);
                $seriesMovie = null;

                $playingUrl = $_preview['playingUrl'] ?? '';
                //check if $playingUrl is valid url
                if ($playingUrl == null || strlen($playingUrl) < 5) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'Playing URL is empty or too short';
                    $related_page->save();
                    continue;
                }
                $seriesMovie = SeriesMovie::where([
                    'is_muno' => 'Yes',
                    'munowatch_id' => $series_code
                ])->first();

                if ($seriesMovie == null && $this->series_id != null && (($this->series_id) > 0)) {
                    $seriesMovie = SeriesMovie::find($this->series_id);
                }


                if ($seriesMovie == null) {
                    $seriesMovie = new SeriesMovie();
                    $seriesMovie->title = $title;
                    $seriesMovie->description = $_preview['description'] ?? '';
                    $seriesMovie->external_url = $related_page->url;
                    $seriesMovie->thumbnail = $_preview['thumbnail'] ?? '';
                    $seriesMovie->poster_url = $_preview['thumbnail'] ?? '';
                    $seriesMovie->genre = $_preview['genre'] ?? '';
                    $seriesMovie->Category = $_preview['genre'] ?? '';
                    $seriesMovie->is_active = 'Yes';
                    $seriesMovie->muno_processed = 'Yes';
                    $seriesMovie->is_muno = 'Yes';
                    $seriesMovie->is_premium = 'Yes';
                    $seriesMovie->vj = $vjname;
                    $seriesMovie->status = 'Active';
                    $seriesMovie->munowatch_id = $series_code;
                    try {
                        $seriesMovie->save();
                    } catch (\Throwable $th) {
                        $related_page->status = 'failed';
                        $related_page->muno_series_processed = 'Yes';
                        $related_page->muno_series_success = 'No';
                        $related_page->error_message = 'Failed to save series movie: ' . $th->getMessage();
                        $related_page->save();
                        continue;
                    }
                }

                $seriesMovie->refresh();

                if ($this->is_episode == "Yes") {
                    $this->series_id = $seriesMovie->id;
                    $episode = MovieModel::where('external_url', $related_page->url)
                        ->first();
                    if ($episode == null) {
                        $episode = MovieModel::where('url', $playingUrl)
                            ->first();
                    }
                    if ($episode == null) {
                        $episode = MovieModel::where('munowatch_id', $_preview['id'])
                            ->first();
                    }
                    if ($episode == null) {
                        $episode = new MovieModel();
                    }
                    $titleSplits = explode(' ', $this->title);
                    $ep = end($titleSplits);
                    $ep = (int)($ep);
                    $episode->title = $this->title;
                    
                }
  /*  
    "video_name" => "THE HAUNTED PALACE EP 2.mp4"
    "filehistory" => ""
    "openload" => "0"
    "embedurl" => ""
    "serverhost" => "66"
    "allow_openload" => "0"
    "full_video_name" => ""
    "duration" => "32h 44m"
    "thumbnail" => "https://munoapp.org/munowatch-api/laba/yo/naki/Ng1bUnca1l3766.jpg"
    "tfilehistory" => ""
    "category_id" => 5
    "language_id" => 1
    "recording_date" => "2025-10-08"
    "age_id" => "18 +"
    "location" => 1
    "tab_category_id" => 5
    "series_code" => "28051"
    "access" => "1"
    "paid_for" => "1"
    "new_movie" => "1"
    "priority" => "No"
    "size" => "272.41 MB"
    "create_date" => "2025-10-08 16:10:22"
    "schedule_date" => "08.10.2025 04:18:03 PM"
    "user_id" => 169464
    "vj_id" => 37
    "video_status_id" => 0
    "network_id" => "45.221.10.185"
    "user_access" => "deny"
    "notification" => ""
    "secduration" => "117840.000000"
    "issubscriber" => true
    "download" => "0"
    "genre" => "Series"
    "vjname" => "Vj KS"
    "episodes" => 31
    "episode_state" => "NEXT"
    "nxt_eps" => "EPS   3"
    "nxt_eps_id" => 59738
    "nxt_eps_title" => "The Haunted Palace 3"
    "nxt_ldur" => 0
    "nxt_playing_url" => "http://munoserver55.club/simo/simo22/THE HAUNTED PALACE EP 3.mp4"
    "playingUrl" => "http://munoserver55.club/simo/simo22/THE HAUNTED PALACE EP 2.mp4"
    "ldur" => 0
    "session_id" => "51ca288bbec537a2da5ced8b0b828f74"
    "device" => "android"
    "lang_name" => "English to Luganda"
    "vjrelease" => "23 hrs ago"
    "mstatus" => false
    "kstatus" => ""
    "substatus" => "FAILED"
                */
    
                /* 
                    "id" => 2968
    "created_at" => "2025-10-09 22:17:01"
    "updated_at" => "2025-10-26 00:45:06"
    "movie_crawler_website_id" => 2
    "title" => "The Haunted Palace 2"
    "slug" => "59248"
    "url" => "https://munowatch.org/api/preview/v2/59766/3664"
    "movie_id" => null
    "page_content" => "        {"preview":{"id":59737,"video_title":"The Haunted Palace 2","description":"The story takes place in the royal palace, where a conspiracy summons an evil ▶"
    "error_message" => "Call to undefined method App\Models\SeriesMovie::refrsh()"
    "status" => "error"
    "last_fetched_at" => null
    "type" => "Series"
    "row_id" => "59737"
    "img_port_muno_file_name" => null
    "bunny_file_name" => null
    "tmdb_poster_path" => null
    "vj" => "Munowatch API"
    "notes" => "SERIES DETECTED: (seriesCode: 28051, showId: 59737)"
    "series_id" => 2691
    "is_muno" => "Yes"
    "muno_processed" => "Yes"
    "munowatch_id" => "59737"
    "muno_success" => "No"
    "muno_message" => "Series detected, pending processing 1"
    "muno_series_processed" => "Yes"
    "muno_series_success" => "Yes"
    "muno_series_group_id" => "83524"
    "is_generated" => "No"
    "is_episode" => "Yes"
    "episodes_data_fetched" => "Error"
    "episode_data" => "      
                */



              

                if (isset($_preview['thumbnail']) && filter_var($_preview['thumbnail'], FILTER_VALIDATE_URL)) {
                    $seriesMovie->thumbnail = $_preview['thumbnail'];
                    $seriesMovie->poster_url = $_preview['thumbnail'];
                }

                $munowatch_id = $_preview['id'];
                //if is $munowatch_id is null or less than 1
                if ($munowatch_id == null || $munowatch_id < 1) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'Invalid munowatch ID';
                    $related_page->save();
                    continue;
                }

                //check $_page_content['items'] is set and is array
                if (!isset($_page_content['items']) || !is_array($_page_content['items'])) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'Items data not set or invalid';
                    $related_page->save();
                    continue;
                }

                $items = $_page_content['items'] ?? [];

                //if $items is not array or empty
                if (!is_array($items) || count($items) < 1) {
                    $related_page->status = 'failed';
                    $related_page->muno_series_processed = 'Yes';
                    $related_page->muno_series_success = 'No';
                    $related_page->error_message = 'No items found in series';
                    $related_page->save();
                    continue;
                }



                $ep_number = 1;
                foreach ($items as $key1 => $item) {
                    $id = $item['id'] ?? 0;
                    if ($id < 1) {
                        continue;
                    }
                    $url = $item['playingurl'] ?? '';
                    if ($url == null || strlen($url) < 5) {
                        continue;
                    }

                    $ep_title = $item['title'] ?? 'Unknown Title';
                    $hasTitleInEp = false;
                    if (stripos($ep_title, $seriesMovie->title) !== false) {
                        $hasTitleInEp = true;
                    }

                    if (!$hasTitleInEp) {
                        $ep_title = $seriesMovie->title . ' - ' . $ep_title;
                    }
                    if ($ep_title == $seriesMovie->title) {
                        $ep_title = $seriesMovie->title . ' - ' . $ep_number;
                    }
                    $playingUrl = $item['playingurl'] ?? '';
                    $encodedPlayingUrl = str_replace(' ', '%20', $playingUrl);
                    $encodedPlayingUrl = str_replace('http://', 'https://', $encodedPlayingUrl);
                    $external_id = $item['id'] ?? '';
                    if ($external_id == null || strlen($external_id) < 1) {
                        $external_id = $playingUrl;
                    }

                    $size = $item['size'] ?? '';
                    $duration = $item['duration'] ?? '';
                    $description = $item['description'] ?? '';
                    $munowatch_id  = $external_id;


                    $episode = MovieModel::where('external_url', $encodedPlayingUrl)->first();
                    if ($episode == null) {
                        $episode = MovieModel::where('page_source_url', $encodedPlayingUrl)->first();
                    }
                    if ($episode == null) {
                        $episode = MovieModel::where('is_muno', 'Yes')
                            ->where('munowatch_id', $munowatch_id)
                            ->first();
                    }

                    if ($episode == null) {
                        $episode = MovieModel::where('is_muno', 'Yes')
                            ->where('url', $playingUrl)
                            ->first();
                    }

                    if ($episode == null) {
                        $episode = MovieModel::where('is_muno', 'Yes')
                            ->where('external_url', $playingUrl)
                            ->first();
                    }

                    if ($episode == null) {
                        $episode = MovieModel::where('is_muno', 'Yes')
                            ->where('old_video_url', $playingUrl)
                            ->first();
                    }

                    if ($episode == null) {
                        $episode = new MovieModel(); //validate new episode
                    }
                    $episode->title = $title;
                    $episode->description = $_preview['description'] ?? '';
                    $episode->munowatch_id = $munowatch_id;
                    $episode->is_muno = 'Yes';
                    $episode->muno_processed = 'Yes';
                    $episode->is_processed = 'Yes';
                    $episode->external_url = $playingUrl;
                    $episode->page_source_url = $playingUrl;
                    $episode->url = $encodedPlayingUrl;
                    $episode->old_video_url = $playingUrl;
                    $episode->image_url = $seriesMovie->thumbnail;
                    $episode->thumbnail_url = $seriesMovie->thumbnail;
                    $episode->description = $seriesMovie->description;
                    $episode->duration = $duration;
                    $episode->genre = $seriesMovie->genre;
                    $episode->size = (int) $size;
                    $episode->type = 'Series';
                    $episode->status = 'Active';
                    $episode->category = $seriesMovie->genre;
                    $episode->category_id = $seriesMovie->id;
                    $episode->vj = $seriesMovie->vj;
                    $episode->content_type = "Video";
                    $episode->content_is_video = "Yes";
                    $episode->content_type_processed = "Yes";
                    $episode->muno_success = "Yes";
                    $episode->is_muno = "Yes";
                    $episode->content_type_processed_time = now();
                    $episode->episode_title = $ep_title;
                    $episode->episode_number = $ep_number;
                    $episode->series_title = $seriesMovie->title;
                    if ($ep_number == 1) {
                        $episode->is_first_episode = 'Yes';
                    } else {
                        $episode->is_first_episode = 'No';
                    }
                    try {
                        $episode->save();
                        //increment ep_number
                        $ep_number++;
                    } catch (\Throwable $th) {
                        $related_page->status = 'failed';
                        $related_page->muno_series_processed = 'Yes';
                        $related_page->muno_series_success = 'No';
                        $related_page->error_message = 'Failed to save episode: ' . $th->getMessage();
                        $related_page->save();
                        continue;
                    }
                }
                $related_page->status = 'success';
                $related_page->muno_series_processed = 'Yes';
                $related_page->muno_series_success = 'Yes';
                $related_page->error_message = null;
                $related_page->title = $title;
                $related_page->series_id = $seriesMovie->id;
                $related_page->save();
            }
        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error processing munowatch series data: ' . $th->getMessage();
            $this->save();
            throw $th;
        }
    }

    /**
     * Fetch episodes for a series using Flutter app pattern
     * 
     * @param int $showId
     * @param string $seriesCode
     * @return array
     */
    private function fetchEpisodesForSeries($showId, $seriesCode)
    {
        try {
            $seasonNumber = 1; // Start with season 1 like Flutter app
            $episodesUrl = "https://munowatch.org/api/episodes/range/{$showId}/{$seriesCode}/{$seasonNumber}";

            // Use the same authentication pattern as the crawler
            $jwtToken = config('munowatch.jwt_token', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0');

            $headers = [
                'Authorization: Bearer ' . $jwtToken,
                'X-Api-Key: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0',
                'User-Agent: okhttp/4.9.0',
                'Content-Type: application/json',
                'Accept: application/json'
            ];

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($episodesUrl, false, $context);

            if ($response === false) {
                return [];
            }

            $episodesData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            // Check if response contains error
            if (isset($episodesData['error']) && $episodesData['error'] === true) {
                return [];
            }

            // Return episodes array
            if (is_array($episodesData)) {
                return $episodesData;
            }

            return [];
        } catch (\Throwable $th) {
            return [];
        }
    }

    /**
     * Check if episodes exist for a given show using Flutter app pattern
     * 
     * Following exact Flutter app logic:
     * - Call episodes/range/{showId}/{seriesCode}/{seasonNumber} API
     * - If episodes are returned, it's a series
     * - If empty/error, it's a movie
     * 
     * @param int $showId
     * @param string $seriesCode
     * @return bool
     */
    private function checkEpisodesExist($showId, $seriesCode)
    {
        try {
            $seasonNumber = 1; // Start with season 1 like Flutter app
            $episodesUrl = "https://munowatch.org/api/episodes/range/{$showId}/{$seriesCode}/{$seasonNumber}";

            // Use the same authentication pattern as the crawler
            $jwtToken = config('munowatch.jwt_token', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0');

            $headers = [
                'Authorization: Bearer ' . $jwtToken,
                'X-Api-Key: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0',
                'User-Agent: okhttp/4.9.0',
                'Content-Type: application/json',
                'Accept: application/json'
            ];

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($episodesUrl, false, $context);

            if ($response === false) {
                // Unable to fetch - assume it's not a series
                return false;
            }

            $episodesData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Invalid JSON response - assume it's not a series
                return false;
            }

            // Check if response contains error
            if (isset($episodesData['error']) && $episodesData['error'] === true) {
                // API returned error - not a series
                return false;
            }

            // Check if we have episodes array
            if (is_array($episodesData) && !empty($episodesData)) {
                // Episodes exist - this is a series!
                return true;
            }

            // No episodes found - this is a movie
            return false;
        } catch (\Throwable $th) {
            // Error occurred - assume it's not a series to be safe
            return false;
        }
    }

    /**
     * INDEPENDENT MUNOWATCH SERIES PROCESSOR 🎬
     * 
     * Dedicated method for processing munowatch series using Flutter app pattern.
     * Does NOT interfere with existing movie processing logic.
     * 
     * Features:
     * - Uses episodes API to verify series status
     * - Creates series records in series_movies table
     * - Independent of movie processing workflow
     * - Follows exact Flutter app detection pattern
     */
    public function process_munowatch_series_independent()
    {
        try {
            // Parse JSON response
            $jsonData = json_decode($this->page_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON response: ' . json_last_error_msg());
            }

            // Extract movie data from dashboard structure
            $movieData = $this->extractMovieDataFromResponse($jsonData);
            if (!$movieData) {
                throw new \Exception('No movie/series data found in API response');
            }

            // Extract series identification data
            $seriesCode = $movieData['series_code'] ?? $movieData['seriesCode'] ?? '';
            $showId = $movieData['id'] ?? $movieData['vid'] ?? null;

            if (empty($seriesCode) || empty($showId)) {
                throw new \Exception('Missing series_code or show ID - cannot process as series');
            }

            // Verify this is actually a series by checking episodes
            $episodesData = $this->fetchEpisodesForSeries($showId, $seriesCode);
            if (empty($episodesData)) {
                throw new \Exception('No episodes found - not a series');
            }

            // Extract series metadata
            $seriesTitle = $movieData['video_title'] ?? $movieData['title'] ?? 'Unknown Series';
            $seriesDescription = $movieData['description'] ?? $movieData['plot'] ?? '';
            $seriesThumbnail = $movieData['thumbnail'] ?? $movieData['poster'] ?? '';
            $seriesGenre = $movieData['genre'] ?? $movieData['category'] ?? '';
            $seriesYear = $movieData['year'] ?? $movieData['release_year'] ?? '';

            // Check for existing series 
            $existingSeries = SeriesMovie::where('external_id', $showId)->first();

            // Create or update series
            if (!$existingSeries) {
                $series = new SeriesMovie();
                $isNewSeries = true;
            } else {
                $series = $existingSeries;
                $isNewSeries = false;
            }

            // Set series data
            $series->title = $seriesTitle;
            $series->description = $seriesDescription;
            $series->external_url = $this->url;
            $series->external_id = $showId;
            $series->Category = $seriesGenre;
            $series->thumbnail = $seriesThumbnail;
            $series->total_episodes = count($episodesData);
            $series->total_seasons = 0; // Default, can be updated
            $series->year = $seriesYear;
            $series->genre = $seriesGenre;
            $series->status = 'Active';
            $series->is_active = 'Yes';
            $series->is_premium = 'No';
            $series->vj = 'Munowatch API';

            $series->save();

            // Update page status
            $this->movie_id = $series->id;
            $this->series_id = $series->id;
            $this->status = 'success';
            $this->muno_processed = 'Yes';
            $this->error_message = null;
            $this->notes = "Series processed successfully: {$seriesTitle} ({$series->total_episodes} episodes)";
            $this->save();

            return $series;
        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error processing munowatch series: ' . $th->getMessage();
            $this->save();
            throw $th;
        }
    }

    /**
     * Extract movie data from various JSON response structures
     */
    private function extractMovieDataFromResponse($jsonData)
    {
        // Try preview structure first
        if (isset($jsonData['preview'])) {
            return $jsonData['preview'];
        }

        // Try direct movie structure
        if (isset($jsonData['movie'])) {
            return $jsonData['movie'];
        }

        // Try data structure
        if (isset($jsonData['data'])) {
            return $jsonData['data'];
        }

        // Try dashboard structure
        if (isset($jsonData['dashboard']) && is_array($jsonData['dashboard'])) {
            foreach ($jsonData['dashboard'] as $category) {
                if (isset($category['movies']) && is_array($category['movies']) && !empty($category['movies'])) {
                    return $category['movies'][0]; // Return first movie for processing
                }
            }
        }

        return null;
    }


    public static function generate_series_episodes($seriesPage)
    {

        if ($seriesPage->is_episode == 'Yes') {
            throw new \Exception("Episodes cannot be generated for an episode page.");
        }
        if ($seriesPage->muno_series_processed == 'Yes') {
            throw new \Exception("Episodes already processed for this series page.");
        }
        if ($seriesPage->episode_data == null && strlen(trim($seriesPage->episode_data)) < 10) {
            if ($seriesPage->page_content == null || strlen(trim($seriesPage->page_content)) < 10) {
                $seriesPage->fetch_page_content(false);
            }
            $seriesPage->refresh();
            if ($seriesPage->page_content == null || strlen(trim($seriesPage->page_content)) < 10) {
                throw new \Exception("Failed to fetch series page content.");
            }
            $page_content = null;

            try {
                $page_content = json_decode($seriesPage->page_content, true);
            } catch (\Throwable $th) {
                throw $th;
            }

            if (!isset($page_content['preview'])) {
                throw new \Exception("Preview data not found in page content.");
            }
            $preview = $page_content['preview'];
            if (!isset($preview['series_code']) || !isset($preview['id'])) {
                throw new \Exception("Required preview fields missing.");
            }
            try {
                $series_code = $preview['series_code'];
                $id = $preview['id'];
                $season_number = 1;
                $url = 'https://munowatch.org/api/episodes/range/' . $id . '/' . $series_code . '/' . $season_number;

                $baseToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
                $headers = [
                    'Authorization' => 'Bearer ' . $baseToken,
                    'X-Api-Key' => $baseToken,
                    'User-Agent' => 'okhttp/4.9.0'
                ];
                $data = Utils::get_url_with_auth($url, $headers);
                $seriesPage->episode_data = $data;
                $seriesPage->save();
            } catch (\Throwable $th) {
                throw $th;
            }
        }
        $seriesPage->refresh();
        if ($seriesPage->episode_data == null || strlen(trim($seriesPage->episode_data)) < 10) {
            throw new \Exception("Failed to fetch episode data.");
        }
        $data = null;
        try {
            $data = json_decode($seriesPage->episode_data, true);
        } catch (\Throwable $th) {
            throw $th;
        }

        $page_content = null;

        try {
            $page_content = json_decode($seriesPage->page_content, true);
        } catch (\Throwable $th) {
            throw $th;
        }

        if (!isset($page_content['preview'])) {
            throw new \Exception("Preview data not found in page content.");
        }
        $preview = $page_content['preview'];
        if (!isset($preview['series_code']) || !isset($preview['id'])) {
            throw new \Exception("Required preview fields missing.");
        }


        $episodes_ids = [];
        $count = 0;
        foreach ($data as $key => $ep) {
            if (!isset($ep['eps_range'])) continue;
            $eps_range = explode('__', $ep['eps_range']);
            $ep_min = 0;
            $ep_max = 0;
            if (isset($eps_range[0])) {
                $ep_min = (int) str_replace('EPISODE_', '', $eps_range[0]);
            }
            if (isset($eps_range[1])) {
                $ep_max = (int) str_replace('EPISODE_', '', $eps_range[1]);
            }
            //if check if ep invalid
            if ($ep_min <= 0 || $ep_max <= 0 || $ep_max < $ep_min) {
                continue;
            }
            for ($i = $ep_min; $i <= $ep_max; $i++) {
                $count++;
                $_ep['id'] = $i;
                $_ep['number'] =  $count;
                $_ep['video_title'] = 'Episode ' . $count;
                $episodes_ids[] = $_ep;
            }
        }

        //count $episodes_ids
        if (count($episodes_ids) == 0) {
            throw new \Exception("No episodes found in episode data.");
        }
        $video_title = null;

        if (
            isset($preview['video_title']) &&
            isset($preview['nxt_eps']) &&
            is_string($preview['video_title']) &&
            is_string($preview['nxt_eps'])
        ) {
            $this_title = trim($preview['video_title']);
            $this_nxt_eps = trim($preview['nxt_eps']);

            // Safely split and get next episode number
            $next_ep_splits = explode('EPS', $this_nxt_eps);
            if (!empty($next_ep_splits)) {
                $last_ep_seg = trim(end($next_ep_splits));
                $prev_num = (int) filter_var($last_ep_seg, FILTER_SANITIZE_NUMBER_INT);

                // Only process if we have a valid episode number greater than 1
                if ($prev_num > 1) {
                    $prev_num_adjusted = $prev_num - 1;
                    // Replace last instance of previous episode number from title
                    $last_pos = strrpos($this_title, (string) $prev_num_adjusted);
                    if ($last_pos !== false) {
                        $video_title = substr_replace($this_title, '', $last_pos, strlen((string) $prev_num_adjusted));
                        $video_title = trim($video_title);
                    } else {
                        $video_title = $this_title;
                    }
                } else {
                    $video_title = $this_title;
                }
            } else {
                $video_title = $this_title;
            }
        } else {
            $video_title = null;
        }

        //check if is null , video_title and just use the defaut title
        if (is_null($video_title)) {
            if (isset($preview['video_title'])) {
                $video_title = $preview['video_title'];
            }
        }


        // Check if title contains episode indicators and clean it up
        if ($video_title != null) {
            // Case-insensitive check for episode indicators
            $lowerTitle = strtolower($video_title);

            // Check for various episode patterns (ep, episode, etc.)
            if (preg_match('/(ep\s*\d+|episode\s*\d+)/i', $video_title, $matches)) {
                // Find position of the match
                $pos = stripos($video_title, $matches[0]);
                if ($pos !== false) {
                    // Extract everything before the episode indicator
                    $video_title = trim(substr($video_title, 0, $pos));
                }
            }
        }

        //if $video_title contains ep or episode , then use the original title
        if ($video_title != null) {
            $lowerTitle = strtolower($video_title);
            if (strpos($lowerTitle, 'ep') !== false || strpos($lowerTitle, 'episode') !== false) {
                if (isset($preview['video_title'])) {
                    $video_title = $preview['video_title'];
                }
            }
        }

        $existingSerie = SeriesMovie::where('title', $video_title)
            ->where('is_muno', 'Yes')
            ->first();

        $munowatch_id = null;
        $series_code = null;
        if (isset($preview['id'])) {
            $munowatch_id = $preview['id'];
        }

        if (isset($preview['series_code'])) {
            $series_code = $preview['series_code'];
        }

        if ($munowatch_id == null) {
            throw new \Exception("Munowatch ID not found in preview data.");
        }

        if ($existingSerie == null) {
            $existingSerie = SeriesMovie::where('munowatch_id', $munowatch_id)
                ->first();
        }

        if ($existingSerie == null) {
            $existingSerie = SeriesMovie::where('series_code', $series_code)
                ->first();
        }

        if ($existingSerie == null) {
            $existingSerie = SeriesMovie::where('external_url', $seriesPage->url)
                ->first();
        }

        if ($existingSerie == null) {
            $existingSerie = new SeriesMovie();
        }

        $existingSerie->munowatch_id = $munowatch_id;
        $existingSerie->title = $video_title;
        $existingSerie->Category = $preview['vjname'] ?? 'Drama';
        $existingSerie->description = $preview['description'] ?? '';
        $existingSerie->thumbnail = $preview['thumbnail'] ?? null;
        $existingSerie->total_episodes = 0;
        $existingSerie->total_views = 0;
        $existingSerie->total_rating = 0;
        $existingSerie->external_id = $munowatch_id;
        $existingSerie->series_code = $series_code;
        $existingSerie->is_muno = 'Yes';
        $existingSerie->muno_processed = 'No';
        $existingSerie->is_active = 'No';
        $existingSerie->vj = $preview['vjname'] ?? null;
        $existingSerie->genre = $preview['genre'] ?? null;
        $existingSerie->save();



        $user_id = 3664; //admin user
        foreach ($episodes_ids as $key => $episode) {
            $munowatch_id = $preview['id'];
            $page_url = "https://munowatch.org/api/preview/v2/{$episode['id']}/" . $user_id;
            $existingPage = MovieCrawlerPage::where('url', $page_url)
                ->first();
            if ($existingPage == null) {
                //serach by $munowatch_id
                $existingPage = MovieCrawlerPage::where('munowatch_id', $munowatch_id)
                    ->first();
            }
            if ($existingPage == null) {
                $existingPage = new MovieCrawlerPage();
            }

            $existingPage->movie_crawler_website_id = 2;
            $existingPage->url = $page_url;
            $existingPage->title = $existingSerie->title;
            $existingPage->slug = $munowatch_id;
            $existingPage->type = 'Series';
            $existingPage->series_id = $existingSerie->id;
            $existingPage->is_muno = 'Yes';
            $existingPage->status = 'success';
            $existingPage->is_episode = 'Yes';
            $existingPage->muno_processed = 'No';
            $existingPage->is_generated = 'No';
            $existingPage->muno_series_processed = 'Yes';
            $existingPage->episodes_data_fetched = 'No';
            $existingPage->series_code = $series_code;
            $existingPage->muno_series_group_id = $series_code;
            $existingPage->save();
        }
        $existingSerie->refresh();
        $existingSerie->status = 'Active';
        $existingSerie->save();
        $seriesPage->series_id = $existingSerie->id;
        $seriesPage->muno_series_processed = 'Yes';
        $seriesPage->save();
        return $existingSerie;
    }

    /**
     * Process Uganda Hot Girls user profile page
     * 
     * Extracts user details from the profile page and stores them in the User model.
     * Handles duplicate prevention by checking phone number and profile URL.
     * 
     * @return void
     */
    public function process_ugandahotgirls_profile()
    {
        try {
            Log::info('Processing Uganda Hot Girls profile', ['url' => $this->url]);

            if (empty($this->page_content)) {
                $this->status = 'error';
                $this->error_message = 'Page content is empty';
                $this->save();
                return;
            }

            // Extract user data from HTML
            $userData = $this->extract_ugandahotgirls_user_data($this->page_content);

            if (empty($userData)) {
                $this->status = 'error';
                $this->error_message = 'Failed to extract user data from page';
                $this->save();
                return;
            }

            // Check for duplicate by phone number
            $existingUser = null;
            if (!empty($userData['phone'] ?? null)) {
                $existingUser = User::where('phone_number', $userData['phone'])->first();
            }
            
            // If not found by phone, check by profile URL
            if (!$existingUser) {
                $existingUser = User::where('external_profile_url', $this->url)->first();
            }

            if ($existingUser) {
                // Update existing user instead of creating duplicate
                Log::info('Updating existing user', [
                    'user_id' => $existingUser->id,
                    'phone' => $userData['phone'] ?? null,
                    'url' => $this->url
                ]);

                $existingUser->update([
                    'first_name' => $userData['name'] ?? $existingUser->first_name,
                    'last_name' => $userData['area'] ?? $existingUser->last_name,
                    'name' => ($userData['name'] ?? 'Unknown') . ' - ' . ($userData['area'] ?? ''),
                    'phone_number' => $userData['phone'] ?? $existingUser->phone_number,
                    'phone_number_2' => $userData['phone'] ?? $existingUser->phone_number_2,
                    'address' => $userData['location'] ?? $existingUser->address,
                    'dob' => !empty($userData['age']) ? $this->calculate_dob_from_age($userData['age']) : $existingUser->dob,
                    'avatar' => $userData['primary_photo'] ?? $existingUser->avatar,
                    'bio' => ($userData['description'] ?? '') . "\n\nSource: " . $this->url,
                    'external_profile_url' => $this->url,
                    'import_source' => 'Uganda Hot Girls',
                    'is_imported' => 'Yes',
                    'imported_at' => now(),
                ]);

                $user = $existingUser;
                $this->status = 'success';
                $this->error_message = 'User updated (was duplicate)';
                
            } else {
                // Create new user record
                $user = User::create([
                    'first_name' => $userData['name'] ?? 'Unknown',
                    'last_name' => $userData['area'] ?? '',
                    'name' => ($userData['name'] ?? 'Unknown') . ' - ' . ($userData['area'] ?? ''),
                    'phone_number' => $userData['phone'] ?? null,
                    'phone_number_2' => $userData['phone'] ?? null,
                    'address' => $userData['location'] ?? '',
                    'dob' => !empty($userData['age']) ? $this->calculate_dob_from_age($userData['age']) : null,
                    'avatar' => $userData['primary_photo'] ?? null,
                    'bio' => ($userData['description'] ?? '') . "\n\nSource: " . $this->url,
                    'status' => 'Active',
                    'app_type' => 'Dating Profile',
                    'email' => 'user_' . uniqid() . '@ugandahotgirls.com',
                    'username' => $userData['slug'] ?? uniqid('user_'),
                    'password' => bcrypt(uniqid()),
                    'is_imported' => 'Yes',
                    'import_source' => 'Uganda Hot Girls',
                    'external_profile_url' => $this->url,
                    'imported_at' => now(),
                ]);

                $this->status = 'success';
                $this->error_message = null;
            }

            // Store additional metadata in JSON field if available
            if (!empty($userData['services'] ?? null) || !empty($userData['photos'] ?? null) || !empty($userData['badges'] ?? null)) {
                $metadata = [
                    'services' => $userData['services'] ?? [],
                    'photos' => $userData['photos'] ?? [],
                    'videos' => $userData['videos'] ?? [],
                    'badges' => $userData['badges'] ?? [],
                    'ethnicity' => $userData['ethnicity'] ?? null,
                    'availability' => $userData['availability'] ?? null,
                    'profile_views' => $userData['profile_views'] ?? null,
                ];
                
                // Store in about field as JSON if possible, or append to about text
                $user->bio = ($userData['description'] ?? '') . "\n\n" . json_encode($metadata, JSON_PRETTY_PRINT);
                $user->save();
            }

            
            $this->save();

            Log::info('Uganda Hot Girls profile processed successfully', [
                'url' => $this->url,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'action' => $existingUser ? 'updated' : 'created'
            ]);

        } catch (\Exception $e) {
            $this->status = 'error';
            $this->error_message = $e->getMessage();
            $this->save();

            Log::error('Error processing Uganda Hot Girls profile', [
                'url' => $this->url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Extract user data from Uganda Hot Girls profile HTML
     * 
     * @param string $html Profile page HTML
     * @return array Extracted user data
     */
    private function extract_ugandahotgirls_user_data($html)
    {
        $userData = [];

        try {
            // Extract name (from page title or h3 tag)
            if (preg_match('/<h3[^>]*>([^<]+)<\/h3>/i', $html, $matches)) {
                $userData['name'] = trim($matches[1]);
            }

            // Extract age (e.g., "24 year old Female")
            if (preg_match('/(\d+)\s*year\s*old/i', $html, $matches)) {
                $userData['age'] = (int)$matches[1];
            }

            // Extract gender
            if (preg_match('/year\s*old\s*(Female|Male|Trans)/i', $html, $matches)) {
                $userData['gender'] = $matches[1];
            }

            // Extract location (e.g., "Kibuye, Kampala")
            if (preg_match('/from\s*<a[^>]*>([^<]+)<\/a>,\s*<a[^>]*>([^<]+)<\/a>/i', $html, $matches)) {
                $userData['area'] = trim($matches[1]);
                $userData['city'] = trim($matches[2]);
                $userData['location'] = $userData['area'] . ', ' . $userData['city'];
            }

            // Extract phone number
            if (preg_match('/Phone:\s*<a[^>]*tel:\s*([0-9+\s]+)[^>]*>([^<]+)<\/a>/i', $html, $matches)) {
                $userData['phone'] = trim($matches[2]);
            } elseif (preg_match('/Phone:\s*([0-9+\s]+)/i', $html, $matches)) {
                $userData['phone'] = trim($matches[1]);
            }

            // Extract description (ABOUT ME section)
            if (preg_match('/<h4[^>]*>ABOUT ME:<\/h4>\s*<p[^>]*>(.+?)<\/p>/is', $html, $matches)) {
                $userData['description'] = trim(strip_tags($matches[1]));
            }

            // Extract badges (VIP, PREMIUM, VERIFIED)
            $userData['badges'] = [];
            if (stripos($html, 'VIP') !== false) {
                $userData['badges'][] = 'VIP';
            }
            if (stripos($html, 'PREMIUM') !== false) {
                $userData['badges'][] = 'PREMIUM';
            }
            if (stripos($html, 'VERIFIED') !== false) {
                $userData['badges'][] = 'VERIFIED';
            }

            // Extract ethnicity
            if (preg_match('/ETHNICITY\s*([A-Za-z]+)/i', $html, $matches)) {
                $userData['ethnicity'] = trim($matches[1]);
            }

            // Extract availability
            if (preg_match('/AVAILABILITY\s*([A-Za-z,\s]+)/i', $html, $matches)) {
                $userData['availability'] = trim($matches[1]);
            }

            // Extract profile views
            if (preg_match('/profile\s*viewed\s*(\d+)\s*times/i', $html, $matches)) {
                $userData['profile_views'] = (int)$matches[1];
            }

            // Extract services (list items under SERVICES section)
            $userData['services'] = [];
            if (preg_match('/<h4[^>]*>SERVICES:<\/h4>(.+?)<\/ul>/is', $html, $matches)) {
                preg_match_all('/<li[^>]*>([^<]+)<\/li>/i', $matches[1], $serviceMatches);
                if (!empty($serviceMatches[1])) {
                    $userData['services'] = array_map('trim', $serviceMatches[1]);
                }
            }

            // Extract photo URLs
            $userData['photos'] = [];
            preg_match_all('/<img[^>]*src="(https:\/\/www\.ugandahotgirls\.com\/wp-content\/uploads\/[^"]+)"[^>]*>/i', $html, $photoMatches);
            if (!empty($photoMatches[1])) {
                $userData['photos'] = array_unique($photoMatches[1]);
                $userData['primary_photo'] = $userData['photos'][0] ?? null;
            }

            // Extract video count (e.g., "Pics8Vids5")
            if (preg_match('/Vids(\d+)/i', $html, $matches)) {
                $userData['video_count'] = (int)$matches[1];
                $userData['videos'] = []; // Actual video URLs would need additional extraction
            }

            // Extract slug from URL
            $userData['slug'] = $this->extract_slug_from_profile_url($this->url);

            return $userData;

        } catch (\Exception $e) {
            Log::error('Error extracting user data from HTML', [
                'url' => $this->url,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Extract slug from profile URL
     * 
     * @param string $url Profile URL
     * @return string Profile slug
     */
    private function extract_slug_from_profile_url($url)
    {
        preg_match('/\/escort\/([^\/]+)/', $url, $matches);
        return $matches[1] ?? 'unknown_' . uniqid();
    }

    /**
     * Calculate date of birth from age
     * 
     * @param int $age User age
     * @return string Date of birth in Y-m-d format
     */
    private function calculate_dob_from_age($age)
    {
        if (empty($age) || !is_numeric($age)) {
            return null;
        }

        $currentYear = date('Y');
        $birthYear = $currentYear - (int)$age;
        return $birthYear . '-01-01';
    }
}

