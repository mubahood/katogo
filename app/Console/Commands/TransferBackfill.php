<?php

namespace App\Console\Commands;

use App\Models\MovieFileTransfer;
use App\Models\MovieModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * transfers:backfill
 *
 * Creates queued transfer records for active movies not yet on Hetzner Storage,
 * ordered by priority:
 *   1. MunoWatch single movies   (tier 3 — most important)
 *   2. Non-MunoWatch single movies (tier 2)
 *   3. MunoWatch series          (tier 1)
 *   4. Non-MunoWatch series      (tier 0)
 *
 * Within each tier, recently-watched > most-viewed > most-downloaded > most-liked.
 *
 * Movies with existing FAILED transfers are skipped — run transfers:diagnose
 * instead of creating duplicate records for those.
 */
class TransferBackfill extends Command
{
    protected $signature = 'transfers:backfill
                            {--dry-run          : Count movies that would be queued without creating records}
                            {--chunk=200         : Process movies in chunks of this size}
                            {--limit=0           : Stop after queuing this many (0 = no limit)}
                            {--source=           : Only queue movies whose video_url contains this string}
                            {--movie-id=         : Queue a single specific movie ID}
                            {--type=all          : Filter by type: all | single | series}
                            {--muno-only         : Only queue MunoWatch movies (is_muno=Yes)}
                            {--reset-failed      : Also re-queue movies whose only transfers are failed (resets attempt_count)}';

    protected $description = 'Queue transfer records for active movies not yet on Hetzner, ordered by tier + recency + engagement';

    public function handle(): int
    {
        $dryRun      = (bool) $this->option('dry-run');
        $chunk       = max(10, (int) $this->option('chunk'));
        $limit       = (int) $this->option('limit');
        $source      = $this->option('source');
        $singleId    = $this->option('movie-id');
        $typeFilter  = $this->option('type');
        $munoOnly    = (bool) $this->option('muno-only');
        $resetFailed = (bool) $this->option('reset-failed');

        if ($dryRun) {
            $this->warn('DRY RUN — no records will be created.');
        }

        if ($singleId) {
            $movie = MovieModel::find((int) $singleId);
            if (!$movie) {
                $this->error("Movie #{$singleId} not found.");
                return 1;
            }
            return $this->processSingle($movie, $dryRun);
        }

        $this->info('Loading recently-watched movie data for priority calculation...');

        // Pre-load last-view date for movies viewed in the past year.
        // movie_views uses movie_model_id (not movie_id).
        $recentlyViewedMap = DB::table('movie_views')
            ->select('movie_model_id', DB::raw('MAX(created_at) as last_viewed_at'))
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('movie_model_id')
            ->pluck('last_viewed_at', 'movie_model_id')
            ->toArray();

        $this->info('Recently-watched map loaded: ' . count($recentlyViewedMap) . ' movies.');

        // ── Build base query ──────────────────────────────────────────────────

        $query = MovieModel::where('status', 'Active')
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->where('url', 'not like', '%' . MovieFileTransfer::HETZNER_HOST . '%');

        if ($munoOnly) {
            $query->where('is_muno', 'Yes');
        }

        if ($typeFilter === 'single') {
            $query->where(function ($q) {
                $q->where('type', 'Movie')->orWhereNull('type')->orWhere('type', '');
            });
        } elseif ($typeFilter === 'series') {
            $query->where('type', 'Series');
        }

        if ($source) {
            $query->where('url', 'like', "%{$source}%");
        }

        // ── Order: tier (MunoWatch singles first) → engagement ───────────────
        // Recency is encoded in the stored priority but can't be in ORDER BY
        // without a slow subquery — the queue dispatcher uses stored priority anyway.
        $query->orderByDesc(DB::raw("
            CASE
                WHEN (is_muno = 'Yes' OR (munowatch_id IS NOT NULL AND munowatch_id != ''))
                     AND (type = 'Movie' OR type IS NULL OR type = '') THEN 3
                WHEN (is_muno != 'Yes' AND (munowatch_id IS NULL OR munowatch_id = ''))
                     AND (type = 'Movie' OR type IS NULL OR type = '') THEN 2
                WHEN (is_muno = 'Yes' OR (munowatch_id IS NOT NULL AND munowatch_id != ''))
                     AND type = 'Series' THEN 1
                ELSE 0
            END
        "))
        ->orderByDesc(DB::raw(
            "COALESCE(downloads_count,0)*5 + COALESCE(views_count,0)*3 + COALESCE(likes_count,0)*2"
        ))
        ->orderByDesc('id');

        $total = $query->count();
        $this->line("Found {$total} candidate movies.");

        if ($total === 0) {
            $this->info('Nothing to backfill — all active movies are already on Hetzner or no matches.');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $queued      = 0;
        $skipped     = 0;
        $resetCount  = 0;

        $query->select([
            'id', 'title', 'url', 'status', 'year', 'duration', 'poster_url',
            'munowatch_id', 'is_muno', 'type', 'season_number', 'episode_number',
            'series_title', 'views_count', 'downloads_count', 'likes_count',
            'in_app_downloads_count',
        ])->chunk($chunk, function ($movies) use (
            $dryRun, $limit, $resetFailed, $recentlyViewedMap,
            &$queued, &$skipped, &$resetCount, $bar
        ) {
            foreach ($movies as $movie) {
                $bar->advance();

                // Check for existing transfer records
                $existing = MovieFileTransfer::where('movie_id', $movie->id)
                    ->whereIn('status', [
                        MovieFileTransfer::STATUS_QUEUED,
                        MovieFileTransfer::STATUS_VERIFYING,
                        MovieFileTransfer::STATUS_TRANSFERRING,
                        MovieFileTransfer::STATUS_COMPLETING,
                        MovieFileTransfer::STATUS_DONE,
                    ])
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                // Check for failed transfers
                $failedTransfer = MovieFileTransfer::where('movie_id', $movie->id)
                    ->where('status', MovieFileTransfer::STATUS_FAILED)
                    ->orderByDesc('id')
                    ->first();

                if ($failedTransfer) {
                    if ($resetFailed && !$dryRun) {
                        // Reset the existing failed record instead of creating a duplicate
                        $priority = MovieFileTransfer::calculatePriority(
                            $movie,
                            $recentlyViewedMap[$movie->id] ?? null
                        );
                        $failedTransfer->update([
                            'status'        => MovieFileTransfer::STATUS_QUEUED,
                            'priority'      => $priority,
                            'attempt_count' => 0,
                            'next_retry_at' => null,
                            'notes'         => ($failedTransfer->notes ? $failedTransfer->notes . ' | ' : '')
                                             . 'Reset by transfers:backfill --reset-failed at ' . now()->toDateTimeString(),
                        ]);
                        $resetCount++;
                    } else {
                        $skipped++; // failed — use transfers:diagnose to handle
                    }
                    continue;
                }

                // Compute priority with recency data
                $priority = MovieFileTransfer::calculatePriority(
                    $movie,
                    $recentlyViewedMap[$movie->id] ?? null
                );

                if (!$dryRun) {
                    MovieFileTransfer::queueForMovie($movie, 'backfill', $priority);
                }
                $queued++;

                if ($limit > 0 && ($queued + $resetCount) >= $limit) {
                    return false;
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Result', 'Count'],
            [
                [$dryRun ? 'Would queue (new records)' : 'Queued (new records)',  $queued],
                ['Reset failed → queued',                                          $resetCount],
                ['Skipped (already has transfer)',                                 $skipped],
                ['Total scanned',                                                  $total],
            ]
        );

        if (!$dryRun) {
            $this->info(($queued + $resetCount) . ' transfers queued. The scheduler picks them up every 5 minutes.');
            Log::info("[transfers:backfill] Queued {$queued} new + {$resetCount} reset. Skipped {$skipped}.");
        } else {
            $this->warn("Re-run without --dry-run to create {$queued} new transfer records.");
        }

        return 0;
    }

    private function processSingle(MovieModel $movie, bool $dryRun): int
    {
        $url = (string)($movie->attributes['url'] ?? $movie->url ?? '');

        $this->line("Movie #{$movie->id}: {$movie->title}");
        $this->line("  Status:    {$movie->status}");
        $this->line("  Type:      " . ($movie->type ?: 'Movie') . " | is_muno: " . ($movie->is_muno ?: 'No'));
        $this->line("  Video URL: " . substr($url, 0, 100));

        if (empty($url)) {
            $this->error('Movie has no url — cannot queue.');
            return 1;
        }

        if (MovieFileTransfer::isAlreadyOnHetzner($url)) {
            $this->info('Already on Hetzner — no transfer needed.');
            return 0;
        }

        $existing = MovieFileTransfer::where('movie_id', $movie->id)
            ->whereIn('status', [
                MovieFileTransfer::STATUS_QUEUED,
                MovieFileTransfer::STATUS_VERIFYING,
                MovieFileTransfer::STATUS_TRANSFERRING,
                MovieFileTransfer::STATUS_COMPLETING,
                MovieFileTransfer::STATUS_DONE,
            ])
            ->first();

        if ($existing) {
            $this->warn("Already has an active/done transfer #{$existing->id} (status: {$existing->status}).");
            return 0;
        }

        $failed = MovieFileTransfer::where('movie_id', $movie->id)
            ->where('status', MovieFileTransfer::STATUS_FAILED)
            ->orderByDesc('id')
            ->first();

        if ($failed) {
            $this->warn("Existing failed transfer #{$failed->id}. Run transfers:diagnose --movie-id={$movie->id} to fix it.");
            return 0;
        }

        $lastViewed = DB::table('movie_views')
            ->where('movie_model_id', $movie->id)
            ->max('created_at');

        $priority = MovieFileTransfer::calculatePriority($movie, $lastViewed);
        $this->line("  Priority:  {$priority}");

        if ($dryRun) {
            $this->warn('Would create a queued transfer record (dry-run).');
            return 0;
        }

        $t = MovieFileTransfer::queueForMovie($movie, 'backfill:single', $priority);
        $this->info("Transfer #{$t->id} created — status: queued, priority: {$priority}.");
        return 0;
    }
}
