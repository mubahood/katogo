<?php

namespace App\Jobs;

use App\Models\MovieFileTransfer;
use App\Services\BunnyTransferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued Bunny upload for one transfer record. Dispatched by the admin panel
 * ("Queue to Bunny" buttons / batch action) and the bunny queue API.
 *
 * The heavy lifting (source fallback hetzner→main→source, streaming, progress,
 * verification) lives in BunnyTransferService — this job is just the queue
 * wrapper so many transfers can be queued at once and processed in order.
 */
class TransferMovieToBunny implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 7500; // > service's 2h curl ceiling

    public function __construct(private readonly int $transferId)
    {
    }

    public function handle(BunnyTransferService $bunny): void
    {
        $t = MovieFileTransfer::find($this->transferId);
        if (!$t) {
            Log::warning("[TransferMovieToBunny] transfer #{$this->transferId} vanished — skipping.");
            return;
        }

        // Queued rows are marked 'pending'; anything else means state moved on
        // (done already / picked up elsewhere) — don't double-process.
        if (!in_array($t->bunny_status, ['pending', 'failed', null], true)) {
            return;
        }

        $result = $bunny->transfer($t);

        if (!$result['success']) {
            Log::warning("[TransferMovieToBunny] movie #{$t->movie_id}: {$result['message']}");
        }
    }

    public function failed(\Throwable $e): void
    {
        $t = MovieFileTransfer::find($this->transferId);
        if ($t && $t->bunny_status !== 'done') {
            $t->bunny_status = 'failed';
            $t->bunny_error  = 'Queue job failed: ' . mb_substr($e->getMessage(), 0, 500);
            $t->save();
        }
    }
}
