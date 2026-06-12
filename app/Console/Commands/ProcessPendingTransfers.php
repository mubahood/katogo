<?php

namespace App\Console\Commands;

use App\Jobs\TransferMovieToHetzner;
use App\Models\MovieFileTransfer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * transfers:process
 *
 * Run by the scheduler every 5 minutes.
 * Picks up queued transfer records and dispatches jobs, honouring the
 * concurrency limit so the server is never overwhelmed.
 * Also detects and resets stuck transfers (active with no progress for 45+ min).
 */
class ProcessPendingTransfers extends Command
{
    protected $signature = 'transfers:process
                            {--concurrency=2  : Max simultaneous active transfers}
                            {--limit=6        : Max new jobs to dispatch per run}
                            {--dry-run        : Show what would be dispatched without doing it}
                            {--stuck-minutes=45 : Reset transfers stuck longer than this many minutes}';

    protected $description = 'Dispatch queued movie file transfers (runs every 5 minutes via scheduler)';

    public function handle(): int
    {
        $maxActive    = (int) $this->option('concurrency');
        $limit        = (int) $this->option('limit');
        $dryRun       = (bool) $this->option('dry-run');
        $stuckMinutes = (int) $this->option('stuck-minutes');

        // ── Step 1: Reset stuck transfers ─────────────────────────────────────
        $stuck = MovieFileTransfer::whereIn('status', [
                MovieFileTransfer::STATUS_TRANSFERRING,
                MovieFileTransfer::STATUS_VERIFYING,
                MovieFileTransfer::STATUS_COMPLETING,
            ])
            ->where('updated_at', '<', now()->subMinutes($stuckMinutes))
            ->get();

        foreach ($stuck as $t) {
            $this->warn("  Resetting stuck transfer #{$t->id} ({$t->movie_title}, status: {$t->status})");
            if (!$dryRun) {
                $t->resetToQueued("Auto-reset: stuck in '{$t->status}' for >{$stuckMinutes}min");
            }
        }

        if ($stuck->isNotEmpty()) {
            $this->line('');
        }

        // ── Step 2: Count currently active ───────────────────────────────────
        $activeCount = MovieFileTransfer::active()->count();
        $slots       = max(0, $maxActive - $activeCount);

        $this->line("Active: {$activeCount}/{$maxActive} | Available slots: {$slots}");

        if ($slots === 0) {
            $this->info('All slots occupied — nothing dispatched this run.');
            $this->printSummary();
            return 0;
        }

        // ── Step 3: Pick next queued records ──────────────────────────────────
        // Priority ordering:
        // 1. Fresh attempts (attempt_count=0) before retries
        // 2. Higher priority score first (views*3 + downloads*5 + likes*2)
        // 3. Oldest queued_at (FIFO as tiebreaker)
        $toDispatch = MovieFileTransfer::pending()
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                  ->orWhere('next_retry_at', '<=', now());
            })
            ->orderByRaw('CASE WHEN attempt_count = 0 THEN 0 ELSE 1 END')
            ->orderByDesc('priority')
            ->orderBy('queued_at')
            ->limit(min($limit, $slots))
            ->get();

        if ($toDispatch->isEmpty()) {
            $this->info('No queued transfers ready to dispatch.');
            $this->printSummary();
            return 0;
        }

        $this->info("Dispatching {$toDispatch->count()} transfer(s)...");

        foreach ($toDispatch as $t) {
            $this->line("  → #{$t->id} | movie #{$t->movie_id} | {$t->movie_title} | {$t->source_type}");
            if (!$dryRun) {
                TransferMovieToHetzner::dispatch($t->id)->onQueue('transfers');
            }
        }

        if ($dryRun) {
            $this->warn('  (dry-run — nothing actually dispatched)');
        }

        Log::info('[transfers:process] Dispatched ' . $toDispatch->count() . ' transfer job(s).', [
            'ids' => $toDispatch->pluck('id')->toArray(),
        ]);

        $this->printSummary();
        return 0;
    }

    private function printSummary(): void
    {
        $counts = MovieFileTransfer::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total = array_sum($counts);

        $this->line('');
        $this->table(
            ['Status', 'Count'],
            [
                ['Queued',       $counts[MovieFileTransfer::STATUS_QUEUED]       ?? 0],
                ['Verifying',    $counts[MovieFileTransfer::STATUS_VERIFYING]    ?? 0],
                ['Transferring', $counts[MovieFileTransfer::STATUS_TRANSFERRING] ?? 0],
                ['Completing',   $counts[MovieFileTransfer::STATUS_COMPLETING]   ?? 0],
                ['Done',         $counts[MovieFileTransfer::STATUS_DONE]         ?? 0],
                ['Failed',       $counts[MovieFileTransfer::STATUS_FAILED]       ?? 0],
                ['Cancelled',    $counts[MovieFileTransfer::STATUS_CANCELLED]    ?? 0],
                ['Skipped',      $counts[MovieFileTransfer::STATUS_SKIPPED]      ?? 0],
                ['── Total',     $total],
            ]
        );
    }
}
