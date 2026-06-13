<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MovieModel;

class MovieVideoURLChange extends Model
{
    protected $table = 'movie_video_url_changes';

    protected $fillable = [
        'movie_model_id', 'movie_title', 'old_url', 'new_url',
        'source', 'status', 'local_status', 'remote_status',
        'transfer_id', 'remote_endpoint', 'remote_response',
        'error_message', 'attempts', 'max_attempts',
        'local_applied_at', 'remote_applied_at',
    ];

    protected $casts = [
        'local_applied_at'  => 'datetime',
        'remote_applied_at' => 'datetime',
    ];

    // ── Constants ─────────────────────────────────────────────────────────────

    const SOURCE_HETZNER   = 'hetzner_transfer';
    const SOURCE_MANUAL    = 'manual';
    const SOURCE_API_PUSH  = 'api_push';
    const SOURCE_REVERT    = 'revert';

    const STATUS_PENDING   = 'pending';
    const STATUS_APPLYING  = 'applying';
    const STATUS_APPLIED   = 'applied';
    const STATUS_FAILED    = 'failed';
    const STATUS_REVERTED  = 'reverted';

    const REMOTE_PENDING   = 'pending';
    const REMOTE_PUSHING   = 'pushing';
    const REMOTE_APPLIED   = 'applied';
    const REMOTE_FAILED    = 'failed';
    const REMOTE_SKIPPED   = 'skipped';

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeApplied(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPLIED);
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_FAILED);
    }

    public function scopeNeedsRemotePush(Builder $q): Builder
    {
        return $q->whereIn('remote_status', [self::REMOTE_PENDING, self::REMOTE_FAILED])
                 ->where('local_status', 'applied')
                 ->where('attempts', '<', \DB::raw('max_attempts'));
    }

    public function scopeRemoteFailed(Builder $q): Builder
    {
        return $q->where('remote_status', self::REMOTE_FAILED);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_model_id');
    }

    public function transfer()
    {
        return $this->belongsTo(\App\Models\MovieFileTransfer::class, 'transfer_id');
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPLIED  => '#27ae60',
            self::STATUS_FAILED   => '#c0392b',
            self::STATUS_APPLYING => '#2980b9',
            self::STATUS_REVERTED => '#8e44ad',
            default               => '#95a5a6',
        };
    }

    public function getRemoteBadgeColorAttribute(): string
    {
        return match ($this->remote_status) {
            self::REMOTE_APPLIED  => '#27ae60',
            self::REMOTE_FAILED   => '#c0392b',
            self::REMOTE_PUSHING  => '#2980b9',
            self::REMOTE_SKIPPED  => '#8e44ad',
            default               => '#95a5a6',
        };
    }

    public function getCanRevertAttribute(): bool
    {
        return $this->local_status === 'applied'
            && !empty($this->old_url)
            && $this->source !== self::SOURCE_REVERT;
    }

    public function getCanRetryRemoteAttribute(): bool
    {
        return $this->remote_status === self::REMOTE_FAILED
            && $this->attempts < $this->max_attempts;
    }

    // ── Static factory ────────────────────────────────────────────────────────

    /**
     * Create a change record, apply locally, and queue the remote push.
     * Called from TransferMovieToHetzner after a successful upload.
     */
    public static function recordAndSync(
        \App\Models\MovieFileTransfer $transfer,
        string $newUrl
    ): self {
        $movie    = MovieModel::find($transfer->movie_id);
        $oldUrl   = $movie?->url;
        $remoteEndpoint = config('services.url_sync.origin_url')
            ? rtrim(config('services.url_sync.origin_url'), '/') . '/api/movie-url-sync'
            : null;

        $change = self::create([
            'movie_model_id'  => $transfer->movie_id,
            'movie_title'     => $transfer->movie_title,
            'old_url'         => $oldUrl,
            'new_url'         => $newUrl,
            'source'          => self::SOURCE_HETZNER,
            'status'          => self::STATUS_APPLYING,
            'local_status'    => 'pending',
            'remote_status'   => $remoteEndpoint ? self::REMOTE_PENDING : self::REMOTE_SKIPPED,
            'transfer_id'     => $transfer->id,
            'remote_endpoint' => $remoteEndpoint,
        ]);

        // Apply locally immediately
        $change->applyLocal();

        // Queue the remote push if an origin URL is configured
        if ($remoteEndpoint) {
            dispatch(new \App\Jobs\PushUrlChangeToOrigin($change->id))
                ->onQueue('url-sync')
                ->delay(now()->addSeconds(5));
        }

        return $change->fresh();
    }

    // ── Instance actions ──────────────────────────────────────────────────────

    /**
     * Apply the URL change to this server's movie_models row.
     */
    public function applyLocal(): void
    {
        try {
            $movie = MovieModel::find($this->movie_model_id);
            if (!$movie) {
                $this->update([
                    'local_status'  => 'failed',
                    'error_message' => "Movie #{$this->movie_model_id} not found",
                ]);
                $this->recalcStatus();
                return;
            }

            $movie->url = $this->new_url;
            $movie->save();

            $this->update([
                'local_status'     => 'applied',
                'local_applied_at' => now(),
            ]);

            Log::info("[MovieVideoURLChange] #{$this->id} local applied. Movie #{$this->movie_model_id}");
        } catch (\Throwable $e) {
            $this->update([
                'local_status'  => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);
            Log::error("[MovieVideoURLChange] #{$this->id} local apply failed: " . $e->getMessage());
        }

        $this->recalcStatus();
    }

    /**
     * Mark remote push as succeeded with server response.
     */
    public function markRemoteApplied(string $response): void
    {
        $this->update([
            'remote_status'     => self::REMOTE_APPLIED,
            'remote_response'   => substr($response, 0, 2000),
            'remote_applied_at' => now(),
        ]);

        $this->recalcStatus();
    }

    /**
     * Mark remote push as failed, increment attempts.
     */
    public function markRemoteFailed(string $reason): void
    {
        $attempts = $this->attempts + 1;
        $this->update([
            'remote_status' => $attempts >= $this->max_attempts
                ? self::REMOTE_FAILED
                : self::REMOTE_PENDING,
            'attempts'      => $attempts,
            'error_message' => substr($reason, 0, 1000),
        ]);

        $this->recalcStatus();
    }

    /**
     * Revert this change: restore old_url on local DB and push revert to remote.
     * Creates a new inverse MovieVideoURLChange record.
     */
    public function revert(): self
    {
        if (!$this->can_revert) {
            throw new \RuntimeException("Change #{$this->id} cannot be reverted (no old_url or already reverted).");
        }

        $remoteEndpoint = config('services.url_sync.origin_url')
            ? rtrim(config('services.url_sync.origin_url'), '/') . '/api/movie-url-sync'
            : null;

        $revert = self::create([
            'movie_model_id'  => $this->movie_model_id,
            'movie_title'     => $this->movie_title,
            'old_url'         => $this->new_url,
            'new_url'         => $this->old_url,
            'source'          => self::SOURCE_REVERT,
            'status'          => self::STATUS_APPLYING,
            'local_status'    => 'pending',
            'remote_status'   => $remoteEndpoint ? self::REMOTE_PENDING : self::REMOTE_SKIPPED,
            'transfer_id'     => $this->transfer_id,
            'remote_endpoint' => $remoteEndpoint,
        ]);

        $revert->applyLocal();

        if ($remoteEndpoint) {
            dispatch(new \App\Jobs\PushUrlChangeToOrigin($revert->id))
                ->onQueue('url-sync')
                ->delay(now()->addSeconds(5));
        }

        // Mark the original change as reverted
        $this->update(['status' => self::STATUS_REVERTED]);

        return $revert->fresh();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    public function recalcStatusPublic(): void { $this->recalcStatus(); }

    private function recalcStatus(): void
    {
        $fresh = $this->fresh();

        $localOk  = $fresh->local_status === 'applied';
        $remoteOk = in_array($fresh->remote_status, [self::REMOTE_APPLIED, self::REMOTE_SKIPPED]);

        if ($localOk && $remoteOk) {
            $fresh->update(['status' => self::STATUS_APPLIED]);
        } elseif ($fresh->local_status === 'failed') {
            $fresh->update(['status' => self::STATUS_FAILED]);
        } elseif ($fresh->remote_status === self::REMOTE_FAILED && $fresh->attempts >= $fresh->max_attempts) {
            $fresh->update(['status' => self::STATUS_FAILED]);
        }
        // Otherwise stay as 'applying' / 'pending'
    }
}
