<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbSyncLog extends Model
{
    protected $table = 'db_sync_logs';
    public $timestamps = false;

    protected $fillable = [
        'run_id', 'table_name', 'rows_fetched', 'rows_upserted', 'rows_skipped',
        'pages_fetched', 'duration_ms', 'status', 'error_message',
        'cursor_id_before', 'cursor_id_after', 'started_at', 'completed_at', 'created_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'created_at'   => 'datetime',
    ];
}
