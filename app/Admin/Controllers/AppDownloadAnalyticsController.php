<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PageVisit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Encore\Admin\Layout\Content;

class AppDownloadAnalyticsController extends Controller
{
    public function index(Content $content)
    {
        return $content
            ->title('App Download Analytics')
            ->description('Traffic monitoring for /app landing page')
            ->body($this->buildDashboard());
    }

    protected function buildDashboard()
    {
        $now    = Carbon::now();
        $today  = Carbon::today();
        $thirty = Carbon::today()->subDays(29);

        // ── Summary counts ──
        $totalVisits   = PageVisit::count();
        $todayVisits   = PageVisit::whereDate('created_at', $today)->count();
        $weekVisits    = PageVisit::where('created_at', '>=', $now->copy()->subDays(7))->count();
        $monthVisits   = PageVisit::where('created_at', '>=', $thirty)->count();
        $uniqueIPs     = PageVisit::distinct('ip_address')->count('ip_address');
        $avgTime       = round(PageVisit::whereNotNull('time_on_page_seconds')->avg('time_on_page_seconds') ?? 0);

        // Button clicks
        $androidClicks = PageVisit::where('button_clicked', 'android')->count();
        $iosClicks     = PageVisit::where('button_clicked', 'ios')->count();
        $webClicks     = PageVisit::where('button_clicked', 'web')->count();
        $totalClicks   = $androidClicks + $iosClicks + $webClicks;
        $bounceCount   = PageVisit::whereNull('button_clicked')
            ->where(function ($q) {
                $q->whereNull('time_on_page_seconds')->orWhere('time_on_page_seconds', '<', 5);
            })->count();
        $bounceRate    = $totalVisits > 0 ? round(($bounceCount / $totalVisits) * 100, 1) : 0;

        // ── Daily visits (last 30 days) ──
        $dailyRaw = PageVisit::select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', $thirty)
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');

        $labels30 = [];
        $visits30 = [];
        for ($i = 29; $i >= 0; $i--) {
            $dk = Carbon::today()->subDays($i);
            $labels30[] = $dk->format('d M');
            $visits30[] = $dailyRaw[$dk->format('Y-m-d')]->cnt ?? 0;
        }

        // ── Device breakdown ──
        $deviceData = PageVisit::select('device_type', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('device_type')
            ->groupBy('device_type')->pluck('cnt', 'device_type')->toArray();

        // ── Browser breakdown ──
        $browserData = PageVisit::select('browser', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('browser')
            ->groupBy('browser')->orderByDesc('cnt')->limit(6)->pluck('cnt', 'browser')->toArray();

        // ── OS breakdown ──
        $osData = PageVisit::select('os', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('os')
            ->groupBy('os')->orderByDesc('cnt')->limit(6)->pluck('cnt', 'os')->toArray();

        // ── Top referrers ──
        $referrers = PageVisit::select('referrer_url', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('referrer_url')->where('referrer_url', '!=', '')
            ->groupBy('referrer_url')->orderByDesc('cnt')->limit(10)->get();

        // ── Top countries ──
        $countries = PageVisit::select('country', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('country')
            ->groupBy('country')->orderByDesc('cnt')->limit(10)->get();

        // ── UTM sources ──
        $utmSources = PageVisit::select('utm_source', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('utm_source')
            ->groupBy('utm_source')->orderByDesc('cnt')->limit(10)->get();

        // ── Hourly heatmap (last 7 days) ──
        $hourlyRaw = PageVisit::select(DB::raw('HOUR(created_at) as h'), DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->groupBy('h')->pluck('cnt', 'h')->toArray();
        $hourlyLabels = [];
        $hourlyData   = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $hourlyData[]   = $hourlyRaw[$h] ?? 0;
        }

        // ── Button clicks over time (last 30 days) ──
        $clicksRaw = PageVisit::select(
                DB::raw('DATE(created_at) as d'),
                'button_clicked',
                DB::raw('COUNT(*) as cnt')
            )
            ->where('created_at', '>=', $thirty)
            ->whereNotNull('button_clicked')
            ->groupBy('d', 'button_clicked')->get();
        $clicksMap = [];
        foreach ($clicksRaw as $r) {
            $clicksMap[$r->d][$r->button_clicked] = $r->cnt;
        }
        $androidDaily = []; $iosDaily = []; $webDaily = [];
        for ($i = 29; $i >= 0; $i--) {
            $dk = Carbon::today()->subDays($i)->format('Y-m-d');
            $androidDaily[] = $clicksMap[$dk]['android'] ?? 0;
            $iosDaily[]     = $clicksMap[$dk]['ios'] ?? 0;
            $webDaily[]     = $clicksMap[$dk]['web'] ?? 0;
        }

        // ── Recent visits (last 20) ──
        $recentVisits = PageVisit::select('id', 'ip_address', 'device_type', 'os', 'browser', 'referrer_url', 'button_clicked', 'time_on_page_seconds', 'created_at')
            ->orderByDesc('id')->limit(20)->get();

        // ════════════════ HTML ════════════════
        $html = '<style>
.av-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:12px}
.av-row{display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap}
.av-card{flex:1;min-width:120px;background:#fff;border-radius:6px;padding:12px 14px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-left:3px solid #ddd;position:relative;transition:box-shadow .15s}
.av-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.12)}
.av-card .av-val{font-size:20px;font-weight:700;line-height:1.1}
.av-card .av-lbl{font-size:9px;color:#888;margin-top:2px;text-transform:uppercase;letter-spacing:.3px}
.av-card .av-ico{position:absolute;right:8px;top:8px;font-size:14px;opacity:.3}
.av-box{background:#fff;border-radius:6px;padding:14px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.av-box-title{font-size:10px;font-weight:700;color:#555;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px}
.av-section{margin-bottom:4px;font-size:11px;font-weight:700;color:#333;border-bottom:2px solid #C0392B;padding-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
.av-tbl{width:100%;border-collapse:collapse;font-size:11px}
.av-tbl th{text-align:left;padding:4px 6px;border-bottom:2px solid #eee;font-weight:700;color:#555;font-size:10px;text-transform:uppercase}
.av-tbl td{padding:4px 6px;border-bottom:1px solid #f5f5f5}
.av-badge{display:inline-block;padding:1px 6px;border-radius:3px;font-size:9px;font-weight:600;color:#fff}
</style>';

        $html .= '<div class="av-wrap">';

        // ── SECTION: Real APK downloads (server-side, from apk_downloads) ──
        try {
            $dl = DB::table('apk_downloads')->selectRaw("
                    COUNT(*) total,
                    SUM(created_at >= CURDATE()) today,
                    SUM(created_at >= NOW() - INTERVAL 7 DAY) week,
                    SUM(created_at >= NOW() - INTERVAL 30 DAY) month
                ")->first();

            $dlVariants = DB::table('apk_downloads')
                ->selectRaw('variant, COUNT(*) c')->groupBy('variant')->pluck('c', 'variant')->toArray();

            $dlSources = DB::table('apk_downloads')
                ->selectRaw("COALESCE(NULLIF(src,''),'(direct)') s, COUNT(*) c")
                ->groupBy('s')->orderByDesc('c')->limit(8)->get();

            $dlDaily = DB::table('apk_downloads')
                ->selectRaw('DATE(created_at) d, COUNT(*) c')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->groupBy('d')->pluck('c', 'd')->toArray();

            // Funnel: page visits → android intent → real downloads
            $androidPageViews = PageVisit::where('page_url', 'like', '%/app/android%')->count();
            $funnelDownloads  = (int) $dl->total;
            $convPct = $androidPageViews > 0 ? round($funnelDownloads / $androidPageViews * 100, 1) : 0;

            $html .= '<div class="av-section">APK Downloads — Real (server-counted)</div>';
            $html .= '<div class="av-row">';
            foreach ([
                ['Total Downloads', number_format((int) $dl->total),  '#C0392B', 'fa-download'],
                ['Today',           number_format((int) $dl->today),  '#28a745', 'fa-calendar-check-o'],
                ['Last 7 Days',     number_format((int) $dl->week),   '#007bff', 'fa-calendar'],
                ['Last 30 Days',    number_format((int) $dl->month),  '#6f42c1', 'fa-calendar-o'],
                ['arm64 / arm32 / universal',
                    number_format($dlVariants['arm64'] ?? 0) . ' / ' . number_format($dlVariants['arm32'] ?? 0) . ' / ' . number_format($dlVariants['universal'] ?? 0),
                    '#e67e22', 'fa-cubes'],
                ['Screen → Download', $convPct . '%', '#16a085', 'fa-filter'],
            ] as $c) {
                $html .= '<div class="av-card" style="border-left:3px solid ' . $c[2] . '">
                            <div class="av-val">' . $c[1] . '</div>
                            <div class="av-lbl">' . $c[0] . '</div>
                            <i class="fa ' . $c[3] . ' av-ico"></i></div>';
            }
            $html .= '</div>';

            // 14-day mini trend + top sources, side by side
            $html .= '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px;">';

            $html .= '<div class="av-box" style="flex:2;min-width:300px;"><div class="av-box-title">Downloads — last 14 days</div>
                      <div style="display:flex;align-items:flex-end;gap:4px;height:70px;">';
            $maxD = max(1, max(array_values($dlDaily) ?: [1]));
            for ($i = 13; $i >= 0; $i--) {
                $dk  = now()->subDays($i)->format('Y-m-d');
                $cnt = (int) ($dlDaily[$dk] ?? 0);
                $h   = max(3, (int) round($cnt / $maxD * 62));
                $html .= '<div title="' . $dk . ': ' . $cnt . '" style="flex:1;background:#C0392B;opacity:' . ($cnt ? '1' : '.15') . ';height:' . $h . 'px;border-radius:2px 2px 0 0;"></div>';
            }
            $html .= '</div><div style="display:flex;justify-content:space-between;font-size:9px;color:#999;margin-top:3px;">
                        <span>' . now()->subDays(13)->format('d M') . '</span><span>today</span></div></div>';

            $html .= '<div class="av-box" style="flex:1;min-width:220px;"><div class="av-box-title">Top Sources (?src=)</div>
                      <table class="av-tbl"><tr><th>Source</th><th>Downloads</th></tr>';
            foreach ($dlSources as $s) {
                $html .= '<tr><td>' . e($s->s) . '</td><td>' . number_format($s->c) . '</td></tr>';
            }
            if ($dlSources->isEmpty()) {
                $html .= '<tr><td colspan="2" style="color:#999;">No downloads yet — share links like movies.mruodel.com/app?src=whatsapp</td></tr>';
            }
            $html .= '</table></div></div>';
        } catch (\Throwable $e) {
            $html .= '<div class="av-box" style="margin-bottom:12px;color:#c00;">APK download stats unavailable: ' . e($e->getMessage()) . '</div>';
        }

        // ── SECTION: Overview Cards ──
        $html .= '<div class="av-section">Traffic Overview</div>';
        $html .= '<div class="av-row">';
        $cards = [
            ['Total Visits',     number_format($totalVisits),  '#007bff', 'fa-eye'],
            ['Today',            number_format($todayVisits),  '#28a745', 'fa-calendar-check-o'],
            ['This Week',        number_format($weekVisits),   '#6f42c1', 'fa-calendar'],
            ['Last 30 Days',     number_format($monthVisits),  '#17a2b8', 'fa-calendar-o'],
            ['Unique Visitors',  number_format($uniqueIPs),    '#e83e8c', 'fa-users'],
            ['Avg Time (sec)',   number_format($avgTime),      '#fd7e14', 'fa-clock-o'],
            ['Bounce Rate',      $bounceRate . '%',            '#dc3545', 'fa-sign-out'],
        ];
        foreach ($cards as [$l, $v, $c, $i]) {
            $html .= "<div class='av-card' style='border-left-color:{$c}'><div class='av-val' style='color:{$c}'>{$v}</div><div class='av-lbl'>{$l}</div><i class='fa {$i} av-ico'></i></div>";
        }
        $html .= '</div>';

        // ── Click cards ──
        $html .= '<div class="av-row">';
        $clickCards = [
            ['Total Clicks',   number_format($totalClicks),   '#C0392B', 'fa-mouse-pointer'],
            ['Android Clicks', number_format($androidClicks), '#3ddc84', 'fa-android'],
            ['iOS Clicks',     number_format($iosClicks),     '#555',    'fa-apple'],
            ['Web Clicks',     number_format($webClicks),     '#17a2b8', 'fa-desktop'],
        ];
        foreach ($clickCards as [$l, $v, $c, $i]) {
            $html .= "<div class='av-card' style='border-left-color:{$c}'><div class='av-val' style='color:{$c}'>{$v}</div><div class='av-lbl'>{$l}</div><i class='fa {$i} av-ico'></i></div>";
        }
        $html .= '</div>';

        // ── CHARTS ──
        $html .= '<div class="av-section">Charts &amp; Analytics</div>';

        // JSON data
        $jLabels    = json_encode($labels30);
        $jVisits    = json_encode($visits30);
        $jAndroid   = json_encode($androidDaily);
        $jIos       = json_encode($iosDaily);
        $jWeb       = json_encode($webDaily);
        $jDevLabels = json_encode(array_keys($deviceData));
        $jDevData   = json_encode(array_values($deviceData));
        $jBrLabels  = json_encode(array_keys($browserData));
        $jBrData    = json_encode(array_values($browserData));
        $jOsLabels  = json_encode(array_keys($osData));
        $jOsData    = json_encode(array_values($osData));
        $jHrLabels  = json_encode($hourlyLabels);
        $jHrData    = json_encode($hourlyData);

        // Row 1: Daily visits line chart + Button clicks stacked bar
        $html .= '<div class="av-row">';
        $html .= '<div class="av-box" style="flex:1;min-width:460px">';
        $html .= '<div class="av-box-title"><i class="fa fa-line-chart" style="color:#007bff"></i> Daily Visits — Last 30 Days</div>';
        $html .= '<div style="position:relative;height:300px"><canvas id="avVisitsChart"></canvas></div></div>';

        $html .= '<div class="av-box" style="flex:1;min-width:460px">';
        $html .= '<div class="av-box-title"><i class="fa fa-mouse-pointer" style="color:#C0392B"></i> Button Clicks — Last 30 Days</div>';
        $html .= '<div style="position:relative;height:300px"><canvas id="avClicksChart"></canvas></div></div>';
        $html .= '</div>';

        // Row 2: Device doughnut + Browser doughnut + OS doughnut
        $html .= '<div class="av-row">';
        $html .= '<div class="av-box" style="flex:1;min-width:220px;text-align:center">';
        $html .= '<div class="av-box-title"><i class="fa fa-mobile" style="color:#C0392B"></i> Device Type</div>';
        $html .= '<div style="position:relative;height:220px"><canvas id="avDeviceChart"></canvas></div></div>';

        $html .= '<div class="av-box" style="flex:1;min-width:220px;text-align:center">';
        $html .= '<div class="av-box-title"><i class="fa fa-globe" style="color:#17a2b8"></i> Browser</div>';
        $html .= '<div style="position:relative;height:220px"><canvas id="avBrowserChart"></canvas></div></div>';

        $html .= '<div class="av-box" style="flex:1;min-width:220px;text-align:center">';
        $html .= '<div class="av-box-title"><i class="fa fa-laptop" style="color:#6f42c1"></i> Operating System</div>';
        $html .= '<div style="position:relative;height:220px"><canvas id="avOsChart"></canvas></div></div>';
        $html .= '</div>';

        // Row 3: Hourly heatmap bar
        $html .= '<div class="av-row">';
        $html .= '<div class="av-box" style="flex:1">';
        $html .= '<div class="av-box-title"><i class="fa fa-clock-o" style="color:#fd7e14"></i> Hourly Traffic — Last 7 Days</div>';
        $html .= '<div style="position:relative;height:250px"><canvas id="avHourlyChart"></canvas></div></div>';
        $html .= '</div>';

        // Row 4: Referrers + Countries + UTM Sources
        $html .= '<div class="av-section">Traffic Sources &amp; Demographics</div>';
        $html .= '<div class="av-row">';

        // Referrers table
        $html .= '<div class="av-box" style="flex:1;min-width:280px;max-height:340px;overflow-y:auto">';
        $html .= '<div class="av-box-title"><i class="fa fa-link" style="color:#007bff"></i> Top Referrers</div>';
        $html .= '<table class="av-tbl"><tr><th>Source</th><th style="text-align:right">Visits</th></tr>';
        if ($referrers->isEmpty()) {
            $html .= '<tr><td colspan="2" style="text-align:center;color:#999;padding:16px">No referrer data yet</td></tr>';
        }
        foreach ($referrers as $ref) {
            $domain = parse_url($ref->referrer_url, PHP_URL_HOST) ?: htmlspecialchars(mb_substr($ref->referrer_url, 0, 40));
            $html .= '<tr><td title="' . htmlspecialchars($ref->referrer_url) . '">' . htmlspecialchars($domain) . '</td>';
            $html .= '<td style="text-align:right"><b>' . number_format($ref->cnt) . '</b></td></tr>';
        }
        $html .= '</table></div>';

        // Countries table
        $html .= '<div class="av-box" style="flex:1;min-width:220px;max-height:340px;overflow-y:auto">';
        $html .= '<div class="av-box-title"><i class="fa fa-globe" style="color:#28a745"></i> Top Countries</div>';
        $html .= '<table class="av-tbl"><tr><th>Country</th><th style="text-align:right">Visits</th></tr>';
        if ($countries->isEmpty()) {
            $html .= '<tr><td colspan="2" style="text-align:center;color:#999;padding:16px">No geo data yet</td></tr>';
        }
        foreach ($countries as $c) {
            $html .= '<tr><td>' . htmlspecialchars($c->country) . '</td>';
            $html .= '<td style="text-align:right"><b>' . number_format($c->cnt) . '</b></td></tr>';
        }
        $html .= '</table></div>';

        // UTM Sources table
        $html .= '<div class="av-box" style="flex:1;min-width:220px;max-height:340px;overflow-y:auto">';
        $html .= '<div class="av-box-title"><i class="fa fa-bullseye" style="color:#e83e8c"></i> UTM Sources</div>';
        $html .= '<table class="av-tbl"><tr><th>Source</th><th style="text-align:right">Visits</th></tr>';
        if ($utmSources->isEmpty()) {
            $html .= '<tr><td colspan="2" style="text-align:center;color:#999;padding:16px">No UTM data yet</td></tr>';
        }
        foreach ($utmSources as $u) {
            $html .= '<tr><td>' . htmlspecialchars($u->utm_source) . '</td>';
            $html .= '<td style="text-align:right"><b>' . number_format($u->cnt) . '</b></td></tr>';
        }
        $html .= '</table></div>';
        $html .= '</div>';

        // ── Recent Visits Table ──
        $html .= '<div class="av-section">Recent Visits</div>';
        $html .= '<div class="av-box" style="max-height:400px;overflow-y:auto">';
        $html .= '<table class="av-tbl">';
        $html .= '<tr><th>#</th><th>IP</th><th>Device</th><th>OS</th><th>Browser</th><th>Referrer</th><th>Click</th><th>Time</th><th>When</th></tr>';
        foreach ($recentVisits as $v) {
            $refShort = $v->referrer_url ? htmlspecialchars(mb_substr(parse_url($v->referrer_url, PHP_URL_HOST) ?: $v->referrer_url, 0, 25)) : '<span style="color:#ccc">direct</span>';
            $clickBadge = $v->button_clicked
                ? '<span class="av-badge" style="background:' . ['android' => '#3ddc84', 'ios' => '#555', 'web' => '#17a2b8'][$v->button_clicked] . '">' . $v->button_clicked . '</span>'
                : '<span style="color:#ccc">—</span>';
            $timeStr = $v->time_on_page_seconds !== null ? $v->time_on_page_seconds . 's' : '—';
            $html .= '<tr>';
            $html .= '<td>' . $v->id . '</td>';
            $html .= '<td>' . htmlspecialchars($v->ip_address) . '</td>';
            $html .= '<td>' . htmlspecialchars($v->device_type ?? '—') . '</td>';
            $html .= '<td>' . htmlspecialchars($v->os ?? '—') . '</td>';
            $html .= '<td>' . htmlspecialchars($v->browser ?? '—') . '</td>';
            $html .= '<td>' . $refShort . '</td>';
            $html .= '<td>' . $clickBadge . '</td>';
            $html .= '<td>' . $timeStr . '</td>';
            $html .= '<td>' . $v->created_at->diffForHumans() . '</td>';
            $html .= '</tr>';
        }
        if ($recentVisits->isEmpty()) {
            $html .= '<tr><td colspan="9" style="text-align:center;color:#999;padding:20px">No visits recorded yet</td></tr>';
        }
        $html .= '</table></div>';

        $html .= '</div>'; // .av-wrap

        // ════════════════ CHART.JS ════════════════
        $html .= "<script>
document.addEventListener('DOMContentLoaded', function(){
    var labels30  = {$jLabels};
    var visits30  = {$jVisits};
    var android30 = {$jAndroid};
    var ios30     = {$jIos};
    var web30     = {$jWeb};

    // Daily visits line chart
    new Chart(document.getElementById('avVisitsChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: labels30,
            datasets: [{
                label: 'Visits',
                data: visits30,
                borderColor: '#C0392B',
                backgroundColor: 'rgba(192,57,43,0.1)',
                borderWidth: 2,
                pointRadius: 2,
                pointHoverRadius: 4,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {display: false},
            scales: {
                xAxes: [{ticks: {fontSize: 9, maxRotation: 45}}],
                yAxes: [{ticks: {fontSize: 10, beginAtZero: true}}]
            }
        }
    });

    // Button clicks stacked bar
    new Chart(document.getElementById('avClicksChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels30,
            datasets: [
                {label: 'Android', data: android30, backgroundColor: '#3ddc84'},
                {label: 'iOS',     data: ios30,     backgroundColor: '#555'},
                {label: 'Web',     data: web30,     backgroundColor: '#17a2b8'}
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {position: 'top', labels: {fontSize: 10}},
            scales: {
                xAxes: [{stacked: true, ticks: {fontSize: 9, maxRotation: 45}}],
                yAxes: [{stacked: true, ticks: {fontSize: 10, beginAtZero: true}}]
            }
        }
    });

    // Device doughnut
    var devColors = ['#C0392B','#3498db','#f39c12','#2ecc71','#9b59b6','#95a5a6'];
    new Chart(document.getElementById('avDeviceChart').getContext('2d'), {
        type: 'doughnut',
        data: {labels: {$jDevLabels}, datasets: [{data: {$jDevData}, backgroundColor: devColors}]},
        options: {responsive: true, maintainAspectRatio: false, legend: {position: 'bottom', labels: {fontSize: 10}}}
    });

    // Browser doughnut
    new Chart(document.getElementById('avBrowserChart').getContext('2d'), {
        type: 'doughnut',
        data: {labels: {$jBrLabels}, datasets: [{data: {$jBrData}, backgroundColor: ['#4285F4','#FF6D01','#EA4335','#0078D4','#663399','#95a5a6']}]},
        options: {responsive: true, maintainAspectRatio: false, legend: {position: 'bottom', labels: {fontSize: 10}}}
    });

    // OS doughnut
    new Chart(document.getElementById('avOsChart').getContext('2d'), {
        type: 'doughnut',
        data: {labels: {$jOsLabels}, datasets: [{data: {$jOsData}, backgroundColor: ['#0078D4','#555','#3ddc84','#f39c12','#e74c3c','#95a5a6']}]},
        options: {responsive: true, maintainAspectRatio: false, legend: {position: 'bottom', labels: {fontSize: 10}}}
    });

    // Hourly bar
    new Chart(document.getElementById('avHourlyChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: {$jHrLabels},
            datasets: [{
                label: 'Visits',
                data: {$jHrData},
                backgroundColor: 'rgba(192,57,43,0.7)',
                borderColor: '#C0392B',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {display: false},
            scales: {
                xAxes: [{ticks: {fontSize: 9}}],
                yAxes: [{ticks: {fontSize: 10, beginAtZero: true}}]
            }
        }
    });
});
</script>";

        return $html;
    }
}
