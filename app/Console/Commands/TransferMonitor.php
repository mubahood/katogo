<?php

namespace App\Console\Commands;

use App\Jobs\TransferMovieToHetzner;
use App\Models\MovieFileTransfer;
use App\Models\MovieModel;
use Illuminate\Console\Command;

/**
 * transfers:monitor
 *
 * Prints a live stats dashboard for the movie file transfer pipeline.
 * Use --watch to auto-refresh every N seconds.
 */
class TransferMonitor extends Command
{
    protected $signature = 'transfers:monitor
                            {--watch=0 : Auto-refresh interval in seconds (0 = run once)}';

    protected $description = 'Display live movie transfer pipeline stats';

    public function handle(): int
    {
        $watch = (int) $this->option('watch');

        do {
            $this->render();

            if ($watch > 0) {
                $this->line('');
                $this->line("Refreshing in {$watch}s… (Ctrl+C to exit)");
                sleep($watch);
                // Clear screen for watch mode
                $this->output->write("\033[2J\033[H");
            }
        } while ($watch > 0);

        return 0;
    }

    private function render(): void
    {
        $counts = MovieFileTransfer::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $queued       = $counts[MovieFileTransfer::STATUS_QUEUED]       ?? 0;
        $verifying    = $counts[MovieFileTransfer::STATUS_VERIFYING]    ?? 0;
        $transferring = $counts[MovieFileTransfer::STATUS_TRANSFERRING] ?? 0;
        $completing   = $counts[MovieFileTransfer::STATUS_COMPLETING]   ?? 0;
        $done         = $counts[MovieFileTransfer::STATUS_DONE]         ?? 0;
        $failed       = $counts[MovieFileTransfer::STATUS_FAILED]       ?? 0;
        $cancelled    = $counts[MovieFileTransfer::STATUS_CANCELLED]    ?? 0;
        $pending      = $queued + $verifying;
        $active       = $transferring + $completing;

        // Average speed from completed transfers
        $avgSpeed = MovieFileTransfer::done()
            ->whereNotNull('transfer_speed_mbps')
            ->avg('transfer_speed_mbps');

        // Average duration from completed transfers
        $avgDuration = MovieFileTransfer::done()
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        // ETA estimate
        $etaHours = null;
        if ($pending > 0 && $avgDuration > 0) {
            $workerSlots = TransferMovieToHetzner::MAX_CONCURRENT ?? 2;
            $etaSeconds  = ($pending * $avgDuration) / $workerSlots;
            $etaHours    = round($etaSeconds / 3600, 1);
        }

        // Total data transferred
        $totalGb = MovieFileTransfer::done()
            ->whereNotNull('dest_size_bytes')
            ->sum('dest_size_bytes') / 1_073_741_824;

        // Movies not yet queued
        $notYetQueued = MovieModel::where('status', 'Active')
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->where('url', 'not like', '%' . MovieFileTransfer::HETZNER_HOST . '%')
            ->whereDoesntHave('transfers', fn($q) => $q->whereIn('status', [
                MovieFileTransfer::STATUS_QUEUED,
                MovieFileTransfer::STATUS_VERIFYING,
                MovieFileTransfer::STATUS_TRANSFERRING,
                MovieFileTransfer::STATUS_COMPLETING,
                MovieFileTransfer::STATUS_DONE,
            ]))
            ->count();

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║          Movie File Transfer — Live Monitor              ║');
        $this->line('╠══════════════════════════════════════════════════════════╣');
        $this->line(sprintf('║  Queued       : %-40s ║', number_format($queued)));
        $this->line(sprintf('║  Active now   : %-40s ║', "{$active} / " . (TransferMovieToHetzner::MAX_CONCURRENT ?? 2) . ' slots'));
        $this->line(sprintf('║  Done         : %-40s ║', number_format($done)));
        $this->line(sprintf('║  Failed       : %-40s ║', number_format($failed)));
        $this->line(sprintf('║  Cancelled    : %-40s ║', number_format($cancelled)));
        $this->line(sprintf('║  Not queued   : %-40s ║', number_format($notYetQueued) . ' (need backfill)'));
        $this->line(sprintf('║  Avg speed    : %-40s ║', $avgSpeed ? number_format($avgSpeed, 1) . ' MB/s' : '—'));
        $this->line(sprintf('║  Avg duration : %-40s ║', $avgDuration ? $this->formatSeconds((int)$avgDuration) : '—'));
        $this->line(sprintf('║  ETA (all)    : %-40s ║', $etaHours !== null ? "~{$etaHours} hours" : '—'));
        $this->line(sprintf('║  Total stored : %-40s ║', number_format($totalGb, 2) . ' GB'));
        $this->line('╚══════════════════════════════════════════════════════════╝');

        // Active transfers
        $actives = MovieFileTransfer::active()
            ->orderBy('started_at')
            ->get();

        if ($actives->isNotEmpty()) {
            $this->line('');
            $this->line('Active transfers:');
            foreach ($actives as $t) {
                $bar   = str_repeat('█', (int)($t->progress_pct / 5)) . str_repeat('░', 20 - (int)($t->progress_pct / 5));
                $speed = $t->formatted_speed;
                $eta   = $t->formatted_eta;
                $title = substr($t->movie_title ?? 'Unknown', 0, 30);
                $this->line("  #{$t->id} | {$title} | [{$bar}] {$t->progress_pct}% | {$speed} | ETA {$eta}");
            }
        }

        // Recent failures
        $recentFailed = MovieFileTransfer::failed()
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        if ($recentFailed->isNotEmpty()) {
            $this->line('');
            $this->line('Recent failures:');
            foreach ($recentFailed as $t) {
                $title = substr($t->movie_title ?? 'Unknown', 0, 35);
                $err   = substr($t->error_message ?? '—', 0, 55);
                $this->line("  #{$t->id} | {$title} | {$err}");
            }
        }

        $this->line('');
        $this->line('Commands:');
        $this->line('  php artisan transfers:process --dry-run   (check what would be dispatched)');
        $this->line('  php artisan transfers:backfill --dry-run  (count movies needing transfer)');
        $this->line('  php artisan transfers:process             (manually trigger a dispatch run)');
    }

    private function formatSeconds(int $s): string
    {
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $s = $s % 60;
        if ($h > 0) return "{$h}h {$m}m {$s}s";
        if ($m > 0) return "{$m}m {$s}s";
        return "{$s}s";
    }
}
