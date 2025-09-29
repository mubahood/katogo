<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Watchlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'movie_model_id',
        'status',
        'added_at'
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    // Relationship to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship to movie
    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_model_id');
    }

    // Scope for active watchlist items
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}