<?php

namespace App\Admin\Controllers;

use App\Models\DbSyncCursor;
use App\Models\DbSyncLog;
use App\Services\SyncPullService;
use Illuminate\Http\Request;

class SyncDashboardController
{
    // ── Main dashboard page ───────────────────────────────────────────────────

    public function index(): string
    {
        $cursors  = DbSyncCursor::orderBy('priority')->orderBy('table_name')->get();
        $recentLogs = DbSyncLog::orderByDesc('created_at')->limit(40)->get();

        // Stats
        $total   = $cursors->count();
        $ok      = $cursors->where('status', 'ok')->count();
        $errors  = $cursors->where('status', 'error')->count();
        $syncing = $cursors->where('status', 'syncing')->count();
        $idle    = $cursors->where('status', 'idle')->count();
        $lastRun = $cursors->max('last_run_at');

        $enabled = config('services.sync.enabled', false);
        $source  = config('services.sync.source_host', '—');

        $html = $this->css();
        $html .= '<div class="sy-wrap">';

        // Header
        $html .= '<div class="sy-hdr">';
        $html .= '<div class="sy-hdr-left">';
        $html .= '<h2 class="sy-title">DB Sync Dashboard</h2>';
        $html .= '<div class="sy-sub">Source: <strong>' . e($source) . '</strong> → Hetzner &nbsp;|&nbsp; ';
        if ($enabled) {
            $html .= '<span class="sy-pill ok">● SYNC ENABLED</span>';
        } else {
            $html .= '<span class="sy-pill err">○ SYNC DISABLED</span>';
        }
        if ($lastRun) {
            $html .= ' &nbsp;|&nbsp; Last run: <strong>' . \Carbon\Carbon::parse($lastRun)->diffForHumans() . '</strong>';
        }
        $html .= '</div></div>';
        $html .= '<div class="sy-hdr-right">';
        $html .= '<button onclick="sySyncNow(this)" class="sy-btn primary">▶ Run Sync Now</button>';
        $html .= '</div>';
        $html .= '</div>';

        // Stat row
        $html .= '<div class="sy-stat-row">';
        foreach ([
            ['Tables', $total, '#5b7fa6'],
            ['OK', $ok, '#27ae60'],
            ['Errors', $errors, '#c0392b'],
            ['Syncing', $syncing, '#2980b9'],
            ['Idle', $idle, '#95a5a6'],
        ] as [$label, $val, $color]) {
            $html .= "<div class=\"sy-stat\" style=\"border-top:3px solid {$color}\">";
            $html .= "<div class=\"sy-stat-val\">{$val}</div>";
            $html .= "<div class=\"sy-stat-lbl\">{$label}</div>";
            $html .= '</div>';
        }
        $html .= '</div>';

        // Progress notification area
        $html .= '<div id="sy-notif" class="sy-notif" style="display:none"></div>';

        // Tables by priority group
        $groups = [1 => 'Priority 1 — Users & Payments', 2 => 'Priority 2 — Movies & Engagement',
                   3 => 'Priority 3 — Support & Moderation', 4 => 'Priority 4 — Config & Reference'];

        foreach ($groups as $pri => $label) {
            $group = $cursors->where('priority', $pri);
            if ($group->isEmpty()) continue;

            $html .= '<div class="sy-group">';
            $html .= '<div class="sy-group-hdr">' . e($label) . '</div>';
            $html .= '<table class="sy-tbl"><thead><tr>';
            $html .= '<th>Table</th><th>Status</th><th>Cursor ID</th><th>Last Run</th><th>Source Rows</th><th>Synced/Run</th><th>Freq</th><th>Actions</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($group as $c) {
                $statusClass = match ($c->status) {
                    'ok'      => 'ok',
                    'error'   => 'err',
                    'syncing' => 'sync',
                    default   => 'idle',
                };
                $html .= "<tr id=\"sy-row-{$c->table_name}\">";
                $html .= "<td class=\"sy-tbl-name\">{$c->table_name}</td>";
                $html .= "<td><span class=\"sy-badge {$statusClass}\">{$c->status}</span></td>";
                $html .= '<td>' . number_format($c->last_synced_id) . '</td>';
                $html .= '<td>' . ($c->last_run_at ? \Carbon\Carbon::parse($c->last_run_at)->diffForHumans() : '<em>never</em>') . '</td>';
                $html .= '<td>' . number_format($c->rows_on_source) . '</td>';
                $html .= '<td>' . number_format($c->rows_this_run) . '</td>';
                $html .= '<td>' . $c->frequency_minutes . 'm</td>';
                $html .= "<td class=\"sy-actions\">";
                $html .= "<button onclick=\"syRunTable('{$c->table_name}',this)\" class=\"sy-btn sm\">Sync</button> ";
                $html .= "<button onclick=\"syReset('{$c->table_name}',this)\" class=\"sy-btn sm warn\">Reset</button>";
                if ($c->status === 'error') {
                    $html .= "<div class=\"sy-err-msg\" title=\"{$c->error_message}\">" . e(substr((string)$c->error_message, 0, 80)) . '</div>';
                }
                $html .= '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        // Recent sync logs
        $html .= '<div class="sy-group">';
        $html .= '<div class="sy-group-hdr">Recent Sync Log (last 40)</div>';
        $html .= '<table class="sy-tbl"><thead><tr>';
        $html .= '<th>Time</th><th>Table</th><th>Status</th><th>Fetched</th><th>Upserted</th><th>Pages</th><th>Duration</th><th>Error</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($recentLogs as $lg) {
            $sc = $lg->status === 'ok' ? 'ok' : ($lg->status === 'error' ? 'err' : 'idle');
            $html .= '<tr>';
            $html .= '<td style="white-space:nowrap">' . \Carbon\Carbon::parse($lg->created_at)->format('d M H:i:s') . '</td>';
            $html .= '<td>' . e($lg->table_name) . '</td>';
            $html .= "<td><span class=\"sy-badge {$sc}\">{$lg->status}</span></td>";
            $html .= '<td>' . number_format($lg->rows_fetched) . '</td>';
            $html .= '<td>' . number_format($lg->rows_upserted) . '</td>';
            $html .= '<td>' . $lg->pages_fetched . '</td>';
            $html .= '<td>' . number_format($lg->duration_ms) . 'ms</td>';
            $html .= '<td style="color:#c0392b;font-size:11px">' . e(substr((string)$lg->error_message, 0, 60)) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        $html .= '</div>'; // sy-wrap
        $html .= $this->js();

        return $html;
    }

    // ── Live JSON endpoint (polled by JS) ─────────────────────────────────────

    public function live(): \Illuminate\Http\JsonResponse
    {
        $cursors = DbSyncCursor::orderBy('priority')->orderBy('table_name')->get();
        return response()->json([
            'stats' => [
                'total'   => $cursors->count(),
                'ok'      => $cursors->where('status', 'ok')->count(),
                'error'   => $cursors->where('status', 'error')->count(),
                'syncing' => $cursors->where('status', 'syncing')->count(),
            ],
            'cursors'  => $cursors->map(fn($c) => [
                'table'       => $c->table_name,
                'status'      => $c->status,
                'last_id'     => $c->last_synced_id,
                'last_run'    => $c->last_run_at?->diffForHumans(),
                'rows_source' => $c->rows_on_source,
                'rows_run'    => $c->rows_this_run,
                'error'       => $c->error_message,
            ])->values(),
            'recent_logs' => DbSyncLog::orderByDesc('created_at')->limit(10)->get()->map(fn($l) => [
                'time'     => $l->created_at?->format('H:i:s'),
                'table'    => $l->table_name,
                'status'   => $l->status,
                'upserted' => $l->rows_upserted,
                'ms'       => $l->duration_ms,
                'error'    => $l->error_message,
            ])->values(),
            'ts' => now()->format('H:i:s'),
        ]);
    }

    // ── Manual trigger ────────────────────────────────────────────────────────

    public function trigger(Request $request): \Illuminate\Http\JsonResponse
    {
        $table = $request->input('table');

        // Dispatch as a background shell command so it doesn't block the HTTP response
        $cmd = 'cd ' . base_path() . ' && php artisan sync:pull --force';
        if ($table) {
            $cmd .= ' --table=' . escapeshellarg($table);
        }
        shell_exec($cmd . ' > /dev/null 2>&1 &');

        return response()->json(['ok' => true, 'message' => 'Sync started in background' . ($table ? " for {$table}" : '') . '.']);
    }

    // ── Reset cursor ──────────────────────────────────────────────────────────

    public function resetCursor(Request $request): \Illuminate\Http\JsonResponse
    {
        $table = $request->input('table');
        if (!$table) {
            return response()->json(['ok' => false, 'message' => 'Table required.'], 422);
        }

        $cursor = DbSyncCursor::where('table_name', $table)->first();
        if (!$cursor) {
            return response()->json(['ok' => false, 'message' => "Table '{$table}' not found."], 404);
        }

        $cursor->update([
            'last_synced_id'  => 0,
            'last_updated_ts' => null,
            'status'          => 'idle',
            'error_message'   => null,
            'consecutive_errors' => 0,
        ]);

        return response()->json(['ok' => true, 'message' => "Cursor for '{$table}' reset to 0."]);
    }

    // ── CSS ───────────────────────────────────────────────────────────────────

    private function css(): string
    {
        return '<style>
.sy-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:13px;color:#2c3e50;max-width:1400px;padding:16px}
.sy-hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #1e3a5f}
.sy-title{margin:0;font-size:20px;font-weight:700;color:#1e3a5f}
.sy-sub{color:#666;margin-top:4px;font-size:12px}
.sy-pill{display:inline-block;padding:2px 8px;font-size:11px;font-weight:700;letter-spacing:.5px}
.sy-pill.ok{background:#e8f5e9;color:#27ae60;border:1px solid #27ae60}
.sy-pill.err{background:#fdecea;color:#c0392b;border:1px solid #c0392b}
.sy-stat-row{display:flex;gap:0;margin-bottom:16px;border:1px solid #d8dde4}
.sy-stat{flex:1;padding:12px 14px;border-right:1px solid #d8dde4;border-radius:0}
.sy-stat:last-child{border-right:none}
.sy-stat-val{font-size:24px;font-weight:700;color:#1e3a5f;line-height:1}
.sy-stat-lbl{font-size:11px;color:#888;margin-top:3px;text-transform:uppercase;letter-spacing:.5px}
.sy-notif{padding:10px 14px;margin-bottom:12px;border-left:3px solid #2980b9;background:#eaf4fb;font-size:12px}
.sy-group{background:#fff;border:1px solid #d8dde4;margin-bottom:14px}
.sy-group-hdr{background:#1e3a5f;color:#fff;font-size:12px;font-weight:700;padding:8px 14px;letter-spacing:.5px;text-transform:uppercase}
.sy-tbl{width:100%;border-collapse:collapse}
.sy-tbl th{background:#f5f7fa;color:#5b7fa6;font-size:11px;font-weight:700;text-transform:uppercase;padding:7px 10px;border-bottom:1px solid #d8dde4;text-align:left;white-space:nowrap}
.sy-tbl td{padding:6px 10px;border-bottom:1px solid #eef1f5;vertical-align:middle}
.sy-tbl tr:last-child td{border-bottom:none}
.sy-tbl tr:hover td{background:#fafbfc}
.sy-tbl-name{font-family:monospace;font-size:12px;font-weight:600;color:#1e3a5f}
.sy-badge{display:inline-block;padding:2px 7px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border:1px solid transparent}
.sy-badge.ok{background:#e8f5e9;color:#27ae60;border-color:#a5d6a7}
.sy-badge.err{background:#fdecea;color:#c0392b;border-color:#ef9a9a}
.sy-badge.sync{background:#e3f2fd;color:#2980b9;border-color:#90caf9}
.sy-badge.idle{background:#f5f5f5;color:#888;border-color:#ccc}
.sy-actions{white-space:nowrap}
.sy-err-msg{color:#c0392b;font-size:10px;margin-top:2px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sy-btn{border:1px solid transparent;padding:4px 12px;font-size:12px;cursor:pointer;font-weight:600;border-radius:0;transition:opacity .15s}
.sy-btn.primary{background:#1e3a5f;color:#fff;border-color:#1e3a5f;padding:7px 18px;font-size:13px}
.sy-btn.primary:hover{opacity:.85}
.sy-btn.sm{background:#f0f4f8;color:#2c3e50;border-color:#d0d8e0;padding:3px 10px}
.sy-btn.sm:hover{background:#e2eaf2}
.sy-btn.sm.warn{color:#c0392b;border-color:#e8a0a0}
.sy-btn:disabled{opacity:.5;cursor:not-allowed}
</style>';
    }

    // ── JS ────────────────────────────────────────────────────────────────────

    private function js(): string
    {
        $csrfToken = csrf_token();
        return '<script>
var SY_CSRF = "' . $csrfToken . '";
function syPost(url, data) {
    return fetch(url, {
        method:"POST",
        headers:{"X-CSRF-TOKEN":SY_CSRF,"Content-Type":"application/json"},
        body:JSON.stringify(data)
    }).then(function(r){
        if(!r.ok && r.status===419) throw new Error("CSRF error — try refreshing the page.");
        return r.json();
    });
}
function syNotif(msg, type) {
    var n = document.getElementById("sy-notif");
    n.style.display = "block";
    n.style.borderColor = type === "err" ? "#c0392b" : "#2980b9";
    n.style.background  = type === "err" ? "#fdecea" : "#eaf4fb";
    n.textContent = msg;
    clearTimeout(n._t);
    n._t = setTimeout(function(){n.style.display="none";}, 6000);
}
function sySyncNow(btn) {
    btn.disabled = true;
    btn.textContent = "Running...";
    syPost("/sync-dashboard/trigger", {}).then(function(d){
        syNotif(d.message, d.ok ? "ok" : "err");
        setTimeout(syRefreshLive, 3000);
    }).catch(function(e){syNotif("Error: "+e, "err");})
    .finally(function(){ btn.disabled=false; btn.textContent="▶ Run Sync Now"; });
}
function syRunTable(table, btn) {
    btn.disabled = true;
    syPost("/sync-dashboard/trigger", {table:table}).then(function(d){
        syNotif(d.message, d.ok ? "ok" : "err");
        setTimeout(syRefreshLive, 3000);
    }).catch(function(e){syNotif("Error: "+e, "err");})
    .finally(function(){ btn.disabled=false; });
}
function syReset(table, btn) {
    if (!confirm("Reset cursor for " + table + " to 0? Next sync will re-pull ALL rows.")) return;
    btn.disabled = true;
    syPost("/sync-dashboard/reset", {table:table}).then(function(d){
        syNotif(d.message, d.ok ? "ok" : "err");
        syRefreshLive();
    }).catch(function(e){syNotif("Error: "+e, "err");})
    .finally(function(){ btn.disabled=false; });
}
// Poll live status every 8 s
function syRefreshLive() {
    fetch("/sync-dashboard/live", {headers:{"Accept":"application/json"}})
    .then(r=>r.json()).then(function(d) {
        (d.cursors||[]).forEach(function(c) {
            var row = document.getElementById("sy-row-" + c.table);
            if (!row) return;
            var badge = row.querySelector(".sy-badge");
            if (badge) { badge.textContent=c.status; badge.className="sy-badge "+({ok:"ok",error:"err",syncing:"sync"}[c.status]||"idle"); }
            var cells = row.querySelectorAll("td");
            if (cells[2]) cells[2].textContent = (c.last_id||0).toLocaleString();
            if (cells[3]) cells[3].textContent = c.last_run||"never";
            if (cells[4]) cells[4].textContent = (c.rows_source||0).toLocaleString();
            if (cells[5]) cells[5].textContent = (c.rows_run||0).toLocaleString();
        });
    }).catch(function(){});
}
setInterval(syRefreshLive, 8000);
</script>';
    }
}
