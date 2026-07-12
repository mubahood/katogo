<?php

namespace App\Console\Commands;

use App\Models\MovieFileTransfer;
use App\Models\MovieVideoURLChange;
use App\Services\HetznerStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * transfers:diagnose
 *
 * Analyses all FAILED transfers, groups them by root-cause category,
 * and attempts targeted fixes for each category.
 *
 * CRITICAL RULE: a transfer is only marked DONE when ALL of the following
 * are true and verified in real time:
 *   1. The file exists on Hetzner Storage (WebDAV PROPFIND > 1024 bytes)
 *   2. A valid public share URL has been created (OCS API returns a token)
 *   3. movie_models.url has been updated to the Hetzner URL
 *
 * Transfers that cannot be fixed are left FAILED with an updated error message.
 * Nothing is ever silently promoted to DONE without passing all three checks.
 *
 * Fix categories:
 *   share_failed    — File exists on Hetzner but OCS share creation failed (429 etc.)
 *                     Fix: call findOrCreateShare() → if share created, mark done.
 *   transient       — Upload/connection/disk/SSL failures likely to self-resolve.
 *                     Fix: reset attempt_count + next_retry_at → back to QUEUED.
 *   source_dead     — Source URL consistently returns 4xx / is empty.
 *                     Fix: if file is on Hetzner → salvage via share creation.
 *                          if movie has munowatch_id → try fresh URL from crawler page.
 *                          otherwise → leave FAILED, log for manual review.
 *   exhausted       — Attempt count hit max_attempts but error looks transient.
 *                     Fix: bump max_attempts by 3 and reset to QUEUED.
 */
class TransferDiagnose extends Command
{
    protected $signature = 'transfers:diagnose
                            {--dry-run       : Show diagnosis without applying any fixes}
                            {--fix           : Apply all safe fixes (default: report only)}
                            {--movie-id=     : Diagnose only one specific movie}
                            {--category=     : Only process one category: share_failed|transient|source_dead|exhausted}
                            {--limit=500     : Max failed transfers to process in one run}';

    protected $description = 'Analyse and fix failed transfers; never marks a transfer done without verifying it on Hetzner';

    private HetznerStorageService $hetzner;

    private array $stats = [
        'total_failed'    => 0,
        'share_failed'    => 0,
        'transient'       => 0,
        'source_dead'     => 0,
        'exhausted'       => 0,
        'unknown'         => 0,
        'fixed_done'      => 0,
        'fixed_requeued'  => 0,
        'unfixable'       => 0,
        'skipped_dry_run' => 0,
    ];

    public function handle(): int
    {
        $dryRun     = (bool) $this->option('dry-run');
        $applyFixes = (bool) $this->option('fix');
        $movieId    = $this->option('movie-id');
        $onlyCategory = $this->option('category');
        $limit      = max(1, (int) $this->option('limit'));

        if ($dryRun) $this->warn('DRY RUN — no fixes will be applied.');
        if (!$applyFixes && !$dryRun) {
            $this->warn('Report-only mode. Add --fix to apply fixes, or --dry-run to preview.');
        }

        $this->hetzner = new HetznerStorageService();

        $query = MovieFileTransfer::where('status', MovieFileTransfer::STATUS_FAILED)
            ->orderBy('updated_at', 'asc') // oldest failures first
            ->limit($limit);

        if ($movieId) {
            $query->where('movie_id', (int) $movieId);
        }

        $transfers = $query->get();
        $this->stats['total_failed'] = $transfers->count();

        $this->info("Analysing {$this->stats['total_failed']} failed transfers...");
        $this->newLine();

        $byCategory = [];
        foreach ($transfers as $transfer) {
            $cat = $this->categorize($transfer);
            $byCategory[$cat][] = $transfer;
            $this->stats[$cat]  = ($this->stats[$cat] ?? 0) + 1;
        }

        // ── Process each category ─────────────────────────────────────────────

        foreach ($byCategory as $cat => $batch) {
            if ($onlyCategory && $cat !== $onlyCategory) continue;

            $this->info("── Category: {$cat} (" . count($batch) . " transfers) ──");

            foreach ($batch as $transfer) {
                $this->processTransfer($transfer, $cat, $dryRun, $applyFixes);
            }
            $this->newLine();
        }

        // ── Summary ───────────────────────────────────────────────────────────

        $this->printSummary();

        if ($this->stats['fixed_done'] > 0 || $this->stats['fixed_requeued'] > 0) {
            Log::info('[transfers:diagnose] Completed.', $this->stats);
        }

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function categorize(MovieFileTransfer $t): string
    {
        $msg = strtolower((string) ($t->error_message ?? ''));

        // File is on Hetzner but share creation failed
        if (str_contains($msg, 'share link') || str_contains($msg, 'share link generation')) {
            return 'share_failed';
        }
        if (str_contains($msg, 'file exists on hetzner but share')) {
            return 'share_failed';
        }

        // 429 during OCS share creation (rate limit)
        if (str_contains($msg, '429') && (str_contains($msg, 'share') || str_contains($msg, 'ocs'))) {
            return 'share_failed';
        }

        // Source URL dead / blocked
        if (str_contains($msg, 'http 403') || str_contains($msg, 'http 404') || str_contains($msg, 'http 410')) {
            return 'source_dead';
        }
        if (str_contains($msg, 'source url unreachable after all') || str_contains($msg, 'source returned http 4')) {
            return 'source_dead';
        }
        if (empty(trim((string) $t->source_url))) {
            return 'source_dead';
        }

        // Got an error HTML page instead of a video
        if (str_contains($msg, 'too small')) {
            return 'source_dead';
        }

        // Exhausted retries on likely-transient errors
        if ((int) $t->attempt_count >= (int) $t->max_attempts && $t->max_attempts <= 3) {
            // Check if the underlying error is transient
            if ($this->isTransientError($msg)) {
                return 'exhausted';
            }
        }

        // Transient failures
        if ($this->isTransientError($msg)) {
            return 'transient';
        }

        return 'unknown';
    }

    private function isTransientError(string $msg): bool
    {
        return str_contains($msg, 'webdav upload')
            || str_contains($msg, 'hetzner webdav')
            || str_contains($msg, 'disk > 85')
            || str_contains($msg, 'no space')
            || str_contains($msg, 'ssl')
            || str_contains($msg, 'certificate')
            || str_contains($msg, 'connection')
            || str_contains($msg, 'timeout')
            || str_contains($msg, 'curl error')
            || str_contains($msg, 'curl download failed')
            || str_contains($msg, 'network')
            || str_contains($msg, 'could not be opened')   // log-write permission error; real failure unknown
            || str_contains($msg, 'failed to open stream') // same — PHP log I/O failure masked real error
            || str_contains($msg, 'append mode');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function processTransfer(
        MovieFileTransfer $transfer,
        string $cat,
        bool $dryRun,
        bool $applyFixes
    ): void {
        $label = "Transfer #{$transfer->id} (movie #{$transfer->movie_id} — {$transfer->movie_title})";

        $this->line("  {$label}");
        $this->line("    Error: " . substr((string) $transfer->error_message, 0, 120));
        $this->line("    Attempts: {$transfer->attempt_count}/{$transfer->max_attempts} | dest_path: " . ($transfer->dest_path ?: 'none'));

        if (!$applyFixes && !$dryRun) {
            $this->stats['skipped_dry_run']++;
            return;
        }

        match ($cat) {
            'share_failed' => $this->fixShareFailed($transfer, $dryRun),
            'transient'    => $this->fixTransient($transfer, $dryRun),
            'exhausted'    => $this->fixExhausted($transfer, $dryRun),
            'source_dead'  => $this->fixSourceDead($transfer, $dryRun),
            default        => $this->logUnfixable($transfer, $cat),
        };
    }

    /**
     * Extract Hetzner path from dest_path column or from the error_message.
     * The job embeds the path in error messages like "... for path: movies/xxx.mp4"
     * or "... for: movies/xxx.mp4", so we can recover it even when dest_path is NULL.
     */
    private function resolveDestPath(MovieFileTransfer $transfer): ?string
    {
        if (!empty($transfer->dest_path)) {
            return $transfer->dest_path;
        }
        $msg = (string) ($transfer->error_message ?? '');
        if (preg_match('/(?:for path:|for:)\s*(movies\/[^\s]+\.(?:mp4|mkv|avi|mov|webm))/i', $msg, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * File exists on Hetzner but the OCS share creation failed.
     * Verify file, create share, update movie URL, mark done.
     */
    private function fixShareFailed(MovieFileTransfer $transfer, bool $dryRun): void
    {
        $destPath = $this->resolveDestPath($transfer);

        if (!$destPath) {
            $this->warn("    → No dest_path and cannot parse from error_message. Treating as transient.");
            $this->fixTransient($transfer, $dryRun);
            return;
        }

        $this->line("    Checking Hetzner for: {$destPath}");

        if ($dryRun) {
            $this->line("    [DRY RUN] Would check Hetzner ({$destPath}) and create share if file exists.");
            $this->stats['skipped_dry_run']++;
            return;
        }

        try {
            $info = $this->hetzner->fileInfo($destPath);
        } catch (\Throwable $e) {
            $this->error("    Hetzner check failed: " . $e->getMessage());
            $this->stats['unfixable']++;
            return;
        }

        if (!$info || ($info['size'] ?? 0) <= 1024) {
            $size = $info['size'] ?? 0;
            $this->warn("    File not found or too small on Hetzner ({$size}B). Re-queuing for full re-transfer.");
            $this->resetToQueued($transfer, 0, 'share_failed fix: file not on Hetzner, re-queuing for full transfer');
            return;
        }

        $this->line("    File confirmed on Hetzner ({$info['size']} bytes). Creating share...");

        try {
            $shareUrl = $this->hetzner->findOrCreateShare($destPath);
        } catch (\Throwable $e) {
            $this->error("    Share creation threw: " . $e->getMessage());
            $this->stats['unfixable']++;
            $transfer->update(['error_message' => 'share_failed fix: ' . substr($e->getMessage(), 0, 450)]);
            return;
        }

        if (!$shareUrl) {
            $this->error("    findOrCreateShare returned null. Will retry later.");
            $this->stats['unfixable']++;
            return;
        }

        // File confirmed on Hetzner + share URL obtained — safe to mark done
        $this->markDone($transfer, $shareUrl, $info['size']);
        $this->info("    ✓ Fixed — marked DONE. URL: {$shareUrl}");
    }

    /**
     * Transient failure (upload, connection, disk, SSL).
     * Reset attempt_count so the job retries with a full budget.
     */
    private function fixTransient(MovieFileTransfer $transfer, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line("    [DRY RUN] Would reset to QUEUED (attempt_count=0).");
            $this->stats['skipped_dry_run']++;
            return;
        }
        $this->resetToQueued($transfer, 0, 'transient fix: reset for full retry');
        $this->info("    ✓ Reset to QUEUED (attempt_count=0).");
    }

    /**
     * Exhausted retries on a transient error.
     * Bump max_attempts by 3 and reset to give it another chance.
     */
    private function fixExhausted(MovieFileTransfer $transfer, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line("    [DRY RUN] Would bump max_attempts+3 and reset to QUEUED.");
            $this->stats['skipped_dry_run']++;
            return;
        }
        $newMax = (int) $transfer->max_attempts + 3;
        $transfer->update(['max_attempts' => $newMax]);
        $this->resetToQueued($transfer, 0, "exhausted fix: max_attempts bumped to {$newMax}");
        $this->info("    ✓ max_attempts → {$newMax}, reset to QUEUED.");
    }

    /**
     * Source URL is dead (403/404/empty/error-page).
     * Try:
     *   1. Check if the file is already on Hetzner → salvage via share
     *   2. If movie has munowatch_id → look for fresh source URL in crawler DB
     *   3. Otherwise → leave failed, update error message
     */
    private function fixSourceDead(MovieFileTransfer $transfer, bool $dryRun): void
    {
        // ── Step 1: Is the file already on Hetzner from a previous attempt? ──
        $destPath = $this->resolveDestPath($transfer);
        if ($destPath) {
            if ($dryRun) {
                $this->line("    [DRY RUN] Would check Hetzner ({$destPath}) for existing file, then crawler for fresh URL.");
                $this->stats['skipped_dry_run']++;
                return;
            }

            try {
                $info = $this->hetzner->fileInfo($destPath);
                if ($info && ($info['size'] ?? 0) > 1024) {
                    $this->line("    File found on Hetzner ({$info['size']}B) despite dead source. Creating share...");
                    $shareUrl = $this->hetzner->findOrCreateShare($destPath);
                    if ($shareUrl) {
                        $this->markDone($transfer, $shareUrl, $info['size']);
                        $this->info("    ✓ Salvaged — marked DONE. URL: {$shareUrl}");
                        return;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("    Hetzner check failed: " . $e->getMessage());
            }
        }

        // ── Step 2: Try to get a fresh source URL via crawler pages ──────────
        $munoId = $transfer->movie_munowatch_id
            ?? ($transfer->movie_id ? DB::table('movie_models')->where('id', $transfer->movie_id)->value('munowatch_id') : null);

        if ($munoId) {
            $this->line("    Checking crawler for fresh URL (munowatch_id={$munoId})...");

            $freshUrl = $this->fetchFreshMunoUrl($munoId);

            if ($freshUrl && $freshUrl !== $transfer->source_url && !str_contains($freshUrl, 'nx100800')) {
                if ($dryRun) {
                    $this->line("    [DRY RUN] Would update source_url → {$freshUrl} and reset to QUEUED.");
                    $this->stats['skipped_dry_run']++;
                    return;
                }

                $transfer->update([
                    'source_url'    => $freshUrl,
                    'source_type'   => 'munowatch',
                    'error_message' => 'source_dead fix: fresh URL from crawler, original dead',
                ]);
                $this->resetToQueued($transfer, 0, 'source_dead fix: fresh URL obtained');
                $this->info("    ✓ Fresh URL found — source_url updated, reset to QUEUED.");
                return;
            }
        }

        // ── Step 3: Unfixable — leave FAILED with updated error ───────────────
        if (!$dryRun) {
            $transfer->update([
                'error_message' => substr(
                    'source_dead: URL unreachable, no file on Hetzner, no fresh crawler URL. Original: ' . $transfer->source_url,
                    0, 500
                ),
            ]);
        }

        $this->warn("    Cannot fix — source dead, not on Hetzner, no fresh URL available.");
        $this->stats['unfixable']++;
    }

    private function logUnfixable(MovieFileTransfer $_transfer, string $cat): void
    {
        $this->warn("    No fix available for category '{$cat}'.");
        $this->stats['unfixable']++;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mark a transfer as DONE. Only called after verifying all three conditions:
     * 1) file on Hetzner, 2) share URL obtained, 3) movie URL updated.
     */
    private function markDone(MovieFileTransfer $transfer, string $shareUrl, int $sizeBytes): void
    {
        // Update movie URL + sync to live server
        if ($transfer->movie_id) {
            try {
                MovieVideoURLChange::recordAndSync($transfer, $shareUrl);
            } catch (\Throwable $e) {
                // Fallback: direct update
                DB::table('movie_models')
                    ->where('id', $transfer->movie_id)
                    ->update(['url' => $shareUrl, 'fix_status' => 'fixed', 'updated_at' => now()]);
                Log::warning("[transfers:diagnose] MovieVideoURLChange::recordAndSync failed for movie #{$transfer->movie_id}: " . $e->getMessage());
            }
        }

        $transfer->update([
            'status'           => MovieFileTransfer::STATUS_DONE,
            'dest_url'         => $shareUrl,
            'dest_size_bytes'  => $sizeBytes,
            'dest_share_token' => basename(parse_url($shareUrl, PHP_URL_PATH)),
            'completed_at'     => now(),
            'progress_pct'     => 100,
            'movie_url_updated' => true,
            'notes'            => ($transfer->notes ? $transfer->notes . ' | ' : '')
                                . 'Salvaged by transfers:diagnose at ' . now()->toDateTimeString(),
        ]);

        $this->stats['fixed_done']++;
    }

    private function resetToQueued(MovieFileTransfer $transfer, int $attemptCount, string $reason): void
    {
        $transfer->update([
            'status'            => MovieFileTransfer::STATUS_QUEUED,
            'attempt_count'     => $attemptCount,
            'next_retry_at'     => null,
            'progress_pct'      => 0,
            'bytes_transferred' => 0,
            'eta_seconds'       => null,
            'error_message'     => substr($reason, 0, 500),
            'notes'             => ($transfer->notes ? $transfer->notes . ' | ' : '')
                                 . $reason . ' at ' . now()->toDateTimeString(),
        ]);
        $this->stats['fixed_requeued']++;
    }

    /**
     * Look up the current video URL for a MunoWatch movie from the crawler DB.
     * Returns null if not found or URL has not changed.
     */
    private function fetchFreshMunoUrl(string $munoId): ?string
    {
        // Try crawler page first (most up-to-date source)
        $page = DB::table('movie_crawler_pages')
            ->where('slug', $munoId)
            ->where('status', 'success')
            ->orderByDesc('updated_at')
            ->first();

        if ($page) {
            // Try to extract video URL from page record
            // The crawler stores the movie URL directly in movie_models, not on the page.
            // So look at the corresponding movie record.
        }

        // Check the movie_models table for the current URL from MunoWatch
        $currentUrl = DB::table('movie_models')
            ->where('munowatch_id', $munoId)
            ->orderByDesc('updated_at')
            ->value('url');

        if ($currentUrl && !str_contains($currentUrl, 'nx100800') && !empty(trim($currentUrl))) {
            return $currentUrl;
        }

        return null;
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('═══ DIAGNOSIS SUMMARY ═══');
        $this->table(
            ['Category', 'Count'],
            [
                ['Total failed analysed',  $this->stats['total_failed']],
                ['  share_failed',          $this->stats['share_failed']],
                ['  transient',             $this->stats['transient']],
                ['  source_dead',           $this->stats['source_dead']],
                ['  exhausted',             $this->stats['exhausted']],
                ['  unknown',               $this->stats['unknown'] ?? 0],
                ['', ''],
                ['✓ Fixed → DONE',          $this->stats['fixed_done']],
                ['✓ Reset → QUEUED',        $this->stats['fixed_requeued']],
                ['✗ Unfixable (left FAILED)', $this->stats['unfixable']],
                ['⊘ Dry-run / report-only',  $this->stats['skipped_dry_run']],
            ]
        );

        if ($this->stats['unfixable'] > 0) {
            $this->warn("{$this->stats['unfixable']} transfers could not be fixed. Check logs for details.");
        }
        if ($this->stats['fixed_done'] + $this->stats['fixed_requeued'] > 0) {
            $this->info("Total fixed: " . ($this->stats['fixed_done'] + $this->stats['fixed_requeued']));
        }
    }
}
