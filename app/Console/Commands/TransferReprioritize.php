<?php

namespace App\Console\Commands;

use App\Models\MovieFileTransfer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * transfers:reprioritize
 *
 * Recalculates and updates the priority column for all non-done transfers
 * using the current tiered formula:
 *   Tier 3 — MunoWatch singles
 *   Tier 2 — Non-Muno singles
 *   Tier 1 — MunoWatch series
 *   Tier 0 — Non-Muno series
 *   + recency bonus (5M/2M/500K/100K based on last view age)
 *   + engagement (downloads×5 + views×3 + likes×2, capped at 999K)
 *
 * Run this after changing the priority formula or after bulk data changes.
 */
class TransferReprioritize extends Command
{
    protected $signature = 'transfers:reprioritize
                            {--dry-run  : Show what would change without updating}
                            {--chunk=500 : Batch size for processing}';

    protected $description = 'Recalculate transfer priorities using the tiered MunoWatch-first formula';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(50, (int) $this->option('chunk'));

        if ($dryRun) $this->warn('DRY RUN — no updates will be written.');

        $this->info('Loading recently-viewed movie data...');
        $recentlyViewedMap = DB::table('movie_views')
            ->select('movie_model_id', DB::raw('MAX(created_at) as last_viewed_at'))
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('movie_model_id')
            ->pluck('last_viewed_at', 'movie_model_id')
            ->toArray();

        $this->info('Recency map: ' . count($recentlyViewedMap) . ' movies viewed in last year.');

        $total = MovieFileTransfer::whereIn('status', [
            MovieFileTransfer::STATUS_QUEUED,
            MovieFileTransfer::STATUS_FAILED,
        ])->count();

        $this->info("Transfers to reprioritize: {$total}");
        if ($total === 0) {
            $this->info('Nothing to do.');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $unchanged = 0;

        MovieFileTransfer::whereIn('status', [
            MovieFileTransfer::STATUS_QUEUED,
            MovieFileTransfer::STATUS_FAILED,
        ])
        ->with(['movie:id,is_muno,type,views_count,downloads_count,likes_count,in_app_downloads_count,munowatch_id'])
        ->chunkById($chunk, function ($transfers) use (
            $dryRun, $recentlyViewedMap, &$updated, &$unchanged, $bar
        ) {
            foreach ($transfers as $transfer) {
                $bar->advance();
                $movie = $transfer->movie;

                if (!$movie) {
                    $unchanged++;
                    continue;
                }

                $newPriority = MovieFileTransfer::calculatePriority(
                    $movie,
                    $recentlyViewedMap[$movie->id] ?? null
                );

                if ($newPriority === (int) $transfer->priority) {
                    $unchanged++;
                    continue;
                }

                if (!$dryRun) {
                    $transfer->update(['priority' => $newPriority]);
                }
                $updated++;
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Result', 'Count'],
            [
                [$dryRun ? 'Would update' : 'Updated', $updated],
                ['Unchanged',                           $unchanged],
                ['Total processed',                     $total],
            ]
        );

        if (!$dryRun) {
            Log::info("[transfers:reprioritize] Updated {$updated} priorities out of {$total}.");
            $this->info("Done. The queue will now process transfers in the correct tier order.");
        }

        return 0;
    }
}
