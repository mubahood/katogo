<?php

namespace App\Console\Commands;

use App\Models\MovieFileTransfer;
use App\Services\BunnyTransferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Transfer the top-N most-watched Hetzner-hosted movies to Bunny Storage.
 *
 * "Top" = most 30-day views first, then all-time downloads as tiebreaker —
 * so the movies users actually watch are the first to benefit from Bunny.
 *
 *   php artisan bunny:transfer-top             (top 50)
 *   php artisan bunny:transfer-top --limit=10  (smaller batch)
 *   php artisan bunny:transfer-top --dry-run   (list targets, no upload)
 *   php artisan bunny:transfer-top --retry-failed
 */
class BunnyTransferTop extends Command
{
    protected $signature = 'bunny:transfer-top
                            {--limit=50 : How many top movies to transfer}
                            {--dry-run : List the targets without uploading}
                            {--retry-failed : Include previously failed bunny transfers}';

    protected $description = 'Transfer top most-watched Hetzner movies to Bunny Storage (records in movie_file_transfers.bunny_*)';

    public function handle(BunnyTransferService $bunny): int
    {
        $limit = (int) $this->option('limit');

        if (!$this->option('dry-run') && !$bunny->isConfigured()) {
            $this->error('Bunny is not configured — set BUNNY_STORAGE_PASSWORD (and friends) in .env, then: php artisan config:cache');
            return 1;
        }

        // Top Hetzner-hosted movies by recent viewership, with a done Hetzner
        // transfer record (that's where dest_path — our filename — lives).
        $statuses = $this->option('retry-failed') ? ['failed', null] : [null];

        $rows = DB::table('movie_models as m')
            ->join('movie_file_transfers as t', function ($j) {
                $j->on('t.movie_id', '=', 'm.id')->where('t.status', 'done');
            })
            ->where('m.status', 'Active')
            ->where('m.url', 'like', '%nx100800.your-storageshare.de%')
            ->where(function ($q) use ($statuses) {
                $q->whereNull('t.bunny_status');
                if (in_array('failed', $statuses, true)) {
                    $q->orWhere('t.bunny_status', 'failed');
                }
            })
            ->select('t.id as transfer_id', 'm.id as movie_id', 'm.title', 'm.downloads_count')
            ->selectRaw('(SELECT COUNT(*) FROM movie_views mv
                          WHERE mv.movie_model_id = m.id
                            AND mv.created_at >= NOW() - INTERVAL 30 DAY) AS views_30d')
            ->orderByDesc('views_30d')
            ->orderByDesc('m.downloads_count')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No eligible movies found (all top Hetzner movies may already be on Bunny).');
            return 0;
        }

        $this->info("Top {$rows->count()} Hetzner movies queued for Bunny" . ($this->option('dry-run') ? ' [DRY RUN]' : '') . ':');

        if ($this->option('dry-run')) {
            foreach ($rows as $i => $r) {
                $n = $i + 1;
                $this->line("  {$n}. movie #{$r->movie_id} \"{$r->title}\" (30d views: {$r->views_30d}, downloads: {$r->downloads_count})");
            }
            return 0;
        }

        $ok = 0;
        $failed = 0;

        foreach ($rows as $i => $r) {
            $n = $i + 1;
            $transfer = MovieFileTransfer::find($r->transfer_id);
            $this->output->write("  {$n}/{$rows->count()} #{$r->movie_id} \"{$r->title}\" … ");

            $result = $bunny->transfer($transfer);

            if ($result['success']) {
                $ok++;
                $this->output->writeln('<info>✓ ' . ($result['bunny_url'] ?? '') . '</info>');
            } else {
                $failed++;
                $this->output->writeln('<error>✗ ' . $result['message'] . '</error>');
            }
        }

        $this->newLine();
        $this->info("Done — success: {$ok}, failed: {$failed}. Monitor: GET /api/v2/admin/bunny/stats");

        return 0;
    }
}
