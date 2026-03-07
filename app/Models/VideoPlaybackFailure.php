<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Jobs\AutoFixMovie;

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

        // Fix tracking
        'fix_status',
        'fix_status_message',
        'number_of_fix_attempts',
        'last_fix_attempt_at',
    ];

    protected $casts = [
        'has_subscription' => 'boolean',
        'retry_count' => 'integer',
        'number_of_fix_attempts' => 'integer',
        'subscription_expires_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_fix_attempt_at' => 'datetime',
        'additional_data' => 'array',
    ];

    /**
     * Boot method - automatically deactivate movie when failure is reported
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($failure) {
            // Do NOT immediately deactivate — let auto-fix attempt repair first.
            // Only auto-fix should set a movie to Inactive (if fix fails).

            // Schedule auto-fix to run AFTER the HTTP response is sent.
            // This re-fetches movie data from the external server and repairs the record.
            // Guarded by cooldown (5 min per movie) and re-entry prevention.
            if ($failure->movie_id && !AutoFixMovie::isInProgress()) {
                try {
                    AutoFixMovie::scheduleAfterResponse((int) $failure->movie_id, (int) $failure->id);
                } catch (\Throwable $e) {
                    // Never let auto-fix scheduling break failure creation
                    Log::error("[AutoFixMovie] Failed to schedule for movie #{$failure->movie_id}: " . $e->getMessage());
                }
            }
        });
    }

    /**
     * Deactivate the related movie
     * Sets status to 'Inactive' and records the reason
     */
    public function deactivateRelatedMovie()
    {
        if (!$this->movie_id) {
            return false;
        }

        try {
            $movie = MovieModel::find($this->movie_id);
            if ($movie && $movie->status !== 'Inactive') {
                $previousStatus = $movie->status;
                $movie->status = 'Inactive';
                
                // Record the failure details in muno_success field
                $deactivationNote = "Auto-deactivated: Playback failure on " . now()->format('Y-m-d H:i:s');
                $errorDetail = $this->error_message ?? $this->error_type ?? 'Unknown error';
                $movie->muno_success = $deactivationNote . " - " . $errorDetail;
                
                // Also set error_message if available
                if ($this->error_message) {
                    $movie->error_message = "Playback failure: " . $this->error_message;
                }
                
                $movie->save();

                Log::info("Movie #{$this->movie_id} ({$this->movie_title}) auto-deactivated due to playback failure #{$this->id}. Previous status: {$previousStatus}");
                
                return true;
            }
        } catch (\Exception $e) {
            Log::error("Failed to deactivate movie #{$this->movie_id} after playback failure: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Get the movie that failed to play
     */
    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_id');
    }

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
     * Mark failure as resolved and optionally reactivate the movie
     */
    public function markAsResolvedAndReactivateMovie($notes = null)
    {
        $this->markAsResolved($notes);
        
        if ($this->movie_id) {
            $movie = MovieModel::find($this->movie_id);
            if ($movie && $movie->status === 'Inactive') {
                $movie->status = 'Active';
                $movie->muno_success = "Reactivated: Issue resolved on " . now()->format('Y-m-d H:i:s');
                $movie->error_message = null;
                $movie->save();
                
                Log::info("Movie #{$this->movie_id} reactivated after resolving playback failure #{$this->id}");
                return true;
            }
        }
        return false;
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
