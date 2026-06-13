<?php

namespace App\Admin\Controllers;

use App\Models\MovieModel;
use App\Models\MyCounter;
use App\Models\NamzCrawlLog;
use App\Models\NamzCrawlSession;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NamzCrawlerController extends AdminController
{
    protected $title = 'Namz Crawler';

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(Content $content): Content
    {
        $session  = NamzCrawlSession::activeSession();
        $lastSess = NamzCrawlSession::lastSession();

        $totalNamz      = MovieModel::where('platform_type', 'namz')->count();
        $activeNamz     = MovieModel::where('platform_type', 'namz')->where('status', 'Active')->count();
        $urlPending     = MovieModel::where('namz_url_status', 'pending')->count();
        $totalSessions  = NamzCrawlSession::count();
        $totalLogs      = NamzCrawlLog::count();
        $successLogs    = NamzCrawlLog::where('status', 'success')->count();

        $isRunning = $session !== null;
        $stopFile  = storage_path('namz_stop');

        // ── Stat cards ──────────────────────────────────────────────────────
        $stats = "
        <style>
          .kt-stat-row { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:18px; }
          .kt-stat     { flex:1 1 160px; background:#fff; border-radius:6px; padding:14px 18px;
                         border-left:4px solid #2980b9; box-shadow:0 1px 4px rgba(0,0,0,.08); }
          .kt-stat .val{ font-size:26px; font-weight:700; color:#2c3e50; }
          .kt-stat .lbl{ font-size:12px; color:#7f8c8d; margin-top:2px; }
          .kt-tbtn      { display:inline-block; padding:6px 14px; border-radius:4px; color:#fff;
                          font-size:13px; text-decoration:none; margin-right:6px; margin-bottom:6px; cursor:pointer; }
          .kt-tbtn-green { background:#27ae60; }
          .kt-tbtn-red   { background:#e74c3c; }
          .kt-tbtn-blue  { background:#2980b9; }
          .kt-tbtn-orange{ background:#e67e22; }
          .kt-tbtn-purple{ background:#8e44ad; }
          .kt-tbtn:hover { opacity:.85; }
          .progress-bar-namz { height:10px; border-radius:5px; background:#ecf0f1; margin-top:6px; overflow:hidden; }
          .progress-bar-namz-fill { height:100%; background:linear-gradient(90deg,#2980b9,#27ae60); }
        </style>
        <div class='kt-stat-row'>
          <div class='kt-stat' style='border-color:#27ae60'>
            <div class='val'>{$totalNamz}</div>
            <div class='lbl'>Total Namz Movies</div>
          </div>
          <div class='kt-stat' style='border-color:#2980b9'>
            <div class='val'>{$activeNamz}</div>
            <div class='lbl'>Active (have URL)</div>
          </div>
          <div class='kt-stat' style='border-color:#e67e22'>
            <div class='val'>{$urlPending}</div>
            <div class='lbl'>URL Pending</div>
          </div>
          <div class='kt-stat' style='border-color:#8e44ad'>
            <div class='val'>{$totalSessions}</div>
            <div class='lbl'>Crawl Sessions</div>
          </div>
          <div class='kt-stat' style='border-color:#16a085'>
            <div class='val'>{$successLogs} / {$totalLogs}</div>
            <div class='lbl'>Log Entries (Success/Total)</div>
          </div>
        </div>";

        // ── Active session status ────────────────────────────────────────────
        $sessionHtml = '';
        if ($isRunning && $session) {
            $pct   = $session->progress_percent;
            $dur   = $session->duration;
            $sessionHtml = "
            <div class='box box-info' style='margin-bottom:18px'>
              <div class='box-header'><h3 class='box-title'><i class='fa fa-spin fa-circle-o-notch'></i> Crawl Running — Session #{$session->id}</h3></div>
              <div class='box-body'>
                <div style='display:flex; gap:20px; flex-wrap:wrap; margin-bottom:10px'>
                  <div><b>Current ID:</b> {$session->id_current} / {$session->id_to}</div>
                  <div><b>Progress:</b> {$pct}%</div>
                  <div><b>Processed:</b> {$session->total_processed}</div>
                  <div><b>Success:</b> {$session->total_success}</div>
                  <div><b>Skipped:</b> {$session->total_skipped}</div>
                  <div><b>Failed:</b> {$session->total_failed}</div>
                  <div><b>New Movies:</b> {$session->total_new_movies}</div>
                  <div><b>URL Pending:</b> {$session->total_url_pending}</div>
                  <div><b>Duration:</b> {$dur}</div>
                </div>
                <div class='progress-bar-namz'><div class='progress-bar-namz-fill' style='width:{$pct}%'></div></div>
              </div>
            </div>";
        } elseif ($lastSess) {
            $badge = $lastSess->status_badge;
            $sessionHtml = "
            <div class='box box-default' style='margin-bottom:18px'>
              <div class='box-header'><h3 class='box-title'>Last Session #{$lastSess->id} — {$badge}</h3></div>
              <div class='box-body'>
                <div style='display:flex; gap:20px; flex-wrap:wrap'>
                  <div><b>Range:</b> {$lastSess->id_from} → {$lastSess->id_to}</div>
                  <div><b>Processed:</b> {$lastSess->total_processed}</div>
                  <div><b>Success:</b> {$lastSess->total_success}</div>
                  <div><b>Skipped:</b> {$lastSess->total_skipped}</div>
                  <div><b>Failed:</b> {$lastSess->total_failed}</div>
                  <div><b>New Movies:</b> {$lastSess->total_new_movies}</div>
                  <div><b>URL Pending:</b> {$lastSess->total_url_pending}</div>
                  <div><b>Duration:</b> {$lastSess->duration}</div>
                  <div><b>Ended:</b> " . ($lastSess->ended_at ? $lastSess->ended_at->format('Y-m-d H:i') : '—') . "</div>
                </div>
              </div>
            </div>";
        }

        // ── Action toolbar ────────────────────────────────────────────────────
        $startDisabled = $isRunning ? 'opacity:.5;pointer-events:none' : '';
        $stopDisabled  = !$isRunning ? 'opacity:.5;pointer-events:none' : '';
        $toolbar = "
        <div style='margin-bottom:18px'>
          <b>Actions:</b><br><br>
          <a href='/namz-crawler/start' class='kt-tbtn kt-tbtn-green' style='{$startDisabled}' onclick=\"return confirm('Start crawl? This runs in the background.')\">
            <i class='fa fa-play'></i> Start Crawl (New)
          </a>
          <a href='/namz-crawler/resume' class='kt-tbtn kt-tbtn-blue' style='{$startDisabled}' onclick=\"return confirm('Resume last session?')\">
            <i class='fa fa-step-forward'></i> Resume Last
          </a>
          <a href='/namz-crawler/stop' class='kt-tbtn kt-tbtn-red' style='{$stopDisabled}' onclick=\"return confirm('Stop the running crawl?')\">
            <i class='fa fa-stop'></i> Stop Crawl
          </a>
          <a href='/namz-crawler/backfill-urls' class='kt-tbtn kt-tbtn-orange' onclick=\"return confirm('Backfill: sync existing namz imdb_id records to new namz_id field?')\">
            <i class='fa fa-refresh'></i> Backfill Old Records
          </a>
          <a href='/namz-crawler/activate-with-url' class='kt-tbtn kt-tbtn-purple'>
            <i class='fa fa-check'></i> Activate Movies With URL
          </a>
          <a href='/namz-crawler/test-login' class='kt-tbtn' style='background:#7f8c8d'>
            <i class='fa fa-key'></i> Test Login
          </a>
          <a href='/namz-crawl-logs' class='kt-tbtn' style='background:#34495e'>
            <i class='fa fa-list'></i> View All Logs
          </a>
        </div>";

        // ── Recent sessions table ────────────────────────────────────────────
        $sessions = NamzCrawlSession::latest()->limit(8)->get();
        $sessRows = [];
        foreach ($sessions as $s) {
            $badge = $s->status_badge;
            $sessRows[] = [
                "#{$s->id}",
                $badge,
                "{$s->id_from} → {$s->id_to} (at {$s->id_current})",
                $s->total_success . ' / ' . $s->total_processed,
                $s->total_new_movies . ' new | ' . $s->total_url_pending . ' pending',
                $s->duration,
                $s->started_at ? $s->started_at->format('m-d H:i') : '—',
                $s->triggered_by,
                "<a href='/namz-crawler/sessions/{$s->id}' class='btn btn-xs btn-info'>Detail</a>",
            ];
        }
        $sessTable = new Table(
            ['ID', 'Status', 'Range / Current', 'Success/Total', 'New | Pending', 'Duration', 'Started', 'By', 'Action'],
            $sessRows
        );

        // ── Recent log entries ────────────────────────────────────────────────
        $logs = NamzCrawlLog::latest()->limit(15)->get();
        $logRows = [];
        foreach ($logs as $l) {
            $vBadge = $l->video_status_badge;
            $sBadge = $l->status_badge;
            $movieLink = $l->movie_id ? "<a href='/movie-models/{$l->movie_id}' target='_blank'>#{$l->movie_id}</a>" : '—';
            $logRows[] = [
                "#{$l->namz_id}",
                e($l->title ?? '—'),
                $sBadge,
                $vBadge,
                $movieLink,
                $l->error_message ? '<small style="color:#e74c3c">' . e(substr($l->error_message, 0, 60)) . '</small>' : '—',
                $l->created_at ? $l->created_at->format('m-d H:i:s') : '—',
            ];
        }
        $logTable = new Table(
            ['Namz ID', 'Title', 'Status', 'Video', 'Movie', 'Error', 'At'],
            $logRows
        );

        return $content
            ->title('Namz Entertainment Crawler')
            ->description('Monitor and control the namzentertainment.com content crawler')
            ->row(function (Row $row) use ($stats, $sessionHtml, $toolbar, $sessTable, $logTable) {
                $row->column(12, function (Column $col) use ($stats, $sessionHtml, $toolbar, $sessTable, $logTable) {
                    $col->append(new Box('Overview', $stats . $sessionHtml . $toolbar));
                    $col->append(new Box('Recent Sessions', $sessTable->render()));
                    $col->append(new Box('Recent Log Entries', $logTable->render()));
                });
            });
    }

    // ── Log Grid ─────────────────────────────────────────────────────────────

    protected function grid(): Grid
    {
        $grid = new Grid(new NamzCrawlLog());
        $grid->model()->orderByDesc('id');

        $grid->disableCreateButton();
        $grid->disableExport();

        $grid->filter(function (Grid\Filter $f) {
            $f->disableIdFilter();
            $f->equal('status')->select([
                'success' => 'Success',
                'skipped' => 'Skipped',
                'failed'  => 'Failed',
                'pending' => 'Pending',
            ]);
            $f->equal('video_status', 'Video Status')->select([
                'found'     => 'Found',
                'not_found' => 'Not Found',
                'pending'   => 'Pending',
            ]);
            $f->equal('result')->select([
                'new_movie'  => 'New Movie',
                'new_series' => 'New Series',
                'existing'   => 'Existing',
                'no_title'   => 'No Title',
                'error'      => 'Error',
            ]);
            $f->equal('session_id', 'Session ID');
            $f->between('namz_id', 'Namz ID Range');
            $f->like('title', 'Title');
            $f->between('created_at', 'Date Range')->datetime();
        });

        $grid->column('id', '#')->sortable();
        $grid->column('namz_id', 'Namz ID')->sortable()->display(function () {
            return "<a href='https://namzentertainment.com/prev.php?id={$this->namz_id}' target='_blank'>{$this->namz_id}</a>";
        });
        $grid->column('title', 'Title')->display(function () {
            return e($this->title ?? '—');
        });
        $grid->column('status', 'Status')->display(function () {
            return $this->status_badge;
        });
        $grid->column('result', 'Result')->display(function () {
            if (!$this->result) return '—';
            $colors = [
                'new_movie'  => '#27ae60',
                'new_series' => '#8e44ad',
                'existing'   => '#7f8c8d',
                'no_title'   => '#95a5a6',
                'error'      => '#e74c3c',
                'dry_run'    => '#2980b9',
            ];
            $c = $colors[$this->result] ?? '#95a5a6';
            return "<span style='background:{$c};color:#fff;padding:2px 6px;border-radius:3px;font-size:11px'>"
                 . str_replace('_', ' ', ucfirst($this->result)) . "</span>";
        });
        $grid->column('video_status', 'Video URL')->display(function () {
            return $this->video_status_badge;
        });
        $grid->column('movie_id', 'Movie')->display(function () {
            if (!$this->movie_id && !$this->series_id) return '—';
            $id = $this->movie_id ?? $this->series_id;
            $type = $this->movie_id ? 'movie-models' : 'series-movies';
            return "<a href='/{$type}/{$id}' target='_blank'>#{$id}</a>";
        });
        $grid->column('session_id', 'Session')->display(function () {
            return $this->session_id
                ? "<a href='/namz-crawler/sessions/{$this->session_id}'>#{$this->session_id}</a>"
                : '—';
        });
        $grid->column('error_message', 'Error')->display(function () {
            if (!$this->error_message) return '—';
            return '<small style="color:#e74c3c">' . e(substr($this->error_message, 0, 80)) . '</small>';
        });
        $grid->column('created_at', 'At')->sortable()->display(function () {
            return $this->created_at ? $this->created_at->format('Y-m-d H:i') : '—';
        });

        // Per-row action: retry if failed
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $row = $actions->row;
            $id  = $row->namz_id;
            if ($row->status === 'failed') {
                $actions->append("<a href='/namz-crawler/retry/{$id}' class='btn btn-xs btn-warning'>Retry</a>");
            }
            if ($row->movie_id) {
                $actions->append("<a href='/namz-crawler/set-url/{$row->movie_id}' class='btn btn-xs btn-success'>Set URL</a>");
            }
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();
        });

        return $grid;
    }

    // ── Session Detail ────────────────────────────────────────────────────────

    public function showSession(int $id, Content $content): Content
    {
        $session = NamzCrawlSession::findOrFail($id);
        $logs    = NamzCrawlLog::where('session_id', $id)->latest()->limit(100)->get();

        $badge = $session->status_badge;
        $pct   = $session->progress_percent;
        $dur   = $session->duration;

        $infoHtml = "
        <div style='display:flex; gap:20px; flex-wrap:wrap; margin-bottom:16px'>
          <div><b>Status:</b> {$badge}</div>
          <div><b>Range:</b> {$session->id_from} → {$session->id_to}</div>
          <div><b>Current ID:</b> {$session->id_current}</div>
          <div><b>Progress:</b> {$pct}%</div>
          <div><b>Processed:</b> {$session->total_processed}</div>
          <div><b>Success:</b> {$session->total_success}</div>
          <div><b>Skipped:</b> {$session->total_skipped}</div>
          <div><b>Failed:</b> {$session->total_failed}</div>
          <div><b>New Movies:</b> {$session->total_new_movies}</div>
          <div><b>New Series:</b> {$session->total_new_series}</div>
          <div><b>URL Pending:</b> {$session->total_url_pending}</div>
          <div><b>Duration:</b> {$dur}</div>
          <div><b>Triggered by:</b> {$session->triggered_by}</div>
          <div><b>Started:</b> " . ($session->started_at ? $session->started_at->format('Y-m-d H:i:s') : '—') . "</div>
          <div><b>Ended:</b> " . ($session->ended_at ? $session->ended_at->format('Y-m-d H:i:s') : '—') . "</div>
        </div>
        <div style='background:#ecf0f1;border-radius:5px;height:12px;overflow:hidden;margin-bottom:16px'>
          <div style='height:100%;width:{$pct}%;background:linear-gradient(90deg,#2980b9,#27ae60)'></div>
        </div>
        " . ($session->notes ? "<div><b>Notes:</b> " . e($session->notes) . "</div>" : '');

        $logRows = [];
        foreach ($logs as $l) {
            $logRows[] = [
                "#{$l->namz_id}",
                e($l->title ?? '—'),
                $l->status_badge,
                $l->video_status_badge,
                $l->result ?? '—',
                $l->movie_id ? "<a href='/movie-models/{$l->movie_id}'>#{$l->movie_id}</a>" : '—',
                $l->error_message ? '<small style="color:red">' . e(substr($l->error_message, 0, 80)) . '</small>' : '—',
                $l->created_at ? $l->created_at->format('H:i:s') : '—',
            ];
        }
        $logTable = new Table(
            ['Namz ID', 'Title', 'Status', 'Video', 'Result', 'Movie', 'Error', 'Time'],
            $logRows
        );

        return $content
            ->title("Session #{$id} Detail")
            ->row(function (Row $row) use ($infoHtml, $logTable) {
                $row->column(12, function (Column $col) use ($infoHtml, $logTable) {
                    $col->append(new Box("Session Info", $infoHtml));
                    $col->append(new Box('Log Entries (last 100)', $logTable->render()));
                });
            });
    }

    // ── Actions ────────────────────────────────────────────────────────────────

    public function startCrawl(Request $request)
    {
        $from  = (int) $request->input('from', 47);
        $to    = (int) $request->input('to', 9200);
        $delay = (int) $request->input('delay', 800);

        // Don't start if already running
        if (NamzCrawlSession::activeSession()) {
            return redirect('/namz-crawler')->with('error', 'A crawl session is already running.');
        }

        $cmd = "php " . base_path('artisan') . " namz:crawl --from={$from} --to={$to} --delay={$delay} >> " . storage_path('logs/namz_crawl.log') . " 2>&1 &";
        exec($cmd);

        return redirect('/namz-crawler')->with('success', "Crawl started for IDs {$from}–{$to}.");
    }

    public function resumeCrawl()
    {
        $last = NamzCrawlSession::where('status', '!=', NamzCrawlSession::STATUS_RUNNING)->latest()->first();
        if (!$last) {
            return redirect('/namz-crawler')->with('error', 'No previous session to resume.');
        }

        $cmd = "php " . base_path('artisan') . " namz:crawl --session={$last->id} >> " . storage_path('logs/namz_crawl.log') . " 2>&1 &";
        exec($cmd);

        return redirect('/namz-crawler')->with('success', "Resumed session #{$last->id}.");
    }

    public function stopCrawl()
    {
        file_put_contents(storage_path('namz_stop'), '1');
        return redirect('/namz-crawler')->with('success', 'Stop signal sent. Crawl will halt after current ID.');
    }

    public function testLogin()
    {
        try {
            $email    = config('services.namz.email');
            $password = config('services.namz.password');
            $jar      = new \GuzzleHttp\Cookie\CookieJar();
            $client   = new \GuzzleHttp\Client(['allow_redirects' => true, 'verify' => false, 'timeout' => 15]);

            // GET signin
            $html = (string) $client->get('https://namzentertainment.com/signin.php', ['cookies' => $jar])->getBody();
            preg_match('/name="token"\s+value="([^"]+)"/', $html, $m);
            $token = $m[1] ?? '';

            if (!$token) {
                return redirect('/namz-crawler')->with('error', 'Could not extract CSRF token from signin page.');
            }

            $client->post('https://namzentertainment.com/alter.php', [
                'cookies'     => $jar,
                'form_params' => ['token' => $token, 'usname' => $email, 'pword' => $password, 'remember' => 'on', 'submit' => 'Sign in'],
            ]);

            $lock = json_decode((string) $client->post('https://namzentertainment.com/lock.php', ['cookies' => $jar])->getBody(), true);

            if (($lock['success'] ?? '') == '1' || ($lock['success'] ?? '') == 1) {
                return redirect('/namz-crawler')->with('success', "Login successful! Session valid. Token extracted, lock.php returned success.");
            }
            return redirect('/namz-crawler')->with('error', 'Login appeared to work but lock.php returned: ' . json_encode($lock));
        } catch (\Throwable $e) {
            return redirect('/namz-crawler')->with('error', 'Login test failed: ' . $e->getMessage());
        }
    }

    public function backfillOldRecords()
    {
        // Sync existing namz movies that have imdb_url like namzentertainment but no namz_id
        $updated = DB::statement("
            UPDATE movie_models
            SET namz_id = imdb_id,
                platform_type = 'namz',
                namz_url_status = CASE WHEN url IS NOT NULL AND url <> '' THEN 'found' ELSE 'pending' END
            WHERE imdb_url LIKE '%namzentertainment%'
              AND (namz_id IS NULL OR namz_id = 0)
              AND imdb_id IS NOT NULL
              AND imdb_id > 0
        ");
        $count = DB::table('movie_models')
            ->where('platform_type', 'namz')
            ->count();

        return redirect('/namz-crawler')->with('success', "Backfill done. {$count} namz movies now have platform_type=namz.");
    }

    public function activateMoviesWithUrl()
    {
        $count = DB::table('movie_models')
            ->where('platform_type', 'namz')
            ->where('namz_url_status', 'pending')
            ->whereNotNull('url')
            ->where('url', '<>', '')
            ->where('url', 'not like', '%namzentertainment%')
            ->update([
                'status'          => 'Active',
                'namz_url_status' => 'found',
                'fix_status'      => null,
            ]);

        return redirect('/namz-crawler')->with('success', "Activated {$count} movies that had URL set.");
    }

    public function retryId(int $namzId)
    {
        // Re-run crawl for a single namz ID
        // First delete SUCCESS counter so it re-processes
        MyCounter::where('type', 'namz_crawler_v2')->where('count_value', $namzId)->delete();

        $cmd = "php " . base_path('artisan') . " namz:crawl --single={$namzId} >> " . storage_path('logs/namz_crawl.log') . " 2>&1 &";
        exec($cmd);

        return redirect()->back()->with('success', "Re-queued namz ID {$namzId}.");
    }

    public function setUrlForm(int $movieId, Content $content): Content
    {
        $movie = MovieModel::findOrFail($movieId);

        $form = "
        <form method='POST' action='/namz-crawler/set-url/{$movieId}/save'>
          <input type='hidden' name='_token' value='" . csrf_token() . "'>
          <div class='form-group'>
            <label>Movie: <b>" . e($movie->title) . "</b> (namz_id: {$movie->namz_id})</label>
          </div>
          <div class='form-group'>
            <label>Namzentertainment Page</label>
            <a href='https://namzentertainment.com/prev.php?id={$movie->namz_id}' target='_blank'>
              Open Movie Page on Namzentertainment ↗
            </a>
          </div>
          <div class='form-group'>
            <label>Current URL</label>
            <input type='text' class='form-control' value='" . e($movie->url ?? '') . "' readonly>
          </div>
          <div class='form-group'>
            <label>New Video URL *</label>
            <input type='url' name='video_url' class='form-control' required placeholder='https://...mp4'>
            <small>Paste the direct video URL from the namzentertainment player (copy from browser network inspector)</small>
          </div>
          <button type='submit' class='btn btn-success'>Save & Activate</button>
          <a href='/namz-crawl-logs' class='btn btn-default'>Cancel</a>
        </form>";

        return $content
            ->title("Set Video URL — " . e($movie->title))
            ->row(function (Row $row) use ($form) {
                $row->column(8, function (Column $col) use ($form) {
                    $col->append(new Box('Set Video URL', $form));
                });
            });
    }

    public function saveUrl(int $movieId, Request $request)
    {
        $videoUrl = $request->input('video_url');
        $movie    = MovieModel::findOrFail($movieId);

        $movie->url             = $videoUrl;
        $movie->external_url    = $videoUrl;
        $movie->status          = 'Active';
        $movie->namz_url_status = 'found';
        $movie->fix_status      = null;
        $movie->save();

        // Update log if exists
        NamzCrawlLog::where('movie_id', $movieId)
            ->where('video_status', 'not_found')
            ->update(['video_url' => $videoUrl, 'video_status' => 'found']);

        return redirect('/namz-crawl-logs')->with('success', "URL saved and movie #{$movieId} activated.");
    }

    public function liveSessions()
    {
        $session = NamzCrawlSession::activeSession();
        if (!$session) {
            return response()->json(['running' => false]);
        }
        $session->refresh();
        return response()->json([
            'running'        => true,
            'session_id'     => $session->id,
            'id_current'     => $session->id_current,
            'id_to'          => $session->id_to,
            'progress'       => $session->progress_percent,
            'total_processed'=> $session->total_processed,
            'total_success'  => $session->total_success,
            'total_failed'   => $session->total_failed,
            'total_new'      => $session->total_new_movies,
            'url_pending'    => $session->total_url_pending,
            'duration'       => $session->duration,
        ]);
    }
}
