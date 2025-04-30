<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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


    //boot
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($model) {
            $model->update_views();
        });
        static::created(function ($model) {
            $model->update_views();
        });
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
}
