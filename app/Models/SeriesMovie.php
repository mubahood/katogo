<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SeriesMovie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'Category', 'description', 'thumbnail', 'total_seasons', 'total_episodes',
        'total_views', 'total_rating', 'is_active', 'vj', 'genre', 'language', 'year',
        'series_code', 'munowatch_id', 'is_muno', 'external_url',
        'fix_status', 'fix_error_message', 'fix_date', 'fix_counter',
    ];

    protected $casts = [
        'fix_date'    => 'datetime',
        'fix_counter' => 'integer',
    ];

    //boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->is_active == 'Yes') {
                MovieModel::where('category_id', $model->id)->update([
                    'status' => 'Active',
                    'thumbnail_url' => $model->thumbnail
                ]);
            } else {
                MovieModel::where('category_id', $model->id)->update([
                    'status' => 'Inactive',
                ]);
            }
            $model->is_active = 'No';
        });

        static::updated(function ($model) {
            if ($model->is_active == 'Yes') {
                MovieModel::where('category_id', $model->id)->update([
                    'status' => 'Active',
                    'thumbnail_url' => $model->thumbnail
                ]);
            } else {
                MovieModel::where('category_id', $model->id)->update([
                    'status' => 'Inactive',
                ]);
            }
            $count_tot_episodes = MovieModel::where('category_id', $model->id)->count();
            $sql = "UPDATE series_movies SET total_episodes = $count_tot_episodes WHERE id = $model->id";
            DB::update($sql);
        });
    }

    //has many relationship with movie model
    public function episodes()
    {
        return $this->hasMany(MovieModel::class, 'category_id', 'id');
    }


    public function getThumbnailAttribute($value)
    {
        //if contains http, return value
        if (strpos($value, 'http') !== false) {
            return $value;
        }
        return 'https://katogo.schooldynamics.ug/storage/' . $value;
    }
}
