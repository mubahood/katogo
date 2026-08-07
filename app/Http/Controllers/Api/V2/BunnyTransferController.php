<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Jobs\TransferMovieToBunny;
use App\Models\MovieFileTransfer;
use App\Services\BunnyTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * API powering the Bunny transfer pipeline.
 *
 * GET  /api/v2/admin/bunny/stats             — headline numbers
 * GET  /api/v2/admin/bunny/records           — paged list (?status=&q=&per_page=)
 * GET  /api/v2/admin/bunny/progress          — live feed: uploading/queued rows + pct
 * POST /api/v2/admin/bunny/queue             — {movie_ids:[..]} or {top:N} → dispatch queue jobs
 * POST /api/v2/admin/bunny/retry/{id}        — retry one (synchronous)
 * POST /api/v2/admin/bunny/retry-all-failed  — re-queue all failed
 * POST /api/v2/admin/bunny/verify/{id}       — verify one against Bunny
 * POST /api/v2/admin/bunny/verify-all        — re-verify all done rows
 */
class BunnyTransferController extends Controller
{
    public function stats()
    {
        $s = DB::table('movie_file_transfers')->selectRaw("
                SUM(bunny_status = 'done')      AS done,
                SUM(bunny_status = 'failed')    AS failed,
                SUM(bunny_status = 'uploading') AS uploading,
                SUM(bunny_status = 'pending')   AS queued,
                SUM(status = 'done' AND bunny_status IS NULL) AS eligible,
                COALESCE(SUM(CASE WHEN bunny_status='done' THEN bunny_size_bytes END),0) AS bytes,
                SUM(bunny_status='done' AND bunny_transferred_at >= NOW() - INTERVAL 24 HOUR) AS done_24h
            ")->first();

        return response()->json([
            'code' => 1,
            'data' => [
                'configured'        => app(BunnyTransferService::class)->isConfigured(),
                'pull_zone_host'    => config('bunny.pull_zone_host'),
                'url_priority'      => config('bunny.url_priority'),
                'done'              => (int) $s->done,
                'failed'            => (int) $s->failed,
                'uploading'         => (int) $s->uploading,
                'queued'            => (int) $s->queued,
                'eligible'          => (int) $s->eligible,
                'done_last_24h'     => (int) $s->done_24h,
                'total_gb_on_bunny' => round($s->bytes / 1073741824, 2),
                'est_storage_cost'  => '$' . number_format($s->bytes / 1073741824 * 0.01, 2) . '/month',
                'last_transfer_at'  => DB::table('movie_file_transfers')->whereNotNull('bunny_transferred_at')->max('bunny_transferred_at'),
            ],
        ]);
    }

    public function records(Request $request)
    {
        $q = MovieFileTransfer::query()
            ->where('status', 'done')
            ->select('id', 'movie_id', 'movie_title', 'bunny_status', 'bunny_url',
                     'bunny_storage_path', 'bunny_size_bytes', 'bunny_attempts',
                     'bunny_source_used', 'bunny_progress_pct', 'bunny_error', 'bunny_transferred_at')
            ->orderByRaw("FIELD(COALESCE(bunny_status,'x'),'uploading','pending','failed','done','x')")
            ->orderByDesc('bunny_transferred_at');

        if ($status = $request->get('status')) {
            $status === 'not_queued' ? $q->whereNull('bunny_status') : $q->where('bunny_status', $status);
        }
        if ($search = $request->get('q')) {
            $q->where(fn ($w) => $w->where('movie_title', 'like', "%{$search}%")->orWhere('movie_id', $search));
        }

        return response()->json(['code' => 1, 'data' => $q->paginate(min(100, (int) $request->get('per_page', 25)))]);
    }

    /** Live progress feed — poll this while transfers run. */
    public function progress()
    {
        return response()->json([
            'code' => 1,
            'data' => [
                'active' => MovieFileTransfer::whereIn('bunny_status', ['uploading', 'pending'])
                    ->select('id', 'movie_id', 'movie_title', 'bunny_status', 'bunny_progress_pct', 'bunny_source_used', 'updated_at')
                    ->orderByRaw("FIELD(bunny_status,'uploading','pending')")
                    ->limit(50)->get(),
                'queue_depth' => (int) MovieFileTransfer::where('bunny_status', 'pending')->count(),
            ],
        ]);
    }

    /** Queue transfers: {movie_ids: [...]} or {top: N}. */
    public function queueBatch(Request $request)
    {
        $transferIds = collect();

        if ($request->filled('movie_ids')) {
            $transferIds = MovieFileTransfer::where('status', 'done')
                ->whereIn('movie_id', (array) $request->input('movie_ids'))
                ->where(fn ($q) => $q->whereNull('bunny_status')->orWhere('bunny_status', 'failed'))
                ->pluck('id');
        } elseif ($request->filled('top')) {
            $n = min(200, max(1, (int) $request->input('top')));
            // Shared strict rule: on Hetzner (dest_url) + Active + on-demand (30d views)
            $transferIds = \App\Admin\Controllers\BunnyTransferAdminController::eligibleQuery()
                ->limit($n)->pluck('t.id');
        } else {
            return response()->json(['code' => 0, 'message' => 'Provide movie_ids[] or top.'], 422);
        }

        foreach ($transferIds as $tid) {
            DB::table('movie_file_transfers')->where('id', $tid)
                ->update(['bunny_status' => 'pending', 'bunny_error' => null, 'updated_at' => now()]);
            TransferMovieToBunny::dispatch($tid)->onQueue('default');
        }

        return response()->json(['code' => 1, 'message' => "Queued {$transferIds->count()} transfer(s).", 'data' => ['queued' => $transferIds->count()]]);
    }

    public function retry($id, BunnyTransferService $bunny)
    {
        $t = MovieFileTransfer::findOrFail($id);
        $t->bunny_status = null;
        $result = $bunny->transfer($t);
        return response()->json(['code' => $result['success'] ? 1 : 0, 'data' => $result]);
    }

    public function retryAllFailed()
    {
        $ids = MovieFileTransfer::where('bunny_status', 'failed')->pluck('id');
        foreach ($ids as $tid) {
            DB::table('movie_file_transfers')->where('id', $tid)
                ->update(['bunny_status' => 'pending', 'bunny_error' => null, 'updated_at' => now()]);
            TransferMovieToBunny::dispatch($tid)->onQueue('default');
        }
        return response()->json(['code' => 1, 'message' => "Re-queued {$ids->count()} failed transfer(s)."]);
    }

    public function verify($id, BunnyTransferService $bunny)
    {
        $t = MovieFileTransfer::findOrFail($id);
        $size = $t->bunny_storage_path ? $bunny->remoteSize($t->bunny_storage_path) : null;

        return response()->json([
            'code' => $size ? 1 : 0,
            'data' => [
                'movie_id'        => $t->movie_id,
                'bunny_url'       => $t->bunny_url,
                'recorded_bytes'  => $t->bunny_size_bytes,
                'actual_bytes'    => $size,
                'exists_on_bunny' => $size !== null,
                'sizes_match'     => $size !== null && (int) $size === (int) $t->bunny_size_bytes,
            ],
        ]);
    }

    public function verifyAll(BunnyTransferService $bunny)
    {
        $ok = 0; $bad = [];
        foreach (MovieFileTransfer::where('bunny_status', 'done')->get() as $t) {
            $size = $t->bunny_storage_path ? $bunny->remoteSize($t->bunny_storage_path) : null;
            if ($size !== null && $size >= 1024 * 100) {
                $ok++;
            } else {
                $bad[] = $t->movie_id;
                $t->bunny_status = 'failed';
                $t->bunny_error  = 'verify-all: file missing on Bunny.';
                $t->save();
            }
        }
        Cache::forget('bunny_url_map');
        return response()->json(['code' => 1, 'data' => ['intact' => $ok, 'missing_movie_ids' => $bad]]);
    }
}
