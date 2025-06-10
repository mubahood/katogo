<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovieDownload extends Model
{
    use HasFactory;

    //belonsg to user_id
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    //created boot
    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            $movie = MovieModel::find($model->movie_model_id);
            if ($movie != null) {
                $downloads_counts = MovieDownload::where('movie_model_id', $model->movie_model_id)->count();
                $movie->downloads_count = $downloads_counts;
                $movie->save();
            }
            //downloads_count
        });

        //updated
        static::updated(function ($model) {
            $movie = MovieModel::find($model->movie_model_id);
            if ($movie != null) {
                $downloads_counts = MovieDownload::where('movie_model_id', $model->movie_model_id)->count();
                $movie->downloads_count = $downloads_counts;
                $movie->save();
            }
        });

        //deleted
        static::deleted(function ($model) {
            $movie = MovieModel::find($model->movie_model_id);
            if ($movie != null) {
                $downloads_counts = MovieDownload::where('movie_model_id', $model->movie_model_id)->count();
                $movie->downloads_count = $downloads_counts;
                $movie->save();
            }
        });
    }
}
