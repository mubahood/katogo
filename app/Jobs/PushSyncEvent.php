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

    public function handle(): void
    {
        $replicaUrl = config('services.sync.replica_url', '');
        $secret     = config('services.sync.event_secret', '');

        if (empty($replicaUrl) || empty($secret)) {
            Log::warning('[PushSyncEvent] SYNC_REPLICA_URL or SYNC_EVENT_SECRET not set — skipping push.');
            $this->delete(); // Don't retry — misconfiguration, not a network error
            return;
        }

        $url = rtrim($replicaUrl, '/') . '/api/internal/sync/receive-event';

        $response = Http::withHeaders([
            'X-Sync-Event-Secret' => $secret,
            'Accept'              => 'application/json',
        ])->timeout(15)->post($url, [
            'table'  => $this->table,
            'action' => 'upsert',
            'rows'   => $this->rows,
        ]);

        if ($response->successful()) {
            Log::info(sprintf(
                '[PushSyncEvent] Pushed %d row(s) of %s to replica. Response: %s',
                count($this->rows),
                $this->table,
                $response->body()
            ));
            return;
        }

        // 4xx means the replica rejected it (bad secret, wrong role, disallowed table) — don't retry
        if ($response->clientError()) {
            Log::error(sprintf(
                '[PushSyncEvent] Replica rejected push for %s: %d — %s',
                $this->table,
                $response->status(),
                $response->body()
            ));
            $this->delete();
            return;
        }

        // 5xx or network error — throw so the queue retries with backoff
        throw new \RuntimeException(sprintf(
            '[PushSyncEvent] Push to replica failed (will retry): %d — %s',
            $response->status(),
            substr($response->body(), 0, 200)
        ));
    }
}
