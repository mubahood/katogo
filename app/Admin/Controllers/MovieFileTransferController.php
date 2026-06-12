<?php

namespace App\Admin\Controllers;

use App\Jobs\TransferMovieToHetzner;
use App\Models\MovieFileTransfer;
use App\Models\MovieModel;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Layout\Column;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class MovieFileTransferController extends AdminController
{
    protected $title = 'Movie File Transfers';

    // ── Grid (list) ───────────────────────────────────────────────────────────

    protected function grid(): Grid
    {
        $grid = new Grid(new MovieFileTransfer());
        $grid->model()->orderBy('priority', 'desc')->orderBy('id', 'desc');
        $grid->disableBatchActions();
        $grid->disableCreateButton();
        $grid->paginate(30);

        // ── Toolbar ───────────────────────────────────────────────────────────
        $grid->tools(function ($tools) {
            $tools->append('
                <div class="btn-group" style="margin-right:4px;">
                    <a href="/movie-file-transfers/process-now" class="btn btn-sm btn-primary"
                       title="Run transfers:process right now — dispatches up to 6 queued jobs">
                        <i class="fa fa-play-circle"></i> Process Now
                    </a>
                </div>
                <div class="btn-group" style="margin-right:4px;">
                    <a href="/movie-file-transfers/retry-all-failed" class="btn btn-sm btn-warning"
                       onclick="return confirm(\'Re-queue all failed transfers?\')"
                       title="Reset every failed record back to queued">
                        <i class="fa fa-refresh"></i> Retry All Failed
                    </a>
                </div>
                <div class="btn-group" style="margin-right:4px;">
                    <a href="/movie-file-transfers/monitor" class="btn btn-sm btn-info"
                       title="Real-time pipeline monitor with auto-refresh">
                        <i class="fa fa-heartbeat"></i> Monitor
                    </a>
                </div>
                <div class="btn-group">
                    <a href="/movie-file-transfers/backfill" class="btn btn-sm btn-default"
                       title="Queue all un-transferred active movies">
                        <i class="fa fa-database"></i> Backfill
                    </a>
                </div>
            ');
        });

        // ── Filters ───────────────────────────────────────────────────────────
        $grid->filter(function ($f) {
            $f->disableIdFilter();
            $f->equal('status', 'Status')->select([
                MovieFileTransfer::STATUS_QUEUED       => 'Queued',
                MovieFileTransfer::STATUS_VERIFYING    => 'Verifying',
                MovieFileTransfer::STATUS_TRANSFERRING => 'Transferring',
                MovieFileTransfer::STATUS_COMPLETING   => 'Completing',
                MovieFileTransfer::STATUS_DONE         => 'Done',
                MovieFileTransfer::STATUS_FAILED       => 'Failed',
                MovieFileTransfer::STATUS_CANCELLED    => 'Cancelled',
                MovieFileTransfer::STATUS_SKIPPED      => 'Skipped',
            ]);
            $f->equal('source_type', 'Source')->select([
                'munowatch' => 'MunoWatch',
                'gdrive'    => 'Google Drive',
                'firebase'  => 'Firebase',
                'hetzner'   => 'Hetzner',
                'other'     => 'Other',
            ]);
            $f->like('movie_title', 'Movie Title');
            $f->equal('movie_id',   'Movie ID');
            $f->between('created_at', 'Queued On')->datetime();
        });

        // ── Columns ───────────────────────────────────────────────────────────

        $grid->column('id', 'ID')->sortable()->width(55);

        $grid->column('movie_title', 'Movie')->display(function () {
            $poster = $this->movie_poster_url
                ? "<img src='" . e($this->movie_poster_url) . "' style='width:30px;height:45px;object-fit:cover;border-radius:3px;margin-right:6px;vertical-align:middle;'>"
                : "<i class='fa fa-film' style='margin-right:6px;color:#aaa;'></i>";
            $title  = e($this->movie_title ?? 'Untitled');
            $year   = $this->movie_year ? " <small class='text-muted'>({$this->movie_year})</small>" : '';
            $mid    = $this->movie_id   ? " <small class='label label-default'>#{$this->movie_id}</small>" : '';
            return "{$poster}<strong>{$title}</strong>{$year}{$mid}";
        })->width(250);

        $grid->column('priority', 'Priority')->display(function ($v) {
            if ($v >= 999000) return "<span class='label label-danger'><i class='fa fa-fire'></i> MAX</span>";
            if ($v >= 10000)  return "<span class='label label-danger'><i class='fa fa-fire'></i> " . number_format($v) . "</span>";
            if ($v >= 100)    return "<span class='label label-warning'>" . number_format($v) . "</span>";
            if ($v > 0)       return "<span class='label label-default'>" . number_format($v) . "</span>";
            return "<span class='text-muted'>0</span>";
        })->sortable()->width(85);

        $grid->column('source_type', 'Src')->display(function ($v) {
            $map = [
                'munowatch' => ['info',    'MW'],
                'gdrive'    => ['warning', 'GD'],
                'firebase'  => ['primary', 'FB'],
                'hetzner'   => ['success', 'HZ'],
            ];
            [$color, $lbl] = $map[$v] ?? ['default', strtoupper(substr($v ?? '?', 0, 3))];
            return "<span class='label label-{$color}'>{$lbl}</span>";
        })->width(55);

        $grid->column('status', 'Status & Progress')->display(function () {
            $color = $this->status_badge_color;
            $lbl   = $this->status_label;
            $icon  = match ($this->status) {
                'queued'       => 'fa-clock-o',
                'verifying'    => 'fa-search',
                'transferring' => 'fa-exchange',
                'completing'   => 'fa-check-square-o',
                'done'         => 'fa-check-circle',
                'failed'       => 'fa-times-circle',
                'cancelled'    => 'fa-ban',
                'skipped'      => 'fa-forward',
                default        => 'fa-circle',
            };

            if ($this->isActive()) {
                $pct      = $this->progress_pct ?? 0;
                $barColor = $this->status === 'completing' ? '#f39c12' : '#00a65a';
                return "
                    <span class='label label-{$color}'><i class='fa {$icon}'></i> {$lbl}</span>
                    <div style='position:relative;margin-top:5px;background:#ddd;border-radius:4px;height:16px;overflow:hidden;'>
                      <div style='width:" . max(3, $pct) . "%;background:{$barColor};height:16px;border-radius:4px;
                                  background-image:repeating-linear-gradient(45deg,rgba(255,255,255,0) 0,rgba(255,255,255,0) 10px,rgba(255,255,255,.18) 10px,rgba(255,255,255,.18) 20px);
                                  background-size:20px 20px;'></div>
                      <span style='position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-size:10px;font-weight:bold;color:#222;'>{$pct}%</span>
                    </div>
                    <small class='text-muted' style='font-size:10px;'>{$this->formatted_speed} &bull; ETA {$this->formatted_eta}</small>
                ";
            }
            if ($this->isFailed()) {
                $err = e(substr($this->error_message ?? '', 0, 55));
                return "<span class='label label-{$color}'><i class='fa {$icon}'></i> {$lbl}</span>
                        <br><small class='text-danger' style='font-size:10px;'><i class='fa fa-warning'></i> {$err}</small>";
            }
            if ($this->isDone()) {
                $extra = $this->formatted_speed !== '—'
                    ? "<br><small class='text-muted' style='font-size:10px;'>{$this->formatted_speed} &bull; {$this->formatted_duration}</small>"
                    : '';
                return "<span class='label label-{$color}'><i class='fa {$icon}'></i> {$lbl}</span>{$extra}";
            }
            return "<span class='label label-{$color}'><i class='fa {$icon}'></i> {$lbl}</span>";
        })->width(200);

        $grid->column('formatted_size', 'Size')->width(70);

        $grid->column('attempt_count', 'Tries')->display(function () {
            $max = $this->max_attempts ?? 3;
            $cnt = $this->attempt_count ?? 0;
            $c   = $cnt >= $max ? 'danger' : ($cnt > 0 ? 'warning' : 'default');
            return "<span class='label label-{$c}'>{$cnt}/{$max}</span>";
        })->width(55);

        $grid->column('queued_at', 'Queued')->display(function ($v) {
            return $v ? \Carbon\Carbon::parse($v)->diffForHumans() : '—';
        })->sortable()->width(95);

        $grid->column('_actions', 'Actions')->display(function () {
            $id   = $this->id;
            $btns = [];

            if ($this->isDone() && $this->dest_url) {
                $btns[] = "<a href='" . e($this->dest_url) . "' target='_blank'
                              class='btn btn-xs btn-success' title='View on Hetzner CDN'>
                              <i class='fa fa-cloud'></i>
                           </a>";
            }
            if ($this->isRetriable() || $this->isFailed()) {
                $btns[] = "<a href='/movie-file-transfers/{$id}/retry'
                              class='btn btn-xs btn-warning' title='Retry this transfer'
                              onclick=\"return confirm('Retry transfer #{$id}?')\">
                              <i class='fa fa-refresh'></i>
                           </a>";
            }
            if (!$this->isDone() && !in_array($this->status, [
                MovieFileTransfer::STATUS_FAILED,
                MovieFileTransfer::STATUS_CANCELLED,
                MovieFileTransfer::STATUS_SKIPPED,
            ])) {
                $btns[] = "<a href='/movie-file-transfers/{$id}/cancel'
                              class='btn btn-xs btn-danger' title='Cancel this transfer'
                              onclick=\"return confirm('Cancel transfer #{$id}?')\">
                              <i class='fa fa-times'></i>
                           </a>";
            }
            if ($this->movie_id) {
                $btns[] = "<a href='/movies-active/{$this->movie_id}/edit'
                              class='btn btn-xs btn-default' title='Edit movie record'>
                              <i class='fa fa-pencil'></i>
                           </a>";
            }
            $btns[] = "<a href='/movie-file-transfers/{$id}'
                          class='btn btn-xs btn-info' title='Full details'>
                          <i class='fa fa-eye'></i>
                       </a>";

            return implode(' ', $btns);
        })->width(145);

        return $grid;
    }

    // ── Index with stats header + live cards ─────────────────────────────────

    public function index(Content $content): Content
    {
        $stats   = $this->getStats();
        $actives = MovieFileTransfer::active()->orderBy('started_at')->get();

        return $content
            ->title('Movie File Transfers')
            ->description('Automated pipeline — source URLs → Hetzner Storage CDN')

            // ── Stat boxes ────────────────────────────────────────────────────
            ->row(function (Row $row) use ($stats) {
                $row->column(2, new InfoBox('Queued',    'hourglass-half', 'aqua',   '/movie-file-transfers?status=queued',       number_format($stats['queued'])));
                $row->column(2, new InfoBox('Active',    'exchange',       'blue',   '/movie-file-transfers?status=transferring', $stats['active'] . '/' . TransferMovieToHetzner::MAX_CONCURRENT));
                $row->column(2, new InfoBox('Done',      'check-circle',   'green',  '/movie-file-transfers?status=done',         number_format($stats['done'])));
                $row->column(2, new InfoBox('Failed',    'times-circle',   'red',    '/movie-file-transfers?status=failed',       number_format($stats['failed'])));
                $row->column(2, new InfoBox('Avg Speed', 'tachometer',     'yellow', '#',                                         $stats['avg_speed']));
                $row->column(2, new InfoBox('Stored',    'hdd-o',          'teal',   '#',                                         $stats['stored_gb'] . ' GB'));
            })

            // ── Alerts + Pipeline Controls ────────────────────────────────────
            ->row(function (Row $row) use ($stats, $actives) {
                $row->column(8, function (Column $col) use ($stats, $actives) {
                    $html = '';

                    if ($actives->count() > 0) {
                        $n    = $actives->count();
                        $html .= "<div class='alert alert-info' style='margin-bottom:8px;padding:8px 14px;'>
                            <i class='fa fa-spinner fa-spin'></i>
                            <strong>{$n} transfer" . ($n > 1 ? 's' : '') . " running.</strong>
                            Page auto-refreshes every 8 seconds.
                            <span id='refresh-countdown' class='pull-right badge bg-light-blue'>8</span>
                        </div>";
                    }

                    if ($stats['not_queued'] > 0) {
                        $html .= "<div class='alert alert-warning' style='margin-bottom:8px;'>
                            <i class='fa fa-exclamation-triangle'></i>
                            <strong>" . number_format($stats['not_queued']) . "</strong> active movies still have external video URLs and are not in the transfer queue.
                            <a href='/movie-file-transfers/backfill' class='btn btn-xs btn-warning' style='margin-left:8px;'>
                                <i class='fa fa-database'></i> Run Backfill
                            </a>
                        </div>";
                    }
                    if ($stats['failed'] > 0) {
                        $html .= "<div class='alert alert-danger' style='margin-bottom:8px;'>
                            <i class='fa fa-times-circle'></i>
                            <strong>" . number_format($stats['failed']) . "</strong> transfer(s) failed.
                            <a href='/movie-file-transfers?status=failed' class='btn btn-xs btn-danger' style='margin-left:8px;'>
                                <i class='fa fa-eye'></i> View Failed
                            </a>
                            <a href='/movie-file-transfers/retry-all-failed' class='btn btn-xs btn-warning' style='margin-left:4px;'
                               onclick=\"return confirm('Retry all " . $stats['failed'] . " failed transfers?')\">
                                <i class='fa fa-refresh'></i> Retry All
                            </a>
                        </div>";
                    }
                    if ($stats['done'] > 0 && $stats['not_queued'] === 0 && $stats['failed'] === 0 && $actives->isEmpty()) {
                        $html .= "<div class='alert alert-success' style='margin-bottom:8px;'>
                            <i class='fa fa-check-circle'></i>
                            Pipeline is clear. <strong>" . number_format($stats['done']) . "</strong> movies successfully on Hetzner CDN.
                        </div>";
                    }

                    if ($html) $col->append($html);
                });

                $row->column(4, function (Column $col) use ($stats) {
                    $fc = number_format($stats['failed']);
                    $nq = number_format($stats['not_queued']);
                    $col->append("
                        <div class='box box-solid box-primary' style='margin-bottom:0;'>
                            <div class='box-header with-border' style='padding:8px 12px;'>
                                <h3 class='box-title' style='font-size:13px;'>
                                    <i class='fa fa-bolt'></i> Pipeline Controls
                                </h3>
                            </div>
                            <div class='box-body' style='padding:8px;'>
                                <a href='/movie-file-transfers/process-now'
                                   class='btn btn-block btn-primary btn-sm' style='margin-bottom:5px;'>
                                    <i class='fa fa-play-circle'></i> Dispatch Queued Transfers
                                </a>
                                <a href='/movie-file-transfers/retry-all-failed'
                                   class='btn btn-block btn-warning btn-sm' style='margin-bottom:5px;'
                                   onclick=\"return confirm('Retry all {$fc} failed transfers?')\">
                                    <i class='fa fa-refresh'></i> Retry All Failed ({$fc})
                                </a>
                                <a href='/movie-file-transfers/backfill'
                                   class='btn btn-block btn-default btn-sm' style='margin-bottom:5px;'>
                                    <i class='fa fa-database'></i> Queue Backfill ({$nq} pending)
                                </a>
                                <a href='/movie-file-transfers/monitor'
                                   class='btn btn-block btn-info btn-sm'>
                                    <i class='fa fa-heartbeat'></i> Open Live Monitor
                                </a>
                            </div>
                        </div>
                    ");
                });
            })

            // ── Live active transfer cards (only when transfers are running) ──
            ->row(function (Row $row) use ($actives) {
                if ($actives->isEmpty()) return;

                $row->column(12, function (Column $col) use ($actives) {
                    $html = "<div class='box box-success'>
                        <div class='box-header with-border' style='padding:8px 15px;'>
                            <h3 class='box-title'>
                                <i class='fa fa-spinner fa-spin'></i> Active Transfers — Live Progress
                            </h3>
                            <div class='box-tools pull-right'>
                                <span class='label label-success'>
                                    <i class='fa fa-circle' style='font-size:8px;'></i> LIVE
                                </span>
                            </div>
                        </div>
                        <div class='box-body' style='padding:10px 15px;'>";

                    foreach ($actives as $t) {
                        $pct      = $t->progress_pct ?? 0;
                        $speed    = $t->formatted_speed;
                        $eta      = $t->formatted_eta;
                        $size     = $t->formatted_size;
                        $elapsed  = $t->started_at ? $t->started_at->diffForHumans() : '—';
                        $worker   = e($t->worker_hostname ?? 'unknown');
                        $barColor = $t->status === MovieFileTransfer::STATUS_COMPLETING ? '#f39c12' : '#00a65a';
                        $badge    = strtoupper($t->status_label ?? '');

                        $bytesHuman = '—';
                        if (($t->bytes_transferred ?? 0) > 0) {
                            $mb = $t->bytes_transferred / 1_048_576;
                            $bytesHuman = $mb >= 1024
                                ? round($mb / 1024, 1) . ' GB'
                                : round($mb, 0) . ' MB';
                        }

                        $html .= "
                            <div style='border:1px solid #c8e6c8;border-radius:8px;padding:14px 16px;
                                        margin-bottom:12px;background:linear-gradient(135deg,#f6fff6,#edfaed);'>
                                <!-- Header row -->
                                <div style='display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;'>
                                    <div>
                                        <strong style='font-size:14px;'>
                                            <i class='fa fa-film'></i>
                                            #" . $t->id . " &mdash; " . e($t->movie_title ?? 'Untitled') . "
                                        </strong>
                                        &nbsp;<span class='label label-default'>movie #" . $t->movie_id . "</span>
                                        &nbsp;<span class='label label-" . $t->status_badge_color . "'>{$badge}</span>
                                    </div>
                                    <div style='display:flex;gap:6px;align-items:center;flex-shrink:0;'>
                                        <small class='text-muted'>
                                            <i class='fa fa-clock-o'></i> Started " . $elapsed . "
                                        </small>
                                        <a href='/movie-file-transfers/" . $t->id . "'
                                           class='btn btn-xs btn-info' title='View full details'>
                                            <i class='fa fa-eye'></i> Details
                                        </a>
                                        <a href='/movie-file-transfers/" . $t->id . "/cancel'
                                           class='btn btn-xs btn-danger' title='Stop this transfer'
                                           onclick=\"return confirm('Cancel transfer #" . $t->id . "?')\">
                                            <i class='fa fa-stop-circle'></i> Stop
                                        </a>
                                    </div>
                                </div>

                                <!-- Progress bar -->
                                <div style='position:relative;background:#ccc;border-radius:6px;height:26px;
                                            overflow:hidden;margin-bottom:10px;'>
                                    <div style='width:" . max(2, $pct) . "%;background:{$barColor};height:26px;
                                                border-radius:6px;transition:width 0.3s ease;
                                                background-image:repeating-linear-gradient(
                                                    45deg,rgba(255,255,255,0) 0,rgba(255,255,255,0) 10px,
                                                    rgba(255,255,255,.18) 10px,rgba(255,255,255,.18) 20px);'>
                                    </div>
                                    <span style='position:absolute;left:50%;top:50%;
                                                 transform:translate(-50%,-50%);font-size:13px;
                                                 font-weight:bold;color:#1a1a1a;
                                                 text-shadow:0 0 4px rgba(255,255,255,0.9);'>
                                        {$pct}%
                                    </span>
                                </div>

                                <!-- Stats row -->
                                <div style='display:flex;flex-wrap:wrap;gap:18px;font-size:12px;color:#444;'>
                                    <span title='Current speed'>
                                        <i class='fa fa-tachometer text-blue'></i>&nbsp;
                                        <strong>{$speed}</strong>
                                    </span>
                                    <span title='Bytes downloaded so far'>
                                        <i class='fa fa-download text-green'></i>&nbsp;
                                        {$bytesHuman} of {$size}
                                    </span>
                                    <span title='Estimated completion time'>
                                        <i class='fa fa-clock-o text-orange'></i>&nbsp;
                                        ETA: <strong>{$eta}</strong>
                                    </span>
                                    <span title='Worker server'>
                                        <i class='fa fa-server text-purple'></i>&nbsp;
                                        {$worker}
                                    </span>
                                    <span title='Attempt number'>
                                        <i class='fa fa-repeat text-muted'></i>&nbsp;
                                        Attempt " . $t->attempt_count . "/" . $t->max_attempts . "
                                    </span>
                                </div>
                            </div>
                        ";
                    }

                    $html .= "</div></div>";
                    $col->append($html);

                    // Auto-refresh countdown
                    $col->append("
                        <script>
                        (function(){
                            var n = 8, el = document.getElementById('refresh-countdown');
                            setInterval(function(){
                                n--;
                                if (el) el.textContent = n;
                                if (n <= 0) location.reload();
                            }, 1000);
                        })();
                        </script>
                    ");
                });
            })

            ->body($this->grid());
    }

    // ── Monitor page (real-time dashboard) ───────────────────────────────────

    public function monitor(Content $content): Content
    {
        $stats   = $this->getStats();
        $actives = MovieFileTransfer::active()->orderBy('started_at')->get();
        $failed  = MovieFileTransfer::failed()->orderByDesc('updated_at')->limit(20)->get();
        $recent  = MovieFileTransfer::done()->orderByDesc('completed_at')->limit(8)->get();
        $nextQ   = MovieFileTransfer::pending()
                       ->orderByDesc('priority')->orderBy('queued_at')
                       ->limit(10)->get();

        return $content
            ->title('Transfer Monitor')
            ->description('Real-time pipeline dashboard — auto-refreshes every 5 seconds when transfers are active')

            // ── Stat boxes ────────────────────────────────────────────────────
            ->row(function (Row $row) use ($stats) {
                $row->column(2, new InfoBox('Queued',    'hourglass-half', 'aqua',   '/movie-file-transfers?status=queued',       number_format($stats['queued'])));
                $row->column(2, new InfoBox('Active',    'exchange',       'blue',   '/movie-file-transfers?status=transferring', $stats['active'] . '/' . TransferMovieToHetzner::MAX_CONCURRENT));
                $row->column(2, new InfoBox('Done',      'check-circle',   'green',  '/movie-file-transfers?status=done',         number_format($stats['done'])));
                $row->column(2, new InfoBox('Failed',    'times-circle',   'red',    '/movie-file-transfers?status=failed',       number_format($stats['failed'])));
                $row->column(2, new InfoBox('Avg Speed', 'tachometer',     'yellow', '#',                                         $stats['avg_speed']));
                $row->column(2, new InfoBox('Stored',    'hdd-o',          'teal',   '#',                                         $stats['stored_gb'] . ' GB'));
            })

            // ── Toolbar + auto-refresh notice ─────────────────────────────────
            ->row(function (Row $row) use ($actives) {
                $row->column(12, function (Column $col) use ($actives) {
                    $statusBar = $actives->count() > 0
                        ? "<div class='alert alert-info' style='margin-bottom:10px;padding:8px 14px;'>
                                <i class='fa fa-spinner fa-spin'></i>
                                <strong>" . $actives->count() . " transfer(s) in progress.</strong>
                                Auto-refreshing every 5 seconds.
                                <span id='monitor-countdown' class='pull-right badge bg-light-blue'>5</span>
                           </div>"
                        : "<div class='alert alert-default' style='margin-bottom:10px;padding:8px 14px;border:1px solid #ccc;'>
                                <i class='fa fa-pause-circle'></i>
                                Workers are idle — no active transfers.
                                <a href='/movie-file-transfers/process-now' class='btn btn-xs btn-primary' style='margin-left:8px;'>
                                    <i class='fa fa-play-circle'></i> Dispatch Now
                                </a>
                           </div>";

                    $col->append($statusBar . "
                        <div style='margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;'>
                            <a href='/movie-file-transfers/process-now' class='btn btn-sm btn-primary'>
                                <i class='fa fa-play-circle'></i> Process Queue Now
                            </a>
                            <a href='/movie-file-transfers/retry-all-failed' class='btn btn-sm btn-warning'
                               onclick=\"return confirm('Retry all failed transfers?')\">
                                <i class='fa fa-refresh'></i> Retry All Failed
                            </a>
                            <a href='/movie-file-transfers/backfill' class='btn btn-sm btn-default'>
                                <i class='fa fa-database'></i> Queue Backfill
                            </a>
                            <a href='/movie-file-transfers' class='btn btn-sm btn-default'>
                                <i class='fa fa-list'></i> All Transfers
                            </a>
                        </div>
                    ");

                    if ($actives->count() > 0) {
                        $col->append("<script>
                            (function(){
                                var n = 5, el = document.getElementById('monitor-countdown');
                                setInterval(function(){
                                    n--;
                                    if (el) el.textContent = n;
                                    if (n <= 0) location.reload();
                                }, 1000);
                            })();
                        </script>");
                    }
                });
            })

            // ── Active transfer cards ─────────────────────────────────────────
            ->row(function (Row $row) use ($actives) {
                $row->column(12, function (Column $col) use ($actives) {
                    if ($actives->isEmpty()) {
                        $col->append("<div class='box box-default'>
                            <div class='box-body' style='padding:14px;'>
                                <span class='text-muted'><i class='fa fa-pause'></i> No active transfers right now.</span>
                            </div>
                        </div>");
                        return;
                    }

                    $html = "<div class='box box-success'>
                        <div class='box-header with-border' style='padding:8px 15px;'>
                            <h3 class='box-title'>
                                <i class='fa fa-spinner fa-spin'></i> Active Transfers
                            </h3>
                            <div class='box-tools pull-right'>
                                <span class='label label-success'><i class='fa fa-circle' style='font-size:8px;'></i> LIVE</span>
                            </div>
                        </div>
                        <div class='box-body' style='padding:12px 15px;'>";

                    foreach ($actives as $t) {
                        $pct      = $t->progress_pct ?? 0;
                        $speed    = $t->formatted_speed;
                        $eta      = $t->formatted_eta;
                        $size     = $t->formatted_size;
                        $elapsed  = $t->started_at ? $t->started_at->diffForHumans() : '—';
                        $worker   = e($t->worker_hostname ?? 'unknown');
                        $barColor = $t->status === MovieFileTransfer::STATUS_COMPLETING ? '#f39c12' : '#00a65a';
                        $badge    = strtoupper($t->status_label ?? '');

                        $bytesHuman = '—';
                        if (($t->bytes_transferred ?? 0) > 0) {
                            $mb = $t->bytes_transferred / 1_048_576;
                            $bytesHuman = $mb >= 1024
                                ? round($mb / 1024, 1) . ' GB'
                                : round($mb, 0) . ' MB';
                        }

                        $html .= "
                            <div style='border:1px solid #c8e6c8;border-radius:8px;padding:14px 16px;
                                        margin-bottom:12px;background:linear-gradient(135deg,#f6fff6,#edfaed);'>
                                <div style='display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;'>
                                    <div>
                                        <strong style='font-size:15px;'>
                                            <i class='fa fa-film'></i>
                                            #" . $t->id . " &mdash; " . e($t->movie_title ?? 'Untitled') . "
                                        </strong>
                                        &nbsp;<span class='label label-default'>movie #" . $t->movie_id . "</span>
                                        &nbsp;<span class='label label-" . $t->status_badge_color . "'>{$badge}</span>
                                    </div>
                                    <div style='display:flex;gap:6px;align-items:center;'>
                                        <small class='text-muted'><i class='fa fa-clock-o'></i> {$elapsed}</small>
                                        <a href='/movie-file-transfers/" . $t->id . "' class='btn btn-xs btn-info'>
                                            <i class='fa fa-eye'></i> Details
                                        </a>
                                        <a href='/movie-file-transfers/" . $t->id . "/cancel'
                                           class='btn btn-xs btn-danger'
                                           onclick=\"return confirm('Cancel transfer #" . $t->id . "?')\">
                                            <i class='fa fa-stop-circle'></i> Cancel
                                        </a>
                                    </div>
                                </div>

                                <div style='position:relative;background:#ccc;border-radius:6px;height:28px;
                                            overflow:hidden;margin-bottom:10px;'>
                                    <div style='width:" . max(2, $pct) . "%;background:{$barColor};height:28px;
                                                border-radius:6px;transition:width 0.3s ease;
                                                background-image:repeating-linear-gradient(
                                                    45deg,rgba(255,255,255,0) 0,rgba(255,255,255,0) 10px,
                                                    rgba(255,255,255,.2) 10px,rgba(255,255,255,.2) 20px);'>
                                    </div>
                                    <span style='position:absolute;left:50%;top:50%;
                                                 transform:translate(-50%,-50%);font-size:14px;
                                                 font-weight:bold;color:#1a1a1a;
                                                 text-shadow:0 0 6px rgba(255,255,255,0.9);'>
                                        {$pct}%
                                    </span>
                                </div>

                                <div style='display:flex;flex-wrap:wrap;gap:20px;font-size:12px;color:#444;'>
                                    <span><i class='fa fa-tachometer text-blue'></i>&nbsp;<strong>{$speed}</strong></span>
                                    <span><i class='fa fa-download text-green'></i>&nbsp;{$bytesHuman} of {$size}</span>
                                    <span><i class='fa fa-clock-o text-orange'></i>&nbsp;ETA: <strong>{$eta}</strong></span>
                                    <span><i class='fa fa-server text-purple'></i>&nbsp;{$worker}</span>
                                    <span><i class='fa fa-repeat text-muted'></i>&nbsp;Attempt " . $t->attempt_count . "/" . $t->max_attempts . "</span>
                                    <span><i class='fa fa-link text-aqua'></i>&nbsp;
                                        <a href='" . e($t->source_url ?? '#') . "' target='_blank' style='font-size:11px;'>Source</a>
                                    </span>
                                </div>
                            </div>
                        ";
                    }

                    $html .= "</div></div>";
                    $col->append($html);
                });
            })

            // ── Failed (left) + Queue & Recent done (right) ───────────────────
            ->row(function (Row $row) use ($failed, $nextQ, $recent) {

                // Failed transfers table
                $row->column(7, function (Column $col) use ($failed) {
                    $retryAllBtn = "<a href='/movie-file-transfers/retry-all-failed'
                            class='btn btn-xs btn-warning'
                            onclick=\"return confirm('Retry all failed transfers?')\">
                            <i class='fa fa-refresh'></i> Retry All
                        </a>
                        <a href='/movie-file-transfers?status=failed' class='btn btn-xs btn-default' style='margin-left:4px;'>
                            <i class='fa fa-list'></i> View All
                        </a>";

                    $html = "<div class='box box-danger'>
                        <div class='box-header with-border' style='padding:8px 15px;'>
                            <h3 class='box-title'><i class='fa fa-times-circle'></i> Failed Transfers</h3>
                            <div class='box-tools pull-right'>{$retryAllBtn}</div>
                        </div>
                        <div class='box-body' style='padding:0;'>";

                    if ($failed->isEmpty()) {
                        $html .= "<div class='alert alert-success' style='margin:12px;'>
                            <i class='fa fa-check-circle'></i> No failed transfers — great!
                        </div>";
                    } else {
                        $html .= "<table class='table table-condensed table-hover' style='margin:0;font-size:12px;'>
                            <thead>
                                <tr style='background:#fdf2f2;'>
                                    <th style='width:40px;'>#</th>
                                    <th>Movie</th>
                                    <th style='width:55px;'>Tries</th>
                                    <th>Error</th>
                                    <th style='width:80px;'>When</th>
                                    <th style='width:85px;'>Actions</th>
                                </tr>
                            </thead>
                            <tbody>";

                        foreach ($failed as $t) {
                            $retryBtn = $t->isRetriable()
                                ? "<a href='/movie-file-transfers/{$t->id}/retry'
                                      class='btn btn-xs btn-warning'
                                      onclick=\"return confirm('Retry #{$t->id}?')\">
                                      <i class='fa fa-refresh'></i>
                                   </a>"
                                : "<span class='label label-default' title='No retries left'>Max</span>";
                            $detailBtn = "<a href='/movie-file-transfers/{$t->id}' class='btn btn-xs btn-info' style='margin-left:3px;' title='View details'>
                                <i class='fa fa-eye'></i>
                            </a>";
                            $tries      = $t->attempt_count . '/' . $t->max_attempts;
                            $triesColor = $t->attempt_count >= $t->max_attempts ? 'danger' : 'warning';
                            $err        = e(substr($t->error_message ?? 'Unknown error', 0, 65));
                            $when       = $t->updated_at ? $t->updated_at->diffForHumans() : '—';

                            $html .= "<tr>
                                <td><small>#{$t->id}</small></td>
                                <td><small>" . e(substr($t->movie_title ?? '—', 0, 28)) . "</small></td>
                                <td><span class='label label-{$triesColor}'>{$tries}</span></td>
                                <td><small class='text-danger' title='" . e($t->error_message ?? '') . "'>{$err}</small></td>
                                <td><small class='text-muted'>{$when}</small></td>
                                <td>{$retryBtn}{$detailBtn}</td>
                            </tr>";
                        }

                        $html .= "</tbody></table>";
                    }

                    $html .= "</div></div>";
                    $col->append($html);
                });

                // Right column: Next in queue + Recently completed
                $row->column(5, function (Column $col) use ($nextQ, $recent) {

                    // Next in queue
                    $qHtml = "<div class='box box-info' style='margin-bottom:10px;'>
                        <div class='box-header with-border' style='padding:8px 15px;'>
                            <h3 class='box-title'>
                                <i class='fa fa-list-ol'></i> Next in Queue
                            </h3>
                        </div>
                        <div class='box-body' style='padding:0;'>";

                    if ($nextQ->isEmpty()) {
                        $qHtml .= "<div style='padding:12px;color:#999;'>
                            <i class='fa fa-check'></i> Queue is empty.
                        </div>";
                    } else {
                        $qHtml .= "<table class='table table-condensed' style='margin:0;font-size:12px;'>
                            <thead><tr style='background:#f0f8ff;'>
                                <th>#</th><th>Movie</th><th>Priority</th><th style='width:70px;'>Actions</th>
                            </tr></thead><tbody>";
                        foreach ($nextQ as $t) {
                            $pri   = number_format($t->priority ?? 0);
                            $priC  = ($t->priority ?? 0) >= 999000 ? 'danger' : (($t->priority ?? 0) >= 100 ? 'warning' : 'default');
                            $qHtml .= "<tr>
                                <td><small>#{$t->id}</small></td>
                                <td><small>" . e(substr($t->movie_title ?? '—', 0, 22)) . "</small></td>
                                <td><span class='label label-{$priC}'>{$pri}</span></td>
                                <td>
                                    <a href='/movie-file-transfers/{$t->id}' class='btn btn-xs btn-info' title='Details'>
                                        <i class='fa fa-eye'></i>
                                    </a>
                                    <a href='/movie-file-transfers/{$t->id}/cancel' class='btn btn-xs btn-danger' style='margin-left:2px;'
                                       onclick=\"return confirm('Cancel #" . $t->id . "?')\">
                                        <i class='fa fa-times'></i>
                                    </a>
                                </td>
                            </tr>";
                        }
                        $qHtml .= "</tbody></table>";
                    }
                    $qHtml .= "</div></div>";
                    $col->append($qHtml);

                    // Recently completed
                    $dHtml = "<div class='box box-success'>
                        <div class='box-header with-border' style='padding:8px 15px;'>
                            <h3 class='box-title'>
                                <i class='fa fa-check-circle text-green'></i> Recently Completed
                            </h3>
                            <div class='box-tools pull-right'>
                                <a href='/movie-file-transfers?status=done' class='btn btn-xs btn-default'>
                                    <i class='fa fa-list'></i> All
                                </a>
                            </div>
                        </div>
                        <div class='box-body' style='padding:0;'>";

                    if ($recent->isEmpty()) {
                        $dHtml .= "<div style='padding:12px;color:#999;'>No completed transfers yet.</div>";
                    } else {
                        $dHtml .= "<table class='table table-condensed' style='margin:0;font-size:12px;'>
                            <thead><tr style='background:#f5fff5;'>
                                <th>#</th><th>Movie</th><th>Speed</th><th>Size</th><th>Done</th>
                            </tr></thead><tbody>";
                        foreach ($recent as $t) {
                            $dHtml .= "<tr>
                                <td>
                                    <a href='/movie-file-transfers/{$t->id}' class='text-success' title='Details'>
                                        #{$t->id}
                                    </a>
                                </td>
                                <td><small>" . e(substr($t->movie_title ?? '—', 0, 22)) . "</small></td>
                                <td><small class='text-success'>" . $t->formatted_speed . "</small></td>
                                <td><small>" . $t->formatted_size . "</small></td>
                                <td><small class='text-muted'>" . ($t->completed_at ? $t->completed_at->diffForHumans() : '—') . "</small></td>
                            </tr>";
                        }
                        $dHtml .= "</tbody></table>";
                    }
                    $dHtml .= "</div></div>";
                    $col->append($dHtml);
                });
            });
    }

    // ── Backfill page ─────────────────────────────────────────────────────────

    public function backfill(Content $content): Content
    {
        $stats = $this->getStats();

        // Top movies not yet queued (for "transfer specific" display)
        $topPending = MovieModel::where('status', 'Active')
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
            ->orderByDesc(DB::raw('(views_count * 3 + downloads_count * 5 + likes_count * 2)'))
            ->limit(10)
            ->get(['id', 'title', 'url', 'year', 'views_count', 'downloads_count', 'likes_count', 'poster_url']);

        $topRows = $topPending->map(function ($m) {
            $priority = ($m->views_count * 3) + ($m->downloads_count * 5) + ($m->likes_count * 2);
            $poster = $m->poster_url
                ? "<img src='" . e($m->poster_url) . "' style='width:28px;height:40px;object-fit:cover;border-radius:2px;vertical-align:middle;'>"
                : "<i class='fa fa-film text-muted'></i>";
            $title = "{$poster} " . e($m->title ?? '—') . ($m->year ? " ({$m->year})" : '');
            return [
                $title,
                "<span class='text-info'><i class='fa fa-eye'></i> " . number_format($m->views_count) . "</span>",
                "<span class='text-success'><i class='fa fa-download'></i> " . number_format($m->downloads_count) . "</span>",
                "<span class='text-warning'><i class='fa fa-heart'></i> " . number_format($m->likes_count) . "</span>",
                "<strong>" . number_format($priority) . "</strong>",
                "<a href='/movie-file-transfers/queue-single/{$m->id}' class='btn btn-xs btn-primary'
                    onclick=\"return confirm('Queue transfer for this movie now?')\">
                   <i class='fa fa-upload'></i> Queue Now
                 </a>",
            ];
        })->toArray();

        $token = csrf_token();

        return $content
            ->title('Queue Backfill')
            ->description('Prioritizes most-viewed and most-downloaded movies first')
            ->row(function (Row $row) use ($stats) {
                $row->column(3, new InfoBox('Active Movies',     'film',       'blue',  '#', number_format($stats['total_active'])));
                $row->column(3, new InfoBox('Already on Hetzner','check',      'green', '#', number_format($stats['done'])));
                $row->column(3, new InfoBox('Queued for Transfer','hourglass', 'aqua',  '#', number_format($stats['queued'])));
                $row->column(3, new InfoBox('Need Queuing',      'warning',    'red',   '#', number_format($stats['not_queued'])));
            })
            ->row(function (Row $row) use ($stats, $token) {
                $row->column(6, function (Column $col) use ($stats, $token) {
                    $notQueued = number_format($stats['not_queued']);
                    $col->append("
                        <div class='box box-warning'>
                          <div class='box-header with-border'>
                            <h3 class='box-title'><i class='fa fa-database'></i> Bulk Backfill</h3>
                          </div>
                          <div class='box-body'>
                            <p>Queue <strong>{$notQueued}</strong> movies not yet on Hetzner.</p>
                            <p class='text-muted'><i class='fa fa-sort-amount-desc'></i>
                              <strong>Priority order:</strong> most downloaded → most viewed → most liked → rest.
                              The scheduler processes " . TransferMovieToHetzner::MAX_CONCURRENT . " at a time.
                            </p>
                            <form method='POST' action='/movie-file-transfers/backfill-run'
                                  onsubmit=\"return confirm('Queue transfers? The scheduler will process them gradually.')\">
                              <input type='hidden' name='_token' value='{$token}'>
                              <div class='form-group' style='margin-bottom:10px;'>
                                <label>Limit (0 = all)</label>
                                <input type='number' name='limit' class='form-control' value='0' min='0' style='width:150px;'>
                              </div>
                              <div class='form-group' style='margin-bottom:15px;'>
                                <label>Source filter (optional, e.g. <code>munowatch</code>)</label>
                                <input type='text' name='source' class='form-control' placeholder='Leave blank for all sources' style='width:250px;'>
                              </div>
                              <button type='submit' class='btn btn-warning'>
                                <i class='fa fa-play'></i> Start Backfill
                              </button>
                            </form>
                          </div>
                        </div>
                    ");
                });
                $row->column(6, function (Column $col) use ($token) {
                    $col->append("
                        <div class='box box-primary'>
                          <div class='box-header with-border'>
                            <h3 class='box-title'><i class='fa fa-search'></i> Transfer Specific Movie</h3>
                          </div>
                          <div class='box-body'>
                            <p>Queue a single movie by its ID immediately at highest priority.</p>
                            <form method='POST' action='/movie-file-transfers/queue-single'
                                  onsubmit=\"return confirm('Queue this movie for immediate transfer?')\">
                              <input type='hidden' name='_token' value='{$token}'>
                              <div class='form-group' style='margin-bottom:15px;'>
                                <label>Movie ID</label>
                                <input type='number' name='movie_id' class='form-control' placeholder='Enter movie ID' min='1' required style='width:200px;'>
                              </div>
                              <button type='submit' class='btn btn-primary'>
                                <i class='fa fa-upload'></i> Queue Transfer
                              </button>
                            </form>
                          </div>
                        </div>
                    ");
                });
            })
            ->row(function (Row $row) use ($topRows) {
                $row->column(12, function (Column $col) use ($topRows) {
                    if ($topRows) {
                        $table = new Table(
                            ['Movie', 'Views', 'Downloads', 'Likes', 'Priority Score', 'Action'],
                            $topRows
                        );
                        $col->append("
                            <div class='box box-default'>
                              <div class='box-header with-border'>
                                <h3 class='box-title'><i class='fa fa-star text-yellow'></i> Top 10 High-Priority Movies Not Yet Transferred</h3>
                                <div class='box-tools pull-right'>
                                  <small class='text-muted'>Sorted by views×3 + downloads×5 + likes×2</small>
                                </div>
                              </div>
                              <div class='box-body table-responsive'>
                                {$table->render()}
                              </div>
                            </div>
                        ");
                    } else {
                        $col->append('<div class="alert alert-success"><i class="fa fa-check"></i> All high-priority movies are already in the transfer queue or on Hetzner.</div>');
                    }
                });
            });
    }

    // ── Action routes ─────────────────────────────────────────────────────────

    public function retry($id)
    {
        $t = MovieFileTransfer::findOrFail($id);
        if (!$t->isFailed() && !$t->isRetriable()) {
            admin_toastr('This transfer cannot be retried.', 'error');
            return redirect()->back();
        }
        $t->resetToQueued('Manual retry from admin panel');
        $t->update(['priority' => $t->priority + 100]);
        TransferMovieToHetzner::dispatch($t->id)->onQueue('transfers');
        admin_toastr("Transfer #{$id} queued for retry (priority boosted).", 'success');
        return redirect('/movie-file-transfers');
    }

    public function cancel($id)
    {
        $t = MovieFileTransfer::findOrFail($id);
        $t->cancel();
        admin_toastr("Transfer #{$id} cancelled.", 'success');
        return redirect('/movie-file-transfers');
    }

    public function retryAllFailed()
    {
        $failed = MovieFileTransfer::failed()->get();
        $count  = 0;
        foreach ($failed as $t) {
            $t->resetToQueued('Bulk retry from admin panel');
            TransferMovieToHetzner::dispatch($t->id)->onQueue('transfers');
            $count++;
        }
        admin_toastr("Queued {$count} failed transfers for retry.", 'success');
        return redirect('/movie-file-transfers/monitor');
    }

    public function processNow()
    {
        Artisan::call('transfers:process', ['--concurrency' => 2, '--limit' => 6]);
        admin_toastr('Dispatch command ran — transfers have been dispatched.', 'success');
        return redirect('/movie-file-transfers/monitor');
    }

    public function backfillRun(Request $request)
    {
        $limit  = max(0, (int) $request->input('limit', 0));
        $source = trim((string) $request->input('source', ''));
        $params = ['--chunk' => 100];
        if ($limit > 0) $params['--limit'] = $limit;
        if ($source !== '') $params['--source'] = $source;

        try {
            Artisan::call('transfers:backfill', $params);
            $output = trim(Artisan::output());
            $lines  = array_filter(explode("\n", $output));
            $queued = 0;
            foreach ($lines as $line) {
                if (preg_match('/Queued\s+\|\s*(\d+)/', $line, $m)) {
                    $queued = (int)$m[1];
                }
            }
            admin_toastr("Backfill complete — {$queued} transfer records created. The scheduler will process them by priority.", 'success');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[backfillRun] ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            admin_toastr('Backfill failed: ' . $e->getMessage(), 'error');
        }

        return redirect('/movie-file-transfers/backfill');
    }

    /** Queue a single specific movie — highest priority (GET from top-10 table or POST from form) */
    public function queueSingle(Request $request, $movieId = null)
    {
        $id = $movieId ?? $request->input('movie_id');
        if (!$id) {
            admin_toastr('No movie ID provided.', 'error');
            return redirect('/movie-file-transfers/backfill');
        }

        $movie = MovieModel::find((int)$id);
        if (!$movie) {
            admin_toastr("Movie #{$id} not found.", 'error');
            return redirect('/movie-file-transfers/backfill');
        }

        $url = (string)($movie->attributes['url'] ?? $movie->url ?? '');
        if (empty($url)) {
            admin_toastr("Movie #{$id} has no video URL.", 'error');
            return redirect('/movie-file-transfers/backfill');
        }

        if (MovieFileTransfer::isAlreadyOnHetzner($url)) {
            admin_toastr("Movie #{$id} is already on Hetzner Storage — no transfer needed.", 'success');
            return redirect('/movie-file-transfers/backfill');
        }

        $existing = MovieFileTransfer::forMovie($movie->id)
            ->whereIn('status', [MovieFileTransfer::STATUS_FAILED, MovieFileTransfer::STATUS_CANCELLED, MovieFileTransfer::STATUS_SKIPPED])
            ->first();

        if ($existing) {
            $existing->resetToQueued('Admin: single-movie queue request');
            $existing->update(['priority' => 999999, 'initiated_by' => 'admin:single']);
            $transfer = $existing;
        } elseif (MovieFileTransfer::hasPendingOrCompleted($movie->id)) {
            $active = MovieFileTransfer::forMovie($movie->id)->whereNotIn('status', [MovieFileTransfer::STATUS_DONE])->first();
            admin_toastr("Movie #{$id} ({$movie->title}) already has an active transfer" . ($active ? " (#{$active->id})" : '') . ".", 'success');
            return redirect('/movie-file-transfers/backfill');
        } else {
            $transfer = MovieFileTransfer::queueForMovie($movie, 'admin:single');
            $transfer->update(['priority' => 999999]);
        }

        TransferMovieToHetzner::dispatch($transfer->id)->onQueue('transfers');

        admin_toastr("Movie #{$id} ({$movie->title}) queued at maximum priority and dispatched immediately.", 'success');
        return redirect('/movie-file-transfers/' . $transfer->id);
    }

    // ── Detail (show) ─────────────────────────────────────────────────────────

    public function show($id, Content $content)
    {
        $t = MovieFileTransfer::findOrFail($id);

        $rows = [
            ['ID',             $t->id],
            ['Movie',          e($t->movie_title ?? '—') . " (#" . $t->movie_id . ")"],
            ['Status',         "<span class='label label-{$t->status_badge_color}'>{$t->status_label}</span>"],
            ['Priority Score', number_format($t->priority ?? 0)],
            ['Source URL',     "<a href='" . e($t->source_url) . "' target='_blank' style='word-break:break-all'>" . e(substr($t->source_url ?? '', 0, 80)) . "…</a>"],
            ['Source Type',    strtoupper($t->source_type ?? '—')],
            ['Source Size',    $t->formatted_size],
            ['Destination',    $t->dest_url ? "<a href='" . e($t->dest_url) . "' target='_blank'>" . e($t->dest_url) . "</a>" : '—'],
            ['Dest Path',      e($t->dest_path ?? '—')],
            ['Progress',       $t->progress_pct . '%'],
            ['Speed',          $t->formatted_speed],
            ['Duration',       $t->formatted_duration],
            ['Attempts',       $t->attempt_count . ' / ' . $t->max_attempts],
            ['Initiated By',   e($t->initiated_by ?? '—')],
            ['Worker',         e($t->worker_hostname ?? '—')],
            ['Queued At',      $t->queued_at?->format('Y-m-d H:i:s') ?? '—'],
            ['Started At',     $t->started_at?->format('Y-m-d H:i:s') ?? '—'],
            ['Completed At',   $t->completed_at?->format('Y-m-d H:i:s') ?? '—'],
            ['Movie URL Updated', $t->movie_url_updated ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>'],
            ['Error',          $t->error_message ? "<code style='color:red;white-space:pre-wrap'>" . e($t->error_message) . "</code>" : '—'],
        ];

        $html = "<div class='box box-default'>
                   <div class='box-header with-border'><h3 class='box-title'>Transfer #{$t->id} — " . e($t->movie_title ?? '') . "</h3></div>
                   <div class='box-body table-responsive'><table class='table table-bordered table-striped'><tbody>";
        foreach ($rows as [$label, $value]) {
            $html .= "<tr><th style='width:180px'>" . e($label) . "</th><td>{$value}</td></tr>";
        }
        $html .= "</tbody></table></div>";

        if ($t->error_trace) {
            $html .= "<div class='box box-danger'>
                        <div class='box-header with-border'><h3 class='box-title'><i class='fa fa-bug'></i> Stack Trace</h3></div>
                        <div class='box-body'>
                          <pre style='font-size:11px;max-height:300px;overflow:auto'>" . e($t->error_trace) . "</pre>
                        </div>
                      </div>";
        }

        $actions = [];
        if ($t->isRetriable() || $t->isFailed()) {
            $actions[] = "<a href='/movie-file-transfers/{$id}/retry' class='btn btn-warning' onclick=\"return confirm('Retry?')\"><i class='fa fa-refresh'></i> Retry</a>";
        }
        if (!$t->isDone() && !$t->isFailed()) {
            $actions[] = "<a href='/movie-file-transfers/{$id}/cancel' class='btn btn-danger' onclick=\"return confirm('Cancel?')\"><i class='fa fa-times'></i> Cancel</a>";
        }
        if ($t->movie_id) {
            $actions[] = "<a href='/movies-active/{$t->movie_id}/edit' class='btn btn-default'><i class='fa fa-pencil'></i> Edit Movie</a>";
        }
        $actions[] = "<a href='/movie-file-transfers' class='btn btn-default'><i class='fa fa-arrow-left'></i> Back to List</a>";
        $html .= "<div class='box-footer'>" . implode(' ', $actions) . "</div></div>";

        return $content
            ->title("Transfer #{$id}")
            ->description(e($t->movie_title ?? ''))
            ->body($html);
    }

    // ── Stats helper ──────────────────────────────────────────────────────────

    private function getStats(): array
    {
        $counts = MovieFileTransfer::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $done   = $counts[MovieFileTransfer::STATUS_DONE] ?? 0;
        $queued = $counts[MovieFileTransfer::STATUS_QUEUED] ?? 0;
        $active = ($counts[MovieFileTransfer::STATUS_TRANSFERRING] ?? 0)
                + ($counts[MovieFileTransfer::STATUS_COMPLETING] ?? 0);
        $failed = $counts[MovieFileTransfer::STATUS_FAILED] ?? 0;

        $avgSpeed    = MovieFileTransfer::done()->whereNotNull('transfer_speed_mbps')->avg('transfer_speed_mbps');
        $avgDuration = MovieFileTransfer::done()->whereNotNull('duration_seconds')->avg('duration_seconds');

        $etaStr = '—';
        if ($queued > 0 && $avgDuration > 0) {
            $etaHours = round(($queued * $avgDuration) / max(1, TransferMovieToHetzner::MAX_CONCURRENT) / 3600, 1);
            $etaStr   = "~{$etaHours}h";
        }

        $storedGb = round(
            MovieFileTransfer::done()->whereNotNull('dest_size_bytes')->sum('dest_size_bytes') / 1_073_741_824,
            2
        );

        $notQueued = MovieModel::where('status', 'Active')
            ->whereNotNull('url')->where('url', '!=', '')
            ->where('url', 'not like', '%' . MovieFileTransfer::HETZNER_HOST . '%')
            ->whereDoesntHave('transfers', fn($q) => $q->whereIn('status', [
                MovieFileTransfer::STATUS_QUEUED, MovieFileTransfer::STATUS_VERIFYING,
                MovieFileTransfer::STATUS_TRANSFERRING, MovieFileTransfer::STATUS_COMPLETING,
                MovieFileTransfer::STATUS_DONE,
            ]))
            ->count();

        $totalActive = MovieModel::where('status', 'Active')->whereNotNull('url')->count();

        return [
            'queued'       => $queued,
            'active'       => $active,
            'done'         => $done,
            'failed'       => $failed,
            'eta'          => $etaStr,
            'avg_speed'    => $avgSpeed ? number_format($avgSpeed, 1) . ' MB/s' : '—',
            'stored_gb'    => $storedGb,
            'not_queued'   => $notQueued,
            'total_active' => $totalActive,
            'avg_speed_raw'   => $avgSpeed,
            'avg_duration'    => $avgDuration,
        ];
    }
}
