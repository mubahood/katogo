<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MergedAccount extends Model
{
    use HasFactory;

    protected $table = 'merged_accounts';

    protected $fillable = [
        'source_user_id',
        'target_user_id',
        'source_email',
        'source_phone_number',
        'target_email',
        'target_phone_number',
        'match_type',
        'merge_reason',
        'source_permissions',
        'target_permissions',
        'source_snapshot',
        'target_snapshot',
        'request_ip',
        'request_user_agent',
        'status',
        'sync_mode',
        'last_synced_at',
        'merged_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'source_permissions' => 'array',
        'target_permissions' => 'array',
        'source_snapshot' => 'array',
        'target_snapshot' => 'array',
        'last_synced_at' => 'datetime',
        'merged_at' => 'datetime',
    ];

    public function sourceUser()
    {
        return $this->belongsTo(User::class, 'source_user_id', 'id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id', 'id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
