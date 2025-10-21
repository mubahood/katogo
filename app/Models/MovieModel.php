<?php

namespace App\Models;
// 	  http://munoserver54.club/saka/saka42/Curse Of The Piper EMMY.mp4
use Carbon\Carbon;
use Dflydev\DotAccessData\Util;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MovieModel extends Model
{
    use HasFactory;

    //process_munowatch
    public static function process_munowatch($movie)
    {
        //write sql that checks if external_url contains munowatch and mark it as Inactive and is_munowatch = Yes
        // $sql = "UPDATE movie_models SET status = 'Inactive', is_muno = 'Yes' WHERE id > 21308 AND (external_url LIKE '%munowatch%' OR url LIKE '%munowatch%')";
        // DB::statement($sql);
        $crawler = MovieCrawlerPage::where('url', $movie->page_source_url)->first();
        if ($crawler == null) {
            $crawler = MovieCrawlerPage::where('url', $movie->external_url)->first();
        }

        $page_source_url = null;
        if ($movie->page_source_url != null && strlen($movie->page_source_url) > 5) {
            $page_source_url = $movie->page_source_url;
        } elseif ($movie->external_url != null && strlen($movie->external_url) > 5) {
            $page_source_url = $movie->external_url;
        }
        if ($page_source_url == null) {
            $movie->muno_processed = 'Yes';
            $movie->status = 'Inactive';
            $movie->muno_success = 'External URL not found';
            $movie->save();
            throw new \Exception('External URL not found');
            return;
        }

        if ($crawler == null) {
            //create the crawler
            $crawler = new MovieCrawlerPage();
            $crawler->url = $movie->page_source_url;
            $crawler->movie_crawler_website_id = 2; //munowatch
            $crawler->title = $movie->title;
            $crawler->slug = $movie->page_source_url;
            $crawler->url = $movie->page_source_url;
            $crawler->vj = 'Munowatch API';
            $crawler->is_muno = 'Yes';
            $crawler->save();
        }

        if (strtolower(trim($crawler->status)) != 'success') {
            try {
                $crawler->fetch_page_content();
            } catch (\Throwable $th) {
                $movie->muno_processed = 'Yes';
                $movie->status = 'Inactive';
                $movie->muno_success = 'Failed to fetch page content: ' . $th->getMessage();
                $movie->save();
                throw $th;
            }
        }
        $crawler->process_munowatch_intelligent();
    }

    //boot
    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            //create//total_episodes
            if ($model->type == 'Series') {
                $series = SeriesMovie::find($model->category_id);
                if ($series != null) {
                    $count_tot_episodes = MovieModel::where('category_id', $series->id)->count();
                    $sql = "UPDATE series_movies SET total_episodes = $count_tot_episodes WHERE id = $series->id";
                    DB::update($sql);
                }
            }
        });
        static::updated(function ($model) {
            //create//total_episodes
            if ($model->type == 'Series') {
                $series = SeriesMovie::find($model->category_id);
                if ($series != null) {
                    $count_tot_episodes = MovieModel::where('category_id', $series->id)->count();
                    $sql = "UPDATE series_movies SET total_episodes = $count_tot_episodes WHERE id = $series->id";
                    DB::update($sql);
                }
            }
        });
        static::creating(function ($model) {
            //check if type is series
            if ($model->type == 'Series') {
                $series = SeriesMovie::find($model->category_id);
                if ($series != null) {
                    $model->category = $series->title;
                    if ($model->thumbnail_url == null || $model->thumbnail_url == '') {
                        $model->thumbnail_url = $series->thumbnail;
                    }
                    //episode_number
                    if ($model->episode_number == 1) {
                        $model->is_first_episode = 'Yes';
                    } else {
                        $model->is_first_episode = 'No';
                    }
                } else {
                    $model->type = 'Movie';
                }
            }
            //check if url contains munowatch
            if (strpos($model->url, 'munowatch') !== false) {
                //$model->status = 'Inactive'; //if yes, set to yes
            }
            if ($model->type == 'Movie') {
                $model->actor = '--';
                //get same movie with external_url
                $existing = MovieModel::where('external_url', $model->external_url)
                    ->where('type', $model->type)
                    ->where('status', 'Active')
                    ->first();
                if ($existing != null) {
                    return false;
                }

                //check using munowatch_id as well
                $existing = MovieModel::where('munowatch_id', $model->munowatch_id)
                    ->where('type', $model->type)
                    ->where('status', 'Active')
                    ->first();
                if ($existing != null) {
                    return false;
                }

                //check using title as well and same vj and is muno
                $existing = MovieModel::where('title', $model->title)
                    ->where('vj', $model->vj)
                    ->where('is_muno', 'Yes')
                    ->where('type', $model->type)
                    ->where('status', 'Active')
                    ->first();
                if ($existing != null) {
                    return false;
                }
            }
        });

        static::updating(function ($model) {
            // Auto-activate movies when Firebase video testing succeeds
            if (
                $model->isDirty('firebase_video_tested_by_curl_works') &&
                $model->firebase_video_tested_by_curl_works == 'Yes'
            ) {
                $model->status = 'Active';
                $model->temp_status = 'Active';
                $model->old_video_url = $model->url;
                $model->url = $model->firebase_video_url;
            }

            if ($model->type == 'Series') {
                $series = SeriesMovie::find($model->category_id);
                if ($series != null) {
                    $model->category = $series->title;
                    if ($model->thumbnail_url == null || $model->thumbnail_url == '') {
                        // $model->thumbnail_url = $series->thumbnail; 
                    }
                    //episode_number
                    if ($model->episode_number == 1) {
                        $model->is_first_episode = 'Yes';
                    } else {
                        $model->is_first_episode = 'No';
                    }
                } else {
                    $model->type = 'Movie';
                }
            }
            //look for duplicates if type is movie
            if ($model->type == 'Movie') {
                $existing = MovieModel::where('external_url', $model->external_url)
                    ->where('type', $model->type)
                    ->where('status', 'Active')
                    ->where('id', '!=', $model->id)
                    ->first();
                if ($existing != null) {
                    //delete dups
                    DB::table('movie_models')->where('id', $model->id)->delete();
                }

                //check using munowatch_id as well
                $existing = MovieModel::where('munowatch_id', $model->munowatch_id)
                    ->where('type', $model->type)
                    ->where('status', 'Active')
                    ->where('id', '!=', $model->id)
                    ->first();
                if ($existing != null) {
                    //delete dups
                    DB::table('movie_models')->where('id', $model->id)->delete();
                }

                //check using title as well and same vj and is muno
                $existing = MovieModel::where('title', $model->title)
                    ->where('vj', $model->vj)
                    ->where('is_muno', 'Yes')
                    ->where('type', $model->type)
                    ->where('status', 'Active')
                    ->where('id', '!=', $model->id)
                    ->first();
                if ($existing != null) {
                    //delete dups
                    DB::table('movie_models')->where('id', $model->id)->delete();
                }
            }

            if (strpos($model->url, 'munowatch') !== false) {
                // $model->status = 'Inactive'; //if yes, set to yes 
            }
            return $model;
        });
    }

    //getter for local_video_link
    public function getLocalVideoLinkAttribute($value)
    {
        if ($value == null || $value == '' || strlen($value) < 5) {
            return null;
        }
        return 'https://storage.googleapis.com/mubahood-movies/' . $value;
    }

    //title getter
    public function getTitleAttribute($value)
    {
        //check if title contains translatedfilms
        if (strpos($value, 'translatedfilms') !== false) {

            $names = explode('/', $value);
            if (count($names) > 1) {
                $value = $names[count($names) - 1];
                DB::table('movie_models')
                    ->where('id', $this->id)
                    ->update([
                        'title' => $value
                    ]);


                return $value;
            }

            /* $new_title = str_replace('https://translatedfilms com/videos/', '', $value);
            $new_title = str_replace('https://translatedfilms.com/videos/', '', $new_title);
            $new_title = str_replace('https://translatedfilms com/', '', $value);
            $new_title = str_replace('https://translatedfilms.com videos/', '', $value);
            $new_title = str_replace('http://translatedfilms.com/videos/', '', $new_title);
            $new_title = str_replace('videos/', '', $new_title);
            $new_title = str_replace('translatedfilms.com', '', $new_title);
            $sql = "UPDATE movie_models SET title = '$new_title' WHERE id = {$this->id}";
            dd($sql);
            DB::update($sql);
            return $new_title; */
        }
        //http://localhost:8888/movies-new/make-tsv

        return ucwords($value);
    }

    //getter for url
    public function getUrlAttribute($value)
    {

        $url = $value;
        //check if url contains  http
        // if (!str_contains($value, 'googleapis')) {
        //     //check if is active
        //     if ($this->status == 'Active' && $this->external_url != null && strlen($this->external_url) > 5) {
        //         //check if firebase_video_url is not null and not empty
        //         if ($this->firebase_video_url != null && strlen($this->firebase_video_url) > 5) {
        //             $url = $this->firebase_video_url;
        //             $sql = "UPDATE movie_models SET url = '$url', old_video_url = '{$value}' WHERE id = {$this->id}";
        //             DB::update($sql);
        //             // return $url;
        //         }
        //     }
        // }

        //encode the url
        $value = $url;
        $value = str_replace(' ', '%20', $value);
        //replace http with https
        $value = str_replace('http://', 'https://', $value);
        return $value;
    }

    private const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/x-msvideo',
        'video/mpeg',
        'video/quicktime',
        'video/x-flv',
        'video/x-matroska',
        'video/webm',
        'video/3gpp',
        'video/3gpp2',
        'video/x-ms-wmv',
        'video/ogg',
        'application/vnd.apple.mpegurl',
        'application/x-mpegurl',
        'application/octet-stream',
        // …and any others you need
    ];

    /**
     * Determine whether the URL points to a movie by checking its Content-Type header.
     *
     * @return self
     */
    public function verify_movie(): self
    {
        $baseUrl = 'https://movies.ug/';
        $url     = $this->url;
        $addedBase = false;

        // Normalize URL
        if (stripos($url, 'http') !== 0) {
            $url = $baseUrl . ltrim($url, '/');
            $addedBase = true;
        }

        // Prepare defaults
        $this->content_type_processed      = 'Yes';
        $this->content_type_processed_time = Carbon::now();
        $this->content_is_video            = 'No';
        $this->status                      = 'Inactive';
        $this->external_url                = $url;

        $client = new Client([
            'timeout'         => 30,
            'allow_redirects' => true,
        ]);

        try {
            // Use HEAD to just fetch headers
            $response = $client->head($url);
            $rawType  = $response->getHeaderLine('Content-Type');
            // Strip charset if present
            [$contentType] = explode(';', $rawType);

            $this->content_type = $contentType;
            $this->url = $url;
            $this->external_url = $url;

            if (in_array(strtolower($contentType), self::VIDEO_MIME_TYPES, true)) {
                $this->content_is_video = 'Yes';
                $this->status           = 'Active';
            }
        } catch (\Exception $e) {
            //delete the url 
            // Handle exceptions (e.g., network issues, invalid URLs)
            $this->content_type = 'Unknown';
            $this->content_is_video = 'No';
            $this->status = 'Inactive';
        }

        // If we prefixed the base URL but it's not a video, revert the stored URL
        if ($this->content_is_video != 'Yes') {
            $this->delete();
            return $this;
        }

        $this->save();
        // Reload and return fresh model
        return self::find($this->id);
    }

    //getter for thumbnail_url
    public function getThumbnailUrlAttribute($value)
    {
        //if contains http, return value
        if (strpos($value, 'http') !== false) {
            return $value;
        }
        return 'https://katogo.schooldynamics.ug/storage/' . $value;
    }




    public function getWatchProgressAttribute()
    {
        $r = request();
        $u = Utils::get_user($r);
        if ($u === null) {
            return 0;
        }

        $view = DB::table('movie_views')->where([
            'movie_model_id' => $this->id,
            'user_id' => $u->id,
        ])->first();

        return $view !== null ? $view->progress : 0;
    }

    public function getMaxProgressAttribute()
    {
        $r = request();
        $u = Utils::get_user($r);
        if ($u === null) {
            return 0;
        }

        $view = DB::table('movie_views')->where([
            'movie_model_id' => $this->id,
            'user_id' => $u->id,
        ])->first();

        return $view !== null ? $view->max_progress : 0;
    }


    protected $appends = [
        'watch_progress',
        'max_progress',
    ];


    public function update_views()
    {
        $views = DB::table('movie_views')->where([
            'movie_model_id' => $this->id,
        ])->count();
        $views_time_count = DB::table('movie_views')->where([
            'movie_model_id' => $this->id,
        ])->sum('progress');

        try {
            $sql = "UPDATE movie_models SET views_count = $views, views_time_count = $views_time_count WHERE id = {$this->id}";
            DB::update($sql);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * Test if external video URL is working using cURL
     * Checks content-type to verify it's a video file
     * 
     * @return string 'Yes' if working, 'No' if not working
     */
    public function testExternalVideoUrl()
    {
        try {
            // Mark as being tested
            $this->video_url_tested_by_curl = 'Yes';
            $this->video_url_tested_by_curl_works = 'No';
            $this->save();

            // Get the video URL to test
            $testUrl = $this->external_url ?? $this->url;

            if (empty($testUrl)) {
                $this->video_url_tested_by_curl_works = 'No';
                $this->save();
                return 'No';
            }

            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $testUrl,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true, // HEAD request only
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            // Check for cURL errors
            if ($error) {
                $this->video_url_tested_by_curl_works = 'No';
                $this->save();
                return 'No';
            }

            // Check HTTP status code
            if ($httpCode < 200 || $httpCode >= 400) {
                $this->video_url_tested_by_curl_works = 'No';
                $this->save();
                return 'No';
            }

            // Enhanced content type validation
            if ($contentType) {
                $contentType = strtolower(explode(';', $contentType)[0]);

                // Strict video content types only - NO application/octet-stream
                $validVideoTypes = [
                    'video/mp4',
                    'video/avi',
                    'video/mov',
                    'video/wmv',
                    'video/flv',
                    'video/webm',
                    'video/mkv',
                    'video/3gp',
                    'video/mpeg',
                    'video/quicktime',
                    'video/x-msvideo',
                    'video/x-flv',
                    'video/x-matroska',
                    'video/ogg',
                    'video/mp2t',
                    'video/3gpp',
                    'video/3gpp2',
                    'video/x-ms-wmv'
                ];

                // Check if it's a proper video content type
                if (in_array($contentType, $validVideoTypes)) {
                    // Additional validation for octet-stream by checking file extension
                    $urlPath = parse_url($testUrl, PHP_URL_PATH);
                    $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

                    $validVideoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp', 'mpeg', 'mpg', 'm4v'];

                    // For octet-stream, also verify file extension
                    if ($contentType === 'application/octet-stream') {
                        if (!in_array($extension, $validVideoExtensions)) {
                            $this->video_url_tested_by_curl_works = 'No';
                            $this->content_type = $contentType;
                            $this->content_is_video = 'No';
                            $this->save();
                            return 'No';
                        }
                    }

                    // Valid video content found
                    $this->video_url_tested_by_curl_works = 'Yes';
                    $this->content_type = $contentType;
                    $this->content_is_video = 'Yes';
                    $this->save();
                    return 'Yes';
                }

                // Check for common non-video types that should be rejected
                $nonVideoTypes = [
                    'text/html',
                    'text/plain',
                    'text/xml',
                    'application/json',
                    'application/xml',
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'application/zip',
                    'application/x-www-form-urlencoded'
                ];

                if (in_array($contentType, $nonVideoTypes)) {
                    $this->video_url_tested_by_curl_works = 'No';
                    $this->content_type = $contentType;
                    $this->content_is_video = 'No';
                    $this->save();
                    return 'No';
                }
            }

            // If content type is unclear, do additional verification
            // Download first few bytes to check for video file signatures
            if ($this->performDeepVideoVerification($testUrl)) {
                $this->video_url_tested_by_curl_works = 'Yes';
                $this->content_type = 'video/unknown';
                $this->content_is_video = 'Yes';
                $this->save();
                return 'Yes';
            }

            // If we reach here, it's not a valid video
            $this->video_url_tested_by_curl_works = 'No';
            $this->save();
            return 'No';
        } catch (\Exception $e) {
            $this->video_url_tested_by_curl = 'Yes';
            $this->video_url_tested_by_curl_works = 'No';
            $this->save();
            return 'No';
        }
    }

    /**
     * Perform deep verification by checking file signature (magic bytes)
     * Downloads first 32 bytes to identify actual file type
     * 
     * @param string $url
     * @return bool
     */
    private function performDeepVideoVerification($url)
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_RANGE => '0-31', // Only download first 32 bytes
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);

            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode < 200 || $httpCode >= 300 || !$data) {
                return false;
            }

            // Check for video file signatures (magic bytes)
            $signatures = [
                // MP4
                "\x00\x00\x00\x18ftypmp41",
                "\x00\x00\x00\x20ftypmp41",
                "\x00\x00\x00\x1Cftypmp42",
                "ftypmp4",
                "ftypisom",
                // AVI
                "RIFF",
                // WebM
                "\x1A\x45\xDF\xA3",
                // MKV 
                "\x1A\x45\xDF\xA3",
                // MOV (QuickTime)
                "moov",
                "mdat",
                "ftyp",
                // FLV
                "FLV",
                // WMV/ASF
                "\x30\x26\xB2\x75\x8E\x66\xCF\x11",
                // 3GP
                "ftyp3g",
            ];

            foreach ($signatures as $signature) {
                if (strpos($data, $signature) !== false) {
                    return true;
                }
            }

            // Additional check for MP4 box headers
            if (strlen($data) >= 8) {
                $boxType = substr($data, 4, 4);
                $mp4BoxTypes = ['ftyp', 'moov', 'mdat', 'free', 'skip', 'wide'];
                if (in_array($boxType, $mp4BoxTypes)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Transfer video to Firebase Storage
     * 
     * @return array Result array with success status and details
     */
    public function transferToFirebase()
    {
        return;
        try {
            // Mark transfer as attempted and in progress
            $this->firebase_transfer_attempted = 'Yes';
            $this->firebase_transfer_transfer_in_progress = 'Yes';
            $this->firebase_transfer_successful = 'No';
            $this->firebase_transfer_failure_reason = null;
            $this->save();

            // Check if already transferred
            if ($this->firebase_transfer_successful === 'Yes' && !empty($this->firebase_video_url)) {
                return [
                    'success' => true,
                    'message' => 'Already transferred to Firebase',
                    'firebase_url' => $this->firebase_video_url
                ];
            }

            // Get video URL
            $videoUrl = $this->external_url ?? $this->url;
            if (empty($videoUrl)) {
                $this->firebase_transfer_transfer_in_progress = 'No';
                $this->firebase_transfer_failure_reason = 'No video URL found';
                $this->save();
                return ['success' => false, 'error' => 'No video URL found'];
            }

            // Store old URL before transfer
            if (empty($this->old_video_url)) {
                $this->old_video_url = $videoUrl;
                $this->save();
            }

            // Generate Firebase filename
            $fileName = 'movie_' . $this->id . '_' . time();

            // Use Utils class to upload to Firebase
            $result = Utils::uploadVideoToFirebase($videoUrl, $fileName, 'movies');

            if ($result['success']) {
                // Get permanent URL for the uploaded video
                $permanentResult = Utils::getFirebasePermanentUrl($result['firebase_path']);
                $firebaseUrl = $permanentResult['success'] ? $permanentResult['url'] : $result['firebase_url'];

                $this->firebase_transfer_transfer_in_progress = 'No';
                $this->firebase_transfer_successful = 'Yes';
                $this->firebase_transfer_path = $result['firebase_path'];
                $this->firebase_video_url = $firebaseUrl;
                $this->firebase_video_url_expires_at = $permanentResult['success'] ? null : now()->addYear();
                $this->save();

                return [
                    'success' => true,
                    'message' => 'Successfully transferred to Firebase',
                    'firebase_url' => $firebaseUrl,
                    'firebase_path' => $result['firebase_path']
                ];
            } else {
                $this->firebase_transfer_transfer_in_progress = 'No';
                $this->firebase_transfer_failure_reason = $result['error'];
                $this->save();

                return [
                    'success' => false,
                    'error' => $result['error']
                ];
            }
        } catch (\Exception $e) {
            $this->firebase_transfer_transfer_in_progress = 'No';
            $this->firebase_transfer_failure_reason = $e->getMessage();
            $this->save();

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test if Firebase video URL is working
     * 
     * @return string 'Yes' if working, 'No' if not working
     */
    public function testFirebaseVideoUrl()
    {
        try {
            return 'Yes';
            // Mark as being tested
            $this->firebase_video_tested_by_curl = 'Yes';
            $this->firebase_video_tested_by_curl_works = 'No';
            $this->save();

            if (empty($this->firebase_video_url)) {
                return 'No';
            }

            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->firebase_video_url,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true, // HEAD request only
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            // Check for errors
            if ($error || $httpCode < 200 || $httpCode >= 400) {
                $this->firebase_video_tested_by_curl_works = 'No';
                $this->save();
                return 'No';
            }

            // Enhanced Firebase content type validation
            if ($contentType) {
                $contentType = strtolower(explode(';', $contentType)[0]);

                // Strict video content types only - NO application/octet-stream
                $validVideoTypes = [
                    'video/mp4',
                    'video/avi',
                    'video/mov',
                    'video/wmv',
                    'video/flv',
                    'video/webm',
                    'video/mkv',
                    'video/3gp',
                    'video/mpeg',
                    'video/quicktime',
                    'video/x-msvideo',
                    'video/x-flv',
                    'video/x-matroska',
                    'video/ogg',
                    'video/mp2t',
                    'video/3gpp',
                    'video/3gpp2',
                    'video/x-ms-wmv'
                ];

                // Check if it's a proper video content type
                if (in_array($contentType, $validVideoTypes)) {
                    $this->firebase_video_tested_by_curl_works = 'Yes';

                    // Auto-activate if Firebase video is working
                    if ($this->status !== 'Active') {
                        $this->status = 'Active';
                    }

                    $this->save();
                    return 'Yes';
                }

                // Check for common non-video types that should be rejected
                $nonVideoTypes = [
                    'text/html',
                    'text/plain',
                    'text/xml',
                    'application/json',
                    'application/xml',
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'application/zip',
                    'application/x-www-form-urlencoded',
                    'application/octet-stream' // Explicitly reject this now
                ];

                if (in_array($contentType, $nonVideoTypes)) {
                    $this->firebase_video_tested_by_curl_works = 'No';
                    $this->save();
                    return 'No';
                }
            }

            // If content type is unclear, do additional verification for Firebase URLs
            if ($this->performDeepVideoVerification($this->firebase_video_url)) {
                $this->firebase_video_tested_by_curl_works = 'Yes';

                // Auto-activate if Firebase video is working
                if ($this->status !== 'Active') {
                    $this->status = 'Active';
                }

                $this->save();
                return 'Yes';
            }

            $this->firebase_video_tested_by_curl_works = 'No';
            $this->save();
            return 'No';
        } catch (\Exception $e) {
            $this->firebase_video_tested_by_curl = 'Yes';
            $this->firebase_video_tested_by_curl_works = 'No';
            $this->save();
            return 'No';
        }
    }

    /**
     * Check if video exists in Firebase Storage
     * 
     * @return bool
     */
    public function checkFirebaseExists()
    {
        try {
            if (empty($this->firebase_transfer_path)) {
                return false;
            }

            $storage = app('firebase.storage');
            $bucket = $storage->getBucket(config('firebase.storage.bucket'));
            $object = $bucket->object($this->firebase_transfer_path);

            return $object->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get movies that need external URL testing
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getNeedsUrlTesting($limit = 50)
    {
        return self::where('video_url_tested_by_curl', '!=', 'Yes')
            ->whereNotNull('url')
            ->limit($limit)
            ->get();
    }

    /**
     * Get movies ready for Firebase transfer
     * 
     * @param int $limit  
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getNeedsFirebaseTransfer($limit = 10)
    {
        return self::where('video_url_tested_by_curl_works', 'Yes')
            ->where('firebase_transfer_successful', '!=', 'Yes')
            ->where('firebase_transfer_transfer_in_progress', '!=', 'Yes')
            ->limit($limit)
            ->get();
    }

    /**
     * Get movies that need Firebase URL testing
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getNeedsFirebaseUrlTesting($limit = 50)
    {
        return self::where('firebase_transfer_successful', 'Yes')
            ->where('firebase_video_tested_by_curl', '!=', 'Yes')
            ->whereNotNull('firebase_video_url')
            ->limit($limit)
            ->get();
    }
}
