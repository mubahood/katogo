<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SeriesMovie extends Model
{
    use HasFactory;

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
            }
        });

        static::updating(function ($model) {

            if ($model->is_active == 'Yes') {
                MovieModel::where('category_id', $model->id)->update([
                    'status' => 'Active',
                    'thumbnail_url' => $model->thumbnail
                ]);
            }
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
