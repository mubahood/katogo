<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoPlaybackFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        // User information
        'user_id',
        'user_name',
        'user_email',
        'user_phone',
        
        // Movie information
        'movie_id',
        'movie_title',
        'original_url',
        'transformed_url',
        
        // Failure details
        'error_message',
        'error_code',
        'error_type',
        'retry_count',
        
        // Device & App information
        'device_model',
        'device_os',
        'device_os_version',
        'app_version',
        'player_type',
        
        // Network information
        'network_type',
        'ip_address',
        'user_agent',
        
        // Subscription status
        'has_subscription',
        'subscription_type',
        'subscription_expires_at',
        
        // Context
        'screen_name',
        'additional_data',
        
        // Resolution status
        'status',
        'admin_notes',
        'resolved_at',
    ];

    protected $casts = [
        'has_subscription' => 'boolean',
        'retry_count' => 'integer',
        'subscription_expires_at' => 'datetime',
        'resolved_at' => 'datetime',
        'additional_data' => 'array',
    ];

    /**
     * Get the user that experienced the failure
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include pending failures
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include failures for subscribed users
     */
    public function scopeSubscribed($query)
    {
        return $query->where('has_subscription', true);
    }

    /**
     * Scope a query to filter by error type
     */
    public function scopeByErrorType($query, $errorType)
    {
        return $query->where('error_type', $errorType);
    }

    /**
     * Scope a query to get recent failures
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Mark failure as resolved
     */
    public function markAsResolved($notes = null)
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }

    /**
     * Get failure count for a specific movie
     */
    public static function getFailureCountForMovie($movieId, $days = 30)
    {
        return static::where('movie_id', $movieId)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    /**
     * Get failure count for a specific user
     */
    public static function getFailureCountForUser($userId, $days = 30)
    {
        return static::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    /**
     * Get most common error types
     */
    public static function getMostCommonErrors($limit = 10)
    {
        return static::selectRaw('error_type, COUNT(*) as count')
            ->groupBy('error_type')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get movies with most failures
     */
    public static function getMoviesWithMostFailures($limit = 20)
    {
        return static::selectRaw('movie_id, movie_title, COUNT(*) as failure_count')
            ->whereNotNull('movie_id')
            ->groupBy('movie_id', 'movie_title')
            ->orderByDesc('failure_count')
            ->limit($limit)
            ->get();
    }
}
