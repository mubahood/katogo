<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs on the REPLICA server (munoapp.store) ONLY.
 *
 * Receives real-time event pushes from the source server (movies.mruodel.com)
 * for Tier-1 tables (users, subscriptions, payments) that need < 1-minute lag.
 *
 * The source server dispatches PushSyncEvent jobs which POST here.
 * This endpoint is a no-op (403) when called on the source server.
 *
 * Auth: X-Sync-Event-Secret header = SYNC_EVENT_SECRET in .env.
 */
class InternalSyncController extends Controller
{
    // Tables accepted via event push — Tier 1 only (most critical, smallest blast radius)
    private const ALLOWED_EVENT_TABLES = [
        'admin_users',
        'subscriptions',
        'subscription_transactions',
        'subscription_plans',
        'coin_transactions',
    ];

    /**
     * POST /api/internal/sync/receive-event
     *
     * Body: { "table": "subscriptions", "action": "upsert", "rows": [{...}, ...] }
     */
    public function receiveEvent(Request $request): JsonResponse
    {
        // Anti-reversal: only process on replica
        if (config('services.sync.role') !== 'replica') {
            return response()->json(['error' => 'Not a replica — this endpoint is disabled'], 403);
        }

        // Auth
        $secret = config('services.sync.event_secret', '');
        if (empty($secret) || $request->header('X-Sync-Event-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $table  = (string) $request->json('table', '');
        $rows   = (array) $request->json('rows', []);

        if (!in_array($table, self::ALLOWED_EVENT_TABLES, true)) {
            return response()->json(['error' => "Table '{$table}' not accepted via event push"], 403);
        }

        if (empty($rows)) {
            return response()->json(['ok' => true, 'upserted' => 0, 'message' => 'No rows']);
        }

        if (count($rows) > 500) {
            return response()->json(['error' => 'Too many rows in one push (max 500)'], 422);
        }

        try {
            $upserted = $this->upsertRows($table, $rows);

            Log::info("[InternalSync] Event push: {$upserted} rows upserted into {$table} from " . $request->ip());

            return response()->json(['ok' => true, 'upserted' => $upserted]);

        } catch (\Throwable $e) {
            Log::error("[InternalSync] Event push failed for {$table}: " . $e->getMessage());
            return response()->json(['error' => 'Upsert failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/internal/sync/status
     *
     * Returns sync health for lightweight monitoring (no auth — public stats only).
     * Called by live server to confirm backup is reachable before pushing.
     */
    public function status(): JsonResponse
    {
        $role = config('services.sync.role', 'unknown');

        if ($role !== 'replica') {
            return response()->json([
                'ok'   => true,
                'role' => $role,
                'msg'  => 'This is the source server — no sync state here.',
            ]);
        }

        try {
            $cursors = DB::table('db_sync_cursors')
                ->select('table_name', 'status', 'last_run_at', 'consecutive_errors')
                ->orderBy('priority')
                ->get();

            $errors = $cursors->where('status', 'error')->count();
            $lastRun = $cursors->max('last_run_at');

            return response()->json([
                'ok'             => true,
                'role'           => $role,
                'total_tables'   => $cursors->count(),
                'tables_in_error' => $errors,
                'last_sync_at'   => $lastRun,
                'is_healthy'     => $errors === 0 && $lastRun && now()->diffInMinutes($lastRun) < 30,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'role' => $role, 'error' => $e->getMessage()], 500);
        }
    }

    private function upsertRows(string $table, array $rows): int
    {
        if (empty($rows)) return 0;

        $columns     = array_keys($rows[0]);
        $colList     = '`' . implode('`,`', $columns) . '`';
        $ph          = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $updateCols  = array_filter($columns, fn($c) => $c !== 'id');
        $updateClause = implode(', ', array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", $updateCols));

        $upserted = 0;
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (array_chunk($rows, 100) as $chunk) {
                $allPh  = implode(',', array_fill(0, count($chunk), $ph));
                $values = array_merge(...array_map(fn($r) => array_values($r), $chunk));

                DB::statement(
                    "INSERT INTO `{$table}` ({$colList}) VALUES {$allPh} ON DUPLICATE KEY UPDATE {$updateClause}",
                    $values
                );
                $upserted += count($chunk);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $upserted;
    }
}
