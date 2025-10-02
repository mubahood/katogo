<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovieWishlist extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'movie_model_id',
        'ip_address',
        'device',
        'platform',
        'browser',
        'country',
        'city',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who wishlisted the movie
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the movie that was wishlisted
     */
    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_model_id');
    }

    /**
     * Check if a user has wishlisted a specific movie
     */
    public static function hasUserWishlistedMovie(int $userId, int $movieId): bool
    {
        return self::where('user_id', $userId)
            ->where('movie_model_id', $movieId)
            ->where('status', 'Active')
            ->exists();
    }

    /**
     * Get total wishlist count for a movie
     */
    public static function getMovieWishlistCount(int $movieId): int
    {
        return self::where('movie_model_id', $movieId)
            ->where('status', 'Active')
            ->count();
    }
}
