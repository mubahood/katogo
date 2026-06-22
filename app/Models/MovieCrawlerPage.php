<?php

namespace App\Models;

use App\Services\MunowatchAuthService;
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
                $session = MunowatchAuthService::getActiveSession();
                $headers = [
                    'Authorization' => 'Bearer ' . $session['app_jwt'],
                    'X-Api-Key'     => $session['app_jwt'],
                    'X-Session-Id'  => $session['session_id'],
                    'User-Agent'    => 'okhttp/4.9.0',
                ];
                $data = Utils::get_url_with_auth($this->url, $headers);

                // If auth failure, refresh session and retry once
                if (!empty($data) && MunowatchAuthService::isAuthFailure($data)) {
                    Log::warning("[CrawlerPage] Auth failure on {$this->url} — refreshing session");
                    MunowatchAuthService::invalidateSession();
                    $session = MunowatchAuthService::refreshSession();
                    $headers['Authorization'] = 'Bearer ' . $session['app_jwt'];
                    $headers['X-Api-Key']     = $session['app_jwt'];
                    $headers['X-Session-Id']  = $session['session_id'];
                    $data = Utils::get_url_with_auth($this->url, $headers);
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
     * INTELLIGENT MUNOWATCH CONTENT PROCESSOR v2.0
     * 
     * Detects whether content is a movie or series using multiple signals,
     * then routes to the appropriate processor.
     *
     * Detection signals (ordered by reliability):
     *  1. genre contains "series"
     *  2. episode_count > 1
     *  3. series_code is non-empty
     *  4. episode_state is "NEXT" or "PREV" (implies series navigation)
     *  5. nxt_eps_id > 0 (next episode chain exists)
     *  6. Live episodes/range API verification (most reliable, costly)
     */
    public function process_munowatch_intelligent()
    {
        if ($this->is_episode === "Yes") {
            $this->process_munowatch_series();
            return;
        }

        // Ensure page content is loaded
        if ($this->page_content == null || strlen(trim($this->page_content)) < 10) {
            try {
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
            }
        }

        if ($this->page_content == null || strlen(trim($this->page_content)) < 10) {
            $this->status = 'error';
            $this->error_message = "Page content is empty for intelligent processing";
            $this->save();
            return;
        }

        try {
            $jsonData = @json_decode($this->page_content, true);
            if (!is_array($jsonData) || json_last_error() !== JSON_ERROR_NONE) {
                $this->status = 'error';
                $this->error_message = 'Failed to parse JSON response: ' . json_last_error_msg();
                $this->save();
                return;
            }

            if (!isset($jsonData['preview']) || !is_array($jsonData['preview'])) {
                $this->status = 'error';
                $this->error_message = 'Invalid munowatch response: missing preview data';
                $this->save();
                return;
            }

            // ── MULTI-SIGNAL SERIES DETECTION ──
            $isSeries = false;
            $seriesCode = null;
            $showId = null;
            $detectionSignals = [];
            $signalStrength = 0; // cumulative weight

            $movieData = $this->extractMovieDataFromResponse($jsonData);

            if ($movieData) {
                $seriesCode = $movieData['series_code'] ?? $movieData['seriesCode'] ?? '';
                $showId = $movieData['id'] ?? $movieData['vid'] ?? null;

                // Signal 1: Genre contains "series" — very strong
                $genre = strtolower($movieData['genre'] ?? '');
                if (strpos($genre, 'series') !== false) {
                    $detectionSignals[] = "genre_series";
                    $signalStrength += 3;
                }

                // Signal 2: Episode count > 1 — strong
                $episodeCount = (int)($movieData['episodes'] ?? 0);
                if ($episodeCount > 1) {
                    $detectionSignals[] = "multi_episode({$episodeCount})";
                    $signalStrength += 3;
                }

                // Signal 3: series_code differs from own video ID — moderate
                // On munowatch, EVERY video has series_code set to its own ID (self-reference).
                // This is NOT a series indicator. Only count when series_code points to a DIFFERENT show.
                if (!empty($seriesCode) && strlen($seriesCode) > 0 && (string)$seriesCode !== (string)$showId) {
                    $detectionSignals[] = "has_series_code({$seriesCode}≠{$showId})";
                    $signalStrength += 2;
                }

                // Signal 4: episode_state is NEXT/PREV — moderate
                $episodeState = strtoupper($movieData['episode_state'] ?? '');
                if (in_array($episodeState, ['NEXT', 'PREV'])) {
                    $detectionSignals[] = "episode_state({$episodeState})";
                    $signalStrength += 2;
                }

                // Signal 5: nxt_eps_id > 0 — moderate (chain exists)
                $nxtEpsId = (int)($movieData['nxt_eps_id'] ?? 0);
                if ($nxtEpsId > 0 && $nxtEpsId != ($showId ?? 0)) {
                    $detectionSignals[] = "has_nxt_eps_id({$nxtEpsId})";
                    $signalStrength += 2;
                }

                // If cumulative strength >= 3, classify as series
                // (genre alone = 3, episode_count alone = 3, series_code+nxt = 4, etc.)
                if ($signalStrength >= 3) {
                    $isSeries = true;
                }

                // Signal 6: Live API check — only if borderline (strength 1-2) and we have needed data
                if (!$isSeries && $signalStrength > 0 && !empty($seriesCode) && !empty($showId)) {
                    if ($this->checkEpisodesExist($showId, $seriesCode)) {
                        $detectionSignals[] = "episodes_api_confirmed";
                        $signalStrength += 5;
                        $isSeries = true;
                    }
                }
            }

            $signalStr = implode(', ', $detectionSignals);

            if ($isSeries) {
                $this->type = 'Series';
                $this->notes = "SERIES DETECTED [strength={$signalStrength}]: {$signalStr} (code: {$seriesCode}, showId: {$showId})";
                $this->muno_processed = 'Yes';
                $this->muno_success = 'No';
                $this->muno_message = 'Series detected, pending processing';

                // Mark any existing movie record as series-pending
                $existing_post = MovieModel::where('external_url', $this->url)->first();
                if ($existing_post != null && $existing_post->muno_processed != 'Yes') {
                    $existing_post->error_message = 'Reclassified as series';
                    $existing_post->status = 'error';
                    $existing_post->muno_processed = 'Yes';
                    $existing_post->muno_success = 'No';
                    $existing_post->muno_message = 'Series detected, pending processing';
                    $existing_post->save();
                }

                $this->save();
                $this->process_munowatch_series();
                return;
            } else {
                $this->type = 'Movie';
                $this->notes = "MOVIE DETECTED [strength={$signalStrength}]: " . ($signalStr ?: 'no series signals');
                $this->save();
                return $this->process_munowatch_movie();
            }
        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error in intelligent content detection: ' . $th->getMessage();
            $this->save();

            // Fallback to movie processor
            try {
                return $this->process_munowatch_movie();
            } catch (\Throwable $fallbackError) {
                $this->error_message = 'Both series and movie processing failed: ' . $fallbackError->getMessage();
                $this->save();
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
            // NOTE: Do NOT set category_id from munowatch API for Movies.
            // The munowatch category_id is a content type ID (e.g. 5 = TV Series),
            // but in our DB category_id is the FK to series_movies.id.
            // Only series episodes should have category_id set (done in SeriesFixerService).

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
            }
            $types = ['movie', 'series'];
            //if not in types make type movie
            if (!in_array(strtolower($movie->type), $types)) {
                $movie->type = 'Movie';
            }

            // If this is a Movie, ensure it has NO series linkage
            if ($movie->type === 'Movie') {
                $movie->category_id = null;
                $movie->episode_number = null;
                $movie->season_number = null;
                $movie->series_title = null;
                $movie->episode_title = null;
                $movie->is_first_episode = null;
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
     * MUNOWATCH SERIES PROCESSOR — v2.0 (uses SeriesFixerService)
     * 
     * Detects/creates the SeriesMovie, then delegates episode syncing to
     * the robust SeriesFixerService (range API + chain traversal).
     *
     * Duplicate prevention: multi-step lookup using cleaned title, munowatch_id,
     * series_code, and external_url before creating a new SeriesMovie.
     *
     * Flow:
     *  1. Parse preview data from page_content
     *  2. Find or create SeriesMovie (robust dedup)
     *  3. Delegate full episode sync to SeriesFixerService
     *  4. Update crawler page status
     */
    public function process_munowatch_series()
    {
        try {
            // ── Step 0: Ensure page content is available ──
            if ($this->page_content == null || strlen($this->page_content) < 10) {
                try {
                    $this->fetch_page_content(false);
                } catch (\Throwable $th) {
                    $this->markSeriesFailed('Failed to fetch page content: ' . $th->getMessage());
                    return;
                }
                if ($this->page_content == null || strlen($this->page_content) < 10) {
                    $this->markSeriesFailed('Page content is empty or too short after fetch attempt');
                    return;
                }
            }

            // ── Step 1: Parse JSON ──
            $jsonData = @json_decode($this->page_content, true);
            if (!is_array($jsonData) || !isset($jsonData['preview'])) {
                $this->markSeriesFailed('Invalid JSON or missing preview: ' . json_last_error_msg());
                return;
            }

            $preview = $jsonData['preview'];
            $series_code = $preview['series_code'] ?? '';
            $showId = $preview['id'] ?? null;
            $rawTitle = trim($preview['video_title'] ?? '');
            $vjname = trim($preview['vjname'] ?? 'Munowatch API');

            if (empty($series_code)) {
                $this->markSeriesFailed('Series code is empty');
                return;
            }
            if (empty($rawTitle)) {
                $this->markSeriesFailed('Title is empty');
                return;
            }

            // ── Step 2: Find or create SeriesMovie (robust duplicate detection) ──
            $fixer = new \App\Services\SeriesFixerService();
            $cleanTitle = $fixer->cleanSeriesTitle($rawTitle);

            $seriesMovie = $this->findExistingSeriesMovie($series_code, $cleanTitle, $showId);

            if ($seriesMovie == null) {
                $seriesMovie = new SeriesMovie();
                $seriesMovie->title = $cleanTitle;
                $seriesMovie->description = $preview['description'] ?? '';
                $seriesMovie->external_url = $this->url;
                $seriesMovie->thumbnail = $preview['thumbnail'] ?? '';
                $seriesMovie->poster_url = $preview['thumbnail'] ?? '';
                $seriesMovie->genre = $preview['genre'] ?? '';
                $seriesMovie->Category = $preview['genre'] ?? '';
                $seriesMovie->is_active = 'No'; // Will be activated after sync
                $seriesMovie->muno_processed = 'Yes';
                $seriesMovie->is_muno = 'Yes';
                $seriesMovie->is_premium = 'Yes';
                $seriesMovie->vj = $vjname;
                $seriesMovie->status = 'Active';
                $seriesMovie->munowatch_id = $series_code;
                $seriesMovie->series_code = $series_code;
                try {
                    $seriesMovie->save();
                    Log::info("[CrawlerSeries] Created new series: '{$cleanTitle}' (ID: {$seriesMovie->id}, code: {$series_code})");
                } catch (\Throwable $th) {
                    $this->markSeriesFailed('Failed to save series: ' . $th->getMessage());
                    return;
                }
            } else {
                // Update series_code if missing
                if (empty($seriesMovie->series_code) && !empty($series_code)) {
                    $seriesMovie->series_code = $series_code;
                }
                if (empty($seriesMovie->munowatch_id) && !empty($series_code)) {
                    $seriesMovie->munowatch_id = $series_code;
                }
                if (empty($seriesMovie->thumbnail) && !empty($preview['thumbnail'])) {
                    $seriesMovie->thumbnail = $preview['thumbnail'];
                    $seriesMovie->poster_url = $preview['thumbnail'];
                }
                $seriesMovie->save();
                Log::info("[CrawlerSeries] Found existing series: '{$seriesMovie->title}' (ID: {$seriesMovie->id})");
            }

            // ── Step 3: Delegate episode syncing to SeriesFixerService ──
            // This uses the robust range API + chain traversal (nxt_eps_id).
            try {
                $syncResult = $fixer->syncAllEpisodes((int) $seriesMovie->id);
                $created = $syncResult['created'] ?? 0;
                $updated = $syncResult['updated'] ?? 0;
                $syncMsg = $syncResult['message'] ?? '';
                Log::info("[CrawlerSeries] Sync for series #{$seriesMovie->id}: {$syncMsg}");
            } catch (\Throwable $th) {
                Log::warning("[CrawlerSeries] Sync failed for series #{$seriesMovie->id}: " . $th->getMessage());
                // Continue — we still created the series even if sync failed
            }

            // ── Step 4: Activate series if it has episodes ──
            try {
                $fixer->checkAndActivateSeries((int) $seriesMovie->id);
            } catch (\Throwable $th) {
                Log::warning("[CrawlerSeries] Activation check failed: " . $th->getMessage());
            }

            // ── Step 5: Update this crawler page and related crawler pages ──
            $this->status = 'success';
            $this->muno_series_processed = 'Yes';
            $this->muno_series_success = 'Yes';
            $this->series_id = $seriesMovie->id;
            $this->series_code = $series_code;
            $this->error_message = null;
            $this->save();

            // Mark related crawler pages (same series_code) as processed
            $this->markRelatedCrawlerPages($series_code, $seriesMovie->id);

        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error processing munowatch series: ' . $th->getMessage();
            $this->save();
            Log::error("[CrawlerSeries] Fatal error: " . $th->getMessage());
        }
    }

    /**
     * Find an existing SeriesMovie using multi-step robust duplicate detection.
     * Uses: munowatch_id match, series_code match, cleaned title match, crawler page link.
     */
    protected function findExistingSeriesMovie(string $seriesCode, string $cleanTitle, ?string $showId = null): ?SeriesMovie
    {
        // 1. Direct munowatch_id (= series_code) match — most specific
        $found = SeriesMovie::where('is_muno', 'Yes')
            ->where('munowatch_id', $seriesCode)
            ->first();
        if ($found) return $found;

        // 2. By series_code
        $found = SeriesMovie::where('series_code', $seriesCode)->first();
        if ($found) return $found;

        // 3. Linked from crawler page
        if ($this->series_id && $this->series_id > 0) {
            $found = SeriesMovie::find($this->series_id);
            if ($found) return $found;
        }

        // 4. By cleaned title (case-insensitive) for muno series
        if (strlen($cleanTitle) > 2) {
            $found = SeriesMovie::where('is_muno', 'Yes')
                ->whereRaw('LOWER(title) = ?', [strtolower($cleanTitle)])
                ->first();
            if ($found) return $found;
        }

        // 5. By external_url
        if (!empty($this->url)) {
            $found = SeriesMovie::where('external_url', $this->url)->first();
            if ($found) return $found;
        }

        return null;
    }

    /**
     * Mark related crawler pages (same series_code) as series-processed.
     */
    protected function markRelatedCrawlerPages(string $seriesCode, int $seriesId): void
    {
        try {
            $related = MovieCrawlerPage::where('page_content', 'like', '%"series_code":"' . $seriesCode . '"%')
                ->where('id', '!=', $this->id)
                ->get();

            foreach ($related as $page) {
                $page->series_id = $seriesId;
                $page->muno_series_processed = 'Yes';
                $page->muno_series_success = 'Yes';
                $page->series_code = $seriesCode;
                $page->save();
            }
            if ($related->count() > 0) {
                Log::info("[CrawlerSeries] Marked {$related->count()} related pages for series #{$seriesId}");
            }
        } catch (\Throwable $th) {
            Log::warning("[CrawlerSeries] Failed to mark related pages: " . $th->getMessage());
        }
    }

    /**
     * Helper: mark this crawler page as failed for series processing.
     */
    protected function markSeriesFailed(string $message): void
    {
        $this->status = 'failed';
        $this->muno_series_processed = 'Yes';
        $this->muno_series_success = 'No';
        $this->error_message = $message;
        $this->save();
        Log::warning("[CrawlerSeries] Page #{$this->id} failed: {$message}");
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
     * INDEPENDENT MUNOWATCH SERIES PROCESSOR
     * 
     * Dedicated method for processing munowatch series using SeriesFixerService.
     * Uses robust duplicate detection and delegates to SeriesFixerService for
     * episode syncing via range API + chain traversal.
     */
    public function process_munowatch_series_independent()
    {
        try {
            $jsonData = json_decode($this->page_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON response: ' . json_last_error_msg());
            }

            $movieData = $this->extractMovieDataFromResponse($jsonData);
            if (!$movieData) {
                throw new \Exception('No movie/series data found in API response');
            }

            $seriesCode = $movieData['series_code'] ?? $movieData['seriesCode'] ?? '';
            $showId = $movieData['id'] ?? $movieData['vid'] ?? null;

            if (empty($seriesCode) || empty($showId)) {
                throw new \Exception('Missing series_code or show ID');
            }

            $fixer = new \App\Services\SeriesFixerService();

            // Clean title
            $rawTitle = $movieData['video_title'] ?? $movieData['title'] ?? 'Unknown Series';
            $cleanTitle = $fixer->cleanSeriesTitle(trim($rawTitle));

            // Robust duplicate detection
            $series = $this->findExistingSeriesMovie($seriesCode, $cleanTitle, $showId);

            if (!$series) {
                $series = new SeriesMovie();
            }

            // Set series data
            $series->title = $cleanTitle;
            $series->description = $movieData['description'] ?? $movieData['plot'] ?? '';
            $series->external_url = $this->url;
            $series->external_id = $showId;
            $series->Category = $movieData['genre'] ?? $movieData['category'] ?? '';
            $series->thumbnail = $movieData['thumbnail'] ?? $movieData['poster'] ?? '';
            $series->genre = $movieData['genre'] ?? '';
            $series->status = 'Active';
            $series->is_active = 'No'; // Will activate after sync
            $series->is_muno = 'Yes';
            $series->munowatch_id = $seriesCode;
            $series->series_code = $seriesCode;
            $series->vj = $movieData['vjname'] ?? 'Munowatch API';
            $series->save();

            // Delegate episode syncing to SeriesFixerService
            $syncResult = $fixer->syncAllEpisodes((int) $series->id);
            $fixer->checkAndActivateSeries((int) $series->id);
            $series->refresh();

            // Update page status
            $this->movie_id = $series->id;
            $this->series_id = $series->id;
            $this->status = 'success';
            $this->muno_processed = 'Yes';
            $this->error_message = null;
            $this->notes = "Series processed: {$cleanTitle} ({$series->total_episodes} episodes)";
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


    /**
     * Generate series episodes using SeriesFixerService (robust range API + chain traversal).
     *
     * This replaces the old approach that incorrectly parsed eps_range as
     * EPISODE_X__EPISODE_Y (they are actually video IDs like 39531__39550).
     *
     * Now delegates entirely to SeriesFixerService which:
     *  - Calls episodes/range API to get all ranges
     *  - Traverses each range via nxt_eps_id chain
     *  - Creates episode records directly in movies table
     *  - Handles non-contiguous video IDs correctly
     *
     * @param MovieCrawlerPage $seriesPage
     * @return SeriesMovie
     */
    public static function generate_series_episodes($seriesPage)
    {
        if ($seriesPage->is_episode == 'Yes') {
            throw new \Exception("Episodes cannot be generated for an episode page.");
        }

        // ── Parse page content to get preview data ──
        if ($seriesPage->page_content == null || strlen(trim($seriesPage->page_content)) < 10) {
            $seriesPage->fetch_page_content(false);
            $seriesPage->refresh();
        }
        if ($seriesPage->page_content == null || strlen(trim($seriesPage->page_content)) < 10) {
            throw new \Exception("Failed to fetch series page content.");
        }

        $page_content = @json_decode($seriesPage->page_content, true);
        if (!isset($page_content['preview'])) {
            throw new \Exception("Preview data not found in page content.");
        }

        $preview = $page_content['preview'];
        if (!isset($preview['series_code']) || !isset($preview['id'])) {
            throw new \Exception("Required preview fields missing (series_code or id).");
        }

        $series_code = $preview['series_code'];
        $showId = $preview['id'];

        // ── Clean the series title using SeriesFixerService ──
        $fixer = new \App\Services\SeriesFixerService();
        $rawTitle = trim($preview['video_title'] ?? 'Unknown Series');
        $cleanTitle = $fixer->cleanSeriesTitle($rawTitle);

        // ── Find or create the SeriesMovie (robust dedup) ──
        $existingSerie = null;

        // 1. By cleaned title + is_muno
        if (strlen($cleanTitle) > 2) {
            $existingSerie = SeriesMovie::where('is_muno', 'Yes')
                ->whereRaw('LOWER(title) = ?', [strtolower($cleanTitle)])
                ->first();
        }

        // 2. By munowatch_id (= series_code)
        if ($existingSerie == null) {
            $existingSerie = SeriesMovie::where('munowatch_id', $series_code)->first();
        }

        // 3. By series_code
        if ($existingSerie == null) {
            $existingSerie = SeriesMovie::where('series_code', $series_code)->first();
        }

        // 4. By external_url
        if ($existingSerie == null && !empty($seriesPage->url)) {
            $existingSerie = SeriesMovie::where('external_url', $seriesPage->url)->first();
        }

        // 5. Linked from crawler page
        if ($existingSerie == null && $seriesPage->series_id && $seriesPage->series_id > 0) {
            $existingSerie = SeriesMovie::find($seriesPage->series_id);
        }

        if ($existingSerie == null) {
            $existingSerie = new SeriesMovie();
        }

        // ── Update series metadata ──
        $existingSerie->munowatch_id = $series_code;
        if (empty($existingSerie->title) || $existingSerie->title === 'Unknown Series') {
            $existingSerie->title = $cleanTitle;
        }
        $existingSerie->Category = $preview['vjname'] ?? $preview['genre'] ?? 'Drama';
        $existingSerie->description = $preview['description'] ?? $existingSerie->description ?? '';
        $existingSerie->thumbnail = $preview['thumbnail'] ?? $existingSerie->thumbnail ?? null;
        $existingSerie->external_id = $showId;
        $existingSerie->series_code = $series_code;
        $existingSerie->is_muno = 'Yes';
        $existingSerie->muno_processed = 'No'; // Will be set to Yes after sync
        $existingSerie->is_active = 'No';
        $existingSerie->vj = $preview['vjname'] ?? $existingSerie->vj ?? null;
        $existingSerie->genre = $preview['genre'] ?? $existingSerie->genre ?? null;
        $existingSerie->save();

        // ── Delegate episode syncing to SeriesFixerService ──
        // This uses the robust chain traversal (nxt_eps_id) instead of broken
        // EPISODE_X__EPISODE_Y parsing. It creates real episode records in movies table.
        $syncResult = $fixer->syncAllEpisodes((int) $existingSerie->id);
        $created = $syncResult['created'] ?? 0;
        $updated = $syncResult['updated'] ?? 0;

        Log::info("[generate_series_episodes] Series #{$existingSerie->id} '{$cleanTitle}': {$created} created, {$updated} updated");

        // ── Activate series if it has episodes ──
        $fixer->checkAndActivateSeries((int) $existingSerie->id);

        // ── Update crawler page ──
        $existingSerie->refresh();
        $seriesPage->series_id = $existingSerie->id;
        $seriesPage->muno_series_processed = 'Yes';
        $seriesPage->muno_series_success = 'Yes';
        $seriesPage->series_code = $series_code;
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

