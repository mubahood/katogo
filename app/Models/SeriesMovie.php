<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeriesMovie extends Model
{
    use HasFactory;

    //boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->is_active == 'Yes') {
                //update sql
                $sql = 'UPDATE series_movies SET is_active = "No" ';
                \DB::statement($sql);
            } 
        });

        static::updating(function ($model) {

            if ($model->is_active == 'Yes') {
                //update sql
                $sql = 'UPDATE series_movies SET is_active = "No" ';
                \DB::statement($sql);
            }
        });
    }
}
