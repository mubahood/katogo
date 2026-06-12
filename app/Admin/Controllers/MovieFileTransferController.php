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

        $grid->model()->orderBy('id', 'desc');
        $grid->disableBatchActions();
        $grid->disableCreateButton();
        $grid->paginate(30);

        // ── Filters ────────────────────────────────────────────────────
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
                'direct'    => 'Direct URL',
                'other'     => 'Other',
            ]);
            $f->like('movie_title', 'Movie Title');
            $f->equal('movie_id', 'Movie ID');
            $f->between('created_at', 'Queued On')->datetime();
        });

        // ── Columns ────────────────────────────────────────────────────
        $grid->column('id', 'ID')->sortable()->width(60);

        $grid->column('movie_title', 'Movie')->display(function () {
            $poster = $this->movie_poster_url
                ? "<img src='{$this->movie_poster_url}' style='width:32px;height:48px;object-fit:cover;border-radius:3px;margin-right:6px;vertical-align:middle;'>"
                : "<i class='fa fa-film' style='margin-right:6px;color:#aaa;'></i>";
            $title = e($this->movie_title ?? 'Untitled');
            $year  = $this->movie_year ? " <small class='text-muted'>({$this->movie_year})</small>" : '';
            $id    = $this->movie_id
                ? " <small class='label label-default'>#{$this->movie_id}</small>"
                : '';
            return "{$poster}<strong>{$title}</strong>{$year}{$id}";
        })->width(260);

        $grid->column('source_type', 'Source')->display(function ($v) {
            $colors = [
                'munowatch' => 'info',
                'gdrive'    => 'warning',
                'firebase'  => 'primary',
                'hetzner'   => 'success',
                'direct'    => 'default',
                'other'     => 'default',
            ];
            $c = $colors[$v] ?? 'default';
            return "<span class='label label-{$c}'>" . strtoupper($v ?? '?') . "</span>";
        })->width(90);

        $grid->column('status', 'Status')->display(function () {
            $color = $this->status_badge_color;
            $label = $this->status_label;

            // Progress bar for active transfers
            if ($this->isActive()) {
                $pct = $this->progress_pct;
                $speed = $this->formatted_speed;
                $eta   = $this->formatted_eta;
                return "
                    <span class='label label-{$color}'>{$label}</span><br>
                    <div style='width:120px;background:#eee;border-radius:3px;margin-top:3px;'>
                      <div style='width:{$pct}%;background:#337ab7;height:6px;border-radius:3px;'></div>
                    </div>
                    <small class='text-muted'>{$pct}% &bull; {$speed} &bull; ETA {$eta}</small>
                ";
            }

            return "<span class='label label-{$color}'>{$label}</span>";
        })->width(180);

        $grid->column('formatted_size', 'Size')->width(80);

        $grid->column('formatted_duration', 'Duration')->display(function () {
            return $this->isDone() ? $this->formatted_duration : '—';
        })->width(80);

        $grid->column('attempt_count', 'Tries')->display(function () {
            $max = $this->max_attempts ?? 3;
            $cnt = $this->attempt_count;
            $color = $cnt >= $max ? 'danger' : ($cnt > 0 ? 'warning' : 'default');
            return "<span class='label label-{$color}'>{$cnt}/{$max}</span>";
        })->width(60);

        $grid->column('queued_at', 'Queued')->display(function ($v) {
            return $v ? \Carbon\Carbon::parse($v)->diffForHumans() : '—';
        })->width(110);

        $grid->column('actions', 'Actions')->display(function () {
            $id    = $this->id;
            $btns  = [];

            if ($this->isDone() && $this->dest_url) {
                $btns[] = "<a href='" . e($this->dest_url) . "' target='_blank'
                              class='btn btn-xs btn-success' title='View on Hetzner'>
                              <i class='fa fa-external-link'></i></a>";
            }

            if ($this->isRetriable() || $this->isFailed()) {
                $btns[] = "<a href='/movie-file-transfers/{$id}/retry'
                              class='btn btn-xs btn-warning' title='Retry'
                              onclick=\"return confirm('Retry this transfer?')\">
                              <i class='fa fa-refresh'></i></a>";
            }

            if (!$this->isDone() && !$this->isFailed()) {
                $btns[] = "<a href='/movie-file-transfers/{$id}/cancel'
                              class='btn btn-xs btn-danger' title='Cancel'
                              onclick=\"return confirm('Cancel this transfer?')\">
                              <i class='fa fa-times'></i></a>";
            }

            if ($this->movie_id) {
                $btns[] = "<a href='/movies-active/{$this->movie_id}/edit'
                              class='btn btn-xs btn-default' title='Edit Movie'>
                              <i class='fa fa-pencil'></i></a>";
            }

            $btns[] = "<a href='/movie-file-transfers/{$id}'
                          class='btn btn-xs btn-info' title='Details'>
                          <i class='fa fa-eye'></i></a>";

            return implode(' ', $btns);
        })->width(140);

        return $grid;
    }

    // ── Index with stats header ───────────────────────────────────────────────

    public function index(Content $content): Content
    {
        $stats = $this->getStats();

        return $content
            ->title('Movie File Transfers')
            ->description('Automated transfer pipeline — source URLs → Hetzner Storage CDN')
            ->row(function (Row $row) use ($stats) {
                $row->column(2, new InfoBox(
                    'Queued', 'hourglass-half', 'aqua',
                    '/movie-file-transfers?status=queued',
                    number_format($stats['queued'])
                ));
                $row->column(2, new InfoBox(
                    'Active', 'exchange', 'blue',
                    '/movie-file-transfers?status=transferring',
                    $stats['active'] . ' / ' . TransferMovieToHetzner::MAX_CONCURRENT
                ));
                $row->column(2, new InfoBox(
                    'Done', 'check-circle', 'green',
                    '/movie-file-transfers?status=done',
                    number_format($stats['done'])
                ));
                $row->column(2, new InfoBox(
                    'Failed', 'times-circle', 'red',
                    '/movie-file-transfers?status=failed',
                    number_format($stats['failed'])
                ));
                $row->column(2, new InfoBox(
                    'ETA', 'clock-o', 'yellow',
                    '/movie-file-transfers',
                    $stats['eta']
                ));
                $row->column(2, new InfoBox(
                    'Stored', 'hdd-o', 'teal',
                    '/movie-file-transfers',
                    $stats['stored_gb'] . ' GB'
                ));
            })
            ->row(function (Row $row) use ($stats) {
                $row->column(12, function (Column $col) use ($stats) {
                    if ($stats['not_queued'] > 0) {
                        $col->append(
                            "<div class='alert alert-warning' style='margin:10px 0 0;'>
                              <i class='fa fa-exclamation-triangle'></i>
                              <strong>{$stats['not_queued']}</strong> active movies still have external video URLs and are not in the transfer queue.
                              <a href='/movie-file-transfers/backfill' class='btn btn-xs btn-warning' style='margin-left:10px;'>
                                <i class='fa fa-database'></i> Run Backfill
                              </a>
                            </div>"
                        );
                    }
                });
            })
            ->body($this->grid());
    }

    // ── Monitor page ──────────────────────────────────────────────────────────

    public function monitor(Content $content): Content
    {
        $stats   = $this->getStats();
        $actives = MovieFileTransfer::active()->orderBy('started_at')->get();
        $failed  = MovieFileTransfer::failed()->orderByDesc('updated_at')->limit(10)->get();

        $activeRows = $actives->map(function ($t) {
            $pct  = $t->progress_pct;
            $bar  = "<div style='background:#eee;border-radius:3px;min-width:120px;'>
                       <div style='width:{$pct}%;background:#337ab7;height:10px;border-radius:3px;'></div>
                     </div><small>{$pct}%</small>";
            return [
                "#{$t->id}",
                e($t->movie_title ?? '—'),
                $bar,
                $t->formatted_speed,
                $t->formatted_eta,
                $t->formatted_size,
                $t->started_at ? $t->started_at->diffForHumans() : '—',
            ];
        })->toArray();

        $failedRows = $failed->map(function ($t) {
            $retry = $t->isRetriable()
                ? "<a href='/movie-file-transfers/{$t->id}/retry' class='btn btn-xs btn-warning'>Retry</a>"
                : '<span class="label label-default">No retries left</span>';
            return [
                "#{$t->id}",
                e($t->movie_title ?? '—'),
                $t->attempt_count . '/' . $t->max_attempts,
                e(substr($t->error_message ?? '—', 0, 80)),
                $t->updated_at ? $t->updated_at->diffForHumans() : '—',
                $retry,
            ];
        })->toArray();

        return $content
            ->title('Transfer Monitor')
            ->description('Live pipeline status')
            ->row(function (Row $row) use ($stats) {
                $row->column(2, new InfoBox('Queued',      'hourglass-half', 'aqua',   '#', number_format($stats['queued'])));
                $row->column(2, new InfoBox('Active',      'exchange',       'blue',   '#', $stats['active'] . '/' . TransferMovieToHetzner::MAX_CONCURRENT));
                $row->column(2, new InfoBox('Done',        'check',          'green',  '#', number_format($stats['done'])));
                $row->column(2, new InfoBox('Failed',      'times',          'red',    '#', number_format($stats['failed'])));
                $row->column(2, new InfoBox('Avg Speed',   'tachometer',     'yellow', '#', $stats['avg_speed']));
                $row->column(2, new InfoBox('ETA (all)',   'clock-o',        'teal',   '#', $stats['eta']));
            })
            ->row(function (Row $row) use ($activeRows) {
                $row->column(12, function (Column $col) use ($activeRows) {
                    if ($activeRows) {
                        $table = new Table(
                            ['#', 'Movie', 'Progress', 'Speed', 'ETA', 'Size', 'Started'],
                            $activeRows
                        );
                        $col->append('<h4>Active Transfers</h4>' . $table->render());
                    } else {
                        $col->append('<div class="alert alert-info">No transfers currently active.</div>');
                    }
                });
            })
            ->row(function (Row $row) use ($failedRows) {
                $row->column(12, function (Column $col) use ($failedRows) {
                    if ($failedRows) {
                        $table = new Table(
                            ['#', 'Movie', 'Attempts', 'Error', 'Failed', 'Action'],
                            $failedRows
                        );
                        $col->append('<h4>Recent Failures</h4>' . $table->render());
                    }
                });
            })
            ->row(function (Row $row) {
                $row->column(12, function (Column $col) {
                    $col->append("
                        <div class='box box-default'>
                          <div class='box-header'><h3 class='box-title'>Actions</h3></div>
                          <div class='box-body'>
                            <a href='/movie-file-transfers/process-now' class='btn btn-primary'
                               onclick=\"return confirm('Dispatch a batch of queued transfers now?')\">
                               <i class='fa fa-play'></i> Process Queue Now
                            </a>
                            &nbsp;
                            <a href='/movie-file-transfers/backfill' class='btn btn-warning'>
                               <i class='fa fa-database'></i> Queue Backfill
                            </a>
                            &nbsp;
                            <a href='/movie-file-transfers' class='btn btn-default'>
                               <i class='fa fa-list'></i> All Transfers
                            </a>
                          </div>
                        </div>
                    ");
                });
            });
    }

    // ── Backfill page ─────────────────────────────────────────────────────────

    public function backfill(Content $content): Content
    {
        $notQueued = MovieModel::where('status', 'Active')
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

        $totalActive = MovieModel::where('status', 'Active')->whereNotNull('url')->count();
        $onHetzner   = MovieFileTransfer::done()->count();

        return $content
            ->title('Queue Backfill')
            ->description('Scan all active movies and create transfer records for those not yet on Hetzner')
            ->row(function (Row $row) use ($notQueued, $totalActive, $onHetzner) {
                $row->column(4, new InfoBox('Active Movies',    'film',       'blue',  '#', number_format($totalActive)));
                $row->column(4, new InfoBox('Already on Hetzner', 'check',   'green', '#', number_format($onHetzner)));
                $row->column(4, new InfoBox('Need Queuing',     'hourglass', 'red',   '#', number_format($notQueued)));
            })
            ->row(function (Row $row) use ($notQueued) {
                $row->column(12, function (Column $col) use ($notQueued) {
                    $col->append("
                        <div class='box box-warning'>
                          <div class='box-header with-border'>
                            <h3 class='box-title'><i class='fa fa-database'></i> Backfill Transfer Queue</h3>
                          </div>
                          <div class='box-body'>
                            <p>
                              This will create <strong>{$notQueued}</strong> queued transfer records
                              for active movies not yet on Hetzner Storage.
                              The scheduler will process them automatically at the rate of
                              <strong>" . TransferMovieToHetzner::MAX_CONCURRENT . " transfers at a time</strong>.
                            </p>
                            <p class='text-muted'>
                              <i class='fa fa-info-circle'></i>
                              No movies will be modified until their individual transfer completes.
                              The process is safe to run multiple times — duplicates are skipped.
                            </p>
                            <form method='POST' action='/movie-file-transfers/backfill-run'
                                  onsubmit=\"return confirm('Queue {$notQueued} transfers? The scheduler will process them gradually.')\">
                                <input type='hidden' name='_token' value='" . csrf_token() . "'>
                                <input type='number' name='limit' class='form-control' style='display:inline-block;width:120px;'
                                       placeholder='Limit (0=all)' value='0' min='0'>
                                &nbsp;
                                <button type='submit' class='btn btn-warning'>
                                    <i class='fa fa-play'></i> Start Backfill
                                </button>
                            </form>
                          </div>
                        </div>
                    ");
                });
            });
    }

    // ── Action routes ─────────────────────────────────────────────────────────

    public function retry(int $id)
    {
        $t = MovieFileTransfer::findOrFail($id);

        if (!$t->isFailed() && !$t->isRetriable()) {
            return redirect()->back()->with('error', 'This transfer cannot be retried.');
        }

        $t->resetToQueued('Manual retry from admin panel');
        TransferMovieToHetzner::dispatch($t->id)->onQueue('transfers');

        return redirect('/movie-file-transfers')->with('success', "Transfer #{$id} queued for retry.");
    }

    public function cancel(int $id)
    {
        $t = MovieFileTransfer::findOrFail($id);
        $t->cancel();
        return redirect('/movie-file-transfers')->with('success', "Transfer #{$id} cancelled.");
    }

    public function processNow()
    {
        Artisan::queue('transfers:process', ['--concurrency' => 2, '--limit' => 6]);
        return redirect('/movie-file-transfers/monitor')
            ->with('success', 'Dispatch command queued — check monitor for results.');
    }

    public function backfillRun(Request $request)
    {
        $limit  = max(0, (int) $request->input('limit', 0));
        $params = ['--chunk' => 100];
        if ($limit > 0) $params['--limit'] = $limit;

        Artisan::queue('transfers:backfill', $params);

        return redirect('/movie-file-transfers/backfill')
            ->with('success', 'Backfill started in background. Refresh this page in a moment.');
    }

    // ── Detail (show) ─────────────────────────────────────────────────────────

    public function show($id, Content $content)
    {
        $t = MovieFileTransfer::findOrFail($id);

        $rows = [
            ['ID',            $t->id],
            ['Movie',         ($t->movie_title ?? '—') . ' (#' . $t->movie_id . ')'],
            ['Status',        "<span class='label label-{$t->status_badge_color}'>{$t->status_label}</span>"],
            ['Source URL',    "<a href='" . e($t->source_url) . "' target='_blank' style='word-break:break-all'>" . e(substr($t->source_url, 0, 80)) . "…</a>"],
            ['Source Type',   strtoupper($t->source_type ?? '—')],
            ['Source Size',   $t->formatted_size],
            ['Destination',   $t->dest_url ? "<a href='" . e($t->dest_url) . "' target='_blank'>" . e($t->dest_url) . "</a>" : '—'],
            ['Dest Path',     e($t->dest_path ?? '—')],
            ['Progress',      $t->progress_pct . '%'],
            ['Speed',         $t->formatted_speed],
            ['Duration',      $t->formatted_duration],
            ['Attempts',      $t->attempt_count . ' / ' . $t->max_attempts],
            ['Initiated By',  $t->initiated_by ?? '—'],
            ['Worker',        $t->worker_hostname ?? '—'],
            ['Queued At',     $t->queued_at?->format('Y-m-d H:i:s') ?? '—'],
            ['Started At',    $t->started_at?->format('Y-m-d H:i:s') ?? '—'],
            ['Completed At',  $t->completed_at?->format('Y-m-d H:i:s') ?? '—'],
            ['Movie URL Updated', $t->movie_url_updated ? '✅ Yes' : 'No'],
            ['Error',         $t->error_message ? "<code style='color:red'>" . e($t->error_message) . "</code>" : '—'],
        ];

        $html = "<div class='box box-default'>
                   <div class='box-header'><h3 class='box-title'>Transfer #{$t->id}</h3></div>
                   <div class='box-body table-responsive'><table class='table table-bordered table-striped'><tbody>";
        foreach ($rows as [$label, $value]) {
            $html .= "<tr><th style='width:180px'>{$label}</th><td>{$value}</td></tr>";
        }
        $html .= "</tbody></table></div>";

        if ($t->error_trace) {
            $html .= "<div class='box box-danger collapsible'>
                        <div class='box-header with-border'>
                          <h3 class='box-title'><i class='fa fa-bug'></i> Stack Trace</h3>
                        </div>
                        <div class='box-body'>
                          <pre style='font-size:11px;max-height:300px;overflow:auto'>" . e($t->error_trace) . "</pre>
                        </div>
                      </div>";
        }

        $actions = [];
        if ($t->isRetriable() || $t->isFailed()) {
            $actions[] = "<a href='/movie-file-transfers/{$id}/retry' class='btn btn-warning'
                             onclick=\"return confirm('Retry this transfer?')\">
                             <i class='fa fa-refresh'></i> Retry</a>";
        }
        if (!$t->isDone() && !$t->isFailed()) {
            $actions[] = "<a href='/movie-file-transfers/{$id}/cancel' class='btn btn-danger'
                             onclick=\"return confirm('Cancel?')\">
                             <i class='fa fa-times'></i> Cancel</a>";
        }
        $actions[] = "<a href='/movie-file-transfers' class='btn btn-default'><i class='fa fa-arrow-left'></i> Back</a>";
        $html .= "<div class='box-footer'>" . implode(' ', $actions) . "</div>";

        $html .= '</div>';

        return $content
            ->title("Transfer #{$id}")
            ->description($t->movie_title ?? '')
            ->body($html);
    }

    // ── Stats helper ──────────────────────────────────────────────────────────

    private function getStats(): array
    {
        $counts = MovieFileTransfer::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $done      = $counts[MovieFileTransfer::STATUS_DONE] ?? 0;
        $queued    = $counts[MovieFileTransfer::STATUS_QUEUED] ?? 0;
        $active    = ($counts[MovieFileTransfer::STATUS_TRANSFERRING] ?? 0)
                   + ($counts[MovieFileTransfer::STATUS_COMPLETING] ?? 0);
        $failed    = $counts[MovieFileTransfer::STATUS_FAILED] ?? 0;

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

        return [
            'queued'      => $queued,
            'active'      => $active,
            'done'        => $done,
            'failed'      => $failed,
            'eta'         => $etaStr,
            'avg_speed'   => $avgSpeed ? number_format($avgSpeed, 1) . ' MB/s' : '—',
            'stored_gb'   => $storedGb,
            'not_queued'  => $notQueued,
            'avg_speed_raw' => $avgSpeed,
            'avg_duration'  => $avgDuration,
        ];
    }
}
