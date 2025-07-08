<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentModerationLog extends Model
{
    protected $fillable = [
        'content_type',
        'content_id',
        'user_id',
        'moderator_id',
        'action_type',
        'reason',
        'filter_result',
        'automated',
        'severity_level',
        'metadata'
    ];

    protected $casts = [
        'filter_result' => 'array',
        'metadata' => 'array',
        'automated' => 'boolean'
    ];

    // Action types
    const ACTION_CONTENT_FILTERED = 'content_filtered';
    const ACTION_CONTENT_APPROVED = 'content_approved';
    const ACTION_CONTENT_BLOCKED = 'content_blocked';
    const ACTION_CONTENT_QUARANTINED = 'content_quarantined';
    const ACTION_USER_WARNING = 'user_warning';
    const ACTION_USER_SUSPENDED = 'user_suspended';
    const ACTION_USER_BANNED = 'user_banned';

    // Severity levels
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Get the user associated with the moderated content
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the moderator who took the action
     */
    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    /**
     * Get the moderated content (polymorphic)
     */
    public function content()
    {
        return $this->morphTo('content', 'content_type', 'content_id');
    }

    /**
     * Scope for automated actions
     */
    public function scopeAutomated($query)
    {
        return $query->where('automated', true);
    }

    /**
     * Scope for manual moderator actions
     */
    public function scopeManual($query)
    {
        return $query->where('automated', false);
    }

    /**
     * Scope for high severity actions
     */
    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity_level', [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL]);
    }

    /**
     * Log a moderation action
     */
    public static function logAction($contentType, $contentId, $userId, $actionType, $options = [])
    {
        return self::create([
            'content_type' => $contentType,
            'content_id' => $contentId,
            'user_id' => $userId,
            'moderator_id' => $options['moderator_id'] ?? null,
            'action_type' => $actionType,
            'reason' => $options['reason'] ?? null,
            'filter_result' => $options['filter_result'] ?? null,
            'automated' => $options['automated'] ?? false,
            'severity_level' => $options['severity_level'] ?? self::SEVERITY_LOW,
            'metadata' => $options['metadata'] ?? null
        ]);
    }
}
