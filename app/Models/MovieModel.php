<?php

namespace App\Models;

use Dflydev\DotAccessData\Util;
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
            $model->created_at = date('Y-m-d H:i:s');
            //check if type is series
            if ($model->type == 'Series') {
                $series = SeriesMovie::find($model->category_id);
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
            }
        });

        static::updating(function ($model) {
            if ($model->type == 'Series') {
                $series = SeriesMovie::find($model->category_id);
                $model->category = $series->title;
                $model->category = $series->title;
                if ($model->thumbnail_url == null || $model->thumbnail_url == '') {
                    $model->thumbnail_url = $series->thumbnail;
                }
            }

            $video_downloaded_to_server_duration = 0;
            if ($model->video_downloaded_to_server_start_time && $model->video_downloaded_to_server_end_time) {
                try {
                    $video_downloaded_to_server_duration = strtotime($model->video_downloaded_to_server_end_time) - strtotime($model->video_downloaded_to_server_start_time);
                } catch (\Exception $e) {
                    $video_downloaded_to_server_duration = -1;
                }
            }
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

        //check if url contains  http
        if (str_contains($value, 'http')) {
            return $value;
        }
        return $value;
        $url = $this->external_url;
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

    public function verify_movie()
    {
        $url = $this->url;
        $url = 'https://mobifliks.info/downloadmp4.php?file=luganda/Suky%20by%20Vj%20Emmy%20-%20Mobifliks.com.mp4';
        //check if contains http
        if (strpos($url, 'http') === false) {
            $url = 'https://movies.ug/' . $url;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);

        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $video_types = [
            "video/mp4",
            "video/x-msvideo",
            "video/mpeg",
            "video/quicktime",
            "video/x-flv",
            "video/x-matroska",
            "video/webm",
            "video/3gpp",
            "video/3gpp2",
            "video/x-ms-wmv",
            "video/ogg",
            "application/vnd.apple.mpegurl",
            "application/x-mpegurl",
            'application/octet-stream',
            'application/x-mpeg',
            'application/x-mpegurl',
            'application/x-mpeg-4',
            'application/x-mpeg-4p',
            'application/x-mpeg-4v',
            'application/x-mpeg-4v2',
            'application/x-mpeg-4v3',
            'application/x-mpeg-4v4',
            'application/x-mpeg-4v5',
            'application/x-mpeg-4v6',
            'application/x-mpeg-4v7',
            'application/x-mpeg-4v8',
            'application/x-mpeg-4v9',

            'video/mp2t',
            'video/x-ms-asf',
            'video/x-msvideo',
            'video/x-m4v',
            'video/x-mpeg',
            'video/x-mpeg2',
            'video/x-mpeg3',
            'video/x-mpeg4',
            'video/x-ms-dvr',
            'video/x-ms-vob',
            'video/x-ms-wvx',
            'video/x-ms-wm',
            'video/x-ms-wmx',
            'video/x-ms-wmv',
            'video/x-ms-wvx',
            'video/x-ms-wvx-dvr',
            'video/x-ms-wvx-live',
            'video/x-ms-wvx-on-demand',
            'video/x-ms-wvx-streaming',
            'video/x-ms-wvx-download',
            'video/x-ms-wvx-stream',
            'video/x-ms-wvx-play',
            'video/x-ms-wvx-record',
            'video/x-ms-wvx-broadcast',
            'video/x-ms-wvx-live-stream',
            'video/x-ms-wvx-on-demand-stream',
            'video/x-ms-wvx-download-stream',
            'video/x-ms-wvx-play-stream',
            'video/x-ms-wvx-record-stream',
        ];

        $this->content_type_processed = 'Yes';
        $this->content_type_processed_time = date('Y-m-d H:i:s');
        $this->content_is_video = 'No';
        $this->content_type =  $contentType;


        if (in_array(strtolower($contentType), $video_types)) {
            $this->content_is_video = 'Yes';
            $this->status = 'Active';
        } else {
            $this->content_is_video = 'No';
            $this->status = 'Inactive';
        }
        $this->content_type = $contentType;
        $this->save();
        return $contentType;
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
}
