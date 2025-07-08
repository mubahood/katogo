<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reporter_id',
        'reported_content_type',
        'reported_content_id',
        'reported_user_id',
        'report_type',
        'description',
        'status',
        'moderator_id',
        'moderation_action',
        'moderation_notes',
        'priority',
        'resolved_at',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved_at' => 'datetime'
    ];

    // Report types
    const TYPE_SPAM = 'spam';
    const TYPE_HARASSMENT = 'harassment';
    const TYPE_INAPPROPRIATE_CONTENT = 'inappropriate_content';
    const TYPE_HATE_SPEECH = 'hate_speech';
    const TYPE_VIOLENCE = 'violence';
    const TYPE_COPYRIGHT = 'copyright';
    const TYPE_MISINFORMATION = 'misinformation';
    const TYPE_OTHER = 'other';

    // Status types
    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_DISMISSED = 'dismissed';
    const STATUS_ESCALATED = 'escalated';

    // Priority levels
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    // Moderation actions
    const ACTION_NO_ACTION = 'no_action';
    const ACTION_WARNING = 'warning';
    const ACTION_CONTENT_REMOVED = 'content_removed';
    const ACTION_USER_SUSPENDED = 'user_suspended';
    const ACTION_USER_BANNED = 'user_banned';
    const ACTION_ESCALATED = 'escalated';

    /**
     * Get the user who made the report
     */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Get the reported user
     */
    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /**
     * Get the moderator handling the report
     */
    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    /**
     * Get the reported content (polymorphic)
     */
    public function reportedContent()
    {
        return $this->morphTo('reported_content', 'reported_content_type', 'reported_content_id');
    }

    /**
     * Scope for pending reports
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for high priority reports
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    /**
     * Scope for reports older than 24 hours
     */
    public function scopeOverdue($query)
    {
        return $query->where('created_at', '<', now()->subHours(24))
                    ->whereIn('status', [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW]);
    }

    /**
     * Check if report is overdue (24 hour SLA)
     */
    public function getIsOverdueAttribute()
    {
        return $this->created_at < now()->subHours(24) && 
               in_array($this->status, [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW]);
    }

    /**
     * Get auto-assignment priority
     */
    public static function getAutoPriority($reportType, $metadata = [])
    {
        // Critical priority for hate speech, violence, harassment
        if (in_array($reportType, [self::TYPE_HATE_SPEECH, self::TYPE_VIOLENCE, self::TYPE_HARASSMENT])) {
            return self::PRIORITY_CRITICAL;
        }

        // High priority for inappropriate content
        if ($reportType === self::TYPE_INAPPROPRIATE_CONTENT) {
            return self::PRIORITY_HIGH;
        }

        // Medium priority for spam and copyright
        if (in_array($reportType, [self::TYPE_SPAM, self::TYPE_COPYRIGHT])) {
            return self::PRIORITY_MEDIUM;
        }

        // Low priority for others
        return self::PRIORITY_LOW;
    }
}
