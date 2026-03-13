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

    protected static function boot()
    {
        parent::boot();

        $syncCounts = function ($model) {
            $movie = MovieModel::find($model->movie_model_id);
            if ($movie) {
                $base = MovieDownload::where('movie_model_id', $model->movie_model_id);
                $movie->downloads_count          = (clone $base)->count();
                $movie->in_app_downloads_count    = (clone $base)->where('download_type', 'in_app')->count();
                $movie->gallery_downloads_count   = (clone $base)->where('download_type', 'gallery')->count();
                $movie->save();
            }
        };

        static::created($syncCounts);
        static::updated($syncCounts);
        static::deleted($syncCounts);
    }
}
