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

        $grid->column('id', 'ID')->sortable()->width(60);

        $grid->column('movie_title', 'Movie')->display(function () {
            $poster = $this->movie_poster_url
                ? "<img src='" . e($this->movie_poster_url) . "' style='width:32px;height:48px;object-fit:cover;border-radius:3px;margin-right:6px;vertical-align:middle;'>"
                : "<i class='fa fa-film' style='margin-right:6px;color:#aaa;'></i>";
            $title = e($this->movie_title ?? 'Untitled');
            $year  = $this->movie_year ? " <small class='text-muted'>({$this->movie_year})</small>" : '';
            $id    = $this->movie_id ? " <small class='label label-default'>#{$this->movie_id}</small>" : '';
            return "{$poster}<strong>{$title}</strong>{$year}{$id}";
        })->width(260);

        $grid->column('priority', 'Priority')->display(function ($v) {
            if ($v >= 1000) return "<span class='label label-danger'><i class='fa fa-fire'></i> " . number_format($v) . "</span>";
            if ($v >= 100)  return "<span class='label label-warning'>" . number_format($v) . "</span>";
            if ($v > 0)     return "<span class='label label-default'>{$v}</span>";
            return "<span class='text-muted'>—</span>";
        })->sortable()->width(90);

        $grid->column('source_type', 'Source')->display(function ($v) {
            $colors = ['munowatch' => 'info','gdrive' => 'warning','firebase' => 'primary','hetzner' => 'success','direct' => 'default','other' => 'default'];
            $c = $colors[$v] ?? 'default';
            return "<span class='label label-{$c}'>" . strtoupper($v ?? '?') . "</span>";
        })->width(90);

        $grid->column('status', 'Status')->display(function () {
            $color = $this->status_badge_color;
            $label = $this->status_label;
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
            if ($this->isFailed()) {
                $err = e(substr($this->error_message ?? '', 0, 50));
                return "<span class='label label-{$color}'>{$label}</span><br><small class='text-danger'>{$err}</small>";
            }
            return "<span class='label label-{$color}'>{$label}</span>";
        })->width(200);

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
            $id   = $this->id;
            $btns = [];
            if ($this->isDone() && $this->dest_url) {
                $btns[] = "<a href='" . e($this->dest_url) . "' target='_blank' class='btn btn-xs btn-success' title='View on Hetzner'><i class='fa fa-external-link'></i></a>";
            }
            if ($this->isRetriable() || $this->isFailed()) {
                $btns[] = "<a href='/movie-file-transfers/{$id}/retry' class='btn btn-xs btn-warning' title='Retry' onclick=\"return confirm('Retry this transfer?')\"><i class='fa fa-refresh'></i></a>";
            }
            if (!$this->isDone() && !$this->isFailed()) {
                $btns[] = "<a href='/movie-file-transfers/{$id}/cancel' class='btn btn-xs btn-danger' title='Cancel' onclick=\"return confirm('Cancel?')\"><i class='fa fa-times'></i></a>";
            }
            if ($this->movie_id) {
                $btns[] = "<a href='/movies-active/{$this->movie_id}/edit' class='btn btn-xs btn-default' title='Edit Movie'><i class='fa fa-pencil'></i></a>";
            }
            $btns[] = "<a href='/movie-file-transfers/{$id}' class='btn btn-xs btn-info' title='Details'><i class='fa fa-eye'></i></a>";
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
            ->description('Automated pipeline — source URLs → Hetzner Storage CDN')
            ->row(function (Row $row) use ($stats) {
                $row->column(2, new InfoBox('Queued',      'hourglass-half', 'aqua',   '/movie-file-transfers?status=queued',       number_format($stats['queued'])));
                $row->column(2, new InfoBox('Active',      'exchange',       'blue',   '/movie-file-transfers?status=transferring', $stats['active'] . ' / ' . TransferMovieToHetzner::MAX_CONCURRENT));
                $row->column(2, new InfoBox('Done',        'check-circle',   'green',  '/movie-file-transfers?status=done',         number_format($stats['done'])));
                $row->column(2, new InfoBox('Failed',      'times-circle',   'red',    '/movie-file-transfers?status=failed',       number_format($stats['failed'])));
                $row->column(2, new InfoBox('ETA',         'clock-o',        'yellow', '/movie-file-transfers',                     $stats['eta']));
                $row->column(2, new InfoBox('Stored',      'hdd-o',          'teal',   '/movie-file-transfers',                     $stats['stored_gb'] . ' GB'));
            })
            ->row(function (Row $row) use ($stats) {
                $row->column(12, function (Column $col) use ($stats) {
                    $html = '';
                    if ($stats['not_queued'] > 0) {
                        $html .= "<div class='alert alert-warning' style='margin:10px 0 0;'>
                          <i class='fa fa-exclamation-triangle'></i>
                          <strong>" . number_format($stats['not_queued']) . "</strong> active movies still have external video URLs and are not in the transfer queue.
                          <a href='/movie-file-transfers/backfill' class='btn btn-xs btn-warning' style='margin-left:10px;'>
                            <i class='fa fa-database'></i> Run Backfill
                          </a>
                        </div>";
                    }
                    if ($stats['failed'] > 0) {
                        $html .= "<div class='alert alert-danger' style='margin:8px 0 0;'>
                          <i class='fa fa-times-circle'></i>
                          <strong>" . number_format($stats['failed']) . "</strong> transfers have failed.
                          <a href='/movie-file-transfers?status=failed' class='btn btn-xs btn-danger' style='margin-left:10px;'>
                            <i class='fa fa-eye'></i> View Failed
                          </a>
                          <a href='/movie-file-transfers/retry-all-failed' class='btn btn-xs btn-warning' style='margin-left:5px;'
                             onclick=\"return confirm('Retry all failed transfers?')\">
                            <i class='fa fa-refresh'></i> Retry All Failed
                          </a>
                        </div>";
                    }
                    if ($html) $col->append($html);
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
            $pct = $t->progress_pct;
            $bar = "<div style='background:#eee;border-radius:3px;min-width:120px;'>
                       <div style='width:{$pct}%;background:#337ab7;height:10px;border-radius:3px;'></div>
                     </div><small>{$pct}%</small>";
            return ["#{$t->id}", e($t->movie_title ?? '—'), $bar, $t->formatted_speed, $t->formatted_eta, $t->formatted_size, $t->started_at ? $t->started_at->diffForHumans() : '—'];
        })->toArray();

        $failedRows = $failed->map(function ($t) {
            $retry = $t->isRetriable()
                ? "<a href='/movie-file-transfers/{$t->id}/retry' class='btn btn-xs btn-warning'>Retry</a>"
                : '<span class="label label-default">No retries left</span>';
            return ["#{$t->id}", e($t->movie_title ?? '—'), $t->attempt_count . '/' . $t->max_attempts, e(substr($t->error_message ?? '—', 0, 80)), $t->updated_at ? $t->updated_at->diffForHumans() : '—', $retry];
        })->toArray();

        return $content
            ->title('Transfer Monitor')
            ->description('Live pipeline status')
            ->row(function (Row $row) use ($stats) {
                $row->column(2, new InfoBox('Queued',    'hourglass-half', 'aqua',   '#', number_format($stats['queued'])));
                $row->column(2, new InfoBox('Active',    'exchange',       'blue',   '#', $stats['active'] . '/' . TransferMovieToHetzner::MAX_CONCURRENT));
                $row->column(2, new InfoBox('Done',      'check',          'green',  '#', number_format($stats['done'])));
                $row->column(2, new InfoBox('Failed',    'times',          'red',    '#', number_format($stats['failed'])));
                $row->column(2, new InfoBox('Avg Speed', 'tachometer',     'yellow', '#', $stats['avg_speed']));
                $row->column(2, new InfoBox('ETA (all)', 'clock-o',        'teal',   '#', $stats['eta']));
            })
            ->row(function (Row $row) use ($activeRows, $failedRows) {
                $row->column(6, function (Column $col) use ($activeRows) {
                    if ($activeRows) {
                        $table = new Table(['#', 'Movie', 'Progress', 'Speed', 'ETA', 'Size', 'Started'], $activeRows);
                        $col->append('<h4><i class="fa fa-exchange text-blue"></i> Active Transfers</h4>' . $table->render());
                    } else {
                        $col->append('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No transfers currently active — workers are idle.</div>');
                    }
                });
                $row->column(6, function (Column $col) use ($failedRows) {
                    if ($failedRows) {
                        $table = new Table(['#', 'Movie', 'Attempts', 'Error', 'Failed', 'Action'], $failedRows);
                        $col->append('<h4><i class="fa fa-times-circle text-red"></i> Recent Failures</h4>' . $table->render());
                    } else {
                        $col->append('<div class="alert alert-success"><i class="fa fa-check"></i> No recent failures.</div>');
                    }
                });
            })
            ->row(function (Row $row) {
                $row->column(12, function (Column $col) {
                    $col->append("
                        <div class='box box-default'>
                          <div class='box-header'><h3 class='box-title'><i class='fa fa-cogs'></i> Quick Actions</h3></div>
                          <div class='box-body'>
                            <a href='/movie-file-transfers/process-now' class='btn btn-primary'
                               onclick=\"return confirm('Dispatch a batch now?')\">
                               <i class='fa fa-play'></i> Process Queue Now
                            </a>
                            &nbsp;
                            <a href='/movie-file-transfers/backfill' class='btn btn-warning'>
                               <i class='fa fa-database'></i> Queue Backfill
                            </a>
                            &nbsp;
                            <a href='/movie-file-transfers/retry-all-failed' class='btn btn-danger'
                               onclick=\"return confirm('Retry all failed transfers?')\">
                               <i class='fa fa-refresh'></i> Retry All Failed
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
            return redirect()->back()->with('error', 'This transfer cannot be retried.');
        }
        $t->resetToQueued('Manual retry from admin panel');
        // Boost priority on retry so it jumps the queue
        $t->update(['priority' => $t->priority + 100]);
        TransferMovieToHetzner::dispatch($t->id)->onQueue('transfers');
        return redirect('/movie-file-transfers')->with('success', "Transfer #{$id} queued for retry (priority boosted).");
    }

    public function cancel($id)
    {
        $t = MovieFileTransfer::findOrFail($id);
        $t->cancel();
        return redirect('/movie-file-transfers')->with('success', "Transfer #{$id} cancelled.");
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
        return redirect('/movie-file-transfers/monitor')->with('success', "Queued {$count} failed transfers for retry.");
    }

    public function processNow()
    {
        Artisan::queue('transfers:process', ['--concurrency' => 2, '--limit' => 6]);
        return redirect('/movie-file-transfers/monitor')->with('success', 'Dispatch command queued — check monitor in a moment.');
    }

    public function backfillRun(Request $request)
    {
        $limit  = max(0, (int) $request->input('limit', 0));
        $source = trim((string) $request->input('source', ''));
        $params = ['--chunk' => 100];
        if ($limit > 0) $params['--limit'] = $limit;
        if ($source !== '') $params['--source'] = $source;

        Artisan::call('transfers:backfill', $params);

        $output = trim(Artisan::output());
        $lines  = array_filter(explode("\n", $output));
        $queued = 0;
        foreach ($lines as $line) {
            if (preg_match('/Queued\s+\|\s*(\d+)/', $line, $m)) {
                $queued = (int)$m[1];
            }
        }

        return redirect('/movie-file-transfers/backfill')
            ->with('success', "Backfill complete — {$queued} transfer records created. The scheduler will process them by priority.");
    }

    /** Queue a single specific movie — highest priority (GET from top-10 table or POST from form) */
    public function queueSingle(Request $request, $movieId = null)
    {
        $id = $movieId ?? $request->input('movie_id');
        if (!$id) {
            return redirect('/movie-file-transfers/backfill')->with('error', 'No movie ID provided.');
        }

        $movie = MovieModel::find((int)$id);
        if (!$movie) {
            return redirect('/movie-file-transfers/backfill')->with('error', "Movie #{$id} not found.");
        }

        $url = (string)($movie->attributes['url'] ?? $movie->url ?? '');
        if (empty($url)) {
            return redirect('/movie-file-transfers/backfill')->with('error', "Movie #{$id} has no video URL.");
        }

        if (MovieFileTransfer::isAlreadyOnHetzner($url)) {
            return redirect('/movie-file-transfers/backfill')->with('success', "Movie #{$id} is already on Hetzner Storage — no transfer needed.");
        }

        // Cancel any existing failed/cancelled transfer, create fresh with max priority
        $existing = MovieFileTransfer::forMovie($movie->id)
            ->whereIn('status', [MovieFileTransfer::STATUS_FAILED, MovieFileTransfer::STATUS_CANCELLED, MovieFileTransfer::STATUS_SKIPPED])
            ->first();

        if ($existing) {
            $existing->resetToQueued('Admin: single-movie queue request');
            // Max priority so it runs next
            $existing->update(['priority' => 999999, 'initiated_by' => 'admin:single']);
            $transfer = $existing;
        } elseif (MovieFileTransfer::hasPendingOrCompleted($movie->id)) {
            $active = MovieFileTransfer::forMovie($movie->id)->whereNotIn('status', [MovieFileTransfer::STATUS_DONE])->first();
            return redirect('/movie-file-transfers/backfill')->with('success',
                "Movie #{$id} ({$movie->title}) already has an active transfer" . ($active ? " (#{$active->id})" : '') . ".");
        } else {
            $transfer = MovieFileTransfer::queueForMovie($movie, 'admin:single');
            // Override with max priority
            $transfer->update(['priority' => 999999]);
        }

        // Dispatch immediately — don't wait for the scheduler
        TransferMovieToHetzner::dispatch($transfer->id)->onQueue('transfers');

        return redirect('/movie-file-transfers/' . $transfer->id)
            ->with('success', "Movie #{$id} ({$movie->title}) queued at maximum priority and dispatched immediately.");
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
