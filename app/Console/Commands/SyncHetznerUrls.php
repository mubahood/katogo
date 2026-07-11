<?php

namespace App\Console\Commands;

use App\Services\HetznerStorageService;
use App\Services\MovieFixerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ensures every movie that has been transferred to Hetzner Storage has its
 * `url` column pointing to the correct Hetzner share link.
 *
 * Two recovery strategies, applied in order:
 *
 *  1. old_video_url restore — If the fixer previously overwrote a Hetzner URL
 *     it first saved it in `old_video_url`. We swap it back.
 *
 *  2. OCS share scan — Fetch all public shares from Hetzner, extract movie IDs
 *     from filenames (pattern: `{seq}_YYYY_MM_movie_{id}_{title}.mp4`), and
 *     update any movie whose `url` still doesn't point to Hetzner.
 *
 * Usage:
 *   php artisan movies:sync-hetzner-urls
 *   php artisan movies:sync-hetzner-urls --dry-run
 *   php artisan movies:sync-hetzner-urls --strategy=old-url-only
 *   php artisan movies:sync-hetzner-urls --strategy=ocs-only
 */
class SyncHetznerUrls extends Command
{
    protected $signature = 'movies:sync-hetzner-urls
                            {--dry-run : Show what would be changed without writing to DB}
                            {--strategy=all : Which strategy to run: all | old-url-only | ocs-only}
                            {--batch=500 : DB update batch size}';

    protected $description = 'Restore and sync Hetzner Storage URLs for movies that were overwritten by the fixer';

    private bool $dry = false;

    public function handle(): int
    {
        $this->dry      = (bool) $this->option('dry-run');
        $strategy       = (string) $this->option('strategy');
        $batch          = max(1, (int) $this->option('batch'));

        $this->newLine();
        $this->info('=== Hetzner URL Sync ===');
        $this->line('Mode:     ' . ($this->dry ? 'DRY RUN (no writes)' : 'LIVE'));
        $this->line('Strategy: ' . $strategy);
        $this->newLine();

        $totalRestored  = 0;
        $totalSynced    = 0;

        // ── Strategy 1: restore from old_video_url ──────────────────────────
        if (in_array($strategy, ['all', 'old-url-only'])) {
            $totalRestored = $this->restoreFromOldUrl($batch);
        }

        // ── Strategy 2: OCS share scan ──────────────────────────────────────
        if (in_array($strategy, ['all', 'ocs-only'])) {
            $totalSynced = $this->syncFromOcsShares($batch);
        }

        $this->newLine();
        $this->info('Done.');
        $this->table(['Metric', 'Count'], [
            ['Restored from old_video_url', $totalRestored],
            ['Updated from Hetzner OCS shares', $totalSynced],
            ['Total', $totalRestored + $totalSynced],
        ]);

        return self::SUCCESS;
    }

    // ─── Strategy 1 ──────────────────────────────────────────────────────────

    private function restoreFromOldUrl(int $batch): int
    {
        $this->info('[Strategy 1] Restoring from old_video_url...');

        $query = DB::table('movie_models')
            ->where('old_video_url', 'LIKE', '%storageshare.de%')
            ->where(function ($q) {
                $q->whereNull('url')
                  ->orWhereRaw("url NOT LIKE '%storageshare.de%'");
            });

        $total = $query->count();
        $this->line("  Found $total movies with Hetzner URL in old_video_url that need restoring.");

        if ($total === 0 || $this->dry) {
            if ($this->dry && $total > 0) {
                $this->line('  [dry-run] Would restore ' . $total . ' movies.');
            }
            return 0;
        }

        $restored = 0;
        $query->orderBy('id')->chunkById($batch, function ($rows) use (&$restored) {
            foreach ($rows as $row) {
                $hetznerUrl  = $row->old_video_url;
                $previousUrl = $row->url;

                DB::table('movie_models')
                    ->where('id', $row->id)
                    ->update([
                        'url'           => $hetznerUrl,
                        'old_video_url' => $previousUrl,
                        'fix_status'    => 'fixed',
                        'updated_at'    => now(),
                    ]);

                $restored++;

                Log::info('SyncHetznerUrls: restored from old_video_url', [
                    'movie_id'   => $row->id,
                    'hetzner_url' => $hetznerUrl,
                    'replaced'   => $previousUrl,
                ]);
            }
        });

        $this->line("  Restored: $restored");
        return $restored;
    }

    // ─── Strategy 2 ──────────────────────────────────────────────────────────

    private function syncFromOcsShares(int $batch): int
    {
        $this->info('[Strategy 2] Fetching share list from Hetzner OCS API...');

        try {
            $hetzner = new HetznerStorageService();
            $shares  = $hetzner->listShares();
        } catch (\Throwable $e) {
            $this->error('Failed to fetch Hetzner shares: ' . $e->getMessage());
            return 0;
        }

        $this->line('  Total shares returned: ' . count($shares));

        // Build map: movie_id => share_url
        // Filename pattern: {seq}_YYYY_MM_movie_{movie_id}_{title_slug}.mp4
        $hetznerHost = 'https://nx100800.your-storageshare.de';
        $idToUrl     = [];

        foreach ($shares as $share) {
            if (($share['share_type'] ?? -1) !== 3) {
                continue; // public link type only
            }
            $token = $share['token'] ?? null;
            $path  = $share['path']  ?? '';
            if (empty($token) || empty($path)) {
                continue;
            }

            $filename = basename($path);
            if (!preg_match('/movie_(\d+)_/i', $filename, $m)) {
                continue;
            }

            $movieId = (int) $m[1];
            $url     = "{$hetznerHost}/s/{$token}/download";

            // Keep latest share if somehow multiple exist for same movie
            $idToUrl[$movieId] = $url;
        }

        $this->line('  Mapped movie IDs from shares: ' . count($idToUrl));

        if (empty($idToUrl)) {
            $this->warn('  No movie IDs could be extracted from share paths.');
            return 0;
        }

        // Only update movies that don't already have a correct Hetzner URL
        $movieIds = array_keys($idToUrl);
        $needUpdate = [];

        foreach (array_chunk($movieIds, 500) as $chunk) {
            $rows = DB::table('movie_models')
                ->whereIn('id', $chunk)
                ->where(function ($q) {
                    $q->whereNull('url')
                      ->orWhereRaw("url NOT LIKE '%storageshare.de%'");
                })
                ->pluck('id')
                ->toArray();

            foreach ($rows as $id) {
                $needUpdate[$id] = $idToUrl[$id];
            }
        }

        $total = count($needUpdate);
        $this->line("  Movies needing URL update: $total");

        if ($total === 0) {
            $this->line('  All Hetzner movies already have correct URLs.');
            return 0;
        }

        if ($this->dry) {
            $this->line("  [dry-run] Would update $total movies.");
            // Show a sample
            $sample = array_slice($needUpdate, 0, 5, true);
            foreach ($sample as $id => $url) {
                $this->line("    movie_id=$id → $url");
            }
            return 0;
        }

        $synced = 0;
        foreach (array_chunk($needUpdate, $batch, true) as $chunk) {
            foreach ($chunk as $movieId => $hetznerUrl) {
                $old = DB::table('movie_models')->where('id', $movieId)->value('url');

                DB::table('movie_models')
                    ->where('id', $movieId)
                    ->update([
                        'url'           => $hetznerUrl,
                        'old_video_url' => $old ?: null,
                        'fix_status'    => 'fixed',
                        'updated_at'    => now(),
                    ]);

                $synced++;
            }

            Log::info('SyncHetznerUrls: OCS batch done', ['batch_size' => count($chunk)]);
        }

        $this->line("  Updated: $synced");
        return $synced;
    }
}
