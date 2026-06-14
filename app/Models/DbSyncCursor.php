<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbSyncCursor extends Model
{
    protected $table = 'db_sync_cursors';

    protected $fillable = [
        'table_name', 'last_synced_id', 'last_updated_ts', 'last_run_at',
        'rows_on_source', 'rows_this_run', 'consecutive_errors',
        'status', 'error_message', 'priority', 'frequency_minutes',
        'enabled', 'has_timestamps', 'is_pivot',
    ];

    protected $casts = [
        'last_updated_ts' => 'datetime',
        'last_run_at'     => 'datetime',
        'enabled'         => 'boolean',
        'has_timestamps'  => 'boolean',
        'is_pivot'        => 'boolean',
    ];

    public function isDue(): bool
    {
        if (!$this->enabled) return false;
        if ($this->status === 'syncing') return false;
        if (!$this->last_run_at) return true;
        return $this->last_run_at->addMinutes($this->frequency_minutes)->isPast();
    }

    public function markSyncing(): void
    {
        $this->update(['status' => 'syncing', 'last_run_at' => now()]);
    }

    public function markOk(int $newId, ?string $newTs, int $rowsThisRun): void
    {
        $this->update([
            'status'             => 'ok',
            'last_synced_id'     => $newId,
            'last_updated_ts'    => $newTs,
            'rows_this_run'      => $rowsThisRun,
            'consecutive_errors' => 0,
            'error_message'      => null,
        ]);
    }

    public function markError(string $message): void
    {
        $this->increment('consecutive_errors');
        $this->update([
            'status'        => 'error',
            'error_message' => substr($message, 0, 1000),
        ]);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'ok'      => '<span style="color:#27ae60;font-weight:600">● ok</span>',
            'syncing' => '<span style="color:#2980b9;font-weight:600">⟳ syncing</span>',
            'error'   => '<span style="color:#c0392b;font-weight:600">✕ error</span>',
            default   => '<span style="color:#95a5a6;font-weight:600">○ idle</span>',
        };
    }
}
