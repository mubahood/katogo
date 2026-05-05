<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTicketRecord extends Model
{
    protected $table = 'customer_ticket_records';

    protected $fillable = [
        'customer_ticket_id',
        'sender_type',
        'sender_id',
        'message',
        'action_type',
        'action_description',
        'is_internal_note',
        'show_to_customer',
        'attachment_url',
        'is_read_by_user',
        'customer_seen',
        'customer_seen_at',
        'is_read_by_support',
    ];

    protected $casts = [
        'is_internal_note'   => 'boolean',
        'show_to_customer'   => 'boolean',
        'is_read_by_user'    => 'boolean',
        'customer_seen'      => 'boolean',
        'customer_seen_at'   => 'datetime',
        'is_read_by_support' => 'boolean',
    ];

    public static array $validActionTypes = [
        'none',
        'needs_user_action',
        'needs_support_action',
        'status_change',
        'agent_has_contacted_customer',
        'customer_has_responded',
        'message_from_customer',
        'rating_of_satisfaction',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(CustomerTicket::class, 'customer_ticket_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    public function isFromUser(): bool
    {
        return $this->sender_type === 'user';
    }

    public function isFromSupport(): bool
    {
        return $this->sender_type === 'support_team';
    }

    public function isSystemMessage(): bool
    {
        return $this->sender_type === 'system';
    }
}
