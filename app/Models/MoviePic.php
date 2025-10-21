<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoviePic extends Model
{
    use HasFactory;

    //fillable of movie_id
    protected $fillable = [
        'movie_id',
        'pic_url', 
    ];
}
