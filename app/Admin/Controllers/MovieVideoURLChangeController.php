<?php

namespace App\Admin\Controllers;

use App\Models\MovieVideoURLChange;
use App\Jobs\PushUrlChangeToOrigin;
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
use Illuminate\Support\Facades\Log;

class MovieVideoURLChangeController extends AdminController
{
    protected $title = 'Movie URL Changes';

    // ── List ──────────────────────────────────────────────────────────────────

    protected function grid(): Grid
    {
        $grid = new Grid(new MovieVideoURLChange());
        $grid->model()->orderByDesc('id');

        $grid->disableCreateButton();
        $grid->disableExport();

        // ── Filters ───────────────────────────────────────────────────────────
        $grid->filter(function (Grid\Filter $f) {
            $f->disableIdFilter();
            $f->equal('status')->select([
                'pending'  => 'Pending',
                'applying' => 'Applying',
                'applied'  => 'Applied',
                'failed'   => 'Failed',
                'reverted' => 'Reverted',
            ]);
            $f->equal('remote_status', 'Remote Status')->select([
                'pending'  => 'Pending',
                'pushing'  => 'Pushing',
                'applied'  => 'Applied',
                'failed'   => 'Failed',
                'skipped'  => 'Skipped',
            ]);
            $f->equal('source')->select([
                'hetzner_transfer' => 'Hetzner Transfer',
                'manual'           => 'Manual',
                'api_push'         => 'API Push (Received)',
                'revert'           => 'Revert',
            ]);
            $f->like('movie_title', 'Movie Title');
            $f->equal('movie_model_id', 'Movie ID');
            $f->between('created_at', 'Date Range')->datetime();
        });

        // ── Columns ───────────────────────────────────────────────────────────
        $grid->column('id', '#')->sortable();

        $grid->column('movie_model_id', 'Movie')->display(function () {
            $id    = (int) $this->movie_model_id;
            $title = e($this->movie_title ?: "Movie #{$id}");
            return "<a href='/movie-models/{$id}' target='_blank'><b>{$title}</b></a><br>"
                 . "<small style='color:#888'>ID: {$id}</small>";
        });

        $grid->column('old_url', 'Old URL')->display(function () {
            if (!$this->old_url) return '<span style="color:#aaa">—</span>';
            $short = strlen($this->old_url) > 55 ? substr($this->old_url, 0, 55) . '…' : $this->old_url;
            return "<a href='" . e($this->old_url) . "' target='_blank' title='" . e($this->old_url) . "'>"
                 . "<small>" . e($short) . "</small></a>";
        });

        $grid->column('new_url', 'New URL')->display(function () {
            $short = strlen($this->new_url) > 55 ? substr($this->new_url, 0, 55) . '…' : $this->new_url;
            return "<a href='" . e($this->new_url) . "' target='_blank' title='" . e($this->new_url) . "'>"
                 . "<small style='color:#27ae60'>" . e($short) . "</small></a>";
        });

        $grid->column('source', 'Source')->display(function () {
            $colors = [
                'hetzner_transfer' => '#2980b9',
                'manual'           => '#8e44ad',
                'api_push'         => '#16a085',
                'revert'           => '#e67e22',
            ];
            $color = $colors[$this->source] ?? '#95a5a6';
            $label = str_replace('_', ' ', ucfirst($this->source));
            return "<span style='background:{$color};color:#fff;padding:2px 7px;border-radius:3px;font-size:11px'>{$label}</span>";
        });

        $grid->column('status', 'Status')->display(function () {
            $color = $this->status_badge_color;
            return "<span style='background:{$color};color:#fff;padding:2px 7px;border-radius:3px;font-size:11px'>"
                 . ucfirst($this->status) . "</span>";
        });

        $grid->column('remote_status', 'Remote')->display(function () {
            $color = $this->remote_badge_color;
            return "<span style='background:{$color};color:#fff;padding:2px 7px;border-radius:3px;font-size:11px'>"
                 . ucfirst($this->remote_status) . "</span>";
        });

        $grid->column('attempts', 'Tries')->display(function () {
            $max = $this->max_attempts;
            $n   = $this->attempts;
            $color = $n >= $max ? '#c0392b' : ($n > 0 ? '#e67e22' : '#27ae60');
            return "<span style='color:{$color}'>{$n}/{$max}</span>";
        });

        $grid->column('created_at', 'Date')->display(function () {
            return "<small>" . e($this->created_at?->diffForHumans()) . "</small>";
        })->sortable();

        // ── Row actions ───────────────────────────────────────────────────────
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $id = $actions->getKey();
            $row = $actions->row;

            // Retry remote push
            if (in_array($row->remote_status, ['failed', 'pending']) && $row->local_status === 'applied') {
                $actions->append(
                    "<a href='/movie-url-changes/{$id}/retry-remote' class='btn btn-xs btn-warning' "
                    . "onclick=\"return confirm('Re-push URL to origin server?')\""
                    . " title='Retry Remote Push'><i class='fa fa-refresh'></i></a> "
                );
            }

            // Revert
            if ($row->can_revert) {
                $actions->append(
                    "<a href='/movie-url-changes/{$id}/revert' class='btn btn-xs btn-danger' "
                    . "onclick=\"return confirm('Revert URL back to old value? This will create a new revert record.')\" "
                    . "title='Revert URL'><i class='fa fa-undo'></i></a> "
                );
            }
        });

        $grid->disableRowSelector(false);
        $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
            $batch->add(new \App\Admin\Actions\BatchRetryRemotePush());
        });

        return $grid;
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    public function show($id, Content $content): Content
    {
        $change = MovieVideoURLChange::findOrFail($id);

        return $content
            ->title('URL Change #' . $id)
            ->description(e($change->movie_title ?? 'Movie #' . $change->movie_model_id))
            ->body(function (Row $row) use ($change) {
                $row->column(12, $this->renderDashboardCss());
                $row->column(12, $this->buildDetailCard($change));
                $row->column(12, $this->buildHistoryTable($change->movie_model_id));
            });
    }

    private function buildDetailCard(MovieVideoURLChange $change): string
    {
        $statusColor = $change->status_badge_color;
        $remoteColor = $change->remote_badge_color;

        $oldUrl = $change->old_url ?? '—';
        $newUrl = $change->new_url;

        $actions = '';
        if (in_array($change->remote_status, ['failed', 'pending']) && $change->local_status === 'applied') {
            $actions .= "<a href='/movie-url-changes/{$change->id}/retry-remote' class='btn btn-warning btn-sm mr-2'>"
                      . "<i class='fa fa-refresh'></i> Retry Remote Push</a>";
        }
        if ($change->can_revert) {
            $actions .= "<a href='/movie-url-changes/{$change->id}/revert' class='btn btn-danger btn-sm' "
                      . "onclick=\"return confirm('Revert this URL change?')\">"
                      . "<i class='fa fa-undo'></i> Revert URL</a>";
        }

        $remoteResp = $change->remote_response
            ? '<pre style="max-height:120px;overflow:auto;font-size:11px;background:#f5f5f5;padding:8px;border-radius:4px">'
              . e($change->remote_response) . '</pre>'
            : '<span style="color:#aaa">—</span>';

        $errMsg = $change->error_message
            ? '<div style="background:#fdf2f2;border:1px solid #e74c3c;padding:8px 12px;border-radius:4px;color:#c0392b;font-size:12px">'
              . e($change->error_message) . '</div>'
            : '<span style="color:#aaa">—</span>';

        $transferLink = $change->transfer_id
            ? "<a href='/movie-file-transfers/{$change->transfer_id}'>#" . e($change->transfer_id) . "</a>"
            : '—';

        return "
        <div style='background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:16px'>
            <h4 style='margin:0 0 16px;color:#2d3748'>URL Change Details</h4>
            <table style='width:100%;border-collapse:collapse;font-size:13px'>
                <tr><td style='padding:6px 8px;color:#64748b;width:160px'>Movie</td>
                    <td style='padding:6px 8px'><b>" . e($change->movie_title ?? 'Movie #' . $change->movie_model_id) . "</b>
                    &nbsp;<small style='color:#888'>ID: {$change->movie_model_id}</small></td></tr>
                <tr style='background:#f8fafc'><td style='padding:6px 8px;color:#64748b'>Old URL</td>
                    <td style='padding:6px 8px;word-break:break-all'><a href='" . e($oldUrl) . "' target='_blank'>" . e($oldUrl) . "</a></td></tr>
                <tr><td style='padding:6px 8px;color:#64748b'>New URL</td>
                    <td style='padding:6px 8px;word-break:break-all'><a href='" . e($newUrl) . "' target='_blank' style='color:#27ae60'>" . e($newUrl) . "</a></td></tr>
                <tr style='background:#f8fafc'><td style='padding:6px 8px;color:#64748b'>Source</td>
                    <td style='padding:6px 8px'>" . str_replace('_', ' ', ucfirst($change->source)) . "</td></tr>
                <tr><td style='padding:6px 8px;color:#64748b'>Status</td>
                    <td style='padding:6px 8px'>
                        <span style='background:{$statusColor};color:#fff;padding:2px 8px;border-radius:3px'>" . ucfirst($change->status) . "</span>
                        &nbsp;
                        <span style='font-size:11px'>Local: <b>" . ucfirst($change->local_status) . "</b></span>
                        &nbsp;·&nbsp;
                        <span style='font-size:11px'>Remote: <span style='background:{$remoteColor};color:#fff;padding:1px 6px;border-radius:3px'>" . ucfirst($change->remote_status) . "</span></span>
                    </td></tr>
                <tr style='background:#f8fafc'><td style='padding:6px 8px;color:#64748b'>Attempts</td>
                    <td style='padding:6px 8px'>{$change->attempts} / {$change->max_attempts}</td></tr>
                <tr><td style='padding:6px 8px;color:#64748b'>Remote Endpoint</td>
                    <td style='padding:6px 8px;word-break:break-all'>" . e($change->remote_endpoint ?? '—') . "</td></tr>
                <tr style='background:#f8fafc'><td style='padding:6px 8px;color:#64748b'>Transfer</td>
                    <td style='padding:6px 8px'>{$transferLink}</td></tr>
                <tr><td style='padding:6px 8px;color:#64748b'>Local Applied</td>
                    <td style='padding:6px 8px'>" . ($change->local_applied_at?->format('Y-m-d H:i:s') ?? '—') . "</td></tr>
                <tr style='background:#f8fafc'><td style='padding:6px 8px;color:#64748b'>Remote Applied</td>
                    <td style='padding:6px 8px'>" . ($change->remote_applied_at?->format('Y-m-d H:i:s') ?? '—') . "</td></tr>
                <tr><td style='padding:6px 8px;color:#64748b'>Remote Response</td>
                    <td style='padding:6px 8px'>{$remoteResp}</td></tr>
                <tr style='background:#f8fafc'><td style='padding:6px 8px;color:#64748b'>Error</td>
                    <td style='padding:6px 8px'>{$errMsg}</td></tr>
                <tr><td style='padding:6px 8px;color:#64748b'>Created</td>
                    <td style='padding:6px 8px'>" . $change->created_at?->format('Y-m-d H:i:s') . "</td></tr>
            </table>
            " . ($actions ? "<div style='margin-top:16px;border-top:1px solid #e2e8f0;padding-top:16px'>{$actions}</div>" : "") . "
        </div>";
    }

    private function buildHistoryTable(int $movieId): string
    {
        $rows = MovieVideoURLChange::where('movie_model_id', $movieId)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) return '';

        $tr = '';
        foreach ($rows as $r) {
            $color  = $r->status_badge_color;
            $rcolor = $r->remote_badge_color;
            $newUrl = strlen($r->new_url) > 50 ? substr($r->new_url, 0, 50) . '…' : $r->new_url;
            $tr .= "<tr>
                <td><a href='/movie-url-changes/{$r->id}'>#{$r->id}</a></td>
                <td>" . str_replace('_', ' ', ucfirst($r->source)) . "</td>
                <td><a href='" . e($r->new_url) . "' target='_blank' style='color:#27ae60;font-size:11px'>" . e($newUrl) . "</a></td>
                <td><span style='background:{$color};color:#fff;padding:1px 6px;border-radius:3px;font-size:11px'>" . ucfirst($r->status) . "</span></td>
                <td><span style='background:{$rcolor};color:#fff;padding:1px 6px;border-radius:3px;font-size:11px'>" . ucfirst($r->remote_status) . "</span></td>
                <td style='font-size:11px'>" . ($r->created_at?->format('m-d H:i')) . "</td>
            </tr>";
        }

        return "
        <div style='background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px'>
            <h5 style='margin:0 0 12px;color:#2d3748'>History for this Movie</h5>
            <table style='width:100%;border-collapse:collapse;font-size:12px'>
                <thead><tr style='background:#f0f4f8'>
                    <th style='padding:6px 8px;text-align:left'>#</th>
                    <th style='padding:6px 8px;text-align:left'>Source</th>
                    <th style='padding:6px 8px;text-align:left'>New URL</th>
                    <th style='padding:6px 8px;text-align:left'>Status</th>
                    <th style='padding:6px 8px;text-align:left'>Remote</th>
                    <th style='padding:6px 8px;text-align:left'>Date</th>
                </tr></thead>
                <tbody>{$tr}</tbody>
            </table>
        </div>";
    }

    // ── Action endpoints ──────────────────────────────────────────────────────

    public function retryRemote(int $id)
    {
        $change = MovieVideoURLChange::findOrFail($id);

        if ($change->remote_status === MovieVideoURLChange::REMOTE_APPLIED) {
            admin_toastr('Remote push already applied.', 'info');
            return redirect('/movie-url-changes/' . $id);
        }

        $change->update([
            'remote_status' => MovieVideoURLChange::REMOTE_PENDING,
            'attempts'      => 0,
        ]);

        dispatch(new PushUrlChangeToOrigin($id))->onQueue('url-sync');

        admin_toastr("Remote push queued for change #{$id}.", 'success');
        return redirect('/movie-url-changes/' . $id);
    }

    public function revert(int $id)
    {
        $change = MovieVideoURLChange::findOrFail($id);

        try {
            $revert = $change->revert();
            admin_toastr("Revert created as change #{$revert->id}. URL restoration is in progress.", 'success');
            return redirect('/movie-url-changes/' . $revert->id);
        } catch (\Throwable $e) {
            admin_toastr('Revert failed: ' . $e->getMessage(), 'error');
            return redirect('/movie-url-changes/' . $id);
        }
    }

    /**
     * Stats dashboard — shows overview of all URL changes.
     */
    public function dashboard(Content $content): Content
    {
        $stats = $this->getStats();

        return $content
            ->title('URL Sync Dashboard')
            ->description('Monitor and manage movie URL changes across servers')
            ->body(function (Row $row) use ($stats) {
                $row->column(12, $this->renderDashboardCss() . $this->buildStatsRow($stats));
                $row->column(12, $this->buildPendingTable());
                $row->column(12, $this->buildRecentTable());
            });
    }

    private function getStats(): array
    {
        $base = MovieVideoURLChange::query();
        return [
            'total'          => (clone $base)->count(),
            'applied'        => (clone $base)->where('status', 'applied')->count(),
            'pending'        => (clone $base)->where('status', 'pending')->count(),
            'failed'         => (clone $base)->where('status', 'failed')->count(),
            'remote_applied' => (clone $base)->where('remote_status', 'applied')->count(),
            'remote_failed'  => (clone $base)->where('remote_status', 'failed')->count(),
            'remote_pending' => (clone $base)->whereIn('remote_status', ['pending', 'pushing'])->count(),
            'reverted'       => (clone $base)->where('status', 'reverted')->count(),
        ];
    }

    private function buildStatsRow(array $s): string
    {
        $boxes = [
            ['#27ae60', 'fa-check-circle',   $s['applied'],        'Fully Applied'],
            ['#2980b9', 'fa-hourglass-half', $s['pending'],        'Pending'],
            ['#c0392b', 'fa-times-circle',   $s['failed'],         'Failed'],
            ['#8e44ad', 'fa-undo',           $s['reverted'],       'Reverted'],
            ['#16a085', 'fa-cloud-upload',   $s['remote_applied'], 'Remote Applied'],
            ['#e67e22', 'fa-exclamation',    $s['remote_failed'],  'Remote Failed'],
        ];

        $html = "<div class='kt-stat-row'>";
        foreach ($boxes as [$color, $icon, $val, $label]) {
            $html .= "
            <div class='kt-stat-box' style='--ac:{$color}'>
                <div class='v'>{$val}</div>
                <div class='l'>{$label}</div>
                <i class='ico fa {$icon}'></i>
            </div>";
        }

        $originUrl = config('services.url_sync.origin_url');
        $pingUrl   = $originUrl ? rtrim($originUrl, '/') . '/api/movie-url-sync/ping' : null;
        $connHtml  = $pingUrl
            ? "<a href='/movie-url-changes/test-connection' class='btn btn-sm btn-default' style='margin-left:12px'>"
              . "<i class='fa fa-plug'></i> Test Connection</a>"
            : "<span style='color:#aaa;font-size:12px;margin-left:12px'>No origin URL configured</span>";

        $html .= "</div>
        <div style='margin:12px 0 8px;display:flex;align-items:center;gap:12px'>
            <a href='/movie-url-changes/push-pending' class='btn btn-sm btn-primary'
               onclick=\"return confirm('Queue all pending remote pushes now?')\">
                <i class='fa fa-send'></i> Push All Pending
            </a>
            <a href='/movie-url-changes' class='btn btn-sm btn-default'>
                <i class='fa fa-list'></i> View All
            </a>
            {$connHtml}
        </div>";

        return $html;
    }

    private function buildPendingTable(): string
    {
        $rows = MovieVideoURLChange::whereIn('remote_status', ['pending', 'failed'])
            ->where('local_status', 'applied')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        if ($rows->isEmpty()) {
            return "<div style='background:#f0fff4;border:1px solid #68d391;padding:12px 16px;border-radius:6px;color:#276749;margin-bottom:12px'>"
                 . "<i class='fa fa-check'></i> No pending remote pushes — all caught up!</div>";
        }

        $tr = '';
        foreach ($rows as $r) {
            $rcolor = $r->remote_badge_color;
            $tr .= "<tr>
                <td><a href='/movie-url-changes/{$r->id}'>#{$r->id}</a></td>
                <td>" . e($r->movie_title ?? 'Movie #' . $r->movie_model_id) . "</td>
                <td><span style='background:{$rcolor};color:#fff;padding:1px 6px;border-radius:3px;font-size:11px'>" . ucfirst($r->remote_status) . "</span></td>
                <td>{$r->attempts}/{$r->max_attempts}</td>
                <td style='font-size:11px'>" . ($r->created_at?->diffForHumans()) . "</td>
                <td><a href='/movie-url-changes/{$r->id}/retry-remote' class='btn btn-xs btn-warning'>
                    <i class='fa fa-refresh'></i> Retry</a></td>
            </tr>";
        }

        return "
        <div style='background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:12px'>
            <h5 style='margin:0 0 10px;color:#c0392b'><i class='fa fa-exclamation-triangle'></i> Needs Remote Push ({$rows->count()})</h5>
            <table style='width:100%;border-collapse:collapse;font-size:12px'>
                <thead><tr style='background:#fef2f2'>
                    <th style='padding:5px 8px;text-align:left'>#</th>
                    <th style='padding:5px 8px;text-align:left'>Movie</th>
                    <th style='padding:5px 8px;text-align:left'>Remote Status</th>
                    <th style='padding:5px 8px;text-align:left'>Attempts</th>
                    <th style='padding:5px 8px;text-align:left'>Created</th>
                    <th style='padding:5px 8px;text-align:left'>Action</th>
                </tr></thead>
                <tbody>{$tr}</tbody>
            </table>
        </div>";
    }

    private function buildRecentTable(): string
    {
        $rows = MovieVideoURLChange::orderByDesc('id')->limit(10)->get();

        $tr = '';
        foreach ($rows as $r) {
            $color  = $r->status_badge_color;
            $rcolor = $r->remote_badge_color;
            $newUrl = strlen($r->new_url) > 50 ? substr($r->new_url, 0, 50) . '…' : $r->new_url;
            $tr .= "<tr>
                <td><a href='/movie-url-changes/{$r->id}'>#{$r->id}</a></td>
                <td>" . e($r->movie_title ?? 'Movie #' . $r->movie_model_id) . "</td>
                <td><a href='" . e($r->new_url) . "' target='_blank' style='color:#27ae60;font-size:11px'>" . e($newUrl) . "</a></td>
                <td><span style='background:{$color};color:#fff;padding:1px 6px;border-radius:3px;font-size:11px'>" . ucfirst($r->status) . "</span></td>
                <td><span style='background:{$rcolor};color:#fff;padding:1px 6px;border-radius:3px;font-size:11px'>" . ucfirst($r->remote_status) . "</span></td>
                <td style='font-size:11px'>" . ($r->created_at?->diffForHumans()) . "</td>
            </tr>";
        }

        return "
        <div style='background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px'>
            <h5 style='margin:0 0 10px;color:#2d3748'>Recent Changes</h5>
            <table style='width:100%;border-collapse:collapse;font-size:12px'>
                <thead><tr style='background:#f0f4f8'>
                    <th style='padding:5px 8px;text-align:left'>#</th>
                    <th style='padding:5px 8px;text-align:left'>Movie</th>
                    <th style='padding:5px 8px;text-align:left'>New URL</th>
                    <th style='padding:5px 8px;text-align:left'>Status</th>
                    <th style='padding:5px 8px;text-align:left'>Remote</th>
                    <th style='padding:5px 8px;text-align:left'>Date</th>
                </tr></thead>
                <tbody>{$tr}</tbody>
            </table>
        </div>";
    }

    /**
     * Queue all pending/failed remote pushes immediately.
     */
    public function pushPending()
    {
        $rows = MovieVideoURLChange::whereIn('remote_status', ['pending', 'failed'])
            ->where('local_status', 'applied')
            ->where('attempts', '<', \DB::raw('max_attempts'))
            ->get();

        $count = 0;
        foreach ($rows as $r) {
            $r->update(['remote_status' => MovieVideoURLChange::REMOTE_PENDING, 'attempts' => 0]);
            dispatch(new PushUrlChangeToOrigin($r->id))->onQueue('url-sync');
            $count++;
        }

        admin_toastr("Queued {$count} remote push(es).", 'success');
        return redirect('/movie-url-changes/dashboard');
    }

    /**
     * Test connection to origin server.
     */
    public function testConnection()
    {
        $originUrl = config('services.url_sync.origin_url');
        $secret    = config('services.url_sync.secret');

        if (!$originUrl || !$secret) {
            admin_toastr('URL_SYNC_ORIGIN_URL or URL_SYNC_SECRET not configured.', 'error');
            return redirect('/movie-url-changes/dashboard');
        }

        $endpoint = rtrim($originUrl, '/') . '/api/movie-url-sync/ping';
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'X-Url-Sync-Secret: ' . $secret,
                'Accept: application/json',
            ],
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            admin_toastr("Connection failed: {$err}", 'error');
        } elseif ($httpCode === 200) {
            admin_toastr("Origin server responded OK (HTTP 200). Connection is healthy.", 'success');
        } else {
            admin_toastr("Origin server returned HTTP {$httpCode}: " . substr($body, 0, 150), 'warning');
        }

        return redirect('/movie-url-changes/dashboard');
    }

    private function renderDashboardCss(): string
    {
        return "<style>
        .kt-stat-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
        .kt-stat-box{
            position:relative;flex:1;min-width:140px;background:#fff;
            border:1px solid #e2e8f0;border-radius:8px;padding:14px 14px 10px;
            border-left:4px solid var(--ac,#2980b9);overflow:hidden
        }
        .kt-stat-box .v{font-size:22px;font-weight:700;color:var(--ac,#2980b9)}
        .kt-stat-box .l{font-size:11px;color:#64748b;margin-top:2px}
        .kt-stat-box .ico{position:absolute;right:10px;top:10px;font-size:22px;opacity:.12;color:var(--ac)}
        </style>";
    }
}
