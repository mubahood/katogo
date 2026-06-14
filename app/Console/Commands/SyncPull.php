<?php

namespace App\Console\Commands;

use App\Models\DbSyncCursor;
use App\Services\SyncPullService;
use Illuminate\Console\Command;

class SyncPull extends Command
{
    protected $signature = 'sync:pull
        {--table=  : Sync only a specific table}
        {--force   : Skip frequency check — sync even if not due}
        {--dry-run : Show what would be synced without writing}
        {--seed    : Seed db_sync_cursors table and exit}
        {--reset=  : Reset cursor for a specific table (or "all")}
        {--status  : Show current sync status and exit}';

    protected $description = 'Pull database changes from Namecheap source server to this server';

    public function handle(SyncPullService $sync): int
    {
        if (!config('services.sync.enabled', false)) {
            $this->warn('Sync is disabled (SYNC_ENABLED is not set to true).');
            return self::SUCCESS;
        }

        // ── Seed cursors ──────────────────────────────────────────────────────
        if ($this->option('seed')) {
            $this->info('Seeding db_sync_cursors...');
            $sync->seedCursors();
            $this->info('Done. ' . DbSyncCursor::count() . ' tables registered.');
            return self::SUCCESS;
        }

        // ── Status report ─────────────────────────────────────────────────────
        if ($this->option('status')) {
            return $this->showStatus();
        }

        // ── Reset cursor ──────────────────────────────────────────────────────
        if ($reset = $this->option('reset')) {
            if ($reset === 'all') {
                DbSyncCursor::query()->update([
                    'last_synced_id'  => 0,
                    'last_updated_ts' => null,
                    'status'          => 'idle',
                    'error_message'   => null,
                ]);
                $this->info('All cursors reset.');
            } else {
                $cursor = DbSyncCursor::where('table_name', $reset)->first();
                if (!$cursor) {
                    $this->error("Table '{$reset}' not found in db_sync_cursors.");
                    return self::FAILURE;
                }
                $cursor->update([
                    'last_synced_id'  => 0,
                    'last_updated_ts' => null,
                    'status'          => 'idle',
                    'error_message'   => null,
                ]);
                $this->info("Cursor for '{$reset}' reset to 0.");
            }
            return self::SUCCESS;
        }

        // ── Main sync ─────────────────────────────────────────────────────────
        $dryRun    = (bool) $this->option('dry-run');
        $force     = (bool) $this->option('force');
        $onlyTable = $this->option('table') ?: null;

        if ($dryRun) {
            $this->warn('[DRY RUN] No data will be written.');
        }

        $this->info('Starting sync from Namecheap (' . config('services.sync.source_host') . ')...');

        $results = $sync->runSync(
            onlyTable: $onlyTable,
            force: $force,
            dryRun: $dryRun,
            progress: function (string|null $table, string $status, string $message) {
                if ($table === null) {
                    $this->line("  <comment>{$message}</comment>");
                    return;
                }

                $icon = match ($status) {
                    'ok'     => '<info>✓</info>',
                    'error'  => '<error>✕</error>',
                    'skip'   => '<comment>○</comment>',
                    'start'  => '<comment>→</comment>',
                    default  => ' ',
                };

                $this->line("  {$icon} <fg=white>{$table}</> {$message}");
            }
        );

        // Summary
        $ok      = count(array_filter($results, fn($l) => $l->status === 'ok'));
        $errors  = count(array_filter($results, fn($l) => $l->status === 'error'));
        $synced  = array_sum(array_column(array_map(fn($l) => ['r' => $l->rows_upserted], $results), 'r'));

        $this->newLine();
        $this->info("Done: {$ok} ok, {$errors} errors, {$synced} rows upserted.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function showStatus(): int
    {
        $cursors = DbSyncCursor::orderBy('priority')->orderBy('table_name')->get();

        if ($cursors->isEmpty()) {
            $this->warn('No cursors found. Run: php artisan sync:pull --seed');
            return self::SUCCESS;
        }

        $rows = $cursors->map(fn($c) => [
            $c->table_name,
            $c->status,
            $c->last_synced_id,
            $c->last_run_at?->diffForHumans() ?? 'never',
            number_format($c->rows_on_source),
            $c->enabled ? 'yes' : 'no',
        ])->toArray();

        $this->table(
            ['Table', 'Status', 'Last ID', 'Last Run', 'Source Rows', 'Enabled'],
            $rows
        );

        return self::SUCCESS;
    }
}
