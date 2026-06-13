<?php

namespace App\Console\Commands;

use App\Jobs\PushUrlChangeToOrigin;
use App\Models\MovieFileTransfer;
use App\Models\MovieVideoURLChange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfills MovieVideoURLChange records for all done transfers that don't have one.
 * Queues remote pushes for each.
 *
 * Usage:
 *   php artisan url-sync:backfill              # all unsynced done transfers
 *   php artisan url-sync:backfill --limit=100  # process at most 100
 *   php artisan url-sync:backfill --dry-run    # count only, no records created
 */
class SyncUrlBackfill extends Command
{
    protected $signature = 'url-sync:backfill
                            {--limit=500   : Max transfers to process per run}
                            {--dry-run     : Count only — no records or jobs created}
                            {--transfer=   : Process a single specific transfer ID}';

    protected $description = 'Backfill MovieVideoURLChange records for all done transfers missing a URL sync record';

    public function handle(): int
    {
        $limit      = (int) $this->option('limit');
        $dryRun     = (bool) $this->option('dry-run');
        $singleId   = $this->option('transfer');
        $remoteEndpoint = config('services.url_sync.origin_url')
            ? rtrim(config('services.url_sync.origin_url'), '/') . '/api/movie-url-sync'
            : null;

        // ── Build query ───────────────────────────────────────────────────────
        $query = MovieFileTransfer::where('status', MovieFileTransfer::STATUS_DONE)
            ->whereNotNull('dest_url')
            ->whereNotIn('id', MovieVideoURLChange::whereNotNull('transfer_id')->pluck('transfer_id'))
            ->orderBy('id');

        if ($singleId) {
            $query->where('id', $singleId);
        } else {
            $query->limit($limit);
        }

        $transfers = $query->get();
        $total     = $transfers->count();

        if ($dryRun) {
            $this->info("DRY RUN: {$total} transfers need URL sync records.");
            return 0;
        }

        if ($total === 0) {
            $this->info('All done transfers already have URL sync records. Nothing to backfill.');
            return 0;
        }

        $this->info("Backfilling {$total} URL sync records...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $created  = 0;
        $failed   = 0;
        $skipped  = 0;

        foreach ($transfers as $transfer) {
            try {
                if (!$transfer->movie_id || !$transfer->dest_url) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Capture the OLD url from movie_models (current value before overwrite)
                $movie  = \App\Models\MovieModel::find($transfer->movie_id);
                $oldUrl = $movie?->url;

                // If the movie's current URL is already the dest_url, it was applied locally.
                // We still need to push to remote if no record exists.
                $localAlreadyApplied = ($movie && $movie->url === $transfer->dest_url);

                $change = MovieVideoURLChange::create([
                    'movie_model_id'    => $transfer->movie_id,
                    'movie_title'       => $transfer->movie_title,
                    'old_url'           => $localAlreadyApplied ? null : $oldUrl,
                    'new_url'           => $transfer->dest_url,
                    'source'            => MovieVideoURLChange::SOURCE_HETZNER,
                    'status'            => $localAlreadyApplied
                        ? MovieVideoURLChange::STATUS_APPLYING
                        : MovieVideoURLChange::STATUS_APPLYING,
                    'local_status'      => $localAlreadyApplied ? 'applied' : 'pending',
                    'remote_status'     => $remoteEndpoint
                        ? MovieVideoURLChange::REMOTE_PENDING
                        : MovieVideoURLChange::REMOTE_SKIPPED,
                    'transfer_id'       => $transfer->id,
                    'remote_endpoint'   => $remoteEndpoint,
                    'local_applied_at'  => $localAlreadyApplied ? now() : null,
                ]);

                // Apply locally if not already done
                if (!$localAlreadyApplied) {
                    $change->applyLocal();
                } else {
                    // Recalc status after setting local to applied
                    $change->recalcStatusPublic();
                }

                // Queue remote push
                if ($remoteEndpoint) {
                    dispatch(new PushUrlChangeToOrigin($change->id))
                        ->onQueue('url-sync')
                        ->delay(now()->addSeconds(rand(1, 10))); // stagger to avoid burst
                }

                $created++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("[SyncUrlBackfill] Transfer #{$transfer->id} failed: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Created: {$created} | Skipped: {$skipped} | Failed: {$failed}");

        return 0;
    }
}
