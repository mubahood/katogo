<?php

namespace App\Console\Commands;

use App\Admin\Controllers\BunnyTransferAdminController;
use App\Jobs\TransferMovieToBunny;
use App\Models\MovieFileTransfer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The Bunny migration continuation protocol. Runs every 10 minutes and keeps
 * the pipeline moving with zero manual attention:
 *
 *  1. UNSTICK — uploads with no progress tick for 30+ min are reset to
 *     pending (worker died mid-stream); the service's resume-adoption check
 *     means an already-complete file is adopted, not re-uploaded.
 *  2. REDISPATCH — pending rows older than 15 min whose queue job was lost
 *     (queue cleared / worker crash) get a fresh job.
 *  3. TOP-UP — when BUNNY_AUTO_MIGRATE=true and the queue is running dry,
 *     the next most-demanded eligible movies (already on Hetzner + watched
 *     in the last 30 days) are queued, keeping ~target in flight.
 *
 * Eligibility comes from ONE shared rule (BunnyTransferAdminController::
 * eligibleQuery) so the pump, admin buttons and API can never disagree.
 */
class BunnyPump extends Command
{
    protected $signature = 'bunny:pump
                            {--target=10 : Keep this many transfers queued when auto-migrate is on}';

    protected $description = 'Keep the Bunny migration flowing: unstick stale uploads, redispatch lost jobs, auto top-up the queue';

    public function handle(): int
    {
        // 1. UNSTICK stale uploads
        $stale = MovieFileTransfer::where('bunny_status', 'uploading')
            ->where('updated_at', '<', now()->subMinutes(30))->get();
        foreach ($stale as $t) {
            $t->bunny_status = 'pending';
            $t->save();
            TransferMovieToBunny::dispatch($t->id)->onQueue('default');
            Log::info("[BunnyPump] Unstuck stale upload movie #{$t->movie_id} → re-queued");
        }

        // 2. REDISPATCH lost pending jobs
        $lost = MovieFileTransfer::where('bunny_status', 'pending')
            ->where('updated_at', '<', now()->subMinutes(15))->get();
        foreach ($lost as $t) {
            $t->touch(); // reset the clock so we don't re-dispatch every run
            TransferMovieToBunny::dispatch($t->id)->onQueue('default');
        }

        // 3. TOP-UP the queue (only when auto-migrate is enabled)
        $topped = 0;
        if (env('BUNNY_AUTO_MIGRATE', true)) {
            $target  = max(1, (int) $this->option('target'));
            $inFlight = MovieFileTransfer::whereIn('bunny_status', ['pending', 'uploading'])->count();

            if ($inFlight < $target) {
                $ids = BunnyTransferAdminController::eligibleQuery()
                    ->limit($target - $inFlight)->pluck('t.id');

                foreach ($ids as $tid) {
                    DB::table('movie_file_transfers')->where('id', $tid)
                        ->update(['bunny_status' => 'pending', 'bunny_error' => null, 'updated_at' => now()]);
                    TransferMovieToBunny::dispatch($tid)->onQueue('default');
                    $topped++;
                }
            }
        }

        $summary = sprintf('unstuck=%d redispatched=%d topped_up=%d', count($stale), count($lost), $topped);
        $this->info("[BunnyPump] {$summary}");
        if (count($stale) || $topped) {
            Log::info("[BunnyPump] {$summary}");
        }

        return 0;
    }
}
