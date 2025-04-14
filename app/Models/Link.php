<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    use HasFactory;

    //creating boot avoid duplicate url entries
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($link) {
            //check if the url already exists
            $existingLink = Link::where('url', $link->url)->first();
            if ($existingLink) {
                return false;
            } 
            
        });
    } 
}
