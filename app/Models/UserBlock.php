<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBlock extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'blocker_id',
        'blocked_user_id',
        'reason',
        'block_type',
        'status',
        'expires_at',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime'
    ];

    // Block types
    const TYPE_USER_INITIATED = 'user_initiated';
    const TYPE_MODERATOR_INITIATED = 'moderator_initiated';
    const TYPE_AUTOMATIC = 'automatic';

    // Status types
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REMOVED = 'removed';

    /**
     * Get the user who initiated the block
     */
    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * Get the blocked user
     */
    public function blockedUser()
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }

    /**
     * Scope for active blocks
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Check if block is still active
     */
    public function getIsActiveAttribute()
    {
        return $this->status === self::STATUS_ACTIVE &&
               ($this->expires_at === null || $this->expires_at > now());
    }

    /**
     * Check if user A has blocked user B
     */
    public static function isBlocked($blockerUserId, $blockedUserId)
    {
        return self::where('blocker_id', $blockerUserId)
                  ->where('blocked_user_id', $blockedUserId)
                  ->active()
                  ->exists();
    }

    /**
     * Check if users have blocked each other (mutual block)
     */
    public static function isMutuallyBlocked($userId1, $userId2)
    {
        return self::isBlocked($userId1, $userId2) || self::isBlocked($userId2, $userId1);
    }

    /**
     * Get all users blocked by a specific user
     */
    public static function getBlockedUsers($userId)
    {
        return self::where('blocker_id', $userId)
                  ->active()
                  ->pluck('blocked_user_id')
                  ->toArray();
    }

    /**
     * Expire old blocks automatically
     */
    public static function expireOldBlocks()
    {
        return self::where('status', self::STATUS_ACTIVE)
                  ->where('expires_at', '<', now())
                  ->update(['status' => self::STATUS_EXPIRED]);
    }
}
