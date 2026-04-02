<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovieDownload extends Model
{
    use HasFactory;

    //belongs to user_id
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    //belongs to movie
    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_model_id');
    }

    protected static function boot()
    {
        parent::boot();

        $syncCounts = function ($model) {
            $counts = MovieDownload::where('movie_model_id', $model->movie_model_id)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN download_type = "in_app" THEN 1 ELSE 0 END) as in_app,
                    SUM(CASE WHEN download_type = "gallery" THEN 1 ELSE 0 END) as gallery
                ')
                ->first();

            if ($counts) {
                MovieModel::where('id', $model->movie_model_id)->update([
                    'downloads_count' => $counts->total ?? 0,
                    'in_app_downloads_count' => $counts->in_app ?? 0,
                    'gallery_downloads_count' => $counts->gallery ?? 0,
                ]);
            }
        };

        static::created($syncCounts);
        static::updated($syncCounts);
        static::deleted($syncCounts);
    }
}
