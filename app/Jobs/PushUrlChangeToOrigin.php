<?php

namespace App\Jobs;

use App\Models\MovieVideoURLChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushUrlChangeToOrigin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries       = 10;
    public int $timeout     = 60;
    public int $backoff     = 60; // seconds between retries

    public function __construct(public readonly int $changeId) {}

    public function handle(): void
    {
        $change = MovieVideoURLChange::find($this->changeId);

        if (!$change) {
            Log::warning("[PushUrlChangeToOrigin] Change #{$this->changeId} not found — skipping.");
            return;
        }

        // Already applied or skipped — nothing to do
        if (in_array($change->remote_status, [
            MovieVideoURLChange::REMOTE_APPLIED,
            MovieVideoURLChange::REMOTE_SKIPPED,
        ])) {
            Log::info("[PushUrlChangeToOrigin] #{$this->changeId} remote already {$change->remote_status} — skipping.");
            return;
        }

        $endpoint = $change->remote_endpoint;
        $secret   = config('services.url_sync.secret');

        if (!$endpoint || !$secret) {
            Log::warning("[PushUrlChangeToOrigin] #{$this->changeId} — no endpoint or secret configured. Marking skipped.");
            $change->update(['remote_status' => MovieVideoURLChange::REMOTE_SKIPPED]);
            $change->fresh()->recalcStatusPublic();
            return;
        }

        $change->update(['remote_status' => MovieVideoURLChange::REMOTE_PUSHING]);

        $payload = json_encode([
            'changes' => [[
                'change_id'      => $change->id,
                'movie_model_id' => $change->movie_model_id,
                'old_url'        => $change->old_url,
                'new_url'        => $change->new_url,
                'source'         => $change->source,
                'transfer_id'    => $change->transfer_id,
            ]],
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Url-Sync-Secret: ' . $secret,
                'User-Agent: Katogo-Hetzner/1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $reason = "cURL error: {$curlErr}";
            Log::warning("[PushUrlChangeToOrigin] #{$this->changeId} push failed — {$reason}");
            $change->markRemoteFailed($reason);
            $this->retryOrFail($change, $reason);
            return;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $reason = "HTTP {$httpCode}: " . substr((string) $body, 0, 200);
            Log::warning("[PushUrlChangeToOrigin] #{$this->changeId} push failed — {$reason}");
            $change->markRemoteFailed($reason);
            $this->retryOrFail($change, $reason);
            return;
        }

        $change->markRemoteApplied((string) $body);

        Log::info("[PushUrlChangeToOrigin] #{$this->changeId} pushed successfully. HTTP {$httpCode}");
    }

    private function retryOrFail(MovieVideoURLChange $change, string $reason): void
    {
        if ($change->attempts < $change->max_attempts) {
            // Exponential backoff: 1m, 2m, 5m, 10m, 30m
            $delays = [60, 120, 300, 600, 1800];
            $delay  = $delays[min($change->attempts - 1, count($delays) - 1)];
            dispatch(new self($this->changeId))
                ->onQueue('url-sync')
                ->delay(now()->addSeconds($delay));
        } else {
            Log::error("[PushUrlChangeToOrigin] #{$this->changeId} exhausted all attempts. Last error: {$reason}");
        }
    }

    public function failed(\Throwable $e): void
    {
        $change = MovieVideoURLChange::find($this->changeId);
        $change?->markRemoteFailed("Job failed: " . $e->getMessage());
        Log::error("[PushUrlChangeToOrigin] #{$this->changeId} job failed: " . $e->getMessage());
    }
}
