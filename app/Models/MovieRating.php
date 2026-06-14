<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovieRating extends Model
{
    protected $fillable = ['user_id', 'movie_id', 'rating', 'review'];

    protected $casts = ['rating' => 'integer'];

    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
