<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MovieView extends Model
{
    use HasFactory;
    //fillable
    protected $fillable = [
        'movie_model_id',
        'user_id',
        'ip_address',
        'device',
        'platform',
        'browser',
        'country',
        'city',
        'status',
        'progress',
        'max_progress',
    ]; 


    //boot - throttle view count updates to once per 5 min per movie
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($model) {
            $model->throttled_update_views();
        });
        static::created(function ($model) {
            $model->throttled_update_views();
        });
    }

    // Throttled view update - only recalculate counts once per 5 min per movie
    public function throttled_update_views(){
        $cacheKey = "mv_views_update_{$this->movie_model_id}";
        if (Cache::has($cacheKey)) {
            return; // Skip - already updated recently
        }
        Cache::put($cacheKey, true, 300); // 5 min throttle
        $this->update_views();
    }

    //udpated movie views
    public function update_views(){
        if($this->movie == null){
            return;
        }
        try {
            $this->movie->update_views();
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    //belongs to movie
    public function movie(){
        return $this->belongsTo(MovieModel::class, 'movie_model_id');
    }

    //belongs to user
    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
}
