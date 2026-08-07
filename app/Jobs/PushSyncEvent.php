<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fire-and-forget: POST one or more rows to the replica's receive-event endpoint.
 *
 * Dispatched by AppServiceProvider model listeners on SYNC_ROLE=source.
 * Retries 3 times with 5-second back-off so transient network blips don't lose data.
 * The SSH tunnel sync (sync:pull) acts as a safety net — even if all retries fail,
 * the row will be picked up within SYNC_FREQUENCY_MINUTES minutes.
 */
class PushSyncEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(
        private readonly string $table,
        private readonly array  $rows,
    ) {}

    /** Cache key marking the replica as temporarily unreachable/overloaded. */
    private const BREAKER_KEY = 'sync_replica_down';

    public function handle(): void
    {
        // Kill-switch: pushes disabled (e.g. replica rebuilt/offline) — discard
        // silently, including jobs queued before the switch was flipped.
        if (!config('services.sync.push_enabled', true)) {
            $this->delete();
            return;
        }

        $replicaUrl = config('services.sync.replica_url', '');
        $secret     = config('services.sync.event_secret', '');

        if (empty($replicaUrl) || empty($secret)) {
            Log::warning('[PushSyncEvent] SYNC_REPLICA_URL or SYNC_EVENT_SECRET not set — skipping push.');
            $this->delete(); // Don't retry — misconfiguration, not a network error
            return;
        }

        // Circuit breaker: replica known-down — requeue quietly without an HTTP
        // call. Prevents thousands of queued events from hammering a dead/rate-
        // limited replica and flooding the log (the Aug 2026 disk-full outages).
        if (\Illuminate\Support\Facades\Cache::get(self::BREAKER_KEY)) {
            $this->release(120);
            return;
        }

        $url = rtrim($replicaUrl, '/') . '/api/internal/sync/receive-event';

        try {
            $response = Http::withHeaders([
                'X-Sync-Event-Secret' => $secret,
                'Accept'              => 'application/json',
            ])->timeout(15)->post($url, [
                'table'  => $this->table,
                'action' => 'upsert',
                'rows'   => $this->rows,
            ]);
        } catch (\Throwable $e) {
            // Network error — trip the breaker, requeue, log at most once/5 min.
            $this->tripBreakerAndRelease('network: ' . substr($e->getMessage(), 0, 120));
            return;
        }

        if ($response->successful()) {
            // No body, no per-push log line — routine success is not news.
            return;
        }

        // 429: replica is rate-limiting — back off, don't log each rejection.
        if ($response->status() === 429) {
            $this->tripBreakerAndRelease('rate-limited (429)');
            return;
        }

        // Other 4xx: replica rejected it (bad secret, wrong role, disallowed
        // table) — permanent for this job; log WITHOUT the response body.
        if ($response->clientError()) {
            Log::error(sprintf(
                '[PushSyncEvent] Replica rejected push for %s: HTTP %d',
                $this->table,
                $response->status()
            ));
            $this->delete();
            return;
        }

        // 5xx — trip the breaker and requeue quietly. sync:pull is the safety
        // net if the job eventually exhausts its attempts.
        $this->tripBreakerAndRelease('HTTP ' . $response->status());
    }

    /**
     * Mark the replica down for 60s, requeue this job, and log a single
     * throttled warning (at most one line per 5 minutes across all jobs).
     */
    private function tripBreakerAndRelease(string $reason): void
    {
        \Illuminate\Support\Facades\Cache::put(self::BREAKER_KEY, true, 60);

        if (\Illuminate\Support\Facades\Cache::add('sync_replica_down_logged', true, 300)) {
            Log::warning(sprintf(
                '[PushSyncEvent] Replica unreachable (%s) — breaker tripped, pushes paused. Further failures suppressed for 5 min.',
                $reason
            ));
        }

        if ($this->attempts() >= $this->tries) {
            // Out of attempts — drop quietly; sync:pull will reconcile the row.
            $this->delete();
            return;
        }

        $this->release(120);
    }
}
