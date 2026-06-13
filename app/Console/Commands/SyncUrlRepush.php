<?php

namespace App\Console\Commands;

use App\Jobs\PushUrlChangeToOrigin;
use App\Models\MovieVideoURLChange;
use Illuminate\Console\Command;

/**
 * Reset remote_status from 'skipped' → 'pending' for Hetzner-sourced records
 * where the origin URL is configured, then queue PushUrlChangeToOrigin jobs.
 *
 * This repairs records created by url-sync:backfill when the config cache
 * had a stale/empty origin URL.
 *
 * Usage: php artisan url-sync:repush [--limit=500]
 */
class SyncUrlRepush extends Command
{
    protected $signature = 'url-sync:repush
                            {--limit=2000 : Max records to fix per run}
                            {--dry-run    : Count only}';

    protected $description = 'Fix skipped remote-status records and queue remote pushes';

    public function handle(): int
    {
        $originUrl = config('services.url_sync.origin_url');
        $secret    = config('services.url_sync.secret');

        if (!$originUrl || !$secret) {
            $this->error('URL_SYNC_ORIGIN_URL or URL_SYNC_SECRET not configured.');
            return 1;
        }

        $endpoint = rtrim($originUrl, '/') . '/api/movie-url-sync';
        $limit    = (int) $this->option('limit');
        $dryRun   = (bool) $this->option('dry-run');

        $query = MovieVideoURLChange::where('remote_status', 'skipped')
            ->where('local_status', 'applied')
            ->where('source', '!=', MovieVideoURLChange::SOURCE_API_PUSH) // don't touch mruodel-received records
            ->limit($limit);

        $count = (clone $query)->count();

        if ($dryRun) {
            $this->info("DRY RUN: {$count} records would be re-queued.");
            return 0;
        }

        if ($count === 0) {
            $this->info('No skipped records to fix.');
            return 0;
        }

        // Collect IDs first — then bulk-update, then bulk-dispatch
        // (avoids chunk pagination drift when updating the WHERE condition)
        $ids = (clone $query)->pluck('id')->toArray();
        $actual = count($ids);

        $this->info("Fixing {$actual} records via bulk UPDATE + dispatching push jobs...");

        // Single UPDATE — no pagination drift
        MovieVideoURLChange::whereIn('id', $ids)->update([
            'remote_status'   => MovieVideoURLChange::REMOTE_PENDING,
            'remote_endpoint' => $endpoint,
            'attempts'        => 0,
            'status'          => MovieVideoURLChange::STATUS_APPLYING,
        ]);

        // Dispatch jobs in chunks of 100 to avoid memory issues
        $bar = $this->output->createProgressBar($actual);
        $bar->start();
        $fixed = 0;

        foreach (array_chunk($ids, 100) as $chunk) {
            foreach ($chunk as $id) {
                dispatch(new PushUrlChangeToOrigin($id))
                    ->onQueue('url-sync')
                    ->delay(now()->addSeconds(rand(1, 60))); // stagger over 60s
                $fixed++;
            }
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Bulk-updated and queued {$fixed} remote push jobs.");

        return 0;
    }
}
