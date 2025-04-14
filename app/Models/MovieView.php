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
}
