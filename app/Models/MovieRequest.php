<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieRequest extends Model
{
    protected $table = 'movie_requests';

    protected $fillable = [
        'user_id',
        'customer_ticket_id',
        'status',
        'request_source',
        'platform_type',
        'app_type',
        'searched_query',
        'requested_movies',
        'user_message',
        'support_reply',
        'support_reply_at',
        'handled_by',
    ];

    protected $casts = [
        'requested_movies' => 'array',
        'support_reply_at' => 'datetime',
    ];

    public static array $validStatuses = [
        'submitted',
        'reviewing',
        'in_progress',
        'fulfilled',
        'rejected',
        'cancelled',
    ];

    public static array $validSources = [
        'search',
        'support',
        'manual',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(CustomerTicket::class, 'customer_ticket_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
