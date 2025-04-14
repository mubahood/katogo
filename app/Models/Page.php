<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;
    
    //boot
    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            //url if already exists
            if (self::where('url', $model->url)->exists()) {
                return false;
            } 
        });
    } 
}
