<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SyncExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Runs on SOURCE server (movies.mruodel.com).
 *
 * Exposes read-only, cursor-based data export for the replica (munoapp.store)
 * to consume as an HTTP fallback when the SSH tunnel is unavailable.
 *
 * ALL endpoints require X-Sync-Export-Secret header = SYNC_EXPORT_SECRET in .env.
 * Tables not in SyncExportService::ALLOWED_TABLES return 403.
 */
class SyncExportController extends Controller
{
    public function __construct(private readonly SyncExportService $svc) {}

    /**
     * GET /api/internal/sync/export
     *
     * Query params:
     *   table       required  Table name (must be in ALLOWED_TABLES)
     *   cursor_id   optional  Minimum id (exclusive). Default 0.
     *   updated_ts  optional  ISO timestamp — also pull rows updated after this
     *   limit       optional  Rows per page, max 1000. Default 500.
     *   offset      optional  For pivot tables. Default 0.
     */
    public function export(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $table     = (string) $request->query('table', '');
        $cursorId  = (int) $request->query('cursor_id', 0);
        $updatedTs = $request->query('updated_ts') ?: null;
        $limit     = min(1000, max(1, (int) $request->query('limit', 500)));
        $offset    = max(0, (int) $request->query('offset', 0));

        if (!$this->svc->isAllowed($table)) {
            Log::warning("[SyncExport] Rejected request for table '{$table}' from " . $request->ip());
            return response()->json(['error' => "Table '{$table}' is not available for export"], 403);
        }

        try {
            $result = $this->svc->export($table, $cursorId, $updatedTs, $limit, $offset);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error("[SyncExport] Error exporting '{$table}': " . $e->getMessage());
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/internal/sync/handshake
     *
     * Health check used by the replica before starting a sync session.
     * Returns server identity, role, and row counts for all syncable tables.
     */
    public function handshake(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'ok'           => true,
            'server'       => config('app.url'),
            'role'         => config('services.sync.role', 'source'),
            'app_env'      => config('app.env'),
            'table_counts' => $this->svc->tableSummary(),
            'ts'           => now()->toISOString(),
        ]);
    }

    private function authenticate(Request $request): bool
    {
        $secret = config('services.sync.export_secret', '');
        if (empty($secret)) {
            Log::warning('[SyncExport] SYNC_EXPORT_SECRET is not configured — rejecting all requests.');
            return false;
        }
        return $request->header('X-Sync-Export-Secret') === $secret;
    }
}
