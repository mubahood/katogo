<?php

namespace App\Admin\Controllers;

use App\Jobs\TransferMovieToBunny;
use App\Models\MovieFileTransfer;
use App\Services\BunnyTransferService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bunny Transfers — admin section.
 *
 * Lists every movie eligible for Bunny (i.e. every completed Hetzner transfer)
 * with its Bunny leg state, live upload progress, one-click queueing (single,
 * batch-selected, or Top-N by viewership), retry/verify controls, and running
 * cost estimates. Auto-refreshes while uploads are active.
 */
class BunnyTransferAdminController extends AdminController
{
    protected $title = 'Bunny Transfers';

    protected function grid(): Grid
    {
        $grid = new Grid(new MovieFileTransfer());

        // Every movie that COULD go to Bunny (Hetzner leg complete), newest state first
        $grid->model()->where('status', 'done')
            ->orderByRaw("FIELD(COALESCE(bunny_status,'x'),'uploading','pending','failed','done','x')")
            ->orderByDesc('bunny_transferred_at')
            ->orderByDesc('id');

        $grid->disableCreateButton();
        $grid->paginate(30);

        // ── Toolbar: queue Top-N + retry/verify all ───────────────────────
        $grid->tools(function ($tools) {
            $tools->append('
                <div class="btn-group" style="margin-right:4px;">
                  <a href="/bunny-transfers/queue-top?n=10" class="btn btn-sm btn-primary"
                     onclick="return confirm(\'Queue the top 10 most-watched Hetzner movies to Bunny?\')">
                     <i class="fa fa-cloud-upload"></i> Queue Top 10</a>
                  <a href="/bunny-transfers/queue-top?n=25" class="btn btn-sm btn-primary"
                     onclick="return confirm(\'Queue the top 25?\')">Top 25</a>
                  <a href="/bunny-transfers/queue-top?n=50" class="btn btn-sm btn-primary"
                     onclick="return confirm(\'Queue the top 50?\')">Top 50</a>
                </div>
                <div class="btn-group" style="margin-right:4px;">
                  <a href="/bunny-transfers/retry-all-failed" class="btn btn-sm btn-warning"
                     onclick="return confirm(\'Re-queue every failed Bunny transfer?\')">
                     <i class="fa fa-refresh"></i> Retry All Failed</a>
                </div>
                <div class="btn-group" style="margin-right:4px;">
                  <a href="/bunny-transfers/verify-all" class="btn btn-sm btn-info"
                     onclick="return confirm(\'Re-verify every done transfer against Bunny (size check)?\')">
                     <i class="fa fa-check-circle"></i> Verify All Done</a>
                </div>
            ');
        });

        // ── Batch action: queue the selected rows ─────────────────────────
        $grid->batchActions(function ($batch) {
            $batch->add(new \App\Admin\Actions\BatchQueueToBunny());
        });

        // ── Headline stats + auto-refresh while active ────────────────────
        $grid->header(function () {
            $s = DB::table('movie_file_transfers')->selectRaw("
                    SUM(bunny_status = 'done')      AS done,
                    SUM(bunny_status = 'failed')    AS failed,
                    SUM(bunny_status = 'uploading') AS uploading,
                    SUM(bunny_status = 'pending')   AS queued,
                    SUM(status = 'done' AND bunny_status IS NULL) AS eligible,
                    COALESCE(SUM(CASE WHEN bunny_status='done' THEN bunny_size_bytes END),0) AS bytes,
                    SUM(bunny_status='done' AND bunny_transferred_at >= NOW() - INTERVAL 24 HOUR) AS done_24h
                ")->first();

            $gb   = number_format(($s->bytes ?? 0) / 1073741824, 1);
            $cost = number_format(($s->bytes ?? 0) / 1073741824 * 0.01, 2);
            $active = ($s->uploading ?? 0) + ($s->queued ?? 0);

            // No auto-reload here — static grid; the Monitor page is the live view.
            $autoRefresh = $active > 0
                ? "<a href='/bunny-transfers/monitor' class='label label-info' style='font-size:12px;padding:7px 12px;'>{$active} in progress — watch live on Monitor →</a>"
                : '';

            return "
              <div style='display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center;'>
                <span class='label label-success' style='font-size:13px;padding:8px 14px;'>✓ Done: {$s->done}</span>
                <span class='label label-warning' style='font-size:13px;padding:8px 14px;'>⇡ Uploading: {$s->uploading}</span>
                <span class='label label-primary' style='font-size:13px;padding:8px 14px;'>⏳ Queued: {$s->queued}</span>
                <span class='label label-danger' style='font-size:13px;padding:8px 14px;'>✗ Failed: {$s->failed}</span>
                <span class='label label-default' style='font-size:13px;padding:8px 14px;'>Eligible: {$s->eligible}</span>
                <span class='label' style='font-size:13px;padding:8px 14px;background:#605ca8;'>On Bunny: {$gb} GB (\${$cost}/mo)</span>
                <span class='label label-default' style='font-size:13px;padding:8px 14px;'>Last 24h: {$s->done_24h}</span>
                {$autoRefresh}
              </div>";
        });

        // ── Columns ───────────────────────────────────────────────────────
        $grid->column('movie_id', 'Movie')->display(fn ($id) => "<a href='/movies-new?id={$id}' target='_blank'>#{$id}</a>");
        $grid->column('movie_title', 'Title')->limit(34);

        $grid->column('bunny_status', 'Bunny')->display(function ($v) {
            if ($v === null) {
                return "<span class='label label-default' style='opacity:.55;'>NOT QUEUED</span>";
            }
            $map = ['done' => 'success', 'failed' => 'danger', 'uploading' => 'warning', 'pending' => 'primary'];
            return "<span class='label label-" . ($map[$v] ?? 'default') . "'>" . strtoupper($v) . "</span>";
        });

        $grid->column('bunny_progress_pct', 'Progress')->display(function ($pct) {
            if ($this->bunny_status === 'done') $pct = 100;
            if (!in_array($this->bunny_status, ['uploading', 'done'], true)) return '';
            $color = $pct >= 100 ? '#00a65a' : '#f39c12';
            return "
              <div style='width:110px;background:#f4f4f4;border-radius:3px;height:16px;position:relative;'>
                <div style='width:{$pct}%;background:{$color};height:16px;border-radius:3px;'></div>
                <span style='position:absolute;top:0;left:0;right:0;text-align:center;font-size:11px;line-height:16px;color:#333;'>{$pct}%</span>
              </div>";
        });

        $grid->column('bunny_source_used', 'Via')->display(function ($v) {
            if (!$v) return '';
            $map = ['hetzner' => 'primary', 'main' => 'info', 'source' => 'default', 'resumed' => 'success'];
            return "<span class='label label-" . ($map[$v] ?? 'default') . "' style='font-size:10px;'>{$v}</span>";
        });

        $grid->column('bunny_size_bytes', 'Size')->display(fn ($b) => $b ? number_format($b / 1073741824, 2) . ' GB' : '—')->sortable();

        $grid->column('bunny_url', 'Bunny URL')->display(function ($u) {
            if (!$u) return '—';
            $name = basename(parse_url($u, PHP_URL_PATH));
            return "<a href='{$u}' target='_blank' style='font-family:monospace;font-size:11px;' title='{$u}'>" . e(mb_substr($name, 0, 28)) . " ↗</a>";
        });

        $grid->column('bunny_error', 'Error')->display(fn ($e) => $e ? "<span style='color:#dd4b39;font-size:11px;' title='" . e($e) . "'>" . e(mb_substr($e, 0, 60)) . "…</span>" : '');
        $grid->column('bunny_transferred_at', 'Transferred')->sortable();

        $grid->column('act', 'Action')->display(function () {
            $id = $this->id;
            switch ($this->bunny_status) {
                case null:
                    return "<a href='/bunny-transfers/queue/{$id}' class='btn btn-xs btn-primary'>Send to Bunny</a>";
                case 'failed':
                    return "<a href='/bunny-transfers/retry/{$id}' class='btn btn-xs btn-warning'
                              onclick=\"return confirm('Retry now (runs immediately)?')\">Retry</a>";
                case 'done':
                    return "<a href='/bunny-transfers/verify/{$id}' class='btn btn-xs btn-info'>Verify</a>";
                default:
                    return '';
            }
        });

        // ── Filters ───────────────────────────────────────────────────────
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->where(function ($q) {
                if ($this->input === 'not_queued') {
                    $q->whereNull('bunny_status');
                } else {
                    $q->where('bunny_status', $this->input);
                }
            }, 'Bunny status')->select([
                'done' => 'Done', 'failed' => 'Failed', 'uploading' => 'Uploading',
                'pending' => 'Queued', 'not_queued' => 'Not queued',
            ]);
            $filter->like('movie_title', 'Title');
            $filter->equal('movie_id', 'Movie ID');
            $filter->equal('bunny_source_used', 'Source used')->select([
                'hetzner' => 'Hetzner', 'main' => 'Main URL', 'source' => 'Original', 'resumed' => 'Resumed',
            ]);
        });

        return $grid;
    }

    // ── Actions ───────────────────────────────────────────────────────────

    /** Queue one row (async, via queue worker). */
    public function queue($id)
    {
        $t = MovieFileTransfer::findOrFail($id);
        if ($t->bunny_status === 'done') {
            admin_toastr("Movie #{$t->movie_id} is already on Bunny.", 'info');
        } else {
            $t->bunny_status = 'pending';
            $t->bunny_error  = null;
            $t->save();
            TransferMovieToBunny::dispatch($t->id)->onQueue('default');
            admin_toastr("Movie #{$t->movie_id} queued for Bunny ✓", 'success');
        }
        return redirect('/bunny-transfers');
    }

    /**
     * Queue the top-N eligible movies. Eligible means, strictly:
     *  - the Hetzner transfer is DONE and has a real dest_url (file IS on Hetzner)
     *  - the movie is Active
     *  - the movie is ON DEMAND: watched in the last 30 days, or downloaded ever
     * Ordered by 30-day views, then downloads — most-demanded first.
     */
    public function queueTop(Request $request)
    {
        $n = min(200, max(1, (int) $request->get('n', 10)));
        $ids = self::eligibleQuery()->limit($n)->pluck('t.id');

        foreach ($ids as $tid) {
            DB::table('movie_file_transfers')->where('id', $tid)
                ->update(['bunny_status' => 'pending', 'bunny_error' => null, 'updated_at' => now()]);
            TransferMovieToBunny::dispatch($tid)->onQueue('default');
        }

        if ($request->ajax()) {
            return response()->json(['code' => 1, 'message' => "Queued {$ids->count()} movie(s) to Bunny."]);
        }
        admin_toastr("Queued {$ids->count()} movie(s) to Bunny.", 'success');
        return redirect('/bunny-transfers');
    }

    /**
     * Retry one failed row. ?async=1 (monitor) queues it as a background job;
     * otherwise runs synchronously for instant grid feedback.
     */
    public function retry($id, Request $request)
    {
        $t = MovieFileTransfer::findOrFail($id);

        if ($request->boolean('async')) {
            $t->bunny_status = 'pending';
            $t->bunny_error  = null;
            $t->save();
            TransferMovieToBunny::dispatch($t->id)->onQueue('default');
            $msg = "Movie #{$t->movie_id} re-queued.";
            return $request->ajax()
                ? response()->json(['code' => 1, 'message' => $msg])
                : redirect('/bunny-transfers/monitor');
        }

        $t->bunny_status = null;
        $result = app(BunnyTransferService::class)->transfer($t);
        $msg = $result['success']
            ? "Movie #{$t->movie_id} → Bunny via {$result['source']} ✓"
            : 'Failed: ' . mb_substr($result['message'], 0, 160);

        if ($request->ajax()) {
            return response()->json(['code' => $result['success'] ? 1 : 0, 'message' => $msg]);
        }
        admin_toastr($msg, $result['success'] ? 'success' : 'error');
        return redirect('/bunny-transfers');
    }

    /** Re-queue every failed row. */
    public function retryAllFailed(Request $request)
    {
        $ids = MovieFileTransfer::where('bunny_status', 'failed')->pluck('id');
        foreach ($ids as $tid) {
            DB::table('movie_file_transfers')->where('id', $tid)
                ->update(['bunny_status' => 'pending', 'bunny_error' => null, 'updated_at' => now()]);
            TransferMovieToBunny::dispatch($tid)->onQueue('default');
        }
        $msg = "Re-queued {$ids->count()} failed transfer(s).";
        if ($request->ajax()) {
            return response()->json(['code' => 1, 'message' => $msg]);
        }
        admin_toastr($msg, 'success');
        return redirect('/bunny-transfers');
    }

    /** Verify one done row against Bunny. */
    public function verify($id)
    {
        $t    = MovieFileTransfer::findOrFail($id);
        $size = $t->bunny_storage_path ? app(BunnyTransferService::class)->remoteSize($t->bunny_storage_path) : null;

        if ($size !== null && (int) $size === (int) $t->bunny_size_bytes) {
            admin_toastr("Verified ✓ — " . number_format($size / 1048576) . " MB intact on Bunny.", 'success');
        } elseif ($size !== null) {
            admin_toastr("Size mismatch! Bunny has " . number_format($size) . " bytes, recorded " . number_format($t->bunny_size_bytes) . ".", 'warning');
        } else {
            admin_toastr('File NOT found on Bunny — consider Retry.', 'error');
        }
        return redirect('/bunny-transfers');
    }

    // ── Bunny Library: everything successfully ON Bunny ──────────────────

    /** Clean listing of completed Bunny transfers with copyable CDN links. */
    public function library(Content $content): Content
    {
        \Encore\Admin\Facades\Admin::script(<<<'JS'
window.bnCopy = function(url, btn){
  navigator.clipboard.writeText(url).then(function(){
    btn.innerText = 'copied ✓'; btn.className = 'btn btn-xs btn-success';
    setTimeout(function(){ btn.innerText = 'copy'; btn.className = 'btn btn-xs btn-default'; }, 1500);
  });
};
JS);

        $grid = new Grid(new MovieFileTransfer());
        $grid->model()->where('bunny_status', 'done')
            ->orderByDesc('bunny_transferred_at');
        $grid->disableCreateButton();
        $grid->disableBatchActions();
        $grid->disableActions();
        $grid->paginate(50);

        $grid->header(function () {
            $s = DB::table('movie_file_transfers')->where('bunny_status', 'done')
                ->selectRaw('COUNT(*) c, COALESCE(SUM(bunny_size_bytes),0) b, MAX(bunny_transferred_at) last')->first();
            $gb = number_format($s->b / 1073741824, 1);
            return "<div style='margin-bottom:10px;font-size:14px;color:#555;'>
                      <b>{$s->c}</b> movies live on Bunny · <b>{$gb} GB</b> stored · latest: {$s->last}
                    </div>";
        });

        $grid->column('movie_id', 'Movie')->display(fn ($id) => "<a href='/movies-new?id={$id}' target='_blank'>#{$id}</a>")->sortable();
        $grid->column('movie_title', 'Title')->limit(40);
        $grid->column('bunny_size_bytes', 'Size')->display(fn ($b) => $b ? number_format($b / 1073741824, 2) . ' GB' : '—')->sortable();
        $grid->column('bunny_source_used', 'Copied from')->display(fn ($v) => $v ? "<span class='label label-info' style='font-size:10px;'>{$v}</span>" : '');
        $grid->column('bunny_url', 'Bunny CDN Link')->display(function ($u) {
            if (!$u) return '—';
            $e = e($u);
            return "<div style='display:flex;gap:6px;align-items:center;'>
                      <a href='{$e}' target='_blank' style='font-family:monospace;font-size:11px;max-width:420px;
                         overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;'>{$e}</a>
                      <button class='btn btn-xs btn-default' onclick='bnCopy(\"{$e}\", this)'>copy</button>
                      <a href='{$e}' target='_blank' class='btn btn-xs btn-primary' title='Test playback'>▶</a>
                    </div>";
        });
        $grid->column('bunny_transferred_at', 'On Bunny since')->sortable();

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('movie_title', 'Title');
            $filter->equal('movie_id', 'Movie ID');
        });

        return $content
            ->title('Bunny Library')
            ->description('Movies successfully hosted on Bunny CDN — verified, live links')
            ->body($grid);
    }

    // ── Monitor dashboard ─────────────────────────────────────────────────

    /** Real-time pipeline dashboard — AJAX polling every 3 seconds (pjax-safe). */
    public function monitor(Content $content): Content
    {
        $s = $this->bunnyStats();

        // Registered via Admin::script so it executes on BOTH full loads and
        // pjax navigations — inline <script> tags in appended HTML do not run
        // under laravel-admin's pjax, which left the first monitor static.
        \Encore\Admin\Facades\Admin::script($this->monitorJs());

        return $content
            ->title('Bunny Transfer Monitor')
            ->description('Real-time Bunny pipeline — AJAX refresh every 3 seconds')
            ->row(function (Row $row) use ($s) {
                $row->column(12, function (Column $col) use ($s) {
                    $col->append($this->monitorHtml($s));
                });
            });
    }

    /** JSON feed for the monitor page. */
    public function liveData(): \Illuminate\Http\JsonResponse
    {
        $s = $this->bunnyStats();

        $active = MovieFileTransfer::where('bunny_status', 'uploading')
            ->select('id', 'movie_id', 'movie_title', 'bunny_progress_pct', 'bunny_source_used', 'dest_size_bytes', 'source_size_bytes', 'updated_at')
            ->orderBy('updated_at')->limit(10)->get()
            ->map(fn ($t) => [
                'id' => $t->id, 'movie_id' => $t->movie_id,
                'title' => mb_substr($t->movie_title ?? '', 0, 40),
                'pct' => (int) $t->bunny_progress_pct,
                'via' => $t->bunny_source_used,
                'gb'  => ($b = (int) ($t->dest_size_bytes ?: $t->source_size_bytes)) > 0 ? round($b / 1073741824, 2) : null,
                'stale' => $t->updated_at && $t->updated_at->lt(now()->subMinutes(10)),
            ]);

        $queued = MovieFileTransfer::where('bunny_status', 'pending')
            ->select('movie_id', 'movie_title')->orderBy('updated_at')->limit(8)->get()
            ->map(fn ($t) => ['movie_id' => $t->movie_id, 'title' => mb_substr($t->movie_title ?? '', 0, 40)]);

        $failed = MovieFileTransfer::where('bunny_status', 'failed')
            ->select('id', 'movie_id', 'movie_title', 'bunny_error', 'bunny_attempts')
            ->orderByDesc('updated_at')->limit(10)->get()
            ->map(fn ($t) => [
                'id' => $t->id, 'movie_id' => $t->movie_id,
                'title' => mb_substr($t->movie_title ?? '', 0, 35),
                'error' => mb_substr($t->bunny_error ?? '', 0, 90),
                'attempts' => $t->bunny_attempts,
            ]);

        $recent = MovieFileTransfer::where('bunny_status', 'done')
            ->select('movie_id', 'movie_title', 'bunny_size_bytes', 'bunny_source_used', 'bunny_transferred_at')
            ->orderByDesc('bunny_transferred_at')->limit(6)->get()
            ->map(function ($t) {
                try {
                    $at = $t->bunny_transferred_at
                        ? \Carbon\Carbon::parse($t->bunny_transferred_at)->diffForHumans()
                        : '';
                } catch (\Throwable) {
                    $at = '';
                }
                return [
                    'movie_id' => $t->movie_id,
                    'title' => mb_substr($t->movie_title ?? '', 0, 35),
                    'gb' => $t->bunny_size_bytes ? number_format($t->bunny_size_bytes / 1073741824, 2) : '?',
                    'via' => $t->bunny_source_used,
                    'at' => $at,
                ];
            });

        return response()->json(['stats' => $s, 'active' => $active, 'queued' => $queued, 'failed' => $failed, 'recent' => $recent]);
    }

    // ── Backfill page ─────────────────────────────────────────────────────

    /** Bulk-queue page: push large batches of eligible movies to Bunny. */
    public function backfill(Content $content): Content
    {
        $s = $this->bunnyStats();

        $topPending = self::eligibleQuery()
            ->addSelect('m.id', 'm.title', 't.dest_size_bytes')
            ->limit(15)->get();

        $rows = '';
        foreach ($topPending as $i => $m) {
            $n  = $i + 1;
            $gb = $m->dest_size_bytes ? number_format($m->dest_size_bytes / 1073741824, 2) . ' GB' : '?';
            $rows .= "<tr><td>{$n}</td><td><a href='/movies-new?id={$m->id}' target='_blank'>#{$m->id}</a></td>
                      <td>" . e(mb_substr($m->title, 0, 45)) . "</td><td>{$m->v30}</td><td>{$gb}</td></tr>";
        }

        $eligible = number_format($s['eligible']);
        $estAllGb = number_format($s['eligible'] * ($s['avg_gb'] ?: 1.0), 0);
        $estCost  = number_format($s['eligible'] * ($s['avg_gb'] ?: 1.0) * 0.01, 0);

        return $content
            ->title('Bunny Queue Backfill')
            ->description('Bulk-queue eligible Hetzner movies for Bunny upload')
            ->row(function (Row $row) use ($rows, $eligible, $estAllGb, $estCost, $s) {
                $row->column(7, function (Column $col) use ($rows, $eligible, $estAllGb, $estCost) {
                    $col->append("
                      <div class='box box-primary'>
                        <div class='box-header with-border'><h3 class='box-title'>Queue a batch</h3></div>
                        <div class='box-body'>
                          <p style='color:#666;'>Eligible (Hetzner-done, not yet on Bunny): <b>{$eligible}</b> movies
                             · full migration ≈ <b>{$estAllGb} GB</b> ≈ <b>\${$estCost}/month</b> storage.</p>
                          <form method='GET' action='/bunny-transfers/backfill-run' style='display:flex;gap:10px;align-items:center;flex-wrap:wrap;'
                                onsubmit=\"return confirm('Queue this batch to Bunny?');\">
                            <label>Queue top</label>
                            <input type='number' name='n' value='50' min='1' max='500' class='form-control' style='width:110px;display:inline-block;'>
                            <label>most-watched eligible movies</label>
                            <button type='submit' class='btn btn-primary'><i class='fa fa-cloud-upload'></i> Queue Batch</button>
                          </form>
                          <hr>
                          <p style='color:#999;font-size:12px;'>Batches are dispatched as background queue jobs (one upload at a time,
                          Hetzner → main URL → original source fallback per movie). Watch progress on the
                          <a href='/bunny-transfers/monitor'>Monitor</a>. Re-running is safe — movies already
                          queued, uploading, or done are never double-queued.</p>
                        </div>
                      </div>");
                });
                $row->column(5, function (Column $col) use ($rows) {
                    $col->append("
                      <div class='box box-default'>
                        <div class='box-header with-border'><h3 class='box-title'>Next up (top 15 by 30-day views)</h3></div>
                        <div class='box-body no-padding'>
                          <table class='table table-striped' style='font-size:12px;'>
                            <thead><tr><th>#</th><th>Movie</th><th>Title</th><th>Views 30d</th><th>Size</th></tr></thead>
                            <tbody>{$rows}</tbody>
                          </table>
                        </div>
                      </div>");
                });
            });
    }

    /** Execute a backfill batch (GET with n=…, from the backfill form). */
    public function backfillRun(Request $request)
    {
        $request->merge(['n' => $request->get('n', 50)]);
        return $this->queueTop($request);
    }

    // ── Shared helpers ────────────────────────────────────────────────────

    /**
     * THE eligibility rule, in one place (admin buttons, backfill, API and the
     * scheduled pump all use it): Hetzner-verified + Active + on-demand,
     * not yet on Bunny (or failed), most-demanded first.
     */
    public static function eligibleQuery()
    {
        return DB::table('movie_models as m')
            ->join('movie_file_transfers as t', fn ($j) => $j->on('t.movie_id', '=', 'm.id')
                ->where('t.status', 'done'))
            ->whereNotNull('t.dest_url')->where('t.dest_url', '!=', '')      // file IS on Hetzner
            ->where('m.status', 'Active')
            ->where(fn ($q) => $q->whereNull('t.bunny_status')->orWhere('t.bunny_status', 'failed'))
            ->selectRaw('t.id')
            ->selectRaw('(SELECT COUNT(*) FROM movie_views mv WHERE mv.movie_model_id = m.id
                          AND mv.created_at >= NOW() - INTERVAL 30 DAY) AS v30')
            ->having('v30', '>', 0)                                          // ON DEMAND: watched recently
            ->orderByDesc('v30')->orderByDesc('m.downloads_count');
    }

    private function bunnyStats(): array
    {
        $s = DB::table('movie_file_transfers')->selectRaw("
                SUM(bunny_status = 'done')      AS done,
                SUM(bunny_status = 'failed')    AS failed,
                SUM(bunny_status = 'uploading') AS uploading,
                SUM(bunny_status = 'pending')   AS queued,
                SUM(status = 'done' AND bunny_status IS NULL) AS eligible,
                COALESCE(SUM(CASE WHEN bunny_status='done' THEN bunny_size_bytes END),0) AS bytes,
                COALESCE(AVG(CASE WHEN bunny_status='done' THEN bunny_size_bytes END),0) AS avg_bytes,
                SUM(bunny_status='done' AND bunny_transferred_at >= NOW() - INTERVAL 24 HOUR) AS done_24h
            ")->first();

        $rate = (int) $s->done_24h;                       // movies/24h
        $eta  = ($rate > 0 && ($s->queued + $s->eligible) > 0)
            ? round(($s->queued + $s->eligible) / $rate, 1) . ' days (at 24h pace)'
            : '—';

        return [
            'done'     => (int) $s->done,
            'failed'   => (int) $s->failed,
            'uploading'=> (int) $s->uploading,
            'queued'   => (int) $s->queued,
            'eligible' => (int) $s->eligible,
            'gb'       => round($s->bytes / 1073741824, 1),
            'cost'     => number_format($s->bytes / 1073741824 * 0.01, 2),
            'avg_gb'   => round($s->avg_bytes / 1073741824, 2),
            'done_24h' => (int) $s->done_24h,
            'eta_all'  => $eta,
            'migrated_pct' => ($s->done + $s->eligible) > 0
                ? round($s->done / ($s->done + $s->eligible) * 100, 1) : 0,
        ];
    }

    /** Page skeleton: controls bar + stat boxes + panels. All data is filled/refreshed by monitorJs(). */
    private function monitorHtml(array $s): string
    {
        $controls = "
          <div style='display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px;
                      background:#fff;border:1px solid #e4e4e4;border-radius:3px;padding:10px 14px;'>
            <b style='color:#555;'><i class='fa fa-sliders'></i> Controls</b>
            <span style='border-left:1px solid #ddd;height:22px;'></span>
            <label style='margin:0;font-weight:400;'>Queue top</label>
            <input type='number' id='bn-topn' value='25' min='1' max='500' class='form-control input-sm' style='width:80px;display:inline-block;'>
            <button class='btn btn-sm btn-primary' onclick='bnQueueTop()'><i class='fa fa-cloud-upload'></i> Queue</button>
            <button class='btn btn-sm btn-warning' onclick='bnRetryAll()'><i class='fa fa-refresh'></i> Retry All Failed</button>
            <button class='btn btn-sm btn-info' onclick='bnVerifyAll()'><i class='fa fa-check-circle'></i> Verify All Done</button>
            <a href='/bunny-transfers' class='btn btn-sm btn-default'><i class='fa fa-list'></i> Grid</a>
            <a href='/bunny-transfers/backfill' class='btn btn-sm btn-default'><i class='fa fa-database'></i> Backfill</a>
            <span style='flex:1'></span>
            <button class='btn btn-sm btn-default' id='bn-pause' onclick='bnTogglePoll()'><i class='fa fa-pause'></i> Pause</button>
            <span id='bn-heartbeat' class='label label-success' style='font-size:11px;padding:6px 10px;'>● LIVE</span>
          </div>
          <div id='bn-flash'></div>";

        return $controls . $this->statBoxesHtml($s);
    }

    private function statBoxesHtml(array $s): string
    {
        $boxes = "
          <style>
            .bn-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
            .bn-box{flex:1;min-width:130px;background:#fff;border:1px solid #e4e4e4;border-left:4px solid var(--ac,#605ca8);
                    border-radius:3px;padding:12px 14px;position:relative;text-decoration:none;color:#333;}
            .bn-box .v{font-size:22px;font-weight:700;} .bn-box .l{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;}
            .bn-panel{background:#fff;border:1px solid #e4e4e4;border-radius:3px;margin-bottom:14px;}
            .bn-panel h4{margin:0;padding:10px 14px;border-bottom:1px solid #eee;font-size:13px;font-weight:700;color:#555;}
            .bn-panel .bd{padding:8px 14px;font-size:12px;}
            .bn-bar{background:#f4f4f4;border-radius:3px;height:18px;position:relative;margin:4px 0;}
            .bn-bar>div{background:#f39c12;height:18px;border-radius:3px;transition:width .8s;}
            .bn-bar span{position:absolute;inset:0;text-align:center;font-size:11px;line-height:18px;}
            .bn-mig{background:#eee;height:26px;border-radius:4px;position:relative;margin:2px 0 14px;}
            .bn-mig>div{background:linear-gradient(90deg,#605ca8,#9b59b6);height:26px;border-radius:4px;}
            .bn-mig span{position:absolute;inset:0;text-align:center;font-weight:700;line-height:26px;font-size:13px;color:#333;}
          </style>
          <div class='bn-row'>
            <a href='/bunny-transfers?bunny_status=pending' class='bn-box' style='--ac:#5b7fa6'><div class='v' id='bn-queued'>{$s['queued']}</div><div class='l'>Queued</div></a>
            <a href='/bunny-transfers?bunny_status=uploading' class='bn-box' style='--ac:#f39c12'><div class='v' id='bn-active'>{$s['uploading']}</div><div class='l'>Uploading</div></a>
            <a href='/bunny-transfers?bunny_status=done' class='bn-box' style='--ac:#27ae60'><div class='v' id='bn-done'>{$s['done']}</div><div class='l'>Done</div></a>
            <a href='/bunny-transfers?bunny_status=failed' class='bn-box' style='--ac:#c0392b'><div class='v' id='bn-failed'>{$s['failed']}</div><div class='l'>Failed</div></a>
            <div class='bn-box' style='--ac:#605ca8'><div class='v' id='bn-gb'>{$s['gb']} GB</div><div class='l'>On Bunny (\${$s['cost']}/mo)</div></div>
            <div class='bn-box' style='--ac:#16a085'><div class='v' id='bn-24h'>{$s['done_24h']}</div><div class='l'>Done last 24h</div></div>
            <div class='bn-box' style='--ac:#d35400'><div class='v' id='bn-eta'>{$s['eta_all']}</div><div class='l'>ETA full migration</div></div>
          </div>
          <div class='bn-mig'><div id='bn-mig-bar' style='width:{$s['migrated_pct']}%;'></div><span id='bn-mig-label'>{$s['migrated_pct']}% of eligible catalog migrated</span></div>

          <div style='display:flex;gap:14px;flex-wrap:wrap;'>
            <div class='bn-panel' style='flex:2;min-width:340px;'><h4>⇡ Active uploads</h4><div class='bd' id='bn-active-list'>—</div></div>
            <div class='bn-panel' style='flex:1;min-width:240px;'><h4>⏳ Next in queue</h4><div class='bd' id='bn-queue-list'>—</div></div>
          </div>
          <div style='display:flex;gap:14px;flex-wrap:wrap;'>
            <div class='bn-panel' style='flex:1;min-width:300px;'><h4>✗ Recent failures</h4><div class='bd' id='bn-failed-list'>—</div></div>
            <div class='bn-panel' style='flex:1;min-width:300px;'><h4>✓ Recently completed</h4><div class='bd' id='bn-recent-list'>—</div></div>
          </div>

          ";

        return $boxes;
    }

    /**
     * Monitor JS — registered through Admin::script() so it survives pjax.
     * Polls live-data every 3s with heartbeat, computes per-upload speed from
     * progress deltas, and drives the AJAX control buttons.
     */
    private function monitorJs(): string
    {
        return <<<'JS'
(function(){
  if (window.__bnMonitorActive) return;   // guard against double-init on pjax
  window.__bnMonitorActive = true;
  var paused = false, prev = {}, timer = null;

  function esc(t){ var d=document.createElement('div'); d.innerText=t||''; return d.innerHTML; }
  function el(id){ return document.getElementById(id); }
  function flash(msg, cls){
    var f = el('bn-flash'); if (!f) return;
    f.innerHTML = "<div class='alert alert-"+(cls||'success')+"' style='padding:8px 14px;margin-bottom:12px;'>"+esc(msg)+"</div>";
    setTimeout(function(){ if (f) f.innerHTML=''; }, 6000);
  }
  function heartbeat(ok){
    var h = el('bn-heartbeat'); if (!h) return;
    if (paused) { h.className='label label-default'; h.innerText='⏸ PAUSED'; return; }
    h.className = 'label label-' + (ok ? 'success' : 'danger');
    h.innerText = ok ? ('● LIVE · ' + new Date().toLocaleTimeString()) : '● CONNECTION LOST — retrying';
  }

  window.bnTogglePoll = function(){
    paused = !paused;
    var b = el('bn-pause');
    if (b) b.innerHTML = paused ? "<i class='fa fa-play'></i> Resume" : "<i class='fa fa-pause'></i> Pause";
    heartbeat(true);
    if (!paused) poll();
  };

  function act(url, confirmMsg){
    if (confirmMsg && !confirm(confirmMsg)) return;
    fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(d){ flash(d.message || 'Done.', d.code === 0 ? 'danger' : 'success'); poll(); })
      .catch(function(){ flash('Request failed — check connection.', 'danger'); });
  }
  window.bnQueueTop  = function(){ var n = (el('bn-topn')||{}).value || 25; act('/bunny-transfers/queue-top?n='+n, 'Queue top '+n+' most-watched movies to Bunny?'); };
  window.bnRetryAll  = function(){ act('/bunny-transfers/retry-all-failed', 'Re-queue every failed Bunny transfer?'); };
  window.bnVerifyAll = function(){ act('/bunny-transfers/verify-all', 'Re-verify every done transfer against Bunny? (may take a while)'); };
  window.bnRetryOne  = function(id){ act('/bunny-transfers/retry/'+id+'?async=1', 'Retry this transfer?'); };

  function speedOf(a){
    var p = prev[a.id], now = Date.now();
    prev[a.id] = {pct: a.pct, t: now, gb: a.gb};
    if (!p || a.pct <= p.pct || !a.gb) return '';
    var mb = (a.pct - p.pct) / 100 * a.gb * 1024;
    var sec = (now - p.t) / 1000;
    if (sec <= 0) return '';
    return ' · ' + (mb / sec).toFixed(1) + ' MB/s';
  }

  function poll(){
    if (paused || document.hidden) return;
    fetch('/bunny-transfers/live-data', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
      .then(function(r){ if (!r.ok) throw 0; return r.json(); })
      .then(function(d){
        heartbeat(true);
        var s = d.stats;
        if (el('bn-queued')) el('bn-queued').innerText = s.queued;
        if (el('bn-active')) el('bn-active').innerText = s.uploading;
        if (el('bn-done'))   el('bn-done').innerText   = s.done;
        if (el('bn-failed')) el('bn-failed').innerText = s.failed;
        if (el('bn-gb'))     el('bn-gb').innerText     = s.gb + ' GB';
        if (el('bn-24h'))    el('bn-24h').innerText    = s.done_24h;
        if (el('bn-eta'))    el('bn-eta').innerText    = s.eta_all;
        if (el('bn-mig-bar'))   el('bn-mig-bar').style.width = s.migrated_pct + '%';
        if (el('bn-mig-label')) el('bn-mig-label').innerText = s.migrated_pct + '% of eligible catalog migrated';

        if (el('bn-active-list')) el('bn-active-list').innerHTML = d.active.length
          ? d.active.map(function(a){
              return "<div style='margin-bottom:8px;'><b>#"+a.movie_id+"</b> "+esc(a.title)
                +(a.gb ? " <span style='color:#888;'>("+a.gb+" GB)</span>" : '')
                +(a.via ? " <span class='label label-info' style='font-size:9px;'>"+a.via+"</span>" : '')
                +(a.stale ? " <span class='label label-danger' style='font-size:9px;'>stalled?</span>" : '')
                +"<div class='bn-bar'><div style='width:"+a.pct+"%'></div><span>"+a.pct+"%"+speedOf(a)+"</span></div></div>";
            }).join('')
          : "<span style='color:#999;'>No active uploads.</span>";

        if (el('bn-queue-list')) el('bn-queue-list').innerHTML = d.queued.length
          ? d.queued.map(function(q){ return "<div>• <b>#"+q.movie_id+"</b> "+esc(q.title)+"</div>"; }).join('')
          : "<span style='color:#999;'>Queue is empty.</span>";

        if (el('bn-failed-list')) el('bn-failed-list').innerHTML = d.failed.length
          ? d.failed.map(function(f){
              return "<div style='margin-bottom:6px;'><b>#"+f.movie_id+"</b> "+esc(f.title)
                +" <button class='btn btn-xs btn-warning' style='padding:0 6px;' onclick='bnRetryOne("+f.id+")'>retry</button>"
                +"<br><span style='color:#c0392b;font-size:11px;'>"+esc(f.error)+"</span></div>";
            }).join('')
          : "<span style='color:#999;'>No failures 🎉</span>";

        if (el('bn-recent-list')) el('bn-recent-list').innerHTML = d.recent.length
          ? d.recent.map(function(r){
              return "<div>✓ <b>#"+r.movie_id+"</b> "+esc(r.title)+" — "+r.gb+" GB"
                +(r.via ? ' via '+r.via : '')+" <span style='color:#999;'>"+esc(r.at||'')+"</span></div>";
            }).join('')
          : "<span style='color:#999;'>Nothing completed yet.</span>";
      })
      .catch(function(){ heartbeat(false); });
  }

  poll();
  timer = setInterval(poll, 3000);
  document.addEventListener('pjax:start', function(){ clearInterval(timer); window.__bnMonitorActive = false; }, {once:true});
})();
JS;
    }

    /** Re-verify every done row (marks missing ones failed). */
    public function verifyAll(Request $request)
    {
        $bunny = app(BunnyTransferService::class);
        $ok = 0; $bad = 0;
        foreach (MovieFileTransfer::where('bunny_status', 'done')->get() as $t) {
            $size = $t->bunny_storage_path ? $bunny->remoteSize($t->bunny_storage_path) : null;
            if ($size !== null && $size >= 1024 * 100) {
                $ok++;
            } else {
                $bad++;
                $t->bunny_status = 'failed';
                $t->bunny_error  = 'Verify-all: file missing on Bunny.';
                $t->save();
            }
        }
        \Illuminate\Support\Facades\Cache::forget('bunny_url_map');

        $msg = "Verified: {$ok} intact" . ($bad ? ", {$bad} missing (marked failed)" : '') . '.';
        if ($request->ajax()) {
            return response()->json(['code' => $bad ? 0 : 1, 'message' => $msg]);
        }
        admin_toastr($msg, $bad ? 'warning' : 'success');
        return redirect('/bunny-transfers');
    }
}
