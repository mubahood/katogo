<?php

namespace App\Services;

use App\Models\DbSyncCursor;
use App\Models\DbSyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Runs on the DESTINATION server (Hetzner).
 *
 * Opens an SSH tunnel to Namecheap MySQL, then pulls each table incrementally
 * using dual-cursor CDC (new rows by id, updated rows by updated_at).
 * All writes use INSERT ... ON DUPLICATE KEY UPDATE (upsert).
 */
class SyncPullService
{
    // Namecheap SSH + DB config — stored in .env
    private string $sourceHost;
    private string $sourceSshUser;
    private string $sourceDbUser;
    private string $sourceDbPass;
    private string $sourceDbName;
    private int    $tunnelPort;
    private int    $batchSize;
    private int    $maxPagesPerTable;

    // SSH tunnel process handle
    private mixed $tunnelProcess = null;

    /** Tables to sync, in priority order. */
    private const TABLE_CONFIG = [
        // [priority, frequency_minutes, has_timestamps, is_pivot]
        'admin_users'                => [1, 5,  true,  false],
        'subscriptions'              => [1, 5,  true,  false],
        'subscription_transactions'  => [1, 5,  true,  false],
        'subscription_plans'         => [1, 5,  true,  false],
        'movie_models'               => [2, 5,  true,  false],
        'series_movies'              => [2, 5,  true,  false],
        'movie_views'                => [2, 5,  true,  false],
        'movie_likes'                => [2, 5,  true,  false],
        'movie_downloads'            => [2, 5,  true,  false],
        'movie_requests'             => [2, 5,  true,  false],
        'movie_searches'             => [2, 5,  true,  false],
        'movie_wishlists'            => [2, 5,  true,  false],
        'user_activity_logs'         => [2, 5,  true,  false],
        'movie_ratings'              => [2, 5,  true,  false],
        'video_playback_failures'    => [3, 15, true,  false],
        'content_reports'            => [3, 15, true,  false],
        'content_moderation_logs'    => [3, 15, true,  false],
        'chat_heads'                 => [3, 15, true,  false],
        'chat_messages'              => [3, 15, true,  false],
        'movie_crawler_websites'     => [3, 15, true,  false],
        'movie_crawler_pages'        => [3, 15, true,  false],
        'movie_pics'                 => [3, 15, true,  false],
        'munowatch_categories'       => [3, 15, true,  false],
        'munowatch_movie_categories' => [3, 15, false, true],
        'safemode_views'             => [3, 15, true,  false],
        'support_audit_logs'         => [3, 15, true,  false],
        'admin_roles'                => [4, 60, true,  false],
        'admin_permissions'          => [4, 60, true,  false],
        'admin_menu'                 => [4, 60, true,  false],
        'admin_operation_log'        => [4, 60, true,  false],
        'streaming_stations'         => [4, 60, true,  false],
        'streaming_urls'             => [4, 60, true,  false],
        'pages'                      => [4, 60, true,  false],
        'links'                      => [4, 60, true,  false],
        'schools'                    => [4, 60, true,  false],
        'blog_posts'                 => [4, 60, true,  false],
        'blog_comments'              => [4, 60, true,  false],
        'blog_likes'                 => [4, 60, false, true],
        'system_configs'             => [4, 60, true,  false],
        'scraper_models'             => [4, 60, true,  false],
        'game_stats'                 => [4, 60, true,  false],
        'trending_notifications'     => [4, 60, true,  false],
        'coin_transactions'          => [4, 60, true,  false],
        'user_blocks'                => [4, 60, true,  false],
        'merged_accounts'            => [4, 60, true,  false],
        'page_visits'                => [4, 60, true,  false],
        'learning_material_categories' => [4, 60, true, false],
        'learning_material_posts'    => [4, 60, true,  false],
        'trivia_questions'           => [4, 60, true,  false],
        'video_transfers'            => [4, 60, true,  false],
        'admin_role_users'           => [4, 60, false, true],
        'admin_role_permissions'     => [4, 60, false, true],
        'admin_user_permissions'     => [4, 60, false, true],
    ];

    public function __construct()
    {
        $this->sourceHost       = config('services.sync.source_host', '209.74.87.69');
        $this->sourceSshUser    = config('services.sync.ssh_user',    'root');
        $this->sourceDbUser     = config('services.sync.db_user',     'katogo');
        $this->sourceDbPass     = config('services.sync.db_pass',     '');
        $this->sourceDbName     = config('services.sync.db_name',     'katogo_3');
        $this->tunnelPort       = (int) config('services.sync.tunnel_port', 13306);
        $this->batchSize        = (int) config('services.sync.batch_size',  500);
        $this->maxPagesPerTable = (int) config('services.sync.max_pages',   30);
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Seed db_sync_cursors with all known tables (idempotent).
     * Call once after running migrations.
     */
    public function seedCursors(): void
    {
        foreach (self::TABLE_CONFIG as $table => [$priority, $freq, $hasTs, $isPivot]) {
            DbSyncCursor::firstOrCreate(
                ['table_name' => $table],
                [
                    'priority'          => $priority,
                    'frequency_minutes' => $freq,
                    'has_timestamps'    => $hasTs,
                    'is_pivot'          => $isPivot,
                    'enabled'           => true,
                    'status'            => 'idle',
                ]
            );
        }
    }

    /**
     * Run a full sync cycle: open tunnel, sync all due tables, close tunnel.
     *
     * @param string|null $onlyTable  Restrict to one table (for manual triggers)
     * @param bool        $force      Ignore frequency check (sync even if not due)
     * @param bool        $dryRun     Log what would happen but don't write to DB
     * @param callable|null $progress Called with (table, status, message) for CLI output
     */
    public function runSync(
        ?string $onlyTable = null,
        bool $force = false,
        bool $dryRun = false,
        ?callable $progress = null
    ): array {
        $progress ??= fn() => null;

        // File lock prevents two concurrent sync:pull processes from colliding on
        // the SSH tunnel port (13306). If the lock is held, skip this run — the
        // scheduler will retry in 5 minutes and the holding process will be done by then.
        $lockPath = storage_path('logs/sync-pull.lock');
        $lockFile = @fopen($lockPath, 'c+');
        if (!$lockFile || !flock($lockFile, LOCK_EX | LOCK_NB)) {
            if ($lockFile) fclose($lockFile);
            $progress(null, 'skip', 'Another sync:pull is already running — skipping to avoid tunnel port conflict.');
            return [];
        }

        $runId   = (string) Str::uuid();
        $results = [];

        try {
            $progress(null, 'open', 'Opening SSH tunnel to Namecheap...');
            $this->openTunnel();
            $progress(null, 'open', "Tunnel active on 127.0.0.1:{$this->tunnelPort}");

            $this->configureNamecheapConnection();

            $cursors = $onlyTable
                ? DbSyncCursor::where('table_name', $onlyTable)->get()
                : DbSyncCursor::where('enabled', true)->orderBy('priority')->orderBy('table_name')->get();

            foreach ($cursors as $cursor) {
                if (!$force && !$cursor->isDue()) {
                    $progress($cursor->table_name, 'skip', 'Not due yet');
                    continue;
                }

                $progress($cursor->table_name, 'start', "Syncing (cursor id={$cursor->last_synced_id})...");

                $log = $this->syncTable($cursor, $runId, $dryRun);
                $results[] = $log;

                $icon = $log->status === 'ok' ? '✓' : '✕';
                $progress(
                    $cursor->table_name,
                    $log->status,
                    "{$icon} {$log->rows_upserted} upserted in {$log->duration_ms}ms"
                        . ($log->error_message ? " — {$log->error_message}" : '')
                );
            }

            // Reapply Hetzner CDN URLs that the sync may have overwritten
            if (!$dryRun && ($onlyTable === null || $onlyTable === 'movie_models')) {
                $this->reapplyHetznerUrls();
            }
        } finally {
            $this->closeTunnel();
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }

        return $results;
    }

    // ── SSH Tunnel ────────────────────────────────────────────────────────────

    private function openTunnel(): void
    {
        $this->closeTunnel();

        $sshBin  = trim(shell_exec('which ssh') ?: '/usr/bin/ssh');
        $sshArgs = [
            $sshBin,
            '-N',                          // no remote command
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'ServerAliveInterval=15',
            '-o', 'ServerAliveCountMax=3',
            '-o', 'ExitOnForwardFailure=yes',
            '-L', "127.0.0.1:{$this->tunnelPort}:127.0.0.1:3306",
            "{$this->sourceSshUser}@{$this->sourceHost}",
        ];

        $this->tunnelProcess = proc_open(
            $sshArgs,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes
        );

        // Wait up to 15 s for the port to be ready
        $deadline = time() + 15;
        while (time() < $deadline) {
            usleep(300_000);
            $conn = @fsockopen('127.0.0.1', $this->tunnelPort, $errno, $errstr, 1);
            if ($conn) { fclose($conn); return; }
        }

        throw new \RuntimeException("SSH tunnel to {$this->sourceHost} did not open in time.");
    }

    private function closeTunnel(): void
    {
        if ($this->tunnelProcess) {
            proc_terminate($this->tunnelProcess);
            proc_close($this->tunnelProcess);
            $this->tunnelProcess = null;
        }
        // Only kill if the port is still bound to an ssh process started by us
        // (not fuser -k blindly — that would kill another concurrent sync:pull's tunnel)
    }

    private function configureNamecheapConnection(): void
    {
        config([
            'database.connections.namecheap' => [
                'driver'      => 'mysql',
                'host'        => '127.0.0.1',
                'port'        => $this->tunnelPort,
                'database'    => $this->sourceDbName,
                'username'    => $this->sourceDbUser,
                'password'    => $this->sourceDbPass,
                'charset'     => 'utf8mb4',
                'collation'   => 'utf8mb4_unicode_ci',
                'strict'      => false,
                'options'     => [\PDO::ATTR_TIMEOUT => 30],
            ],
        ]);
        DB::purge('namecheap');
    }

    // ── Table sync ────────────────────────────────────────────────────────────

    private function syncTable(DbSyncCursor $cursor, string $runId, bool $dryRun): DbSyncLog
    {
        $table     = $cursor->table_name;
        $startedAt = now();
        $startMs   = (int)(microtime(true) * 1000);

        $log = new DbSyncLog([
            'run_id'           => $runId,
            'table_name'       => $table,
            'cursor_id_before' => $cursor->last_synced_id,
            'started_at'       => $startedAt,
            'created_at'       => $startedAt,
        ]);

        try {
            $cursor->markSyncing();

            // Check table exists on source (MariaDB doesn't support ? in SHOW TABLES LIKE)
            $sourceExists = DB::connection('namecheap')
                ->table('information_schema.tables')
                ->where('table_schema', $this->sourceDbName)
                ->where('table_name', $table)
                ->exists();

            if (empty($sourceExists)) {
                $cursor->update(['status' => 'ok']);
                $log->fill(['status' => 'skipped', 'error_message' => 'Table not found on source', 'completed_at' => now()]);
                $log->save();
                return $log;
            }

            $totalUpserted = 0;
            $totalFetched  = 0;
            $pages         = 0;
            $newCursorId   = (int) $cursor->last_synced_id;
            $newUpdatedTs  = $cursor->last_updated_ts?->toDateTimeString();

            if ($cursor->is_pivot) {
                // Full sync for pivot tables using REPLACE INTO (they're small)
                [$fetched, $upserted] = $this->syncPivotTable($table, $dryRun);
                $totalFetched  += $fetched;
                $totalUpserted += $upserted;
                $pages++;
            } else {
                // Incremental sync for tables with `id`
                do {
                    $rows = $this->fetchRows($table, $newCursorId, $newUpdatedTs, $cursor->has_timestamps);

                    if (!empty($rows)) {
                        $totalFetched += count($rows);

                        if (!$dryRun) {
                            $totalUpserted += $this->upsertRows($table, $rows);
                        }

                        // Advance cursors
                        foreach ($rows as $row) {
                            if (($row['id'] ?? 0) > $newCursorId) {
                                $newCursorId = (int) $row['id'];
                            }
                            if ($cursor->has_timestamps && !empty($row['updated_at'])) {
                                if ($newUpdatedTs === null || $row['updated_at'] > $newUpdatedTs) {
                                    $newUpdatedTs = $row['updated_at'];
                                }
                            }
                        }
                    }

                    $pages++;

                } while (count($rows ?? []) >= $this->batchSize && $pages < $this->maxPagesPerTable);
            }

            // Update source row count (best-effort)
            try {
                $totalRows = DB::connection('namecheap')->table($table)->count();
            } catch (\Throwable) {
                $totalRows = 0;
            }

            if (!$dryRun) {
                $cursor->markOk($newCursorId, $newUpdatedTs, $totalUpserted);
                $cursor->update(['rows_on_source' => $totalRows]);
            } else {
                $cursor->update(['status' => 'idle']);
            }

            $durationMs = (int)(microtime(true) * 1000) - $startMs;
            $log->fill([
                'rows_fetched'    => $totalFetched,
                'rows_upserted'   => $totalUpserted,
                'pages_fetched'   => $pages,
                'duration_ms'     => $durationMs,
                'status'          => 'ok',
                'cursor_id_after' => $newCursorId,
                'completed_at'    => now(),
            ]);
            $log->save();

        } catch (\Throwable $e) {
            $cursor->markError($e->getMessage());

            $durationMs = (int)(microtime(true) * 1000) - $startMs;
            $log->fill([
                'duration_ms'   => $durationMs,
                'status'        => 'error',
                'error_message' => substr($e->getMessage(), 0, 1000),
                'completed_at'  => now(),
            ]);
            $log->save();

            Log::error("[SyncPull] {$table}: {$e->getMessage()}");
        }

        return $log;
    }

    // ── Row fetching ──────────────────────────────────────────────────────────

    private function fetchRows(string $table, int $cursorId, ?string $updatedTs, bool $hasTimestamps): array
    {
        $src = DB::connection('namecheap');

        // New rows: id > cursor
        $newRows = $src->table($table)
            ->where('id', '>', $cursorId)
            ->orderBy('id')
            ->limit($this->batchSize)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        // Updated rows: updated_at > ts AND id <= cursor (catch modifications to already-synced rows)
        // Skip this lookup if cursor is 0 (initial sync — no previously synced rows to update)
        $updatedRows = [];
        if ($hasTimestamps && $updatedTs && $cursorId > 1000) {
            $updatedRows = $src->table($table)
                ->where('updated_at', '>', $updatedTs)
                ->where('id', '<=', $cursorId)
                ->orderBy('updated_at')
                ->limit($this->batchSize)
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        }

        // Deduplicate by id (updated rows have priority)
        $byId = [];
        foreach (array_merge($newRows, $updatedRows) as $row) {
            $byId[$row['id']] = $row;
        }

        return array_values($byId);
    }

    private function syncPivotTable(string $table, bool $dryRun): array
    {
        $src  = DB::connection('namecheap');
        $rows = $src->table($table)->get()->map(fn($r) => (array) $r)->toArray();

        if (empty($rows) || $dryRun) {
            return [count($rows), 0];
        }

        // For pivot tables use REPLACE INTO (handles INSERT + UPDATE)
        $columns = array_keys($rows[0]);
        $colList = '`' . implode('`,`', $columns) . '`';
        $ph = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';

        $upserted = 0;
        $chunks = array_chunk($rows, 200);
        foreach ($chunks as $chunk) {
            $allPh  = implode(',', array_fill(0, count($chunk), $ph));
            $values = array_merge(...array_map(fn($r) => array_values($r), $chunk));

            DB::statement(
                "REPLACE INTO `{$table}` ({$colList}) VALUES {$allPh}",
                $values
            );
            $upserted += count($chunk);
        }

        return [count($rows), $upserted];
    }

    // ── Upsert ────────────────────────────────────────────────────────────────

    private function upsertRows(string $table, array $rows): int
    {
        if (empty($rows)) return 0;

        $columns = array_keys($rows[0]);
        $colList = '`' . implode('`,`', $columns) . '`';
        $ph      = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';

        // Build update clause (update all columns except id on conflict)
        $updateCols = array_filter($columns, fn($c) => $c !== 'id');
        $updateClause = implode(', ', array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", $updateCols));

        $upserted = 0;
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (array_chunk($rows, 100) as $chunk) {
                $allPh  = implode(',', array_fill(0, count($chunk), $ph));
                $values = [];
                foreach ($chunk as $row) {
                    foreach (array_values($row) as $v) {
                        $values[] = $v;
                    }
                }

                DB::statement(
                    "INSERT INTO `{$table}` ({$colList}) VALUES {$allPh}
                     ON DUPLICATE KEY UPDATE {$updateClause}",
                    $values
                );
                $upserted += count($chunk);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $upserted;
    }

    /**
     * After syncing movie_models, reapply Hetzner CDN URLs for completed transfers.
     * The sync may have overwritten Hetzner URLs with old Namecheap source URLs.
     */
    private function reapplyHetznerUrls(): void
    {
        try {
            DB::statement("
                UPDATE movie_models mm
                INNER JOIN movie_file_transfers mft ON mft.movie_id = mm.id
                SET mm.url = mft.dest_url
                WHERE mft.status = 'done'
                  AND mft.dest_url IS NOT NULL
                  AND mft.dest_url != ''
            ");
        } catch (\Throwable $e) {
            Log::warning('[SyncPull] reapplyHetznerUrls failed: ' . $e->getMessage());
        }
    }
}
