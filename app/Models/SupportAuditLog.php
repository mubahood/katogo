<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'actor_role',
        'event_type',
        'entity_type',
        'entity_id',
        'description',
        'meta',
        'ip_address',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
