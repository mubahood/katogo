<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class MovieFileTransfer extends Model
{
    protected $table = 'movie_file_transfers';

    protected $fillable = [
        'movie_id',
        'movie_title',
        'movie_year',
        'movie_quality',
        'movie_duration',
        'movie_poster_url',
        'movie_munowatch_id',
        'movie_is_series',
        'movie_episode_info',
        'source_url',
        'source_type',
        'source_size_bytes',
        'source_verified_at',
        'dest_type',
        'dest_path',
        'dest_url',
        'dest_share_token',
        'dest_file_id',
        'dest_size_bytes',
        'status',
        'progress_pct',
        'bytes_transferred',
        'transfer_speed_mbps',
        'eta_seconds',
        'queued_at',
        'started_at',
        'completed_at',
        'duration_seconds',
        'attempt_count',
        'max_attempts',
        'last_attempted_at',
        'next_retry_at',
        'error_message',
        'error_trace',
        'error_context',
        'movie_url_updated',
        'old_movie_url_backed_up',
        'notes',
        'initiated_by',
        'worker_hostname',
    ];

    protected $casts = [
        'movie_is_series'         => 'boolean',
        'movie_episode_info'      => 'array',
        'error_context'           => 'array',
        'movie_url_updated'       => 'boolean',
        'old_movie_url_backed_up' => 'boolean',
        'source_verified_at'      => 'datetime',
        'queued_at'               => 'datetime',
        'started_at'              => 'datetime',
        'completed_at'            => 'datetime',
        'last_attempted_at'       => 'datetime',
        'next_retry_at'           => 'datetime',
    ];

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_QUEUED       = 'queued';
    const STATUS_VERIFYING    = 'verifying';
    const STATUS_TRANSFERRING = 'transferring';
    const STATUS_COMPLETING   = 'completing';
    const STATUS_DONE         = 'done';
    const STATUS_FAILED       = 'failed';
    const STATUS_CANCELLED    = 'cancelled';
    const STATUS_SKIPPED      = 'skipped';

    // ── Hetzner URL prefix for dedup check ───────────────────────────────────

    const HETZNER_HOST = 'your-storageshare.de';

    // ── Relationship ─────────────────────────────────────────────────────────

    public function movie(): BelongsTo
    {
        return $this->belongsTo(MovieModel::class, 'movie_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_QUEUED, self::STATUS_VERIFYING]);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_TRANSFERRING, self::STATUS_COMPLETING]);
    }

    public function scopeDone(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DONE);
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_FAILED);
    }

    public function scopeCancelled(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_CANCELLED);
    }

    /** Records that failed but still have retry budget and whose delay has elapsed. */
    public function scopeReadyToRetry(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_QUEUED)
                 ->where('attempt_count', '>', 0)
                 ->where(function (Builder $q) {
                     $q->whereNull('next_retry_at')
                       ->orWhere('next_retry_at', '<=', now());
                 });
    }

    /** All records for a specific movie. */
    public function scopeForMovie(Builder $q, int $movieId): Builder
    {
        return $q->where('movie_id', $movieId);
    }

    // ── Static factory ────────────────────────────────────────────────────────

    /**
     * Create a queued transfer record from a MovieModel.
     * Captures a snapshot of movie fields at queue time.
     */
    public static function queueForMovie(MovieModel $movie, string $initiatedBy = 'observer'): static
    {
        $sourceType = 'other';
        $url = (string)($movie->video_url ?? '');

        if (str_contains($url, 'munowatch')) {
            $sourceType = 'munowatch';
        } elseif (str_contains($url, 'drive.google.com') || str_contains($url, 'googleapis.com')) {
            $sourceType = 'gdrive';
        } elseif (str_contains($url, 'firebase') || str_contains($url, 'firebaseapp')) {
            $sourceType = 'firebase';
        } elseif (str_contains($url, self::HETZNER_HOST)) {
            $sourceType = 'hetzner';
        }

        return static::create([
            'movie_id'           => $movie->id,
            'movie_title'        => $movie->title,
            'movie_year'         => $movie->year ?? null,
            'movie_quality'      => $movie->quality ?? null,
            'movie_duration'     => $movie->duration ?? null,
            'movie_poster_url'   => $movie->poster_url ?? null,
            'movie_munowatch_id' => $movie->munowatch_id ?? null,
            'movie_is_series'    => ($movie->type ?? '') === 'Series',
            'movie_episode_info' => ($movie->type ?? '') === 'Series' ? [
                'season'       => $movie->season_number,
                'episode'      => $movie->episode_number,
                'series_title' => $movie->series_title,
            ] : null,
            'source_url'    => $url,
            'source_type'   => $sourceType,
            'dest_type'     => 'hetzner',
            'status'        => self::STATUS_QUEUED,
            'queued_at'     => now(),
            'initiated_by'  => $initiatedBy,
            'max_attempts'  => 3,
        ]);
    }

    // ── Guard helpers ─────────────────────────────────────────────────────────

    /**
     * True if the movie already has a pending, active, or completed transfer.
     * Used by the observer to prevent duplicate queue entries.
     */
    public static function hasPendingOrCompleted(int $movieId): bool
    {
        return static::where('movie_id', $movieId)
            ->whereIn('status', [
                self::STATUS_QUEUED,
                self::STATUS_VERIFYING,
                self::STATUS_TRANSFERRING,
                self::STATUS_COMPLETING,
                self::STATUS_DONE,
            ])
            ->exists();
    }

    /**
     * True if the given URL is already hosted on Hetzner Storage.
     */
    public static function isAlreadyOnHetzner(string $url): bool
    {
        return str_contains($url, self::HETZNER_HOST);
    }

    // ── State transitions ─────────────────────────────────────────────────────

    /**
     * Mark this transfer as cancelled. Only cancels non-terminal statuses.
     */
    public function cancel(): void
    {
        if (in_array($this->status, [self::STATUS_DONE, self::STATUS_CANCELLED, self::STATUS_SKIPPED])) {
            return;
        }
        $this->update(['status' => self::STATUS_CANCELLED]);
        Log::info("[MovieFileTransfer] #{$this->id} cancelled.");
    }

    /**
     * Reset a failed or stuck transfer back to queued so the scheduler picks it up.
     */
    public function resetToQueued(string $reason = ''): void
    {
        $this->update([
            'status'           => self::STATUS_QUEUED,
            'progress_pct'     => 0,
            'bytes_transferred' => 0,
            'eta_seconds'      => null,
            'next_retry_at'    => null,
            'notes'            => $reason ?: $this->notes,
        ]);
        Log::info("[MovieFileTransfer] #{$this->id} reset to queued. Reason: {$reason}");
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    public function getFormattedSpeedAttribute(): string
    {
        if (!$this->transfer_speed_mbps) return '—';
        return number_format($this->transfer_speed_mbps, 1) . ' MB/s';
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->source_size_bytes ?? $this->dest_size_bytes ?? 0;
        if (!$bytes) return '—';
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) return round($bytes, 1) . ' ' . $unit;
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' TB';
    }

    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_seconds) return '—';
        $s = $this->duration_seconds;
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $s = $s % 60;
        if ($h > 0) return "{$h}h {$m}m {$s}s";
        if ($m > 0) return "{$m}m {$s}s";
        return "{$s}s";
    }

    public function getFormattedEtaAttribute(): string
    {
        if (!$this->eta_seconds || $this->eta_seconds <= 0) return '—';
        $s = $this->eta_seconds;
        $m = intdiv($s, 60);
        $s = $s % 60;
        if ($m > 0) return "{$m}m {$s}s";
        return "{$s}s";
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED       => 'default',
            self::STATUS_VERIFYING    => 'info',
            self::STATUS_TRANSFERRING => 'primary',
            self::STATUS_COMPLETING   => 'warning',
            self::STATUS_DONE         => 'success',
            self::STATUS_FAILED       => 'danger',
            self::STATUS_CANCELLED    => 'warning',
            self::STATUS_SKIPPED      => 'default',
            default                   => 'default',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED       => 'Queued',
            self::STATUS_VERIFYING    => 'Verifying',
            self::STATUS_TRANSFERRING => 'Transferring',
            self::STATUS_COMPLETING   => 'Completing',
            self::STATUS_DONE         => 'Done',
            self::STATUS_FAILED       => 'Failed',
            self::STATUS_CANCELLED    => 'Cancelled',
            self::STATUS_SKIPPED      => 'Skipped',
            default                   => ucfirst($this->status),
        };
    }

    public function isRetriable(): bool
    {
        return $this->status === self::STATUS_FAILED
            && $this->attempt_count < $this->max_attempts;
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRANSFERRING, self::STATUS_COMPLETING]);
    }
}
