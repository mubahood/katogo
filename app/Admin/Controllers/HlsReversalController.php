<?php

namespace App\Admin\Controllers;

use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Layout\Column;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HlsReversalController
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function adminBase(): string
    {
        return '/' . trim((string) config('admin.route.prefix', 'admin'), '/');
    }

    private function statusBadge(string $status): string
    {
        $statusColors = [
            'pending'   => '#f0ad4e',
            'approved'  => '#337ab7',
            'executing' => '#5bc0de',
            'reversed'  => '#5cb85c',
            'failed'    => '#d9534f',
            'skipped'   => '#999999',
            'active'    => '#5cb85c',
            'inactive'  => '#999999',
        ];
        $color = $statusColors[$status] ?? '#999999';
        return "<span style='background:{$color};color:#fff;padding:2px 8px;border-radius:10px;font-size:11px'>" . e($status) . "</span>";
    }

    private function reasonBadge(?string $reason): string
    {
        $reasonLabels = [
            'no_traffic_7d'     => 'No Traffic 7d',
            'low_completion'    => 'Low Completion',
            'combined'          => 'Combined',
            'series_no_traffic' => 'Series No Traffic',
            'no_views_ever'     => 'Never Watched',
            'manual'            => 'Manual',
        ];
        $reasonColors = [
            'no_traffic_7d'     => '#e67e22',
            'low_completion'    => '#8e44ad',
            'combined'          => '#c0392b',
            'series_no_traffic' => '#16a085',
            'no_views_ever'     => '#7f8c8d',
            'manual'            => '#2980b9',
        ];
        $label = $reasonLabels[$reason ?? ''] ?? ($reason ?? '—');
        $color = $reasonColors[$reason ?? ''] ?? '#95a5a6';
        return "<span style='background:{$color};color:#fff;padding:2px 8px;border-radius:10px;font-size:11px'>" . e($label) . "</span>";
    }

    // ── Index / Dashboard ─────────────────────────────────────────────────────

    public function index(Content $content): Content
    {
        $base = $this->adminBase();

        // Stats from hls_transfer_records
        $activeTransfers = DB::table('hls_transfer_records')->where('status', 'active')->count();
        $activeGB        = round(
            (float) DB::table('hls_transfer_records')->where('status', 'active')->sum('total_size_mb') / 1024,
            2
        );

        // Stats from hls_reversal_queue
        $pendingCount  = DB::table('hls_reversal_queue')->where('status', 'pending')->count();
        $reversedCount = DB::table('hls_reversal_queue')->where('status', 'reversed')->count();
        $reversedGB    = round(
            (float) DB::table('hls_reversal_queue')->where('status', 'reversed')->sum('size_mb') / 1024,
            2
        );
        $failedCount   = DB::table('hls_reversal_queue')->where('status', 'failed')->count();

        // Top 10 pending candidates by score
        $topPending = DB::table('hls_reversal_queue')
            ->where('status', 'pending')
            ->orderByDesc('score')
            ->limit(10)
            ->get();

        // Build toolbar HTML
        $toolbar = <<<HTML
        <div style="margin-bottom:16px">
            <a href="{$base}/hls-reversals/analyze" class="btn btn-warning btn-sm">
                <i class="fa fa-search"></i> Analyze Candidates
            </a>
            &nbsp;
            <a href="{$base}/hls-reversals/queue" class="btn btn-primary btn-sm">
                <i class="fa fa-list"></i> View Queue
            </a>
            &nbsp;
            <a href="{$base}/hls-reversals/records" class="btn btn-info btn-sm">
                <i class="fa fa-database"></i> View Records
            </a>
        </div>
        HTML;

        // Build top-pending table rows
        $pendingRows = [];
        foreach ($topPending as $row) {
            $pendingRows[] = [
                e($row->movie_title ?? '—'),
                $this->reasonBadge($row->reason ?? null),
                number_format((float) ($row->score ?? 0), 2),
                number_format((int) ($row->views_7d ?? 0)),
                number_format((float) ($row->avg_completion_pct ?? 0), 1) . '%',
                number_format((float) ($row->size_mb ?? 0), 1) . ' MB',
            ];
        }

        if (empty($pendingRows)) {
            $pendingRows = [['<span style="color:#999">No pending entries.</span>', '', '', '', '', '']];
        }

        $pendingTable   = new Table(['Movie', 'Reason', 'Score', 'Views 7d', 'Completion %', 'Size MB'], $pendingRows);
        $pendingTableHtml = $pendingTable->render();
        $topBox         = new Box('Top 10 Pending Candidates (by Score)', $pendingTableHtml);

        return $content
            ->title('HLS Reversals')
            ->description('Manage and execute HLS content reversals')
            ->row(function (Row $row) use ($toolbar) {
                $row->column(12, $toolbar);
            })
            ->row(function (Row $row) use ($base, $activeTransfers, $activeGB, $pendingCount, $reversedCount, $reversedGB, $failedCount) {
                $row->column(3, new InfoBox(
                    'Active HLS Transfers',
                    'exchange',
                    'blue',
                    $base . '/hls-reversals/records',
                    $activeTransfers . ' (' . $activeGB . ' GB)'
                ));
                $row->column(3, new InfoBox(
                    'Pending Reversal',
                    'clock-o',
                    'yellow',
                    $base . '/hls-reversals/queue',
                    $pendingCount
                ));
                $row->column(3, new InfoBox(
                    'Reversed',
                    'check-circle',
                    'green',
                    $base . '/hls-reversals/queue?status=reversed',
                    $reversedCount . ' (' . $reversedGB . ' GB freed)'
                ));
                $row->column(3, new InfoBox(
                    'Failed',
                    'exclamation-triangle',
                    'red',
                    $base . '/hls-reversals/queue?status=failed',
                    $failedCount
                ));
            })
            ->row(function (Row $row) use ($topBox) {
                $row->column(12, $topBox);
            });
    }

    // ── Queue List ────────────────────────────────────────────────────────────

    public function queue(Request $request, Content $content): Content
    {
        $base         = $this->adminBase();
        $csrf         = csrf_token();
        $statusFilter = $request->input('status', '');
        $reasonFilter = $request->input('reason', '');

        $query = DB::table('hls_reversal_queue')->orderByDesc('score');

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }
        if ($reasonFilter !== '') {
            $query->where('reason', $reasonFilter);
        }

        $items = $query->paginate(25)->withQueryString();

        $statusOptions = [
            ''          => 'All Statuses',
            'pending'   => 'Pending',
            'approved'  => 'Approved',
            'executing' => 'Executing',
            'reversed'  => 'Reversed',
            'failed'    => 'Failed',
            'skipped'   => 'Skipped',
        ];
        $reasonOptions = [
            ''                  => 'All Reasons',
            'no_traffic_7d'     => 'No Traffic 7d',
            'low_completion'    => 'Low Completion',
            'combined'          => 'Combined',
            'series_no_traffic' => 'Series No Traffic',
            'no_views_ever'     => 'Never Watched',
            'manual'            => 'Manual',
        ];

        $statusOpts = '';
        foreach ($statusOptions as $val => $lbl) {
            $sel = $statusFilter === $val ? ' selected' : '';
            $statusOpts .= "<option value='{$val}'{$sel}>" . e($lbl) . "</option>";
        }
        $reasonOpts = '';
        foreach ($reasonOptions as $val => $lbl) {
            $sel = $reasonFilter === $val ? ' selected' : '';
            $reasonOpts .= "<option value='{$val}'{$sel}>" . e($lbl) . "</option>";
        }

        $filterForm = <<<HTML
        <form method="GET" action="{$base}/hls-reversals/queue" style="margin-bottom:15px">
            <div class="row">
                <div class="col-md-3">
                    <select name="status" class="form-control input-sm">{$statusOpts}</select>
                </div>
                <div class="col-md-3">
                    <select name="reason" class="form-control input-sm">{$reasonOpts}</select>
                </div>
                <div class="col-md-3" style="padding-top:1px">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    &nbsp;
                    <a href="{$base}/hls-reversals/queue" class="btn btn-default btn-sm">Reset</a>
                </div>
            </div>
        </form>
        HTML;

        $batchBtn = <<<HTML
        <div style="margin-bottom:12px">
            <form method="POST" action="{$base}/hls-reversals/execute-batch" style="display:inline"
                  onsubmit="return confirm('Execute all pending reversals (up to 50)? This will call the Python reversal API.')">
                <input type="hidden" name="_token" value="{$csrf}">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fa fa-play-circle"></i> Execute All Pending
                </button>
            </form>
            &nbsp;
            <a href="{$base}/hls-reversals/analyze" class="btn btn-warning btn-sm">
                <i class="fa fa-search"></i> Analyze Candidates
            </a>
        </div>
        HTML;

        $tableRows = '';
        foreach ($items as $row) {
            $statusBadge = $this->statusBadge($row->status ?? 'pending');
            $reasonBadge = $this->reasonBadge($row->reason ?? null);
            $canApprove  = ($row->status ?? '') === 'pending';
            $canSkip     = in_array($row->status ?? '', ['pending', 'approved'], true);
            $canExecute  = in_array($row->status ?? '', ['pending', 'approved'], true);

            $approveBtn = $canApprove
                ? "<form method='POST' action='{$base}/hls-reversals/queue/{$row->id}/approve' style='display:inline'>
                       <input type='hidden' name='_token' value='{$csrf}'>
                       <button type='submit' class='btn btn-xs btn-primary'
                               onclick=\"return confirm('Approve entry #{$row->id} for reversal?')\">
                           <i class='fa fa-check'></i> Approve
                       </button>
                   </form> "
                : '';

            $skipBtn = $canSkip
                ? "<form method='POST' action='{$base}/hls-reversals/queue/{$row->id}/skip' style='display:inline'>
                       <input type='hidden' name='_token' value='{$csrf}'>
                       <button type='submit' class='btn btn-xs btn-warning'
                               onclick=\"return confirm('Skip entry #{$row->id}?')\">
                           <i class='fa fa-forward'></i> Skip
                       </button>
                   </form> "
                : '';

            $executeBtn = $canExecute
                ? "<form method='POST' action='{$base}/hls-reversals/queue/{$row->id}/execute' style='display:inline'>
                       <input type='hidden' name='_token' value='{$csrf}'>
                       <button type='submit' class='btn btn-xs btn-danger'
                               onclick=\"return confirm('Execute reversal for entry #{$row->id} now? This calls the Python API.')\">
                           <i class='fa fa-play'></i> Execute
                       </button>
                   </form>"
                : '';

            $identifiedAt = $row->identified_at ? date('Y-m-d H:i', strtotime($row->identified_at)) : '—';
            $executedAt   = $row->executed_at   ? date('Y-m-d H:i', strtotime($row->executed_at))   : '—';

            $tableRows .= "<tr>
                <td style='font-size:12px;color:#666'>{$row->id}</td>
                <td>" . e($row->movie_title ?? '—') . "</td>
                <td>{$reasonBadge}</td>
                <td>" . number_format((float) ($row->score ?? 0), 2) . "</td>
                <td>" . number_format((int) ($row->views_7d ?? 0)) . "</td>
                <td>" . number_format((float) ($row->avg_completion_pct ?? 0), 1) . "%</td>
                <td>" . number_format((float) ($row->size_mb ?? 0), 1) . "</td>
                <td>{$statusBadge}</td>
                <td style='font-size:12px;color:#666'>{$identifiedAt}</td>
                <td style='font-size:12px;color:#666'>{$executedAt}</td>
                <td style='white-space:nowrap'>{$approveBtn}{$skipBtn}{$executeBtn}</td>
            </tr>";
        }

        if ($items->isEmpty()) {
            $tableRows = "<tr><td colspan='11' style='text-align:center;color:#999;padding:24px'>No entries found.</td></tr>";
        }

        $paginationLinks = (string) $items->links();

        $html = $filterForm . $batchBtn . <<<HTML
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" style="font-size:13px">
                <thead>
                    <tr style="background:#f5f5f5">
                        <th>ID</th>
                        <th>Movie Title</th>
                        <th>Reason</th>
                        <th>Score</th>
                        <th>Views 7d</th>
                        <th>Avg Completion %</th>
                        <th>Size MB</th>
                        <th>Status</th>
                        <th>Identified At</th>
                        <th>Executed At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {$tableRows}
                </tbody>
            </table>
        </div>
        <div style="margin-top:10px">{$paginationLinks}</div>
        HTML;

        $box = new Box('HLS Reversal Queue', $html);

        return $content
            ->title('HLS Reversal Queue')
            ->description('Pending, approved, and executed reversals ordered by priority score')
            ->row(function (Row $row) use ($box) {
                $row->column(12, $box);
            });
    }

    // ── Transfer Records ──────────────────────────────────────────────────────

    public function records(Request $request, Content $content): Content
    {
        $base         = $this->adminBase();
        $statusFilter = $request->input('status', '');

        $query = DB::table('hls_transfer_records')->orderByDesc('id');

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $items = $query->paginate(25)->withQueryString();

        $statusOptions = [
            ''         => 'All Statuses',
            'active'   => 'Active',
            'reversed' => 'Reversed',
            'failed'   => 'Failed',
        ];
        $statusOpts = '';
        foreach ($statusOptions as $val => $lbl) {
            $sel = $statusFilter === $val ? ' selected' : '';
            $statusOpts .= "<option value='{$val}'{$sel}>" . e($lbl) . "</option>";
        }

        $filterForm = <<<HTML
        <form method="GET" action="{$base}/hls-reversals/records" style="margin-bottom:15px">
            <div class="row">
                <div class="col-md-3">
                    <select name="status" class="form-control input-sm">{$statusOpts}</select>
                </div>
                <div class="col-md-3" style="padding-top:1px">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    &nbsp;
                    <a href="{$base}/hls-reversals/records" class="btn btn-default btn-sm">Reset</a>
                </div>
            </div>
        </form>
        HTML;

        $tableRows = '';
        foreach ($items as $row) {
            $statusBadge  = $this->statusBadge($row->status ?? 'active');
            $seriesHtml   = !empty($row->series_episode)
                ? "<span style='background:#337ab7;color:#fff;padding:2px 6px;border-radius:10px;font-size:11px'>S</span> " . e($row->series_episode)
                : '<span style="color:#ccc">—</span>';
            $transferredAt = isset($row->transferred_at) && $row->transferred_at
                ? date('Y-m-d H:i', strtotime($row->transferred_at))
                : '—';
            $reversedAt = isset($row->reversed_at) && $row->reversed_at
                ? date('Y-m-d H:i', strtotime($row->reversed_at))
                : '—';

            $tableRows .= "<tr>
                <td style='font-size:12px;color:#666'>{$row->id}</td>
                <td>" . e($row->movie_title ?? '—') . "</td>
                <td>{$seriesHtml}</td>
                <td><code style='font-size:11px'>" . e($row->quality_ladder ?? '—') . "</code></td>
                <td>" . number_format((float) ($row->total_size_mb ?? 0), 1) . "</td>
                <td>{$statusBadge}</td>
                <td style='font-size:12px;color:#666'>{$transferredAt}</td>
                <td style='font-size:12px;color:#666'>{$reversedAt}</td>
            </tr>";
        }

        if ($items->isEmpty()) {
            $tableRows = "<tr><td colspan='8' style='text-align:center;color:#999;padding:24px'>No records found.</td></tr>";
        }

        $paginationLinks = (string) $items->links();

        $html = $filterForm . <<<HTML
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" style="font-size:13px">
                <thead>
                    <tr style="background:#f5f5f5">
                        <th>ID</th>
                        <th>Movie Title</th>
                        <th>Series Episode</th>
                        <th>Quality Ladder</th>
                        <th>Total Size MB</th>
                        <th>Status</th>
                        <th>Transferred At</th>
                        <th>Reversed At</th>
                    </tr>
                </thead>
                <tbody>
                    {$tableRows}
                </tbody>
            </table>
        </div>
        <div style="margin-top:10px">{$paginationLinks}</div>
        HTML;

        $box = new Box('HLS Transfer Records', $html);

        return $content
            ->title('HLS Transfer Records')
            ->description('All HLS transfer history — active, reversed, and failed')
            ->row(function (Row $row) use ($box) {
                $row->column(12, $box);
            });
    }

    // ── Analyze ───────────────────────────────────────────────────────────────

    public function analyze(Content $content): Content
    {
        $base     = $this->adminBase();
        $csrf     = csrf_token();
        $apiUrl   = 'https://movies.mruodel.com/admin-react-api/reversals/analyze?limit=200';

        $candidates = [];
        $error      = null;

        try {
            $response = Http::timeout(30)->get($apiUrl);
            if ($response->successful()) {
                $data       = $response->json();
                $candidates = $data['candidates'] ?? $data['data'] ?? (is_array($data) ? $data : []);
            } else {
                $error = 'API returned HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 300);
            }
        } catch (\Exception $e) {
            $error = 'API call failed: ' . $e->getMessage();
        }

        if ($error !== null) {
            $escapedError = e($error);
            $html = <<<HTML
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-triangle"></i>
                <strong>Error fetching candidates:</strong><br>
                {$escapedError}
            </div>
            <a href="{$base}/hls-reversals/analyze" class="btn btn-default btn-sm">
                <i class="fa fa-refresh"></i> Retry
            </a>
            &nbsp;
            <a href="{$base}/hls-reversals" class="btn btn-default btn-sm">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </a>
            HTML;
        } else {
            $count     = count($candidates);
            $tableRows = '';

            foreach ($candidates as $c) {
                $isSeriesHtml = !empty($c['is_series'])
                    ? "<span style='background:#337ab7;color:#fff;padding:2px 6px;border-radius:10px;font-size:11px'>Series</span>"
                    : '<span style="color:#ccc">No</span>';

                $tableRows .= "<tr>
                    <td>" . e($c['movie_title'] ?? $c['title'] ?? '—') . "</td>
                    <td>" . $this->reasonBadge($c['reason'] ?? null) . "</td>
                    <td>" . number_format((float) ($c['score'] ?? 0), 2) . "</td>
                    <td>" . number_format((int) ($c['views_7d'] ?? 0)) . "</td>
                    <td>" . number_format((int) ($c['views_total'] ?? $c['total_views'] ?? 0)) . "</td>
                    <td>" . number_format((float) ($c['avg_completion_pct'] ?? $c['avg_completion'] ?? 0), 1) . "%</td>
                    <td>" . number_format((float) ($c['size_mb'] ?? 0), 1) . "</td>
                    <td>{$isSeriesHtml}</td>
                </tr>";
            }

            if (empty($candidates)) {
                $tableRows = "<tr><td colspan='8' style='text-align:center;color:#999;padding:24px'>No candidates found — all HLS content appears to be actively watched.</td></tr>";
            }

            $confirmMsg = "Save and execute all {$count} candidates as reversals (up to 50 will be processed immediately)?";

            $batchBtn = <<<HTML
            <form method="POST" action="{$base}/hls-reversals/execute-batch" style="display:inline"
                  onsubmit="return confirm('{$confirmMsg}')">
                <input type="hidden" name="_token" value="{$csrf}">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fa fa-play-circle"></i> Save to Queue &amp; Execute
                </button>
            </form>
            HTML;

            $html = <<<HTML
            <div style="margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span>
                    <strong>{$count}</strong> candidates returned by the analyzer.
                </span>
                {$batchBtn}
                <a href="{$base}/hls-reversals/queue" class="btn btn-primary btn-sm">
                    <i class="fa fa-list"></i> View Queue
                </a>
                <a href="{$base}/hls-reversals/analyze" class="btn btn-default btn-sm">
                    <i class="fa fa-refresh"></i> Re-analyze
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" style="font-size:13px">
                    <thead>
                        <tr style="background:#f5f5f5">
                            <th>Movie</th>
                            <th>Reason</th>
                            <th>Score</th>
                            <th>Views 7d</th>
                            <th>Views Total</th>
                            <th>Avg Completion %</th>
                            <th>Size MB</th>
                            <th>Is Series</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$tableRows}
                    </tbody>
                </table>
            </div>
            HTML;
        }

        $box = new Box('Reversal Candidates from Analyzer', $html);

        return $content
            ->title('HLS Reversal Analyzer')
            ->description('Live candidates fetched from the Python analysis API')
            ->row(function (Row $row) use ($box) {
                $row->column(12, $box);
            });
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    public function approve(int $id)
    {
        $affected = DB::table('hls_reversal_queue')
            ->where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status'     => 'approved',
                'updated_at' => now(),
            ]);

        if ($affected) {
            admin_toastr("Queue entry #{$id} has been approved.", 'success');
        } else {
            admin_toastr("Could not approve entry #{$id} — it may not be in 'pending' status.", 'error');
        }

        return redirect()->back();
    }

    // ── Skip ──────────────────────────────────────────────────────────────────

    public function skip(int $id)
    {
        $affected = DB::table('hls_reversal_queue')
            ->where('id', $id)
            ->whereIn('status', ['pending', 'approved'])
            ->update([
                'status'     => 'skipped',
                'updated_at' => now(),
            ]);

        if ($affected) {
            admin_toastr("Queue entry #{$id} has been skipped.", 'success');
        } else {
            admin_toastr("Could not skip entry #{$id} — it may already be processed.", 'warning');
        }

        return redirect()->back();
    }

    // ── Execute One ───────────────────────────────────────────────────────────

    public function executeOne(int $id)
    {
        $apiUrl = "https://movies.mruodel.com/admin-react-api/reversals/queue/{$id}/execute";

        try {
            $response = Http::timeout(60)->post($apiUrl);
            $data     = $response->json() ?? [];

            if ($response->successful()) {
                $msg = $data['message'] ?? $data['msg'] ?? "Reversal for entry #{$id} executed successfully.";
                admin_toastr($msg, 'success');
            } else {
                $msg = $data['message'] ?? $data['error'] ?? $data['msg'] ?? ('API error: HTTP ' . $response->status());
                admin_toastr($msg, 'error');
            }
        } catch (\Exception $e) {
            admin_toastr('API call failed: ' . $e->getMessage(), 'error');
        }

        return redirect()->back();
    }

    // ── Execute Batch ─────────────────────────────────────────────────────────

    public function executeBatch()
    {
        $apiUrl = 'https://movies.mruodel.com/admin-react-api/reversals/execute-batch';

        try {
            $response = Http::timeout(120)->post($apiUrl, ['max_count' => 50]);
            $data     = $response->json() ?? [];

            if ($response->successful()) {
                $msg = $data['message'] ?? $data['msg'] ?? 'Batch execution completed successfully.';
                admin_toastr($msg, 'success');
            } else {
                $msg = $data['message'] ?? $data['error'] ?? $data['msg'] ?? ('Batch API error: HTTP ' . $response->status());
                admin_toastr($msg, 'error');
            }
        } catch (\Exception $e) {
            admin_toastr('Batch API call failed: ' . $e->getMessage(), 'error');
        }

        return redirect()->back();
    }
}
