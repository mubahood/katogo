<?php

namespace App\Console\Commands;

use App\Models\MovieFileTransfer;
use App\Models\MovieModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * transfers:backfill
 *
 * One-off command to create queued transfer records for all existing active movies
 * that have an external video URL (not already on Hetzner) and no existing transfer.
 *
 * Run with --dry-run first to verify the scope, then without to actually queue them.
 * The scheduler's transfers:process command will pick them up gradually.
 */
class TransferBackfill extends Command
{
    protected $signature = 'transfers:backfill
                            {--dry-run         : Count movies that would be queued without creating records}
                            {--chunk=200        : Process movies in chunks of this size}
                            {--limit=0          : Stop after queuing this many (0 = no limit)}
                            {--source=          : Only queue movies whose video_url contains this string}
                            {--movie-id=        : Queue a single specific movie ID}';

    protected $description = 'Queue transfer records for all active movies not yet on Hetzner Storage';

    public function handle(): int
    {
        $dryRun   = (bool) $this->option('dry-run');
        $chunk    = max(10, (int) $this->option('chunk'));
        $limit    = (int) $this->option('limit');
        $source   = $this->option('source');
        $singleId = $this->option('movie-id');

        if ($dryRun) {
            $this->warn('DRY RUN — no records will be created.');
        }

        // ── Single movie mode ─────────────────────────────────────────────────
        if ($singleId) {
            $movie = MovieModel::find((int)$singleId);
            if (!$movie) {
                $this->error("Movie #{$singleId} not found.");
                return 1;
            }
            return $this->processSingle($movie, $dryRun);
        }

        // ── Bulk mode ─────────────────────────────────────────────────────────
        $this->info('Scanning active movies without a Hetzner transfer...');

        $queued = 0;
        $skipped = 0;
        $alreadyDone = 0;

        // Build base query: active movies with a video_url not on Hetzner
        $query = MovieModel::where('status', 'Active')
            ->whereNotNull('video_url')
            ->where('video_url', '!=', '')
            ->where('video_url', 'not like', '%' . MovieFileTransfer::HETZNER_HOST . '%');

        if ($source) {
            $query->where('video_url', 'like', "%{$source}%");
        }

        $total = $query->count();
        $this->line("Found {$total} candidate movies.");

        if ($total === 0) {
            $this->info('Nothing to backfill — all active movies are already on Hetzner.');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->select(['id', 'title', 'video_url', 'status', 'year', 'quality',
                        'duration', 'poster_url', 'munowatch_id', 'type',
                        'season_number', 'episode_number', 'series_title'])
              ->chunkById($chunk, function ($movies) use (
                  $dryRun, $limit, &$queued, &$skipped, &$alreadyDone, $bar
              ) {
                  foreach ($movies as $movie) {
                      $bar->advance();

                      // Skip if already has a pending/completed transfer
                      if (MovieFileTransfer::hasPendingOrCompleted($movie->id)) {
                          $alreadyDone++;
                          continue;
                      }

                      if (!$dryRun) {
                          MovieFileTransfer::queueForMovie($movie, 'backfill');
                      }
                      $queued++;

                      if ($limit > 0 && $queued >= $limit) {
                          return false; // stop chunk iteration
                      }
                  }
              });

        $bar->finish();
        $this->line('');
        $this->line('');

        $this->table(
            ['Result', 'Count'],
            [
                [$dryRun ? 'Would queue' : 'Queued',   $queued],
                ['Already done/pending',                $alreadyDone],
                ['Total scanned',                       $total],
            ]
        );

        if ($dryRun) {
            $this->warn("Re-run without --dry-run to create {$queued} transfer records.");
        } else {
            $this->info("{$queued} transfer records created. The scheduler will process them automatically.");
            Log::info("[transfers:backfill] Created {$queued} transfer records.");
        }

        return 0;
    }

    private function processSingle(MovieModel $movie, bool $dryRun): int
    {
        $url = (string)($movie->video_url ?? '');

        $this->line("Movie #{$movie->id}: {$movie->title}");
        $this->line("Status:    {$movie->status}");
        $this->line("Video URL: " . substr($url, 0, 100));

        if (empty($url)) {
            $this->error('Movie has no video_url — cannot queue.');
            return 1;
        }

        if (MovieFileTransfer::isAlreadyOnHetzner($url)) {
            $this->info('Already on Hetzner — no transfer needed.');
            return 0;
        }

        if (MovieFileTransfer::hasPendingOrCompleted($movie->id)) {
            $this->warn('Already has a pending or completed transfer.');
            return 0;
        }

        if ($dryRun) {
            $this->warn('Would create a queued transfer record (dry-run).');
            return 0;
        }

        $t = MovieFileTransfer::queueForMovie($movie, 'backfill:single');
        $this->info("Transfer #{$t->id} created — status: queued.");
        return 0;
    }
}
