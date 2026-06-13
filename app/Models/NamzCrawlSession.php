<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NamzCrawlSession extends Model
{
    protected $fillable = [
        'status', 'id_from', 'id_to', 'id_current',
        'total_processed', 'total_success', 'total_skipped', 'total_failed',
        'total_new_movies', 'total_new_series', 'total_url_pending',
        'triggered_by', 'notes', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    const STATUS_RUNNING   = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_STOPPED   = 'stopped';
    const STATUS_FAILED    = 'failed';

    public function logs()
    {
        return $this->hasMany(NamzCrawlLog::class, 'session_id');
    }

    public function getProgressPercentAttribute(): int
    {
        $range = $this->id_to - $this->id_from;
        if ($range <= 0) return 0;
        $done = $this->id_current - $this->id_from;
        return (int) min(100, round(($done / $range) * 100));
    }

    public function getDurationAttribute(): string
    {
        if (!$this->started_at) return '—';
        $end = $this->ended_at ?? now();
        $diff = $this->started_at->diffInSeconds($end);
        $h = intdiv($diff, 3600);
        $m = intdiv($diff % 3600, 60);
        $s = $diff % 60;
        return ($h > 0 ? "{$h}h " : '') . ($m > 0 ? "{$m}m " : '') . "{$s}s";
    }

    public function getStatusBadgeAttribute(): string
    {
        $colors = [
            'running'   => '#2980b9',
            'completed' => '#27ae60',
            'stopped'   => '#e67e22',
            'failed'    => '#e74c3c',
        ];
        $color = $colors[$this->status] ?? '#95a5a6';
        return "<span style='background:{$color};color:#fff;padding:2px 8px;border-radius:3px;font-size:11px'>"
             . ucfirst($this->status) . "</span>";
    }

    public static function activeSession(): ?self
    {
        return self::where('status', self::STATUS_RUNNING)->latest()->first();
    }

    public static function lastSession(): ?self
    {
        return self::latest()->first();
    }
}
