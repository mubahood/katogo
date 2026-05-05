<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerTicket extends Model
{
    protected $table = 'customer_tickets';

    protected $fillable = [
        'user_id',
        'status',
        'ticket_type',
        'resolution_state',
        'subject',
        'account_origin',
        'app_type',
        'platform_type',
        'platform',
        'assigned_to',
        'last_reply_at',
        'reply_count',
        'rating_of_satisfaction',
        'agent_has_contacted_customer',
        'customer_has_responded',
        'has_unread_user',
        'has_unread_support',
        'is_movie_request',
        'movie_request_payload',
    ];

    protected $casts = [
        'has_unread_user'    => 'boolean',
        'has_unread_support' => 'boolean',
        'last_reply_at'      => 'datetime',
        'reply_count'        => 'integer',
        'rating_of_satisfaction' => 'integer',
        'agent_has_contacted_customer' => 'boolean',
        'customer_has_responded' => 'boolean',
        'is_movie_request' => 'boolean',
        'movie_request_payload' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    /** The user who owns this ticket */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The support team member assigned to this ticket */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Compatibility alias for admin-grid relation parsing that may request
     * snake_case relation names (assigned_agent).
     */
    public function assigned_agent(): BelongsTo
    {
        return $this->assignedAgent();
    }

    /** All message records inside this ticket */
    public function records(): HasMany
    {
        return $this->hasMany(CustomerTicketRecord::class, 'customer_ticket_id')->orderBy('created_at');
    }

    /** Latest record (for ticket list previews) */
    public function latestRecord(): HasMany
    {
        return $this->hasMany(CustomerTicketRecord::class, 'customer_ticket_id')
            ->latest()
            ->limit(1);
    }

    // ── Status helpers ────────────────────────────────────────────────

    public static array $validStatuses = [
        'open', 'pending', 'in_progress', 'resolved', 'closed', 'escalated',
    ];

    public static array $validTicketTypes = [
        'general',
        'account_opening',
        'payment_thanks',
        'payment_fail',
        'auto_account_issue',
        'subscription_issue',
        'technical_issue',
        'billing_issue',
        'content_issue',
        'movie_request',
    ];

    public static array $validResolutionStates = [
        'unresolved',
        'resolved',
        'cancelled',
    ];

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isEscalated(): bool
    {
        return $this->status === 'escalated';
    }

    public function isResolvedState(): bool
    {
        return $this->resolution_state === 'resolved';
    }

    public function isCancelledState(): bool
    {
        return $this->resolution_state === 'cancelled';
    }
}
