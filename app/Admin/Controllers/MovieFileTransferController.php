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
                $dUrl   = e($this->dest_url);
                $dTitle = e($this->movie_title ?? 'Movie');
                $dSize  = e($this->formatted_size);
                $dYear  = e($this->movie_year ?? '');
                $dPoster= e($this->movie_poster_url ?? '');
                $btns[] = "<button class='btn btn-xs btn-success kt-play-btn' title='Preview video'
                               data-url='{$dUrl}' data-title='{$dTitle}' data-year='{$dYear}'
                               data-size='{$dSize}' data-poster='{$dPoster}'>
                               <i class='fa fa-play'></i>
                           </button>";
                $btns[] = "<a href='{$dUrl}' target='_blank' class='btn btn-xs btn-default' title='Open CDN URL'>
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
        $stats = $this->getStats();
        $max   = TransferMovieToHetzner::MAX_CONCURRENT;

        return $content
            ->title('Movie File Transfers')
            ->description('Automated pipeline — source URLs → Hetzner Storage CDN')

            // ── CSS + Stats + Toolbar (one compact row) ───────────────────────────
            ->row(function (Row $row) use ($stats, $max) {
                $row->column(12, function (Column $col) use ($stats, $max) {
                    $q  = number_format($stats['queued']);
                    $a  = $stats['active'] . '/' . $max;
                    $d  = number_format($stats['done']);
                    $f  = number_format($stats['failed']);
                    $sp = e($stats['avg_speed']);
                    $gb = $stats['stored_gb'];
                    $fc = number_format($stats['failed']);
                    $nq = number_format($stats['not_queued']);

                    $col->append($this->renderDashboardCss() . "
                        <div class='kt-stat-row'>
                            <a href='/movie-file-transfers?status=queued' class='kt-stat-box' style='--ac:#5b7fa6'>
                                <div class='v' id='stat-queued'>{$q}</div>
                                <div class='l'>Queued</div>
                                <i class='ico fa fa-hourglass-half'></i>
                            </a>
                            <a href='/movie-file-transfers?status=transferring' class='kt-stat-box' style='--ac:#2980b9'>
                                <div class='v' id='stat-active'>{$a}</div>
                                <div class='l'>Active / Slots</div>
                                <i class='ico fa fa-exchange'></i>
                            </a>
                            <a href='/movie-file-transfers?status=done' class='kt-stat-box' style='--ac:#27ae60'>
                                <div class='v' id='stat-done'>{$d}</div>
                                <div class='l'>Completed</div>
                                <i class='ico fa fa-check-circle'></i>
                            </a>
                            <a href='/movie-file-transfers?status=failed' class='kt-stat-box' style='--ac:#c0392b'>
                                <div class='v' id='stat-failed'>{$f}</div>
                                <div class='l'>Failed</div>
                                <i class='ico fa fa-times-circle'></i>
                            </a>
                            <a href='#' class='kt-stat-box' style='--ac:#d68910'>
                                <div class='v' id='stat-speed'>{$sp}</div>
                                <div class='l'>Avg Speed</div>
                                <i class='ico fa fa-tachometer'></i>
                            </a>
                            <a href='#' class='kt-stat-box' style='--ac:#1e3a5f'>
                                <div class='v' id='stat-stored'>{$gb} GB</div>
                                <div class='l'>On Hetzner</div>
                                <i class='ico fa fa-hdd-o'></i>
                            </a>
                        </div>
                        <div class='kt-toolbar'>
                            <div class='kt-toolbar-left'>
                                <div class='kt-sync-bar kt-sync-bar-inline' id='kt-sync-bar'>
                                    <span class='kt-sdot' id='kt-sdot'></span>
                                    <span id='kt-smsg'>Connecting...</span>
                                    <span class='kt-stime' id='kt-stime'></span>
                                </div>
                                <div id='kt-alerts-section'></div>
                            </div>
                            <div class='kt-toolbar-right'>
                                <a href='/movie-file-transfers/process-now' class='kt-tbtn kt-tbtn-primary'>
                                    <i class='fa fa-play-circle'></i> Dispatch
                                </a>
                                <a href='/movie-file-transfers/retry-all-failed' class='kt-tbtn kt-tbtn-warning'
                                   onclick=\"return confirm('Retry all {$fc} failed transfers?')\">
                                    <i class='fa fa-refresh'></i> Retry Failed ({$fc})
                                </a>
                                <a href='/movie-file-transfers/backfill' class='kt-tbtn kt-tbtn-neutral'>
                                    <i class='fa fa-database'></i> Backfill ({$nq})
                                </a>
                                <a href='/movie-file-transfers/monitor' class='kt-tbtn kt-tbtn-success'>
                                    <i class='fa fa-heartbeat'></i> Monitor
                                </a>
                            </div>
                        </div>
                    ");
                });
            })

            // ── Live transfer cards — 2-column grid ───────────────────────────────
            ->row(function (Row $row) use ($max) {
                $row->column(12, function (Column $col) use ($max) {
                    $col->append("
                        <div class='kt-live-box' id='live-section-box'>
                            <div class='kt-live-hdr'>
                                <div class='kt-live-hdr-title'>
                                    <span class='kt-live-dot'></span>
                                    Active Transfers — Live
                                </div>
                                <span id='live-count-badge' class='kt-live-count'></span>
                            </div>
                            <div class='kt-cards-grid' id='active-cards-wrap'>
                                <div class='kt-empty-state kt-empty-full' id='active-cards-empty'>
                                    <i class='fa fa-pause-circle'></i>
                                    No active transfers — workers are idle.
                                </div>
                            </div>
                        </div>
                    " . $this->renderLiveJs($max));
                });
            })

            ->row(function (Row $row) {
                $row->column(12, function (Column $col) {
                    $col->append($this->renderPreviewModal());
                });
            })
            ->body($this->grid());
    }

    // ── Monitor page (real-time dashboard) ───────────────────────────────────

    public function monitor(Content $content): Content
    {
        $stats = $this->getStats();
        $max   = TransferMovieToHetzner::MAX_CONCURRENT;

        return $content
            ->title('Transfer Monitor')
            ->description('Real-time pipeline dashboard — live polling every 3 seconds')

            // ── CSS + Stat boxes ──────────────────────────────────────────────
            ->row(function (Row $row) use ($stats, $max) {
                $row->column(12, function (Column $col) use ($stats, $max) {
                    $q  = number_format($stats['queued']);
                    $a  = $stats['active'] . '/' . $max;
                    $d  = number_format($stats['done']);
                    $f  = number_format($stats['failed']);
                    $sp = e($stats['avg_speed']);
                    $gb = $stats['stored_gb'];

                    $col->append($this->renderDashboardCss() . "
                        <div class='kt-stat-row'>
                            <a href='/movie-file-transfers?status=queued' class='kt-stat-box' style='--ac:#5b7fa6'>
                                <div class='v' id='stat-queued'>{$q}</div>
                                <div class='l'>Queued</div>
                                <i class='ico fa fa-hourglass-half'></i>
                            </a>
                            <a href='/movie-file-transfers?status=transferring' class='kt-stat-box' style='--ac:#2980b9'>
                                <div class='v' id='stat-active'>{$a}</div>
                                <div class='l'>Active / Slots</div>
                                <i class='ico fa fa-exchange'></i>
                            </a>
                            <a href='/movie-file-transfers?status=done' class='kt-stat-box' style='--ac:#27ae60'>
                                <div class='v' id='stat-done'>{$d}</div>
                                <div class='l'>Completed</div>
                                <i class='ico fa fa-check-circle'></i>
                            </a>
                            <a href='/movie-file-transfers?status=failed' class='kt-stat-box' style='--ac:#c0392b'>
                                <div class='v' id='stat-failed'>{$f}</div>
                                <div class='l'>Failed</div>
                                <i class='ico fa fa-times-circle'></i>
                            </a>
                            <a href='#' class='kt-stat-box' style='--ac:#d68910'>
                                <div class='v' id='stat-speed'>{$sp}</div>
                                <div class='l'>Avg Speed</div>
                                <i class='ico fa fa-tachometer'></i>
                            </a>
                            <a href='#' class='kt-stat-box' style='--ac:#1e3a5f'>
                                <div class='v' id='stat-stored'>{$gb} GB</div>
                                <div class='l'>On Hetzner</div>
                                <i class='ico fa fa-hdd-o'></i>
                            </a>
                        </div>
                    ");
                });
            })

            // ── Toolbar ───────────────────────────────────────────────────────
            ->row(function (Row $row) use ($stats) {
                $row->column(12, function (Column $col) use ($stats) {
                    $fc = number_format($stats['failed']);
                    $nq = number_format($stats['not_queued']);
                    $col->append("
                        <div class='kt-toolbar'>
                            <div class='kt-toolbar-left'>
                                <div class='kt-sync-bar kt-sync-bar-inline' id='kt-sync-bar'>
                                    <span class='kt-sdot' id='kt-sdot'></span>
                                    <span id='kt-smsg'>Connecting...</span>
                                    <span class='kt-stime' id='kt-stime'></span>
                                </div>
                                <div id='kt-alerts-section'></div>
                            </div>
                            <div class='kt-toolbar-right'>
                                <a href='/movie-file-transfers/process-now' class='kt-tbtn kt-tbtn-primary'>
                                    <i class='fa fa-play-circle'></i> Process Queue
                                </a>
                                <a href='/movie-file-transfers/retry-all-failed' class='kt-tbtn kt-tbtn-warning'
                                   onclick=\"return confirm('Retry all failed transfers?')\">
                                    <i class='fa fa-refresh'></i> Retry All ({$fc})
                                </a>
                                <a href='/movie-file-transfers/backfill' class='kt-tbtn kt-tbtn-neutral'>
                                    <i class='fa fa-database'></i> Backfill ({$nq})
                                </a>
                                <a href='/movie-file-transfers' class='kt-tbtn kt-tbtn-neutral'>
                                    <i class='fa fa-list'></i> All Transfers
                                </a>
                            </div>
                        </div>
                    ");
                });
            })

            // ── Live active cards — 2-column grid ─────────────────────────────
            ->row(function (Row $row) {
                $row->column(12, function (Column $col) {
                    $col->append("
                        <div class='kt-live-box' id='live-section-box'>
                            <div class='kt-live-hdr'>
                                <div class='kt-live-hdr-title'>
                                    <span class='kt-live-dot'></span>
                                    Active Transfers — Live
                                </div>
                                <span id='live-count-badge' class='kt-live-count'></span>
                            </div>
                            <div class='kt-cards-grid' id='active-cards-wrap'>
                                <div class='kt-empty-state kt-empty-full' id='active-cards-empty'>
                                    <i class='fa fa-pause-circle'></i>
                                    No active transfers — workers are idle.
                                </div>
                            </div>
                        </div>
                    ");
                });
            })

            // ── Failed (left) + Queue & Recent (right) ────────────────────────
            ->row(function (Row $row) use ($max) {

                // Failed table — rows updated by JS
                $row->column(7, function (Column $col) {
                    $col->append("
                        <div class='kt-live-box'>
                            <div class='kt-live-hdr'>
                                <div class='kt-live-hdr-title' style='color:#922b21;'>
                                    <i class='fa fa-times-circle'></i> Failed Transfers
                                </div>
                                <div style='display:flex;gap:6px;'>
                                    <a href='/movie-file-transfers/retry-all-failed' class='kt-abtn kt-abtn-warning'
                                       onclick=\"return confirm('Retry all failed transfers?')\">
                                        <i class='fa fa-refresh'></i> Retry All
                                    </a>
                                    <a href='/movie-file-transfers?status=failed' class='kt-abtn kt-abtn-info'>
                                        <i class='fa fa-list'></i> View All
                                    </a>
                                </div>
                            </div>
                            <div style='padding:0;'>
                                <table class='kt-table-mini' style='width:100%;'>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Movie</th>
                                            <th>Tries</th>
                                            <th>Error</th>
                                            <th>When</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id='monitor-failed-tbody'>
                                        <tr><td colspan='6' class='kt-empty-state'>Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ");
                });

                // Queue + Recent — rows updated by JS
                $row->column(5, function (Column $col) use ($max) {
                    $col->append("
                        <div class='kt-live-box' style='margin-bottom:14px;'>
                            <div class='kt-live-hdr'>
                                <div class='kt-live-hdr-title' style='color:#1a5f8a;'>
                                    <i class='fa fa-list-ol'></i> Next in Queue
                                </div>
                            </div>
                            <div style='padding:0;'>
                                <table class='kt-table-mini' style='width:100%;'>
                                    <thead>
                                        <tr><th>#</th><th>Movie</th><th>Priority</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody id='monitor-queue-tbody'>
                                        <tr><td colspan='4' class='kt-empty-state'>Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class='kt-live-box'>
                            <div class='kt-live-hdr'>
                                <div class='kt-live-hdr-title' style='color:#1e8449;'>
                                    <i class='fa fa-check-circle'></i> Recently Completed
                                </div>
                                <a href='/movie-file-transfers?status=done' class='kt-abtn kt-abtn-success'>
                                    <i class='fa fa-list'></i> All
                                </a>
                            </div>
                            <div style='padding:0;'>
                                <table class='kt-table-mini' style='width:100%;'>
                                    <thead>
                                        <tr><th>#</th><th>Movie</th><th>Speed</th><th>Size</th><th>Done</th></tr>
                                    </thead>
                                    <tbody id='monitor-recent-tbody'>
                                        <tr><td colspan='5' class='kt-empty-state'>Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    " . $this->renderMonitorJs($max));
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
        Artisan::call('transfers:process', ['--concurrency' => 100, '--limit' => 300]);
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

        $statusColors = [
            'queued'       => ['#e8f4fd', '#1a5f8a', '#3498db'],
            'verifying'    => ['#e8f4fd', '#1a5276', '#2980b9'],
            'transferring' => ['#d5f5e3', '#1a6b3a', '#00a65a'],
            'completing'   => ['#fef6e0', '#8a5a00', '#f39c12'],
            'done'         => ['#d5f5e3', '#1a6b3a', '#27ae60'],
            'failed'       => ['#fdecea', '#8b1c13', '#e74c3c'],
            'cancelled'    => ['#f5f5f5', '#666',    '#aaa'],
            'skipped'      => ['#f5f5f5', '#666',    '#aaa'],
        ];
        [$sbg, $sfg, $sacc] = $statusColors[$t->status] ?? ['#f5f5f5', '#666', '#aaa'];

        $statusIcon = match ($t->status) {
            'queued'       => 'fa-clock-o',
            'verifying'    => 'fa-search',
            'transferring' => 'fa-exchange',
            'completing'   => 'fa-check-square-o',
            'done'         => 'fa-check-circle',
            'failed'       => 'fa-times-circle',
            'cancelled'    => 'fa-ban',
            default        => 'fa-circle',
        };

        $pct     = $t->progress_pct ?? 0;
        $poster  = $t->movie_poster_url ? "<img src='" . e($t->movie_poster_url) . "' style='width:80px;height:120px;object-fit:cover;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.25);flex-shrink:0;'>" : '';
        $srcFull = e($t->source_url ?? '');
        $srcShort= e(substr($t->source_url ?? '', 0, 90));
        $destUrl = $t->dest_url;

        $liveJs = $t->isActive() ? $this->renderShowLiveJs($t->id) : '';

        $html = "
<style>
.kt-show-wrap{font-family:system-ui,-apple-system,sans-serif;}
.kt-hero{display:flex;gap:20px;align-items:flex-start;padding:20px 24px;background:#fff;border-radius:12px;box-shadow:0 2px 14px rgba(0,0,0,.07);margin-bottom:14px;}
.kt-hero-info{flex:1;min-width:0;}
.kt-hero-badge{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;background:{$sbg};color:{$sfg};border:1px solid {$sacc}33;margin-bottom:10px;}
.kt-hero-title{font-size:22px;font-weight:800;color:#1a1a2e;margin:0 0 4px;line-height:1.2;}
.kt-hero-sub{font-size:13px;color:#888;margin-bottom:12px;}
.kt-hero-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;}
.kt-ha{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:filter .15s,transform .1s;}
.kt-ha:hover{filter:brightness(1.06);transform:translateY(-1px);text-decoration:none;}
.kt-ha-green{background:linear-gradient(135deg,#00a65a,#00d860);color:#fff;}
.kt-ha-orange{background:linear-gradient(135deg,#c87f2a,#f0a332);color:#fff;}
.kt-ha-red{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;}
.kt-ha-blue{background:linear-gradient(135deg,#2471a3,#3498db);color:#fff;}
.kt-ha-grey{background:#f0f0f0;color:#444;border:1px solid #ddd;}
.kt-2col{display:grid;grid-template-columns:1fr 380px;gap:14px;align-items:start;}
@media(max-width:900px){.kt-2col{grid-template-columns:1fr;}}
.kt-card-section{background:#fff;border-radius:10px;box-shadow:0 1px 8px rgba(0,0,0,.06);margin-bottom:14px;overflow:hidden;}
.kt-sec-hdr{padding:11px 18px;font-size:13px;font-weight:700;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:8px;color:#333;}
.kt-sec-hdr i{color:{$sacc};}
.kt-dt{width:100%;border-collapse:collapse;font-size:13px;}
.kt-dt tr td:first-child{width:150px;padding:9px 14px 9px 18px;color:#888;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;border-bottom:1px solid #f8f8f8;vertical-align:top;}
.kt-dt tr td:last-child{padding:9px 18px;color:#1a1a2e;border-bottom:1px solid #f8f8f8;word-break:break-word;}
.kt-dt tr:last-child td{border-bottom:none;}
.kt-prog-wrap{margin:0 18px 16px;}
.kt-prog-big{position:relative;height:36px;background:#e9ecef;border-radius:10px;overflow:hidden;}
.kt-prog-fill{position:absolute;left:0;top:0;bottom:0;border-radius:10px;
    background:linear-gradient(90deg,#00b347,#00d860);
    background-image:repeating-linear-gradient(45deg,rgba(255,255,255,.16) 0,rgba(255,255,255,.16) 14px,transparent 14px,transparent 28px),linear-gradient(90deg,#00b347,#00d860);
    animation:kt-stripe 0.7s linear infinite;transition:width 1s cubic-bezier(.25,.46,.45,.94);}
.kt-prog-fill.completing{background:linear-gradient(90deg,#e67e22,#f39c12);}
.kt-prog-pct{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-size:13px;font-weight:800;color:rgba(0,0,0,.75);}
.kt-prog-chips{display:flex;gap:18px;flex-wrap:wrap;margin-top:10px;}
.kt-pchip{display:flex;flex-direction:column;align-items:center;}
.kt-pchip .pv{font-size:18px;font-weight:800;color:{$sacc};line-height:1;}
.kt-pchip .pl{font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:#aaa;font-weight:700;margin-top:2px;}
.kt-timeline{padding:14px 18px;}
.kt-tl-item{display:flex;gap:12px;margin-bottom:12px;align-items:flex-start;}
.kt-tl-item:last-child{margin-bottom:0;}
.kt-tl-dot{width:10px;height:10px;border-radius:50%;background:{$sacc};flex-shrink:0;margin-top:3px;}
.kt-tl-dot.dim{background:#ddd;}
.kt-tl-label{font-size:12px;font-weight:700;color:#555;}
.kt-tl-val{font-size:12px;color:#888;}
.kt-err-box{background:#fff5f5;border:1px solid #f5c6c2;border-radius:10px;margin-bottom:14px;overflow:hidden;}
.kt-err-hdr{padding:10px 18px;background:#fdecea;border-bottom:1px solid #f5c6c2;font-size:13px;font-weight:700;color:#8b1c13;display:flex;align-items:center;gap:8px;}
.kt-err-body{padding:14px 18px;font-size:13px;color:#7b241c;word-break:break-word;}
.kt-trace-toggle{cursor:pointer;color:#c0392b;font-size:12px;text-decoration:underline;margin-top:10px;display:inline-block;}
.kt-trace{display:none;margin-top:10px;padding:10px 14px;background:#fff;border:1px solid #e8c8c8;border-radius:6px;font-size:10.5px;line-height:1.5;color:#555;max-height:280px;overflow:auto;white-space:pre-wrap;word-break:break-all;}
.kt-dest-box{padding:14px 18px;}
.kt-dest-url{display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f0fff8;border:1px solid #a9dfbf;border-radius:8px;}
.kt-dest-url a{color:#1a6b3a;font-size:13px;font-weight:600;word-break:break-all;flex:1;text-decoration:none;}
.kt-dest-url a:hover{text-decoration:underline;}
.kt-live-indicator{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:#2b7a9e;background:#f0f9ff;border:1px solid #c5e8f7;border-radius:6px;padding:4px 10px;margin-left:10px;}
.kt-live-dot-sm{width:7px;height:7px;background:#00c853;border-radius:50%;animation:kt-pulse 1.4s infinite;}
@keyframes kt-stripe{from{background-position:0 0,0 0;}to{background-position:28px 0,0 0;}}
@keyframes kt-pulse{0%{transform:scale(1);box-shadow:0 0 0 0 rgba(0,200,83,.5);}70%{transform:scale(1.15);box-shadow:0 0 0 6px rgba(0,200,83,0);}100%{transform:scale(1);box-shadow:0 0 0 0 rgba(0,200,83,0);}}
</style>
<div class='kt-show-wrap'>

<!-- Hero bar -->
<div class='kt-hero'>
    {$poster}
    <div class='kt-hero-info'>
        <div class='kt-hero-badge'>
            <i class='fa {$statusIcon}'></i>
            <span id='sh-status-label'>" . e($t->status_label) . "</span>
            " . ($t->isActive() ? "<span class='kt-live-indicator'><span class='kt-live-dot-sm'></span> Live</span>" : '') . "
        </div>
        <h2 class='kt-hero-title'>" . e($t->movie_title ?? 'Untitled') . ($t->movie_year ? " <span style='font-weight:400;color:#aaa;font-size:16px;'>(" . e($t->movie_year) . ")</span>" : '') . "</h2>
        <div class='kt-hero-sub'>Transfer #" . $t->id . " &bull; Movie #" . ($t->movie_id ?? '—') . " &bull; Source: " . strtoupper($t->source_type ?? '?') . " &bull; Priority: <strong>" . number_format($t->priority ?? 0) . "</strong></div>
        <div class='kt-hero-actions'>
            " . ($t->isDone() && $destUrl ? "<a href='" . e($destUrl) . "' target='_blank' class='kt-ha kt-ha-green'><i class='fa fa-cloud-download'></i> Play from CDN</a>" : '') . "
            " . (($t->isRetriable() || $t->isFailed()) ? "<a href='/movie-file-transfers/{$id}/retry' class='kt-ha kt-ha-orange' onclick=\"return confirm('Retry transfer #{$id}?')\"><i class='fa fa-refresh'></i> Retry</a>" : '') . "
            " . (!$t->isDone() && !in_array($t->status, ['failed','cancelled','skipped']) ? "<a href='/movie-file-transfers/{$id}/cancel' class='kt-ha kt-ha-red' onclick=\"return confirm('Cancel transfer #{$id}?')\"><i class='fa fa-stop'></i> Cancel</a>" : '') . "
            " . ($t->movie_id ? "<a href='/movies-active/{$t->movie_id}/edit' class='kt-ha kt-ha-blue'><i class='fa fa-pencil'></i> Edit Movie</a>" : '') . "
            <a href='/movie-file-transfers' class='kt-ha kt-ha-grey'><i class='fa fa-arrow-left'></i> All Transfers</a>
            <a href='/movie-file-transfers/monitor' class='kt-ha kt-ha-grey'><i class='fa fa-heartbeat'></i> Monitor</a>
        </div>
    </div>
</div>";

        // Progress section — shown for active and for done
        if ($t->isActive() || $t->isDone()) {
            $fillClass = $t->status === 'completing' ? ' completing' : '';
            $html .= "
<div class='kt-card-section'>
    <div class='kt-sec-hdr'><i class='fa fa-tasks'></i> Progress" . ($t->isActive() ? " <span class='kt-live-indicator' style='margin-left:auto;'><span class='kt-live-dot-sm'></span> Updating live</span>" : '') . "</div>
    <div class='kt-prog-wrap'>
        <div class='kt-prog-big'>
            <div class='kt-prog-fill{$fillClass}' id='sh-prog-fill' style='width:" . max(2, $pct) . "%'></div>
            <span class='kt-prog-pct' id='sh-prog-pct'>{$pct}%</span>
        </div>
        <div class='kt-prog-chips'>
            <div class='kt-pchip'><div class='pv' id='sh-speed'>" . e($t->formatted_speed) . "</div><div class='pl'>Speed</div></div>
            <div class='kt-pchip'><div class='pv' id='sh-eta'>" . e($t->formatted_eta) . "</div><div class='pl'>ETA</div></div>
            <div class='kt-pchip'><div class='pv' id='sh-bytes'>" . (($t->bytes_transferred ?? 0) > 0 ? round(($t->bytes_transferred / 1_048_576), 0) . ' MB' : '—') . "</div><div class='pl'>Downloaded</div></div>
            <div class='kt-pchip'><div class='pv'>" . e($t->formatted_size) . "</div><div class='pl'>Total Size</div></div>
            " . ($t->isDone() ? "<div class='kt-pchip'><div class='pv'>" . e($t->formatted_duration) . "</div><div class='pl'>Duration</div></div>" : '') . "
        </div>
    </div>
</div>";
        }

        // Error section
        if ($t->error_message || $t->error_trace) {
            $html .= "
<div class='kt-err-box'>
    <div class='kt-err-hdr'><i class='fa fa-exclamation-circle'></i> Transfer Error — Attempt {$t->attempt_count}/{$t->max_attempts}</div>
    <div class='kt-err-body'>
        <strong>" . e($t->error_message ?? 'Unknown error') . "</strong>
        " . ($t->error_trace ? "
        <span class='kt-trace-toggle' onclick=\"var el=document.getElementById('kt-trace');el.style.display=el.style.display==='block'?'none':'block';\">
            <i class='fa fa-bug'></i> Stack Trace
        </span>
        <pre class='kt-trace' id='kt-trace'>" . e($t->error_trace) . "</pre>" : '') . "
    </div>
</div>";
        }

        // Video player / CDN destination
        if ($destUrl) {
            $html .= $this->renderVideoPlayer($t);
        }

        // Two-column: details + timeline
        $html .= "<div class='kt-2col'>";

        // Left: transfer details
        $html .= "
<div>
    <div class='kt-card-section'>
        <div class='kt-sec-hdr'><i class='fa fa-info-circle'></i> Transfer Details</div>
        <table class='kt-dt'>";
        $srcHtml = $t->source_url
            ? "<a href='" . e($t->source_url) . "' target='_blank' style='color:#2471a3;word-break:break-all'>" . $srcShort . ($srcFull !== $srcShort ? "…" : '') . "</a>"
            : '—';
        $detailRows = [
            ['Source URL',    $srcHtml],
            ['Source Type',   "<span class='label label-info'>" . strtoupper($t->source_type ?? '—') . "</span>"],
            ['Source Size',   e($t->formatted_size)],
            ['Worker',        e($t->worker_hostname ?? '—')],
            ['Initiated By',  e($t->initiated_by ?? '—')],
            ['Attempts',      $t->attempt_count . " / " . $t->max_attempts],
            ['Priority',      number_format($t->priority ?? 0)],
        ];
        foreach ($detailRows as [$lbl, $val]) {
            $html .= "<tr><td>" . e($lbl) . "</td><td>{$val}</td></tr>";
        }
        $html .= "</table></div>";

        // Notes (if any)
        if ($t->notes) {
            $html .= "
    <div class='kt-card-section'>
        <div class='kt-sec-hdr'><i class='fa fa-sticky-note'></i> Notes</div>
        <div style='padding:12px 18px;font-size:13px;color:#555;'>" . nl2br(e($t->notes)) . "</div>
    </div>";
        }
        $html .= "</div>";

        // Right: timeline
        $html .= "
<div>
    <div class='kt-card-section'>
        <div class='kt-sec-hdr'><i class='fa fa-history'></i> Timeline</div>
        <div class='kt-timeline'>";
        $tlItems = [
            ['Queued',    $t->queued_at,    $t->queued_at ? $t->queued_at->diffForHumans() : null],
            ['Started',   $t->started_at,   $t->started_at ? $t->started_at->diffForHumans() : null],
            ['Completed', $t->completed_at, $t->completed_at ? $t->completed_at->diffForHumans() : null],
        ];
        foreach ($tlItems as [$label, $ts, $ago]) {
            $dotClass = $ts ? '' : ' dim';
            $valHtml = $ts
                ? "<span class='kt-tl-val'>" . e($ts->format('Y-m-d H:i:s')) . " <em style='font-size:11px;'>(" . e($ago) . ")</em></span>"
                : "<span class='kt-tl-val'>—</span>";
            $html .= "
            <div class='kt-tl-item'>
                <div class='kt-tl-dot{$dotClass}'></div>
                <div>
                    <div class='kt-tl-label'>" . e($label) . "</div>
                    {$valHtml}
                </div>
            </div>";
        }
        $html .= "</div></div>

    <div class='kt-card-section'>
        <div class='kt-sec-hdr'><i class='fa fa-film'></i> Movie Record</div>
        <table class='kt-dt'>";
        $movieRows = [
            ['Movie ID',   $t->movie_id ?? '—'],
            ['Title',      e($t->movie_title ?? '—')],
            ['Year',       e($t->movie_year ?? '—')],
            ['URL Updated', $t->movie_url_updated ? '<span style="color:#27ae60;font-weight:700;">Yes</span>' : '<span style="color:#888;">No</span>'],
        ];
        foreach ($movieRows as [$lbl, $val]) {
            $html .= "<tr><td>" . e($lbl) . "</td><td>{$val}</td></tr>";
        }
        $html .= "</table>
        " . ($t->movie_id ? "<div style='padding:8px 18px 14px;'><a href='/movies-active/{$t->movie_id}/edit' class='kt-ha kt-ha-blue' style='font-size:12px;padding:6px 12px;'><i class='fa fa-pencil'></i> Edit Movie Record</a></div>" : '') . "
    </div>
</div>
</div>

{$liveJs}
</div>";

        return $content
            ->title("Transfer #{$id} — " . e($t->movie_title ?? ''))
            ->description('')
            ->body($html);
    }

    private function renderVideoPlayer(MovieFileTransfer $t): string
    {
        $url  = $t->dest_url;
        $path = $t->dest_path ?? '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'mp4';

        $mimeMap = [
            'mp4'  => 'video/mp4',
            'm4v'  => 'video/mp4',
            'mov'  => 'video/mp4',
            'webm' => 'video/webm',
            'ogg'  => 'video/ogg',
            'mkv'  => 'video/x-matroska',
            'avi'  => 'video/x-msvideo',
        ];
        $videoExts = array_keys($mimeMap);

        $eUrl     = e($url);
        $ePath    = e($path);
        $eName    = e(basename($path));
        // $eTitle unused — kept for future tooltip use
        $size     = e($t->formatted_size);
        $speed    = $t->formatted_speed;
        $dur      = $t->formatted_duration;
        $updated  = $t->movie_url_updated;
        $poster   = $t->movie_poster_url ? e($t->movie_poster_url) : '';

        if (in_array($ext, $videoExts)) {
            $mime   = $mimeMap[$ext];
            $native = in_array($ext, ['mp4', 'm4v', 'mov', 'webm', 'ogg']);
            $warn   = !$native
                ? "<div style='padding:8px 18px;background:#fff8e1;border-top:1px solid #ffe082;font-size:12px;color:#7a5c00;'>
                       <i class='fa fa-exclamation-triangle'></i>
                       <strong>{$ext}</strong> may not play in all browsers — if the player stays black, use Download or Open in Tab.
                   </div>"
                : '';
            return "
<script>
function ktCopyUrl(url, btn) {
    navigator.clipboard.writeText(url).then(function() {
        btn.innerHTML = '<i class=\"fa fa-check\"></i> Copied!';
        setTimeout(function() { btn.innerHTML = '<i class=\"fa fa-copy\"></i> Copy URL'; }, 2000);
    }).catch(function() { prompt('Copy this URL:', url); });
}
</script>
<div class='kt-card-section' id='kt-player-section'>
    <div class='kt-sec-hdr' style='background:linear-gradient(135deg,#0d1b2a,#1b3a5c);color:#fff;border-bottom:none;padding:13px 18px;'>
        <i class='fa fa-play-circle' style='color:#00e676;font-size:17px;'></i>
        <span style='font-size:13px;font-weight:700;'>Video Preview</span>
        <span style='margin-left:8px;font-size:11px;font-weight:400;opacity:.65;'>Streaming directly from Hetzner CDN · HTTP/2 · Range requests ✓</span>
        <span style='margin-left:auto;background:rgba(255,255,255,.1);padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;'>{$size} &bull; " . strtoupper($ext) . "</span>
    </div>
    <div style='background:#000;position:relative;'>
        <video id='kt-video-player' controls preload='metadata' style='width:100%;max-height:560px;display:block;outline:none;'
               " . ($poster ? "poster='{$poster}'" : '') . ">
            <source src='{$eUrl}' type='{$mime}'>
            <div style='color:#aaa;padding:40px;text-align:center;font-size:14px;'>
                Your browser cannot play this format inline.
                <a href='{$eUrl}' style='color:#00e676;'>Download the file</a>.
            </div>
        </video>
    </div>
    {$warn}
    <div style='padding:11px 18px;background:#fff;display:flex;align-items:center;gap:10px;flex-wrap:wrap;border-top:1px solid #f0f0f0;'>
        <code style='background:#f4f4f4;border:1px solid #e8e8e8;padding:3px 8px;border-radius:4px;font-size:11px;color:#555;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;' title='{$ePath}'>{$ePath}</code>
        <div style='display:flex;gap:7px;flex-shrink:0;align-items:center;'>
            " . ($updated ? "<span style='font-size:11px;color:#27ae60;font-weight:700;'><i class='fa fa-check-circle'></i> App URL Updated</span>"
                          : "<span style='font-size:11px;color:#e67e22;'><i class='fa fa-warning'></i> App URL Pending</span>") . "
            <button onclick=\"ktCopyUrl('{$eUrl}', this)\" class='kt-ha kt-ha-grey' style='font-size:12px;padding:5px 12px;'>
                <i class='fa fa-copy'></i> Copy URL
            </button>
            <a href='{$eUrl}' target='_blank' class='kt-ha kt-ha-blue' style='font-size:12px;padding:5px 12px;'>
                <i class='fa fa-external-link'></i> New Tab
            </a>
            <a href='{$eUrl}' download='{$eName}' class='kt-ha kt-ha-green' style='font-size:12px;padding:5px 12px;'>
                <i class='fa fa-download'></i> Download
            </a>
        </div>
    </div>
    " . ($speed !== '—' ? "
    <div style='padding:7px 18px;background:#fafafa;border-top:1px solid #f5f5f5;font-size:12px;color:#aaa;display:flex;gap:16px;flex-wrap:wrap;'>
        <span><i class='fa fa-tachometer'></i> Transfer speed: <strong style='color:#555;'>{$speed}</strong></span>
        " . ($dur !== '—' ? "<span><i class='fa fa-clock-o'></i> Transfer time: <strong style='color:#555;'>{$dur}</strong></span>" : '') . "
        <span><i class='fa fa-server'></i> Worker: <strong style='color:#555;'>" . e($t->worker_hostname ?? '—') . "</strong></span>
    </div>" : '') . "
</div>";
        }

        // Non-video file — generic download card
        return "
<div class='kt-card-section'>
    <div class='kt-sec-hdr'><i class='fa fa-cloud'></i> Stored on Hetzner CDN</div>
    <div class='kt-dest-box'>
        <div class='kt-dest-url'>
            <i class='fa fa-file-o' style='color:#00a65a;font-size:18px;flex-shrink:0;'></i>
            <a href='{$eUrl}' target='_blank' style='word-break:break-all;'>{$eUrl}</a>
            <a href='{$eUrl}' target='_blank' class='kt-ha kt-ha-green' style='flex-shrink:0;font-size:12px;padding:5px 12px;'>
                <i class='fa fa-download'></i> Download
            </a>
        </div>
        <div style='margin-top:8px;font-size:12px;color:#888;'>
            <i class='fa fa-folder-open'></i> <code>{$ePath}</code> &bull;
            Movie URL updated: " . ($updated ? "<strong style='color:#27ae60;'>Yes</strong>" : "<span style='color:#e67e22;'>No</span>") . "
        </div>
    </div>
</div>";
    }

    private function renderShowLiveJs(int $transferId): string
    {
        return "<script>
(function() {
    var tid = {$transferId};
    var poll = function() {
        fetch('/movie-file-transfers/live-data', {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json(); })
        .then(function(data) {
            var t = null;
            data.active.forEach(function(a){ if (a.id == tid) t = a; });
            if (!t) return; // transfer finished — stop updating
            var fill = document.getElementById('sh-prog-fill');
            var pct  = document.getElementById('sh-prog-pct');
            var spd  = document.getElementById('sh-speed');
            var eta  = document.getElementById('sh-eta');
            var byt  = document.getElementById('sh-bytes');
            if (fill) { fill.style.width = Math.max(2,t.progress_pct) + '%'; fill.className='kt-prog-fill'+(t.status==='completing'?' completing':''); }
            if (pct)  pct.textContent  = t.progress_pct + '%';
            if (spd)  spd.textContent  = t.speed_human || '—';
            if (eta)  eta.textContent  = t.eta_human || '—';
            if (byt)  byt.textContent  = t.bytes_human || '—';
            var lbl = document.getElementById('sh-status-label');
            if (lbl) lbl.textContent = t.status_label;
            setTimeout(poll, 3000);
        })
        .catch(function(){ setTimeout(poll, 8000); });
    };
    setTimeout(poll, 3000);
})();
</script>";
    }

    // ── JSON API: live transfer status ───────────────────────────────────────

    public function liveStatus(): \Illuminate\Http\JsonResponse
    {
        $stats   = $this->getStats();
        $actives = MovieFileTransfer::active()->orderBy('started_at')->get();
        $failed  = MovieFileTransfer::failed()->orderByDesc('updated_at')->limit(15)->get();
        $nextQ   = MovieFileTransfer::pending()->orderByDesc('priority')->orderBy('queued_at')->limit(8)->get();
        $recent  = MovieFileTransfer::done()->orderByDesc('completed_at')->limit(6)->get();

        return response()->json([
            'stats'          => $stats,
            'active'         => $actives->map(fn($t) => $this->formatTransfer($t))->values(),
            'failed'         => $failed->map(fn($t) => $this->formatTransfer($t))->values(),
            'next_queue'     => $nextQ->map(fn($t) => $this->formatTransfer($t))->values(),
            'recent_done'    => $recent->map(fn($t) => $this->formatTransfer($t))->values(),
            'max_concurrent' => TransferMovieToHetzner::MAX_CONCURRENT,
            'ts'             => now()->format('H:i:s'),
        ]);
    }

    private function formatTransfer(MovieFileTransfer $t): array
    {
        $mb = ($t->bytes_transferred ?? 0) / 1_048_576;
        $bytesHuman = $mb > 0
            ? ($mb >= 1024 ? round($mb / 1024, 1) . ' GB' : round($mb, 0) . ' MB')
            : '—';

        return [
            'id'               => $t->id,
            'movie_id'         => $t->movie_id,
            'movie_title'      => $t->movie_title,
            'movie_year'       => $t->movie_year,
            'movie_poster_url' => $t->movie_poster_url,
            'status'           => $t->status,
            'status_label'     => $t->status_label,
            'status_color'     => $t->status_badge_color,
            'priority'         => $t->priority ?? 0,
            'progress_pct'     => $t->progress_pct ?? 0,
            'bytes_human'      => $bytesHuman,
            'size_human'       => $t->formatted_size,
            'speed_human'      => $t->formatted_speed,
            'eta_human'        => $t->formatted_eta,
            'duration_human'   => $t->formatted_duration,
            'worker'           => $t->worker_hostname,
            'attempt_count'    => $t->attempt_count ?? 0,
            'max_attempts'     => $t->max_attempts ?? 3,
            'error_message'    => $t->error_message ? substr($t->error_message, 0, 100) : null,
            'source_type'      => $t->source_type,
            'source_url'       => $t->source_url,
            'dest_url'         => $t->dest_url,
            'started_ago'      => $t->started_at   ? $t->started_at->diffForHumans()   : null,
            'completed_ago'    => $t->completed_at ? $t->completed_at->diffForHumans() : null,
            'queued_ago'       => $t->queued_at    ? $t->queued_at->diffForHumans()    : null,
            'is_active'        => $t->isActive(),
            'is_done'          => $t->isDone(),
            'is_failed'        => $t->isFailed(),
            'is_retriable'     => $t->isRetriable(),
        ];
    }

    // ── Preview modal (injected once into the index page) ─────────────────────

    private function renderPreviewModal(): string
    {
        return '
<!-- Preview modal -->
<div class="modal fade" id="kt-preview-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="width:92%;max-width:1000px;margin:30px auto;" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.5);">
            <div class="modal-header" style="background:linear-gradient(135deg,#0d1b2a,#1b3a5c);color:#fff;padding:14px 20px;border:none;display:flex;align-items:center;gap:12px;">
                <img id="kt-pm-poster" src="" style="width:36px;height:54px;object-fit:cover;border-radius:4px;display:none;flex-shrink:0;">
                <div style="flex:1;min-width:0;">
                    <div id="kt-pm-title" style="font-size:15px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
                    <div id="kt-pm-meta" style="font-size:11px;opacity:.65;margin-top:2px;"></div>
                </div>
                <a id="kt-pm-open" href="#" target="_blank" class="btn btn-xs btn-default" style="flex-shrink:0;">
                    <i class="fa fa-external-link"></i> Open Tab
                </a>
                <a id="kt-pm-dl" href="#" class="btn btn-xs btn-success" style="flex-shrink:0;">
                    <i class="fa fa-download"></i> Download
                </a>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;font-size:24px;line-height:1;opacity:.7;margin-left:8px;">&times;</button>
            </div>
            <div class="modal-body" style="padding:0;background:#000;line-height:0;">
                <video id="kt-pm-video" controls preload="metadata"
                       style="width:100%;max-height:65vh;display:block;outline:none;">
                </video>
            </div>
            <div style="padding:8px 16px;background:#111;display:flex;align-items:center;gap:10px;">
                <span style="font-size:11px;color:#555;">Range requests: HTTP/2 · Hetzner Nextcloud CDN</span>
                <div id="kt-pm-urlbar" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:10px;color:#333;font-family:monospace;"></div>
                <button onclick="ktPmCopy()" class="btn btn-xs btn-default" style="flex-shrink:0;font-size:11px;">
                    <i class="fa fa-copy"></i> Copy URL
                </button>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var pmUrl = "";

    // Open modal when any play button is clicked (event delegation)
    document.addEventListener("click", function(e) {
        var btn = e.target.closest(".kt-play-btn");
        if (!btn) return;
        e.preventDefault();
        pmUrl = btn.dataset.url || "";
        var title  = btn.dataset.title  || "Movie";
        var year   = btn.dataset.year   || "";
        var size   = btn.dataset.size   || "";
        var poster = btn.dataset.poster || "";

        var v = document.getElementById("kt-pm-video");
        v.src = pmUrl;
        v.load();

        var img = document.getElementById("kt-pm-poster");
        if (poster) { img.src = poster; img.style.display = "block"; }
        else { img.style.display = "none"; }

        document.getElementById("kt-pm-title").textContent = title + (year ? " (" + year + ")" : "");
        document.getElementById("kt-pm-meta").textContent  = "Size: " + size + " · Hetzner CDN";
        document.getElementById("kt-pm-open").href = pmUrl;
        document.getElementById("kt-pm-dl").href   = pmUrl;
        document.getElementById("kt-pm-urlbar").textContent = pmUrl;

        $("#kt-preview-modal").modal("show");
    });

    // Stop video when modal closes
    $("#kt-preview-modal").on("hidden.bs.modal", function() {
        var v = document.getElementById("kt-pm-video");
        v.pause();
        v.removeAttribute("src");
        v.load();
    });

    window.ktPmCopy = function() {
        if (!pmUrl) return;
        navigator.clipboard.writeText(pmUrl).then(function() {
            var btn = document.querySelector(".kt-pm-copy");
            if (btn) { btn.textContent = "Copied!"; setTimeout(function() { btn.textContent = "Copy URL"; }, 2000); }
        }).catch(function() { prompt("Copy URL:", pmUrl); });
    };
})();
</script>';
    }

    // ── Dashboard CSS ─────────────────────────────────────────────────────────

    private function renderDashboardCss(): string
    {
        return '<style>
/* ══════════════════════════════════════════════════════════════
   Katogo Transfer Dashboard — Compact 2-Column Theme
══════════════════════════════════════════════════════════════ */

/* ── Stat row ──────────────────────────────────────────────── */
.kt-stat-row{display:flex;gap:0;margin-bottom:0;border:1px solid #d8dde4;border-bottom:none;}
.kt-stat-box{
    position:relative;flex:1;min-width:90px;background:#fff;
    border-radius:0;padding:10px 14px 8px;
    border-right:1px solid #d8dde4;text-decoration:none;overflow:hidden;
    border-top:3px solid var(--ac,#2980b9);
    transition:background 0.1s;}
.kt-stat-box:last-child{border-right:none;}
.kt-stat-box:hover{background:#f8f9fb;text-decoration:none;}
.kt-stat-box .v{font-size:20px;font-weight:700;color:#1a2332;
    font-family:system-ui,sans-serif;line-height:1;margin-bottom:3px;}
.kt-stat-box .l{font-size:10px;text-transform:uppercase;letter-spacing:1px;
    color:#8b96a5;font-weight:700;}
.kt-stat-box .ico{position:absolute;right:10px;top:50%;transform:translateY(-50%);
    font-size:26px;opacity:0.04;color:#000;}

/* ── Compact toolbar ────────────────────────────────────────── */
.kt-toolbar{display:flex;align-items:center;justify-content:space-between;
    gap:8px;padding:6px 10px;background:#fff;
    border:1px solid #d8dde4;border-top:none;margin-bottom:10px;flex-wrap:wrap;}
.kt-toolbar-left{display:flex;align-items:center;gap:8px;flex:1;min-width:0;flex-wrap:wrap;}
.kt-toolbar-right{display:flex;align-items:center;gap:4px;flex-shrink:0;flex-wrap:wrap;}
.kt-sync-bar-inline{display:flex;align-items:center;gap:6px;padding:3px 8px;
    background:#f4f8fd;border:1px solid #c5d8ee;border-left:3px solid #2980b9;
    font-size:11px;color:#2c5f8a;white-space:nowrap;}
.kt-sync-bar-inline.err{background:#fff5f5;border-color:#f5c6c2;border-left-color:#c0392b;color:#9b2c2c;}
.kt-tbtn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;
    font-size:11px;font-weight:700;text-decoration:none;border:1px solid transparent;
    cursor:pointer;transition:filter .12s;white-space:nowrap;}
.kt-tbtn:hover{filter:brightness(.9);text-decoration:none;}
.kt-tbtn-primary{background:#2980b9;color:#fff;}
.kt-tbtn-warning{background:#d68910;color:#fff;}
.kt-tbtn-success{background:#27ae60;color:#fff;}
.kt-tbtn-neutral{background:#eceff1;color:#37474f;border-color:#cfd8dc;}

/* ── Section boxes ──────────────────────────────────────────── */
.kt-live-box{background:#fff;border:1px solid #d8dde4;margin-bottom:10px;}
.kt-live-hdr{display:flex;justify-content:space-between;align-items:center;
    padding:7px 14px;background:#fff;border-bottom:2px solid #1e3a5f;}
.kt-live-hdr-title{font-size:11px;font-weight:700;color:#1e3a5f;
    text-transform:uppercase;letter-spacing:.6px;display:flex;gap:7px;align-items:center;}
.kt-live-dot{width:6px;height:6px;background:#27ae60;flex-shrink:0;
    animation:kt-blink 2s ease-in-out infinite;}
.kt-live-count{font-size:11px;color:#888;font-weight:600;}
@keyframes kt-blink{0%,100%{opacity:1;}50%{opacity:.25;}}
@keyframes kt-pulse{0%,100%{opacity:1;}50%{opacity:.25;}}

/* ── 2-column card grid ─────────────────────────────────────── */
.kt-cards-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;}
@media(max-width:900px){.kt-cards-grid{grid-template-columns:1fr;}}
.kt-empty-full{grid-column:1/-1;}

/* ── Transfer cards (compact) ───────────────────────────────── */
.kt-card{padding:8px 12px;border-left:3px solid #27ae60;
    border-bottom:1px solid #eff1f4;border-right:1px solid #eff1f4;
    background:#fff;transition:background .1s;}
.kt-card.completing{border-left-color:#d68910;}
.kt-card.verifying{border-left-color:#2980b9;}
.kt-card:hover{background:#fafbfc;}
.kt-card-hdr{display:flex;justify-content:space-between;align-items:center;
    margin-bottom:5px;gap:6px;}
.kt-card-info{display:flex;align-items:center;gap:6px;flex:1;min-width:0;}
.kt-movie-num{color:#adb5bd;font-size:10px;white-space:nowrap;
    background:#f5f6fa;padding:1px 5px;border:1px solid #e9ecef;font-family:monospace;}
.kt-movie-name{font-size:12px;font-weight:700;color:#1a2332;white-space:nowrap;
    overflow:hidden;text-overflow:ellipsis;max-width:200px;}
.kt-card-acts{display:flex;align-items:center;gap:4px;flex-shrink:0;}
.kt-elapsed{font-size:10px;color:#adb5bd;white-space:nowrap;}

/* ── Action buttons ─────────────────────────────────────────── */
.kt-abtn{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;
    border-radius:0;font-size:10px;font-weight:600;text-decoration:none;
    border:1px solid transparent;cursor:pointer;transition:opacity .12s;}
.kt-abtn:hover{opacity:.8;text-decoration:none;}
.kt-abtn-info{background:#ebf5fb;color:#1a5f8a;border-color:#aed6f1;}
.kt-abtn-danger{background:#fdedec;color:#c0392b;border-color:#f1948a;}
.kt-abtn-warning{background:#fef9e7;color:#9a7d0a;border-color:#f9e79f;}
.kt-abtn-success{background:#eafaf1;color:#1e8449;border-color:#a9dfbf;}

/* ── Slim progress bar ──────────────────────────────────────── */
.kt-prog-row{display:flex;align-items:center;gap:6px;margin:4px 0 4px;}
.kt-prog{flex:1;position:relative;height:8px;background:#eceff1;
    border:1px solid #d8dde4;overflow:hidden;}
.kt-fill{position:absolute;left:0;top:0;bottom:0;background:#27ae60;
    background-image:repeating-linear-gradient(
        -45deg,transparent,transparent 5px,
        rgba(255,255,255,.15) 5px,rgba(255,255,255,.15) 10px);
    animation:kt-stripe .9s linear infinite;
    transition:width 1.2s cubic-bezier(.25,.46,.45,.94);}
.kt-fill.completing{background:#d68910;}
@keyframes kt-stripe{to{background-position:10px 0;}}
.kt-pct{font-size:10px;font-weight:700;color:#546e7a;white-space:nowrap;min-width:30px;text-align:right;}

/* ── Chips (one-line meta) ──────────────────────────────────── */
.kt-chips{display:flex;flex-wrap:nowrap;gap:10px;overflow:hidden;}
.kt-chip{display:flex;align-items:center;gap:3px;font-size:10px;color:#78909c;}
.kt-chip i{font-size:9px;color:#b0bec5;}
.kt-chip .cv{font-weight:600;color:#37474f;}
.kt-chip.hl .cv{color:#1a6b3a;}

/* ── Sync / status bar (standalone) ────────────────────────── */
.kt-sync-bar{display:flex;align-items:center;gap:8px;padding:6px 12px;
    background:#f4f8fd;border:1px solid #c5d8ee;border-left:3px solid #2980b9;
    margin-bottom:10px;font-size:12px;color:#2c5f8a;}
.kt-sync-bar.err{background:#fff5f5;border-color:#f5c6c2;border-left-color:#c0392b;color:#9b2c2c;}
.kt-sdot{width:7px;height:7px;background:#27ae60;flex-shrink:0;
    animation:kt-blink 2s ease-in-out infinite;}
.kt-sdot.err{background:#c0392b;animation:none;}
.kt-stime{margin-left:auto;color:#adb5bd;font-size:11px;}

/* ── Pipeline controls (kept for backward compat) ──────────── */
.kt-ctrl-box{background:#fff;border:1px solid #d8dde4;}
.kt-ctrl-hdr{padding:9px 14px;background:#1e3a5f;color:#fff;font-size:11px;
    font-weight:700;text-transform:uppercase;letter-spacing:.7px;
    display:flex;align-items:center;gap:8px;}
.kt-ctrl-body{padding:6px;}
.kt-ctrl-btn{display:flex;align-items:center;gap:8px;width:100%;padding:7px 12px;
    border-radius:0;font-size:12px;font-weight:600;text-decoration:none;
    margin-bottom:3px;border:none;cursor:pointer;transition:filter .12s;}
.kt-ctrl-btn:hover{filter:brightness(.92);text-decoration:none;}
.kt-ctrl-btn:last-child{margin-bottom:0;}
.kt-ctrl-a{background:#2980b9;color:#fff;}
.kt-ctrl-b{background:#d68910;color:#fff;}
.kt-ctrl-c{background:#eceff1;color:#37474f;border:1px solid #cfd8dc;}
.kt-ctrl-d{background:#27ae60;color:#fff;}

/* ── Alert bars ─────────────────────────────────────────────── */
.kt-alert-bar{padding:5px 10px;margin-bottom:4px;font-size:11px;
    display:flex;align-items:center;gap:6px;}
.kt-alert-bar.warn{background:#fffbf0;border:1px solid #f9e79f;
    border-left:3px solid #d68910;color:#7d6608;}
.kt-alert-bar.err{background:#fff5f5;border:1px solid #fed7d7;
    border-left:3px solid #c0392b;color:#9b2c2c;}
.kt-alert-bar.ok{background:#f0fdf4;border:1px solid #bbf7d0;
    border-left:3px solid #27ae60;color:#166534;}

/* ── Empty state ─────────────────────────────────────────────── */
.kt-empty-state{padding:20px 16px;text-align:center;color:#adb5bd;font-size:12px;}
.kt-empty-state i{display:block;font-size:22px;margin-bottom:6px;opacity:.4;}

/* ── Mini tables ─────────────────────────────────────────────── */
.kt-table-mini{width:100%;font-size:12px;border-collapse:collapse;}
.kt-table-mini thead tr th{background:#f5f6fa;padding:6px 10px;font-weight:700;
    color:#6c757d;border-bottom:2px solid #dee2e6;text-align:left;
    font-size:11px;text-transform:uppercase;letter-spacing:.5px;}
.kt-table-mini tbody tr td{padding:6px 10px;border-bottom:1px solid #f2f3f5;vertical-align:middle;}
.kt-table-mini tbody tr:hover{background:#f8f9fa;}
.kt-table-mini tbody tr:last-child td{border-bottom:none;}

/* ── Priority badges ─────────────────────────────────────────── */
.kt-pri-max{background:#fdedec;color:#c0392b;padding:1px 5px;font-size:10px;
    font-weight:700;border:1px solid #f5c6c2;}
.kt-pri-hi{background:#fef9e7;color:#9a7d0a;padding:1px 5px;font-size:10px;
    font-weight:700;border:1px solid #f9e79f;}
.kt-pri-lo{background:#f5f6fa;color:#6c757d;padding:1px 5px;font-size:10px;
    font-weight:700;border:1px solid #dee2e6;}

/* ── Live indicator (small) ──────────────────────────────────── */
.kt-live-indicator{display:inline-flex;align-items:center;gap:5px;font-size:11px;
    color:#1a5f8a;background:#f0f7ff;border:1px solid #c5daf0;padding:3px 8px;}
.kt-live-dot-sm{width:6px;height:6px;background:#27ae60;flex-shrink:0;
    animation:kt-blink 2s ease-in-out infinite;}
</style>';
    }

    // ── Live JS (index page) ──────────────────────────────────────────────────

    private function renderLiveJs(int $maxConcurrent): string
    {
        return '<script>
(function() {
    var MAX_CON  = ' . $maxConcurrent . ';
    var POLL     = 3000;
    var IDLE_POLL = 10000;
    var errCount = 0;
    var pollTimer = null;

    function esc(s) {
        return String(s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;")
            .replace(/>/g,"&gt;").replace(/"/g,"&quot;");
    }
    function fmt(n) { return (n||0).toLocaleString(); }

    function poll() {
        fetch("/movie-file-transfers/live-data", {
            headers:{"X-Requested-With":"XMLHttpRequest","Accept":"application/json"}
        })
        .then(function(r) {
            if (!r.ok) throw new Error("HTTP " + r.status);
            return r.json();
        })
        .then(function(data) {
            errCount = 0;
            updateStats(data.stats, data.max_concurrent);
            updateAlerts(data.stats, data.active.length);
            updateLiveSection(data.active);
            setSyncBar(true, data.ts);
            clearTimeout(pollTimer);
            pollTimer = setTimeout(poll, data.active.length > 0 ? POLL : IDLE_POLL);
        })
        .catch(function(err) {
            errCount++;
            setSyncBar(false, null);
            clearTimeout(pollTimer);
            pollTimer = setTimeout(poll, Math.min(30000, POLL * Math.pow(2, errCount)));
        });
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el && el.textContent !== String(val)) el.textContent = val;
    }

    function updateStats(s, mc) {
        setText("stat-queued", fmt(s.queued));
        setText("stat-active", s.active + "/" + (mc||MAX_CON));
        setText("stat-done",   fmt(s.done));
        setText("stat-failed", fmt(s.failed));
        setText("stat-speed",  s.avg_speed||"—");
        setText("stat-stored", (s.stored_gb||0) + " GB");
    }

    function updateAlerts(s, activeCount) {
        var sec = document.getElementById("kt-alerts-section");
        if (!sec) return;
        var html = "";
        if (activeCount > 0) {
            html += "<div class=\"kt-alert-bar\" style=\"background:#e8f4fd;border:1px solid #b8d9f5;color:#1a5f8a;\">" +
                "<span style=\"width:8px;height:8px;background:#3498db;border-radius:50%;flex-shrink:0;animation:kt-pulse 1.4s infinite;display:inline-block;\"></span>" +
                "<strong>" + activeCount + " transfer" + (activeCount > 1 ? "s" : "") + " in progress.</strong>" +
                "</div>";
        }
        if (s.not_queued > 0) {
            html += "<div class=\"kt-alert-bar warn\">" +
                "<i class=\"fa fa-exclamation-triangle\"></i>" +
                "<strong>" + fmt(s.not_queued) + "</strong> movies need queuing. " +
                "<a href=\"/movie-file-transfers/backfill\" class=\"kt-abtn kt-abtn-warning\" style=\"margin-left:6px;\">" +
                "<i class=\"fa fa-database\"></i> Backfill</a>" +
                "</div>";
        }
        if (s.failed > 0) {
            html += "<div class=\"kt-alert-bar err\">" +
                "<i class=\"fa fa-times-circle\"></i>" +
                "<strong>" + fmt(s.failed) + "</strong> transfers failed. " +
                "<a href=\"/movie-file-transfers?status=failed\" class=\"kt-abtn kt-abtn-danger\" style=\"margin-left:6px;\"><i class=\"fa fa-eye\"></i> View</a>" +
                "<a href=\"/movie-file-transfers/retry-all-failed\" class=\"kt-abtn kt-abtn-warning\" style=\"margin-left:4px;\" onclick=\"return confirm(\'Retry all?\')\"><i class=\"fa fa-refresh\"></i> Retry All</a>" +
                "</div>";
        }
        if (!html && s.done > 0) {
            html = "<div class=\"kt-alert-bar ok\"><i class=\"fa fa-check-circle\"></i> Pipeline clear — " + fmt(s.done) + " movies on Hetzner CDN.</div>";
        }
        sec.innerHTML = html;
    }

    function updateLiveSection(actives) {
        var wrap  = document.getElementById("active-cards-wrap");
        var empty = document.getElementById("active-cards-empty");
        var badge = document.getElementById("live-count-badge");
        if (!wrap) return;

        if (badge) badge.textContent = actives.length > 0 ? actives.length + " running" : "Idle";

        // Remove stale cards
        var existing = {};
        wrap.querySelectorAll(".kt-card[data-tid]").forEach(function(el) {
            existing[el.dataset.tid] = el;
        });
        var activeIds = {};
        actives.forEach(function(t) { activeIds[t.id] = true; });
        Object.keys(existing).forEach(function(id) {
            if (!activeIds[id]) {
                var el = existing[id];
                el.style.opacity = "0";
                el.style.transition = "opacity 0.3s";
                setTimeout(function() { el.parentNode && el.parentNode.removeChild(el); }, 300);
            }
        });

        if (empty) empty.style.display = actives.length === 0 ? "block" : "none";

        actives.forEach(function(t) {
            var card = wrap.querySelector(".kt-card[data-tid=\"" + t.id + "\"]");
            if (card) {
                updateCardEl(card, t);
            } else {
                var tmp = document.createElement("div");
                tmp.innerHTML = buildCardHtml(t);
                var newCard = tmp.firstElementChild;
                newCard.style.opacity = "0";
                if (empty && empty.parentNode === wrap) {
                    wrap.insertBefore(newCard, empty);
                } else {
                    wrap.appendChild(newCard);
                }
                setTimeout(function() { newCard.style.opacity = "1"; newCard.style.transition = "opacity 0.4s"; }, 10);
            }
        });
    }

    function updateCardEl(card, t) {
        var fill = card.querySelector(".kt-fill");
        if (fill) {
            fill.style.width = Math.max(2, t.progress_pct) + "%";
            fill.className = "kt-fill" + (t.status === "completing" ? " completing" : "");
        }
        var pct = card.querySelector(".kt-pct");
        if (pct) pct.textContent = t.progress_pct + "%";
        setChip(card, "speed", t.speed_human);
        setChip(card, "eta",   "ETA: " + t.eta_human);
        setChip(card, "bytes", t.bytes_human + " of " + t.size_human);
        var elapEl = card.querySelector(".kt-elapsed");
        if (elapEl && t.started_ago) elapEl.textContent = t.started_ago;
    }

    function setChip(card, key, val) {
        var el = card.querySelector("[data-chip=\"" + key + "\"]");
        if (el) el.textContent = val;
    }

    function buildCardHtml(t) {
        var pct   = Math.max(2, t.progress_pct);
        var cls   = t.status === "completing" ? " completing" : (t.status === "verifying" ? " verifying" : "");
        var fillC = t.status === "completing" ? " completing" : "";
        var badge = badgeHtml(t.status_color, t.status_label.toUpperCase());
        var started = t.started_ago || "—";
        return "<div class=\"kt-card" + cls + "\" data-tid=\"" + t.id + "\">" +
            "<div class=\"kt-card-hdr\">" +
                "<div class=\"kt-card-info\">" +
                    "<span class=\"kt-movie-num\">#" + t.id + "</span>" +
                    "<strong class=\"kt-movie-name\">" + esc(t.movie_title || "Untitled") + "</strong>" +
                    badge +
                "</div>" +
                "<div class=\"kt-card-acts\">" +
                    "<span class=\"kt-elapsed\">" + esc(started) + "</span>" +
                    "<a href=\"/movie-file-transfers/" + t.id + "\" class=\"kt-abtn kt-abtn-info\"><i class=\"fa fa-eye\"></i></a>" +
                    "<a href=\"/movie-file-transfers/" + t.id + "/cancel\" class=\"kt-abtn kt-abtn-danger\" onclick=\"return confirm(\'Stop #" + t.id + "?\')\"><i class=\"fa fa-stop-circle\"></i></a>" +
                "</div>" +
            "</div>" +
            "<div class=\"kt-prog-row\">" +
                "<div class=\"kt-prog\">" +
                    "<div class=\"kt-fill" + fillC + "\" style=\"width:" + pct + "%\"></div>" +
                "</div>" +
                "<span class=\"kt-pct\">" + t.progress_pct + "%</span>" +
            "</div>" +
            "<div class=\"kt-chips\">" +
                "<div class=\"kt-chip hl\"><i class=\"fa fa-tachometer\"></i><span class=\"cv\" data-chip=\"speed\">" + esc(t.speed_human) + "</span></div>" +
                "<div class=\"kt-chip\"><i class=\"fa fa-download\"></i><span class=\"cv\" data-chip=\"bytes\">" + esc(t.bytes_human) + "/" + esc(t.size_human) + "</span></div>" +
                "<div class=\"kt-chip hl\"><i class=\"fa fa-clock-o\"></i><span class=\"cv\" data-chip=\"eta\">" + esc(t.eta_human) + "</span></div>" +
                "<div class=\"kt-chip\"><i class=\"fa fa-repeat\"></i><span class=\"cv\">" + t.attempt_count + "/" + t.max_attempts + "</span></div>" +
            "</div>" +
        "</div>";
    }

    function badgeHtml(color, label) {
        var map = {
            primary:"#d6eaf8:#1a5276", info:"#d6eaf8:#1a5276",
            warning:"#fef6e0:#9a6c00", success:"#d5f5e3:#1a6b3a",
            danger:"#fdedec:#8b1c13",  "default":"#eee:#666"
        };
        var parts = (map[color] || map["default"]).split(":");
        return "<span style=\"background:" + parts[0] + ";color:" + parts[1] + ";font-size:10px;font-weight:700;padding:1px 6px;letter-spacing:0.3px;\">" + label + "</span>";
    }

    function setSyncBar(ok, ts) {
        var bar  = document.getElementById("kt-sync-bar");
        var dot  = document.getElementById("kt-sdot");
        var msg  = document.getElementById("kt-smsg");
        var time = document.getElementById("kt-stime");
        if (bar)  bar.className  = "kt-sync-bar" + (ok ? "" : " err");
        if (dot)  dot.className  = "kt-sdot" + (ok ? "" : " err");
        if (msg)  msg.textContent = ok ? "Live — updating every 3s" : "Connection lost — retrying...";
        if (time && ts) time.textContent = "Updated " + ts;
    }

    poll();
})();
</script>';
    }

    // ── Monitor JS (monitor page — same core + table updates) ────────────────

    private function renderMonitorJs(int $maxConcurrent): string
    {
        return '<script>
(function() {
    var MAX_CON   = ' . $maxConcurrent . ';
    var POLL      = 3000;
    var IDLE_POLL = 10000;
    var errCount  = 0;
    var pollTimer = null;

    function esc(s) {
        return String(s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;")
            .replace(/>/g,"&gt;").replace(/"/g,"&quot;");
    }
    function fmt(n) { return (n||0).toLocaleString(); }

    function poll() {
        fetch("/movie-file-transfers/live-data", {
            headers:{"X-Requested-With":"XMLHttpRequest","Accept":"application/json"}
        })
        .then(function(r) {
            if (!r.ok) throw new Error("HTTP " + r.status);
            return r.json();
        })
        .then(function(data) {
            errCount = 0;
            updateStats(data.stats, data.max_concurrent);
            updateAlerts(data.stats, data.active.length);
            updateLiveSection(data.active);
            updateMonitorTables(data);
            setSyncBar(true, data.ts);
            clearTimeout(pollTimer);
            pollTimer = setTimeout(poll, data.active.length > 0 ? POLL : IDLE_POLL);
        })
        .catch(function(err) {
            errCount++;
            setSyncBar(false, null);
            clearTimeout(pollTimer);
            pollTimer = setTimeout(poll, Math.min(30000, POLL * Math.pow(2, errCount)));
        });
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el && el.textContent !== String(val)) el.textContent = val;
    }

    function updateStats(s, mc) {
        setText("stat-queued", fmt(s.queued));
        setText("stat-active", s.active + "/" + (mc||MAX_CON));
        setText("stat-done",   fmt(s.done));
        setText("stat-failed", fmt(s.failed));
        setText("stat-speed",  s.avg_speed||"—");
        setText("stat-stored", (s.stored_gb||0) + " GB");
    }

    function updateAlerts(s, activeCount) {
        var sec = document.getElementById("kt-alerts-section");
        if (!sec) return;
        var html = "";
        if (activeCount > 0) {
            html += "<div class=\"kt-alert-bar\" style=\"background:#e8f4fd;border:1px solid #b8d9f5;color:#1a5f8a;\">" +
                "<span style=\"width:8px;height:8px;background:#3498db;border-radius:50%;flex-shrink:0;animation:kt-pulse 1.4s infinite;display:inline-block;\"></span>" +
                "<strong>" + activeCount + " transfer" + (activeCount > 1 ? "s" : "") + " in progress.</strong>" +
                "</div>";
        }
        if (s.not_queued > 0) {
            html += "<div class=\"kt-alert-bar warn\">" +
                "<i class=\"fa fa-exclamation-triangle\"></i>" +
                "<strong>" + fmt(s.not_queued) + "</strong> movies need queuing. " +
                "<a href=\"/movie-file-transfers/backfill\" class=\"kt-abtn kt-abtn-warning\" style=\"margin-left:6px;\">" +
                "<i class=\"fa fa-database\"></i> Backfill</a></div>";
        }
        if (s.failed > 0) {
            html += "<div class=\"kt-alert-bar err\">" +
                "<i class=\"fa fa-times-circle\"></i>" +
                "<strong>" + fmt(s.failed) + "</strong> transfers failed. " +
                "<a href=\"/movie-file-transfers?status=failed\" class=\"kt-abtn kt-abtn-danger\" style=\"margin-left:6px;\"><i class=\"fa fa-eye\"></i> View</a>" +
                "<a href=\"/movie-file-transfers/retry-all-failed\" class=\"kt-abtn kt-abtn-warning\" style=\"margin-left:4px;\" onclick=\"return confirm(\'Retry all?\')\"><i class=\"fa fa-refresh\"></i> Retry All</a>" +
                "</div>";
        }
        if (!html && s.done > 0) {
            html = "<div class=\"kt-alert-bar ok\"><i class=\"fa fa-check-circle\"></i> Pipeline clear — " + fmt(s.done) + " movies on Hetzner CDN.</div>";
        }
        sec.innerHTML = html;
    }

    function updateLiveSection(actives) {
        var wrap  = document.getElementById("active-cards-wrap");
        var empty = document.getElementById("active-cards-empty");
        var badge = document.getElementById("live-count-badge");
        if (!wrap) return;

        if (badge) badge.textContent = actives.length > 0 ? actives.length + " running" : "Idle";

        var existing = {};
        wrap.querySelectorAll(".kt-card[data-tid]").forEach(function(el) {
            existing[el.dataset.tid] = el;
        });
        var activeIds = {};
        actives.forEach(function(t) { activeIds[t.id] = true; });
        Object.keys(existing).forEach(function(id) {
            if (!activeIds[id]) {
                var el = existing[id];
                el.style.opacity = "0";
                el.style.transition = "opacity 0.3s";
                setTimeout(function() { el.parentNode && el.parentNode.removeChild(el); }, 300);
            }
        });

        if (empty) empty.style.display = actives.length === 0 ? "block" : "none";

        actives.forEach(function(t) {
            var card = wrap.querySelector(".kt-card[data-tid=\"" + t.id + "\"]");
            if (card) {
                updateCardEl(card, t);
            } else {
                var tmp = document.createElement("div");
                tmp.innerHTML = buildCardHtml(t);
                var newCard = tmp.firstElementChild;
                newCard.style.opacity = "0";
                if (empty && empty.parentNode === wrap) {
                    wrap.insertBefore(newCard, empty);
                } else {
                    wrap.appendChild(newCard);
                }
                setTimeout(function() { newCard.style.opacity = "1"; newCard.style.transition = "opacity 0.4s"; }, 10);
            }
        });
    }

    function updateCardEl(card, t) {
        var fill = card.querySelector(".kt-fill");
        if (fill) {
            fill.style.width = Math.max(2, t.progress_pct) + "%";
            fill.className = "kt-fill" + (t.status === "completing" ? " completing" : "");
        }
        var pct = card.querySelector(".kt-pct");
        if (pct) pct.textContent = t.progress_pct + "%";
        setChip(card, "speed", t.speed_human);
        setChip(card, "eta",   "ETA: " + t.eta_human);
        setChip(card, "bytes", t.bytes_human + " of " + t.size_human);
        var elapEl = card.querySelector(".kt-elapsed");
        if (elapEl && t.started_ago) elapEl.textContent = t.started_ago;
    }

    function setChip(card, key, val) {
        var el = card.querySelector("[data-chip=\"" + key + "\"]");
        if (el) el.textContent = val;
    }

    function buildCardHtml(t) {
        var pct   = Math.max(2, t.progress_pct);
        var cls   = t.status === "completing" ? " completing" : (t.status === "verifying" ? " verifying" : "");
        var fillC = t.status === "completing" ? " completing" : "";
        var badge = badgeHtml(t.status_color, t.status_label.toUpperCase());
        var started = t.started_ago || "—";
        return "<div class=\"kt-card" + cls + "\" data-tid=\"" + t.id + "\">" +
            "<div class=\"kt-card-hdr\">" +
                "<div class=\"kt-card-info\">" +
                    "<span class=\"kt-movie-num\">#" + t.id + "</span>" +
                    "<strong class=\"kt-movie-name\">" + esc(t.movie_title || "Untitled") + "</strong>" +
                    badge +
                "</div>" +
                "<div class=\"kt-card-acts\">" +
                    "<span class=\"kt-elapsed\">" + esc(started) + "</span>" +
                    "<a href=\"/movie-file-transfers/" + t.id + "\" class=\"kt-abtn kt-abtn-info\"><i class=\"fa fa-eye\"></i></a>" +
                    "<a href=\"/movie-file-transfers/" + t.id + "/cancel\" class=\"kt-abtn kt-abtn-danger\" onclick=\"return confirm(\'Stop #" + t.id + "?\')\"><i class=\"fa fa-stop-circle\"></i></a>" +
                "</div>" +
            "</div>" +
            "<div class=\"kt-prog-row\">" +
                "<div class=\"kt-prog\">" +
                    "<div class=\"kt-fill" + fillC + "\" style=\"width:" + pct + "%\"></div>" +
                "</div>" +
                "<span class=\"kt-pct\">" + t.progress_pct + "%</span>" +
            "</div>" +
            "<div class=\"kt-chips\">" +
                "<div class=\"kt-chip hl\"><i class=\"fa fa-tachometer\"></i><span class=\"cv\" data-chip=\"speed\">" + esc(t.speed_human) + "</span></div>" +
                "<div class=\"kt-chip\"><i class=\"fa fa-download\"></i><span class=\"cv\" data-chip=\"bytes\">" + esc(t.bytes_human) + "/" + esc(t.size_human) + "</span></div>" +
                "<div class=\"kt-chip hl\"><i class=\"fa fa-clock-o\"></i><span class=\"cv\" data-chip=\"eta\">" + esc(t.eta_human) + "</span></div>" +
                "<div class=\"kt-chip\"><i class=\"fa fa-repeat\"></i><span class=\"cv\">" + t.attempt_count + "/" + t.max_attempts + "</span></div>" +
            "</div>" +
        "</div>";
    }

    function badgeHtml(color, label) {
        var map = {
            primary:"#d6eaf8:#1a5276", info:"#d6eaf8:#1a5276",
            warning:"#fef6e0:#9a6c00", success:"#d5f5e3:#1a6b3a",
            danger:"#fdedec:#8b1c13",  "default":"#eee:#666"
        };
        var parts = (map[color] || map["default"]).split(":");
        return "<span style=\"background:" + parts[0] + ";color:" + parts[1] + ";font-size:10px;font-weight:700;padding:1px 6px;letter-spacing:0.3px;\">" + label + "</span>";
    }

    function priClass(p) {
        return p >= 999000 ? "kt-pri-max" : (p >= 100 ? "kt-pri-hi" : "kt-pri-lo");
    }

    function updateMonitorTables(data) {
        // Failed tbody
        var ftbody = document.getElementById("monitor-failed-tbody");
        if (ftbody) {
            if (!data.failed || data.failed.length === 0) {
                ftbody.innerHTML = "<tr><td colspan=\"6\" class=\"kt-empty-state\"><i class=\"fa fa-check-circle\" style=\"color:#00a65a\"></i> No failed transfers.</td></tr>";
            } else {
                var fhtml = "";
                data.failed.forEach(function(t) {
                    var triesC = t.attempt_count >= t.max_attempts ? "kt-pri-max" : "kt-pri-hi";
                    var retryBtn = t.is_retriable
                        ? "<a href=\"/movie-file-transfers/" + t.id + "/retry\" class=\"kt-abtn kt-abtn-warning\" onclick=\"return confirm(\'Retry #" + t.id + "?\')\"><i class=\"fa fa-refresh\"></i></a>"
                        : "<span class=\"kt-pri-lo\">Max</span>";
                    fhtml += "<tr>" +
                        "<td><small>#" + t.id + "</small></td>" +
                        "<td><small>" + esc((t.movie_title||"—").substring(0,28)) + "</small></td>" +
                        "<td><span class=\"" + triesC + "\">" + t.attempt_count + "/" + t.max_attempts + "</span></td>" +
                        "<td><small style=\"color:#c0392b;\" title=\"" + esc(t.error_message||"") + "\">" + esc((t.error_message||"Unknown").substring(0,55)) + "</small></td>" +
                        "<td><small style=\"color:#aaa;\">" + esc(t.queued_ago||"—") + "</small></td>" +
                        "<td style=\"white-space:nowrap;\">" + retryBtn +
                            "<a href=\"/movie-file-transfers/" + t.id + "\" class=\"kt-abtn kt-abtn-info\" style=\"margin-left:3px;\"><i class=\"fa fa-eye\"></i></a>" +
                        "</td>" +
                    "</tr>";
                });
                ftbody.innerHTML = fhtml;
            }
        }

        // Queue tbody
        var qtbody = document.getElementById("monitor-queue-tbody");
        if (qtbody) {
            if (!data.next_queue || data.next_queue.length === 0) {
                qtbody.innerHTML = "<tr><td colspan=\"4\" class=\"kt-empty-state\"><i class=\"fa fa-check\"></i> Queue is empty.</td></tr>";
            } else {
                var qhtml = "";
                data.next_queue.forEach(function(t) {
                    qhtml += "<tr>" +
                        "<td><small>#" + t.id + "</small></td>" +
                        "<td><small>" + esc((t.movie_title||"—").substring(0,22)) + "</small></td>" +
                        "<td><span class=\"" + priClass(t.priority) + "\">" + fmt(t.priority) + "</span></td>" +
                        "<td style=\"white-space:nowrap;\">" +
                            "<a href=\"/movie-file-transfers/" + t.id + "\" class=\"kt-abtn kt-abtn-info\"><i class=\"fa fa-eye\"></i></a>" +
                            "<a href=\"/movie-file-transfers/" + t.id + "/cancel\" class=\"kt-abtn kt-abtn-danger\" style=\"margin-left:3px;\" onclick=\"return confirm(\'Cancel #" + t.id + "?\')\"><i class=\"fa fa-times\"></i></a>" +
                        "</td>" +
                    "</tr>";
                });
                qtbody.innerHTML = qhtml;
            }
        }

        // Recent done tbody
        var rtbody = document.getElementById("monitor-recent-tbody");
        if (rtbody) {
            if (!data.recent_done || data.recent_done.length === 0) {
                rtbody.innerHTML = "<tr><td colspan=\"5\" class=\"kt-empty-state\">No completed transfers yet.</td></tr>";
            } else {
                var rhtml = "";
                data.recent_done.forEach(function(t) {
                    rhtml += "<tr>" +
                        "<td><a href=\"/movie-file-transfers/" + t.id + "\" style=\"color:#00a65a;\">#" + t.id + "</a></td>" +
                        "<td><small>" + esc((t.movie_title||"—").substring(0,22)) + "</small></td>" +
                        "<td><small style=\"color:#00a65a;\">" + esc(t.speed_human||"—") + "</small></td>" +
                        "<td><small>" + esc(t.size_human||"—") + "</small></td>" +
                        "<td><small style=\"color:#aaa;\">" + esc(t.completed_ago||"—") + "</small></td>" +
                    "</tr>";
                });
                rtbody.innerHTML = rhtml;
            }
        }
    }

    function setSyncBar(ok, ts) {
        var bar  = document.getElementById("kt-sync-bar");
        var dot  = document.getElementById("kt-sdot");
        var msg  = document.getElementById("kt-smsg");
        var time = document.getElementById("kt-stime");
        if (bar)  bar.className   = "kt-sync-bar" + (ok ? "" : " err");
        if (dot)  dot.className   = "kt-sdot" + (ok ? "" : " err");
        if (msg)  msg.textContent = ok ? "Live — updating every 3s" : "Connection lost — retrying...";
        if (time && ts) time.textContent = "Updated " + ts;
    }

    poll();
})();
</script>';
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
