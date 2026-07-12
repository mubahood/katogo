<?php

namespace App\Admin\Controllers;

use App\Models\DbSyncCursor;
use App\Models\DbSyncLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sync Dashboard for the Hetzner replica server.
 * Shows live sync status, completion %, lag, and recent logs.
 * Auto-refreshes every 5 seconds via JS polling.
 */
class SyncDashboardController
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function adminBase(): string
    {
        return '/' . trim((string) config('admin.route.prefix', 'admin'), '/');
    }

    /**
     * Fetch local row counts from INFORMATION_SCHEMA in one fast query (~5ms).
     * InnoDB estimates are close enough for a status dashboard.
     */
    private function localCounts(array $tables): array
    {
        if (empty($tables)) return [];
        $pdo = DB::getPdo();
        $in  = implode(',', array_map([$pdo, 'quote'], $tables));
        $rows = DB::select(
            "SELECT TABLE_NAME tn, COALESCE(TABLE_ROWS, 0) rc
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ({$in})"
        );
        $map = array_fill_keys($tables, 0);
        foreach ($rows as $r) $map[$r->tn] = (int) $r->rc;
        return $map;
    }

    /** Completion percentage (works for both regular and pivot tables). */
    private function pct(int $local, int $source): float
    {
        if ($source <= 0) return 100.0;
        return min(100.0, round($local / $source * 100, 1));
    }

    /** CSS class for lag chip based on lag vs expected frequency. */
    private function lagClass(?string $lastRun, int $freqMin): string
    {
        if (!$lastRun) return 'lg-none';
        $lag = now()->diffInMinutes(Carbon::parse($lastRun));
        if ($lag <= $freqMin * 2)  return 'lg-ok';
        if ($lag <= $freqMin * 6)  return 'lg-warn';
        return 'lg-err';
    }

    /** Short row count: 1.2M, 88.4k, 435 */
    private function fmtN(int $n): string
    {
        if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
        if ($n >= 10_000)    return number_format($n / 1_000, 1) . 'k';
        if ($n >= 1_000)     return number_format($n / 1_000, 2) . 'k';
        return (string) $n;
    }

    /** Human duration: 218s → 3m 38s */
    private function fmtMs(int $ms): string
    {
        $s = (int) round($ms / 1000);
        if ($s < 60) return "{$s}s";
        return floor($s / 60) . 'm ' . ($s % 60) . 's';
    }

    private function statusBadge(string $status): string
    {
        $map = ['ok' => 'ok', 'error' => 'err', 'syncing' => 'sync', 'idle' => 'idle'];
        $cls = $map[$status] ?? 'idle';
        $dot = match ($status) {
            'ok'     => '●',
            'error'  => '✕',
            'syncing'=> '◌',
            default  => '○',
        };
        return "<span class=\"sd-badge {$cls}\">{$dot} {$status}</span>";
    }

    private function progressBar(float $pct): string
    {
        $fill  = min(100, $pct);
        $color = $pct >= 100 ? '#16a34a' : ($pct >= 80 ? '#2563eb' : ($pct >= 50 ? '#d97706' : '#dc2626'));
        return "<div class=\"sd-bar-track\"><div class=\"sd-bar-fill\" style=\"width:{$fill}%;background:{$color}\"></div></div>";
    }

    // ── Main page ─────────────────────────────────────────────────────────────

    public function index(): string
    {
        $cursors    = DbSyncCursor::where('enabled', true)->orderBy('priority')->orderBy('table_name')->get();
        $recentLogs = DbSyncLog::orderByDesc('created_at')->limit(60)->get();
        $lcounts    = $this->localCounts($cursors->pluck('table_name')->all());

        $total     = $cursors->count();
        $okCnt     = $cursors->where('status', 'ok')->count();
        $errCnt    = $cursors->where('status', 'error')->count();
        $syncCnt   = $cursors->where('status', 'syncing')->count();
        $idleCnt   = $cursors->where('status', 'idle')->count();
        $lastRun   = $cursors->max('last_run_at');
        $lagMin    = $lastRun ? (int) now()->diffInMinutes(Carbon::parse($lastRun)) : null;
        $isHealthy = $errCnt === 0 && $lagMin !== null && $lagMin < 30;
        $totalSrc  = (int) $cursors->sum('rows_on_source');
        $totalLoc  = (int) array_sum($lcounts);
        $ovPct     = $totalSrc > 0 ? min(100, round($totalLoc / $totalSrc * 100, 1)) : 100.0;
        $totalRowsSynced = $recentLogs->sum('rows_upserted');

        $enabled = (bool) config('services.sync.enabled', false);
        $source  = e(config('services.sync.source_host', '—'));
        $base    = $this->adminBase();
        $role    = config('services.sync.role', 'replica');

        $hClass = $isHealthy ? 'hok' : ($errCnt > 0 ? 'herr' : 'hwarn');
        $hText  = $isHealthy ? 'Healthy' : ($errCnt > 0 ? "{$errCnt} Error" . ($errCnt !== 1 ? 's' : '') : 'Lag Warning');
        $hIcon  = $isHealthy ? '✓' : ($errCnt > 0 ? '✕' : '⚠');
        $lastRunFmt = $lastRun ? Carbon::parse($lastRun)->diffForHumans() : 'Never';
        $lagFmt     = $lagMin !== null ? "{$lagMin}m ago" : '—';
        $syncPill   = $enabled
            ? '<span class="sd-pill on">● SYNC ON</span>'
            : '<span class="sd-pill off">○ SYNC OFF</span>';

        $groups = [
            1 => ['P1', 'Users & Payments'],
            2 => ['P2', 'Movies & Engagement'],
            3 => ['P3', 'Support & Moderation'],
            4 => ['P4', 'Config & Reference'],
        ];

        $o  = $this->css();
        $o .= '<div class="sd-root">';

        // ── Header ─────────────────────────────────────────────────────────────
        $o .= <<<HTML
        <div class="sd-hdr">
          <div class="sd-hdr-l">
            <div class="sd-title">⟳&thinsp; DB Sync Dashboard</div>
            <div class="sd-subtitle">
              <code>{$source}</code>
              <span class="sd-arrow">→</span>
              <code>munoapp.store</code>
              <span class="sd-role-chip">{$role}</span>
              {$syncPill}
            </div>
          </div>
          <div class="sd-hdr-r">
            <div class="sd-last-run-lbl">Last sync</div>
            <div class="sd-last-run-val" id="sd-last-run">{$lastRunFmt}</div>
            <button id="sd-sync-btn" class="sd-btn-primary" onclick="sdSyncAll(this)">
              <span class="sd-btn-spin" id="sd-btn-spin">▶</span>
              Run Full Sync
            </button>
          </div>
        </div>
        HTML;

        // ── Stat Cards ─────────────────────────────────────────────────────────
        $ovBar    = $this->progressBar($ovPct);
        $locFmt   = $this->fmtN($totalLoc);
        $srcFmt   = $this->fmtN($totalSrc);

        $o .= <<<HTML
        <div class="sd-stats">
          <div class="sd-stat sd-health-{$hClass}">
            <div class="sd-stat-icon">{$hIcon}</div>
            <div class="sd-stat-val">{$hText}</div>
            <div class="sd-stat-lbl">System Health</div>
          </div>
          <div class="sd-stat">
            <div class="sd-stat-val">{$total}</div>
            <div class="sd-stat-lbl">Total Tables</div>
          </div>
          <div class="sd-stat">
            <div class="sd-stat-val sd-ok-val" id="sd-ok-val">{$okCnt}</div>
            <div class="sd-stat-lbl">Synced OK</div>
          </div>
          <div class="sd-stat">
            <div class="sd-stat-val sd-err-val" id="sd-err-val" style="color:{'#dc2626'}">{$errCnt}</div>
            <div class="sd-stat-lbl">Errors</div>
          </div>
          <div class="sd-stat">
            <div class="sd-stat-val" id="sd-idle-val">{$idleCnt}</div>
            <div class="sd-stat-lbl">Idle</div>
          </div>
          <div class="sd-stat sd-ovpct-stat">
            <div class="sd-stat-val">{$ovPct}%</div>
            <div class="sd-stat-lbl">Overall Sync</div>
            {$ovBar}
            <div class="sd-ovpct-detail">{$locFmt} / {$srcFmt} rows</div>
          </div>
        </div>
        HTML;

        // ── Notification bar ───────────────────────────────────────────────────
        $o .= '<div id="sd-notif" class="sd-notif" hidden></div>';

        // ── Priority groups ────────────────────────────────────────────────────
        foreach ($groups as $pri => [$priLabel, $priDesc]) {
            $group = $cursors->where('priority', $pri);
            if ($group->isEmpty()) continue;

            $gErr  = $group->where('status', 'error')->count();
            $gSync = $group->where('status', 'syncing')->count();
            $gChip = $gErr
                ? "<span class=\"sd-g-chip gerr\">{$gErr} error" . ($gErr > 1 ? 's' : '') . '</span>'
                : ($gSync
                    ? "<span class=\"sd-g-chip gsync\">syncing…</span>"
                    : "<span class=\"sd-g-chip gok\">{$group->count()} ok</span>");

            $o .= <<<HTML
            <div class="sd-group">
              <div class="sd-g-hdr">
                <div><span class="sd-g-badge">{$priLabel}</span> {$priDesc}</div>
                {$gChip}
              </div>
              <div class="sd-tbl-wrap">
              <table class="sd-tbl">
                <thead>
                  <tr>
                    <th class="th-n">Table</th>
                    <th class="th-s">Status</th>
                    <th class="th-p">Progress</th>
                    <th class="th-r">Rows (local / source)</th>
                    <th class="th-l">Last Sync</th>
                    <th class="th-u">Last Run +Rows</th>
                    <th class="th-f">Freq</th>
                    <th class="th-a">Actions</th>
                  </tr>
                </thead>
                <tbody>
            HTML;

            foreach ($group as $c) {
                $loc    = $lcounts[$c->table_name] ?? 0;
                $src    = (int) $c->rows_on_source;
                $pct    = ($c->is_pivot && $loc === 0 && $c->status === 'ok') ? 100.0 : $this->pct($loc, $src);
                $bar    = $this->progressBar($pct);
                $lgCls  = $this->lagClass($c->last_run_at, (int) $c->frequency_minutes);
                $lagTxt = $c->last_run_at
                    ? ((int) now()->diffInMinutes(Carbon::parse($c->last_run_at))) . 'm'
                    : 'never';
                $runN   = (int) $c->rows_this_run;
                $runFmt = $runN > 0 ? '<span class="sd-plus">+' . $this->fmtN($runN) . '</span>' : '<span class="sd-dash">—</span>';
                $pivTag = $c->is_pivot ? '<span class="sd-pivot">pivot</span>' : '';
                $tname  = e($c->table_name);
                $badge  = $this->statusBadge($c->status);
                $freq   = (int) $c->frequency_minutes;
                $pctDisp = $pct >= 100 ? 100 : $pct;
                $consecN = (int) $c->consecutive_errors;
                $rowCls  = $c->status === 'error' ? ' class="row-err"' : '';

                $o .= <<<HTML
                <tr id="sd-row-{$tname}"{$rowCls}>
                  <td class="td-n"><span class="sd-tname">{$tname}</span>{$pivTag}</td>
                  <td class="td-s">{$badge}</td>
                  <td class="td-p">
                    {$bar}
                    <span class="sd-pct-lbl">{$pctDisp}%</span>
                  </td>
                  <td class="td-r">
                    <span class="sd-loc-n">{$this->fmtN($loc)}</span>
                    <span class="sd-sep">/</span>
                    <span class="sd-src-n">{$this->fmtN($src)}</span>
                  </td>
                  <td class="td-l"><span class="sd-lag {$lgCls}">{$lagTxt}</span></td>
                  <td class="td-u">{$runFmt}</td>
                  <td class="td-f">{$freq}m</td>
                  <td class="td-a">
                    <button class="sd-btn-sm" onclick="sdSyncTable('{$tname}',this)">Sync</button>
                    <button class="sd-btn-sm warn" onclick="sdResetTable('{$tname}',this)">Reset</button>
                  </td>
                </tr>
                HTML;

                if ($c->status === 'error' && $c->error_message) {
                    $errMsg = e(mb_substr((string) $c->error_message, 0, 200));
                    $o .= <<<HTML
                    <tr class="row-err-detail">
                      <td colspan="8">
                        <span class="sd-consec">{$consecN}×</span>
                        <span class="sd-errmsg">{$errMsg}</span>
                      </td>
                    </tr>
                    HTML;
                }
            }

            $o .= '</tbody></table></div></div>';
        }

        // ── Sync Log ───────────────────────────────────────────────────────────
        $o .= <<<HTML
        <div class="sd-log">
          <div class="sd-log-hdr">
            <span>Live Sync Log</span>
            <span class="sd-live-dot" id="sd-live-dot">●</span>
            <span class="sd-live-ts" id="sd-live-ts"></span>
            <span class="sd-log-hint">auto-refreshes every 5s</span>
          </div>
          <div class="sd-log-scroll">
          <table class="sd-log-tbl">
            <thead>
              <tr>
                <th>Time</th>
                <th>Table</th>
                <th>Status</th>
                <th>Fetched</th>
                <th>Upserted</th>
                <th>Pages</th>
                <th>Duration</th>
                <th>Error</th>
              </tr>
            </thead>
            <tbody id="sd-log-body">
        HTML;

        foreach ($recentLogs as $i => $lg) {
            $sc    = $lg->status === 'ok' ? 'ok' : ($lg->status === 'error' ? 'err' : 'idle');
            $time  = $lg->created_at ? Carbon::parse($lg->created_at)->format('H:i:s') : '—';
            $err   = $lg->error_message ? e(mb_substr((string) $lg->error_message, 0, 80)) : '';
            $newest = $i === 0 ? ' class="log-new"' : '';
            $o .= <<<HTML
            <tr{$newest}>
              <td class="log-t">{$time}</td>
              <td class="log-n">{$lg->table_name}</td>
              <td><span class="sd-badge {$sc}">{$lg->status}</span></td>
              <td>{$this->fmtN((int)$lg->rows_fetched)}</td>
              <td>{$this->fmtN((int)$lg->rows_upserted)}</td>
              <td>{$lg->pages_fetched}</td>
              <td>{$this->fmtMs((int)$lg->duration_ms)}</td>
              <td class="log-err">{$err}</td>
            </tr>
            HTML;
        }

        $o .= '</tbody></table></div></div>';
        $o .= '</div>'; // sd-root

        $o .= $this->js($base);
        return $o;
    }

    // ── Live JSON endpoint ────────────────────────────────────────────────────

    public function live(): JsonResponse
    {
        $cursors = DbSyncCursor::where('enabled', true)->orderBy('priority')->orderBy('table_name')->get();
        $lcounts = $this->localCounts($cursors->pluck('table_name')->all());

        $lastRun = $cursors->max('last_run_at');
        $lagMin  = $lastRun ? (int) now()->diffInMinutes(Carbon::parse($lastRun)) : null;

        return response()->json([
            'stats' => [
                'ok'      => $cursors->where('status', 'ok')->count(),
                'error'   => $cursors->where('status', 'error')->count(),
                'syncing' => $cursors->where('status', 'syncing')->count(),
                'idle'    => $cursors->where('status', 'idle')->count(),
                'lag_min' => $lagMin,
                'last_run' => $lastRun ? Carbon::parse($lastRun)->diffForHumans() : 'Never',
                'is_healthy' => ($cursors->where('status', 'error')->count() === 0 && $lagMin !== null && $lagMin < 30),
            ],
            'rows' => $cursors->map(function ($c) use ($lcounts) {
                $loc = $lcounts[$c->table_name] ?? 0;
                $src = (int) $c->rows_on_source;
                $pct = ($c->is_pivot && $loc === 0 && $c->status === 'ok')
                    ? 100.0
                    : $this->pct($loc, $src);
                $lag = $c->last_run_at ? (int) now()->diffInMinutes(Carbon::parse($c->last_run_at)) : null;
                return [
                    'table'    => $c->table_name,
                    'status'   => $c->status,
                    'pct'      => $pct,
                    'loc'      => $loc,
                    'src'      => $src,
                    'lag_min'  => $lag,
                    'lag_cls'  => $this->lagClass($c->last_run_at, (int) $c->frequency_minutes),
                    'rows_run' => (int) $c->rows_this_run,
                    'error'    => $c->status === 'error' ? mb_substr((string) $c->error_message, 0, 120) : null,
                    'consec'   => (int) $c->consecutive_errors,
                ];
            })->values(),
            'logs' => DbSyncLog::orderByDesc('created_at')->limit(20)->get()->map(fn($l) => [
                'time'   => $l->created_at ? Carbon::parse($l->created_at)->format('H:i:s') : '—',
                'table'  => $l->table_name,
                'status' => $l->status,
                'fetch'  => $this->fmtN((int) $l->rows_fetched),
                'ups'    => $this->fmtN((int) $l->rows_upserted),
                'pages'  => $l->pages_fetched,
                'ms'     => $this->fmtMs((int) $l->duration_ms),
                'err'    => $l->error_message ? mb_substr((string) $l->error_message, 0, 80) : '',
            ])->values(),
            'ts' => now()->format('H:i:s'),
        ]);
    }

    // ── Trigger sync ──────────────────────────────────────────────────────────

    public function trigger(Request $request): JsonResponse
    {
        $table = $request->input('table');
        $cmd   = 'cd ' . escapeshellarg(base_path()) . ' && php artisan sync:pull --force';
        if ($table) {
            $cmd .= ' --table=' . escapeshellarg($table);
        }
        shell_exec($cmd . ' >> ' . escapeshellarg(storage_path('logs/sync-pull.log')) . ' 2>&1 &');

        Log::info('[SyncDashboard] Manual sync triggered' . ($table ? " for {$table}" : ' (all tables)'));

        return response()->json([
            'ok'  => true,
            'msg' => $table ? "Syncing {$table} in background…" : 'Full sync started in background…',
        ]);
    }

    // ── Reset cursor ──────────────────────────────────────────────────────────

    public function resetCursor(Request $request): JsonResponse
    {
        $table = $request->input('table');
        if (!$table) {
            return response()->json(['ok' => false, 'msg' => 'Table name is required.'], 422);
        }
        $cursor = DbSyncCursor::where('table_name', $table)->first();
        if (!$cursor) {
            return response()->json(['ok' => false, 'msg' => "No cursor found for '{$table}'."], 404);
        }
        $cursor->update([
            'last_synced_id'    => 0,
            'last_updated_ts'   => null,
            'status'            => 'idle',
            'error_message'     => null,
            'consecutive_errors' => 0,
        ]);
        Log::info("[SyncDashboard] Cursor reset for {$table}");
        return response()->json(['ok' => true, 'msg' => "Cursor for '{$table}' reset — will re-sync all rows on next run."]);
    }

    // ── CSS ───────────────────────────────────────────────────────────────────

    private function css(): string
    {
        return <<<'CSS'
        <style>
        /* ── Reset & Base ────────────────────────────────────────── */
        .sd-root *{box-sizing:border-box;margin:0;padding:0}
        .sd-root{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:13px;color:#1e293b;background:#f1f5f9;min-height:100vh;padding:0 0 40px}

        /* ── Header ──────────────────────────────────────────────── */
        .sd-hdr{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
        .sd-title{font-size:22px;font-weight:700;letter-spacing:-.3px;color:#f8fafc}
        .sd-subtitle{display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap}
        .sd-subtitle code{background:rgba(255,255,255,.12);padding:2px 8px;border-radius:4px;font-size:12px;color:#cbd5e1;font-family:ui-monospace,monospace}
        .sd-arrow{color:#64748b;font-size:16px}
        .sd-role-chip{background:rgba(37,99,235,.4);color:#93c5fd;border:1px solid rgba(147,197,253,.3);padding:2px 8px;font-size:11px;font-weight:600;border-radius:999px;letter-spacing:.4px;text-transform:uppercase}
        .sd-pill{font-size:11px;font-weight:700;letter-spacing:.5px;padding:3px 10px;border-radius:999px}
        .sd-pill.on{background:rgba(22,163,74,.2);color:#4ade80;border:1px solid rgba(74,222,128,.3)}
        .sd-pill.off{background:rgba(220,38,38,.2);color:#fca5a5;border:1px solid rgba(252,165,165,.3)}
        .sd-hdr-r{display:flex;flex-direction:column;align-items:flex-end;gap:6px}
        .sd-last-run-lbl{font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px}
        .sd-last-run-val{font-size:13px;color:#e2e8f0;font-weight:600}
        .sd-btn-primary{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer;border-radius:6px;display:flex;align-items:center;gap:8px;transition:all .15s;white-space:nowrap}
        .sd-btn-primary:hover{background:linear-gradient(135deg,#3b82f6,#2563eb);transform:translateY(-1px);box-shadow:0 4px 12px rgba(37,99,235,.4)}
        .sd-btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none}
        @keyframes spin{to{transform:rotate(360deg)}}
        .spinning{display:inline-block;animation:spin 1s linear infinite}

        /* ── Stat Cards ──────────────────────────────────────────── */
        .sd-stats{display:flex;gap:0;border-bottom:1px solid #e2e8f0;background:#fff}
        .sd-stat{flex:1;padding:16px 18px;border-right:1px solid #e2e8f0;min-width:0}
        .sd-stat:last-child{border-right:none}
        .sd-stat-icon{font-size:28px;font-weight:800;line-height:1;margin-bottom:2px}
        .sd-stat-val{font-size:26px;font-weight:800;color:#0f172a;line-height:1;font-variant-numeric:tabular-nums}
        .sd-stat-lbl{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:3px;white-space:nowrap}
        .sd-health-hok{background:linear-gradient(135deg,#f0fdf4,#dcfce7)}
        .sd-health-hok .sd-stat-icon,.sd-health-hok .sd-stat-val{color:#15803d}
        .sd-health-herr{background:linear-gradient(135deg,#fef2f2,#fee2e2)}
        .sd-health-herr .sd-stat-icon,.sd-health-herr .sd-stat-val{color:#dc2626}
        .sd-health-hwarn{background:linear-gradient(135deg,#fffbeb,#fef3c7)}
        .sd-health-hwarn .sd-stat-icon,.sd-health-hwarn .sd-stat-val{color:#d97706}
        .sd-ok-val{color:#15803d}
        .sd-err-val{color:#dc2626}
        .sd-ovpct-stat{flex:1.5}
        .sd-ovpct-detail{font-size:11px;color:#64748b;margin-top:4px;font-variant-numeric:tabular-nums}

        /* ── Progress bar ────────────────────────────────────────── */
        .sd-bar-track{background:#e2e8f0;height:6px;border-radius:999px;overflow:hidden;margin-top:6px}
        .sd-bar-fill{height:100%;border-radius:999px;transition:width .4s ease}
        .sd-pct-lbl{font-size:11px;color:#475569;margin-left:4px;font-variant-numeric:tabular-nums}

        /* ── Notification ────────────────────────────────────────── */
        .sd-notif{padding:10px 16px;margin:12px 16px;border-radius:6px;font-size:13px;border-left:4px solid #2563eb;background:#eff6ff;color:#1e40af;transition:opacity .3s}
        .sd-notif.notif-ok{border-color:#16a34a;background:#f0fdf4;color:#15803d}
        .sd-notif.notif-err{border-color:#dc2626;background:#fef2f2;color:#b91c1c}

        /* ── Priority Groups ─────────────────────────────────────── */
        .sd-group{background:#fff;border:1px solid #e2e8f0;margin:12px 0;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        .sd-g-hdr{background:#1e3a5f;color:#e2e8f0;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:700;letter-spacing:.4px;text-transform:uppercase}
        .sd-g-badge{background:rgba(255,255,255,.15);padding:2px 8px;border-radius:4px;font-size:10px;margin-right:6px}
        .sd-g-chip{font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700}
        .sd-g-chip.gok{background:rgba(22,163,74,.2);color:#4ade80}
        .sd-g-chip.gerr{background:rgba(220,38,38,.25);color:#fca5a5}
        .sd-g-chip.gsync{background:rgba(37,99,235,.2);color:#93c5fd}

        /* ── Data Table ──────────────────────────────────────────── */
        .sd-tbl-wrap{overflow-x:auto}
        .sd-tbl{width:100%;border-collapse:collapse;min-width:860px}
        .sd-tbl th{background:#f8fafc;color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;padding:8px 12px;border-bottom:2px solid #e2e8f0;white-space:nowrap;text-align:left}
        .sd-tbl td{padding:9px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .sd-tbl tr:last-child td{border-bottom:none}
        .sd-tbl tr:hover td,.sd-tbl tr:hover th{background:#f8fafc}
        .row-err td{background:#fff8f8}
        .row-err-detail td{background:#fff1f1;padding:4px 12px 8px 24px}

        .th-n{width:200px}.th-s{width:100px}.th-p{width:180px}.th-r{width:140px}
        .th-l{width:80px}.th-u{width:90px}.th-f{width:50px}.th-a{width:130px}

        .sd-tname{font-family:ui-monospace,SFMono-Regular,monospace;font-size:12px;font-weight:600;color:#0f172a}
        .sd-pivot{font-size:9px;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:1px 5px;border-radius:3px;margin-left:5px;vertical-align:middle;font-weight:600;letter-spacing:.3px;text-transform:uppercase}
        .sd-loc-n{color:#0f172a;font-weight:600;font-variant-numeric:tabular-nums}
        .sd-sep{color:#cbd5e1;margin:0 4px}
        .sd-src-n{color:#64748b;font-variant-numeric:tabular-nums}
        .sd-plus{color:#16a34a;font-weight:700;font-variant-numeric:tabular-nums}
        .sd-dash{color:#cbd5e1}
        .sd-consec{background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:3px;margin-right:8px}
        .sd-errmsg{color:#b91c1c;font-size:12px;font-family:ui-monospace,monospace}

        /* ── Lag chips ───────────────────────────────────────────── */
        .sd-lag{font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;font-variant-numeric:tabular-nums;white-space:nowrap}
        .lg-ok  {background:#dcfce7;color:#15803d}
        .lg-warn{background:#fef3c7;color:#92400e}
        .lg-err {background:#fee2e2;color:#991b1b}
        .lg-none{background:#f1f5f9;color:#94a3b8}

        /* ── Status badges ───────────────────────────────────────── */
        .sd-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;font-size:11px;font-weight:700;border-radius:4px;letter-spacing:.3px;border:1px solid transparent;white-space:nowrap}
        .sd-badge.ok  {background:#dcfce7;color:#15803d;border-color:#86efac}
        .sd-badge.err {background:#fee2e2;color:#991b1b;border-color:#fca5a5}
        .sd-badge.sync{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}
        .sd-badge.idle{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
        .sd-badge.sync{animation:pulse 1.5s ease-in-out infinite}

        /* ── Action buttons ──────────────────────────────────────── */
        .sd-btn-sm{border:1px solid #cbd5e1;background:#f8fafc;color:#334155;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;border-radius:4px;transition:all .1s;margin-right:4px}
        .sd-btn-sm:hover{background:#e2e8f0;border-color:#94a3b8}
        .sd-btn-sm.warn{color:#dc2626;border-color:#fca5a5}
        .sd-btn-sm.warn:hover{background:#fee2e2}
        .sd-btn-sm:disabled{opacity:.5;cursor:not-allowed}

        /* ── Log Section ─────────────────────────────────────────── */
        .sd-log{background:#fff;border:1px solid #e2e8f0;margin:12px 0;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        .sd-log-hdr{background:#1e3a5f;color:#e2e8f0;padding:10px 16px;display:flex;align-items:center;gap:10px;font-size:12px;font-weight:700;letter-spacing:.4px;text-transform:uppercase}
        .sd-live-dot{color:#4ade80;font-size:14px;line-height:1}
        @keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
        .sd-live-dot{animation:blink 2s step-end infinite}
        .sd-live-ts{color:#94a3b8;font-size:11px;font-weight:400;margin-left:4px}
        .sd-log-hint{color:#475569;font-size:10px;font-weight:400;margin-left:auto}
        .sd-log-scroll{max-height:420px;overflow-y:auto}
        .sd-log-tbl{width:100%;border-collapse:collapse;min-width:760px}
        .sd-log-tbl th{position:sticky;top:0;background:#f8fafc;color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;padding:7px 10px;border-bottom:2px solid #e2e8f0;text-align:left;z-index:1}
        .sd-log-tbl td{padding:6px 10px;border-bottom:1px solid #f8fafc;vertical-align:middle;font-size:12px}
        .log-t{font-family:ui-monospace,monospace;color:#64748b;white-space:nowrap}
        .log-n{font-family:ui-monospace,monospace;font-weight:600;color:#0f172a;white-space:nowrap}
        .log-err{color:#dc2626;font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        @keyframes fadein{from{background:#fffbeb}to{background:transparent}}
        .log-new{animation:fadein 2s ease-out}

        /* ── Responsive ──────────────────────────────────────────── */
        @media(max-width:900px){
          .sd-stats{flex-wrap:wrap}
          .sd-stat{flex:1 1 45%;min-width:130px}
          .sd-hdr{flex-direction:column}
          .sd-hdr-r{flex-direction:row;align-items:center;flex-wrap:wrap}
        }
        @media(max-width:600px){
          .sd-stat{flex:1 1 100%}
        }
        </style>
        CSS;
    }

    // ── JavaScript ────────────────────────────────────────────────────────────

    private function js(string $base): string
    {
        $csrf = csrf_token();

        return <<<JS
        <script>
        (function(){
        var BASE = "{$base}", CSRF = "{$csrf}";

        function api(path, opts) {
            opts = opts || {};
            var init = {
                method:  opts.method  || 'GET',
                headers: Object.assign({'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json',
                                        'Content-Type': 'application/json'}, opts.headers || {}),
            };
            if (opts.body) init.body = JSON.stringify(opts.body);
            return fetch(BASE + path, init).then(function(r) {
                if (r.status === 419) { location.reload(); throw new Error('CSRF'); }
                return r.json();
            });
        }

        function notif(msg, type) {
            var n = document.getElementById('sd-notif');
            if (!n) return;
            n.textContent = msg;
            n.className = 'sd-notif notif-' + (type || 'info');
            n.hidden = false;
            clearTimeout(n._t);
            n._t = setTimeout(function(){ n.hidden = true; }, 7000);
        }

        // ── Run full sync ──────────────────────────────────────────
        window.sdSyncAll = function(btn) {
            btn.disabled = true;
            var spin = document.getElementById('sd-btn-spin');
            if (spin) spin.className = 'spinning';
            btn.querySelector('.sd-btn-spin') && (btn.querySelector('.sd-btn-spin').className = 'sd-btn-spin spinning');
            api('/sync-dashboard/trigger', {method:'POST', body:{}})
                .then(function(d){ notif(d.msg, d.ok ? 'ok' : 'err'); })
                .catch(function(e){ notif('Request failed: ' + e, 'err'); })
                .finally(function(){
                    btn.disabled = false;
                    if (spin) spin.className = 'sd-btn-spin';
                    setTimeout(sdRefresh, 4000);
                });
        };

        // ── Sync single table ──────────────────────────────────────
        window.sdSyncTable = function(table, btn) {
            btn.disabled = true;
            api('/sync-dashboard/trigger', {method:'POST', body:{table:table}})
                .then(function(d){ notif(d.msg, d.ok ? 'ok' : 'err'); setTimeout(sdRefresh, 3000); })
                .catch(function(e){ notif('Error: ' + e, 'err'); })
                .finally(function(){ btn.disabled = false; });
        };

        // ── Reset cursor ───────────────────────────────────────────
        window.sdResetTable = function(table, btn) {
            if (!confirm('Reset cursor for "' + table + '" to 0?\\nNext sync will re-pull ALL rows from source.')) return;
            btn.disabled = true;
            api('/sync-dashboard/reset', {method:'POST', body:{table:table}})
                .then(function(d){ notif(d.msg, d.ok ? 'ok' : 'err'); sdRefresh(); })
                .catch(function(e){ notif('Error: ' + e, 'err'); })
                .finally(function(){ btn.disabled = false; });
        };

        // ── Live refresh ───────────────────────────────────────────
        var KNOWN_LOG_IDS = new Set();

        function sdRefresh() {
            api('/sync-dashboard/live').then(function(d) {
                // Update stat values
                setEl('sd-ok-val', d.stats.ok);
                setEl('sd-err-val', d.stats.error);
                setEl('sd-idle-val', d.stats.idle);
                setEl('sd-last-run', d.stats.last_run || '—');

                // Update live timestamp
                setEl('sd-live-ts', d.ts);

                // Update per-table rows
                (d.rows || []).forEach(function(r) {
                    var row = document.getElementById('sd-row-' + r.table);
                    if (!row) return;
                    // Status badge
                    var badge = row.querySelector('.sd-badge');
                    if (badge) {
                        var dot = {ok:'●',error:'✕',syncing:'◌',idle:'○'}[r.status] || '○';
                        badge.textContent = dot + ' ' + r.status;
                        badge.className = 'sd-badge ' + ({ok:'ok',error:'err',syncing:'sync',idle:'idle'}[r.status]||'idle');
                    }
                    // Progress bar
                    var fill = row.querySelector('.sd-bar-fill');
                    if (fill) {
                        var pct = Math.min(100, r.pct || 0);
                        fill.style.width = pct + '%';
                        fill.style.background = pct >= 100 ? '#16a34a' : (pct >= 80 ? '#2563eb' : (pct >= 50 ? '#d97706' : '#dc2626'));
                    }
                    var pctLbl = row.querySelector('.sd-pct-lbl');
                    if (pctLbl) pctLbl.textContent = (r.pct >= 100 ? 100 : r.pct) + '%';
                    // Local/src counts
                    var locEl = row.querySelector('.sd-loc-n');
                    if (locEl) locEl.textContent = fmtN(r.loc);
                    var srcEl = row.querySelector('.sd-src-n');
                    if (srcEl) srcEl.textContent = fmtN(r.src);
                    // Lag chip
                    var lagEl = row.querySelector('.sd-lag');
                    if (lagEl && r.lag_min !== null) {
                        lagEl.textContent = r.lag_min + 'm';
                        lagEl.className   = 'sd-lag ' + (r.lag_cls || 'lg-none');
                    }
                    // Run rows
                    var runEl = row.querySelector('.td-u');
                    if (runEl) {
                        runEl.innerHTML = r.rows_run > 0
                            ? '<span class="sd-plus">+' + fmtN(r.rows_run) + '</span>'
                            : '<span class="sd-dash">—</span>';
                    }
                });

                // Update log body — prepend new entries
                var body = document.getElementById('sd-log-body');
                if (body && d.logs && d.logs.length) {
                    var html = '';
                    d.logs.forEach(function(l, i) {
                        var key = l.time + '-' + l.table;
                        var sc  = l.status === 'ok' ? 'ok' : (l.status === 'error' ? 'err' : 'idle');
                        var newCls = (i === 0 && !KNOWN_LOG_IDS.has(key)) ? ' class="log-new"' : '';
                        KNOWN_LOG_IDS.add(key);
                        html += '<tr' + newCls + '>'
                            + '<td class="log-t">' + l.time + '</td>'
                            + '<td class="log-n">' + l.table + '</td>'
                            + '<td><span class="sd-badge ' + sc + '">' + l.status + '</span></td>'
                            + '<td>' + l.fetch + '</td>'
                            + '<td>' + l.ups + '</td>'
                            + '<td>' + l.pages + '</td>'
                            + '<td>' + l.ms + '</td>'
                            + '<td class="log-err">' + esc(l.err) + '</td>'
                            + '</tr>';
                    });
                    body.innerHTML = html;
                }
            }).catch(function(){});
        }

        function setEl(id, val) {
            var el = document.getElementById(id);
            if (el) el.textContent = val;
        }

        function fmtN(n) {
            n = parseInt(n, 10) || 0;
            if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
            if (n >= 10000)   return (n/1000).toFixed(1) + 'k';
            if (n >= 1000)    return (n/1000).toFixed(2) + 'k';
            return String(n);
        }

        function esc(s) {
            return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        // Poll every 5 seconds
        setInterval(sdRefresh, 5000);
        // Initial refresh after 2s (catches in-progress syncs)
        setTimeout(sdRefresh, 2000);

        })();
        </script>
        JS;
    }
}
