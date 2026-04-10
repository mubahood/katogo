<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * MigrationController — Secure one-time endpoint for running the old-server migration.
 *
 * This controller provides an HTTP-accessible way to trigger the artisan migration
 * command on a production server where SSH is not available.
 *
 * SECURITY:
 *   - Protected by a secret token that must match the one in .env
 *   - Logs all access attempts
 *   - Should be removed after successful migration
 *
 * USAGE:
 *   1. Add MIGRATION_SECRET=<random-string> to your .env
 *   2. Deploy the code via git
 *   3. Hit: POST /api/run-migration?secret=<your-secret>&mode=dry-run
 *   4. If dry-run looks good: POST /api/run-migration?secret=<your-secret>&mode=live
 *   5. Verify: POST /api/run-migration?secret=<your-secret>&mode=verify
 *   6. DELETE this file and the route after migration is complete
 */
class MigrationController extends Controller
{
    public function runMigration(Request $request)
    {
        // ── Security check ───────────────────────────────────────
        $expectedSecret = env('MIGRATION_SECRET');
        $providedSecret = $request->query('secret');

        if (!$expectedSecret || strlen($expectedSecret) < 16) {
            Log::warning('Migration endpoint hit but MIGRATION_SECRET is not configured');
            return response()->json(['error' => 'Not configured'], 403);
        }

        if (!hash_equals($expectedSecret, $providedSecret ?? '')) {
            Log::warning('Migration endpoint hit with invalid secret', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $mode = $request->query('mode', 'dry-run');

        // The data files must be in the transition-data/ directory at the project root
        $dataPath = base_path('transition-data');

        if (!is_dir($dataPath)) {
            return response()->json([
                'error' => 'Data directory not found',
                'expected' => $dataPath,
            ], 404);
        }

        Log::info('Migration endpoint triggered', [
            'mode' => $mode,
            'ip'   => $request->ip(),
        ]);

        try {
            if ($mode === 'verify') {
                // Run verification
                $exitCode = Artisan::call('migrate:verify', [
                    '--data-path' => $dataPath,
                ]);
            } else {
                // Run migration (dry-run or live)
                $params = ['--data-path' => $dataPath];
                if ($mode === 'dry-run') {
                    $params['--dry-run'] = true;
                }
                $exitCode = Artisan::call('migrate:old-server', $params);
            }

            $output = Artisan::output();

            return response()->json([
                'status'    => $exitCode === 0 ? 'success' : 'completed_with_issues',
                'mode'      => $mode,
                'exit_code' => $exitCode,
                'output'    => $output,
            ]);
        } catch (\Exception $e) {
            Log::error('Migration endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'error'  => $e->getMessage(),
            ], 500);
        }
    }
}
