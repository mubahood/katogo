<?php

namespace App\Models;

use Carbon\Carbon;
use Dflydev\DotAccessData\Util;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MovieModel extends Model
{
    use HasFactory;

    //boot
    protected static function boot()
    {
        parent::boot();

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
        });

        static::updating(function ($model) {
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

        if (str_contains($value, 'https:')) {
            return $value;
        }
        if (str_contains($value, 'http:')) {
            return $value;
        }

        //check if it contains http, return the value
        if (strpos($value, 'http') !== false) {
            return $value;
        }

        $url = $value;
        //check if url contains  http
        if (str_contains($value, 'googleapis')) {
            $url = $this->external_url;
        }

        //check if doest not have http
        if (strpos($url, 'http') === false) {
            return 'https://movies.ug/' . $value;
        }
        return $url;
        if ($value == null || $value == '' || strlen($value) < 5) {
            return '';
        }

        //check if does not contain google and return this.external_url
        if (!(strpos($value, 'google') !== false)) {
            return $this->external_url;
        }
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
}
