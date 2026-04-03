<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\User;
use App\Models\MovieView;
use App\Models\MovieDownload;
use App\Models\SeriesMovie;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Encore\Admin\Layout\Content;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        return $content
            ->title('Dashboard')
            ->description('System overview')
            ->body($this->buildDashboard());
    }

    protected function buildDashboard()
    {
        return Cache::remember('admin_dashboard_html', 300, function () {
        $activeMovies  = MovieModel::where('status', 'Active')->count();
        $moviesCount   = MovieModel::where('type', 'Movie')->count();
        $seriesCount   = MovieModel::where('type', 'Series')->count();
        $totalSeries   = SeriesMovie::count();
        $activeSeries  = SeriesMovie::where('is_active', 'Yes')->count();
        $errMovies     = MovieModel::whereNotNull('error_message')->count();

        // Users
        $totalUsers    = User::count();
        $todayUsers    = User::whereDate('created_at', Carbon::today())->count();
        $weekUsers     = User::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $monthUsers    = User::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $ugflixUsers   = User::where('app_type', 'ugflix')->count();
        $lugaflixUsers = User::where('app_type', 'lugaflix')->count();
        $munoUsers     = User::where('app_type', 'muno_app')->count();
        $webUsers      = User::where('app_type', 'web')->count();
        $androidUsers  = User::where('platform', 'android')->count();
        $iosUsers      = User::where('platform', 'ios')->count();
        $guestUsers    = User::where('is_guest', 'Yes')->count();

        // Subscriptions
        $activeSubs = DB::table('subscriptions')
            ->where('status', 'Active')
            ->where('end_date_time', '>=', Carbon::now())
            ->count();
        $expiredSubs = DB::table('subscriptions')->where('status', 'Expired')->count();
        $totalSubs   = DB::table('subscriptions')->count();
        $subRevenue  = DB::table('subscriptions')
            ->where('payment_status', 'Completed')
            ->sum('amount_paid');
        $totalWithdrawals = DB::table('subscription_transactions')
            ->where('status', 'completed')
            ->where('transaction_type', 'Withdrawal')
            ->sum('amount');
        $netRevenue = $subRevenue + $totalWithdrawals;

        // Platform balance breakdown
        $pesapalRate = 0.035;
        $platformRevenueRaw = DB::table('subscriptions')
            ->where('payment_status', 'Completed')
            ->selectRaw("COALESCE(app_type, 'unassigned') as plat, SUM(amount_paid) as total")
            ->groupBy('plat')
            ->pluck('total', 'plat');
        $platformWithdrawalsRaw = DB::table('subscription_transactions')
            ->where('status', 'Completed')
            ->where('transaction_type', 'Withdrawal')
            ->selectRaw("COALESCE(platform, 'unassigned') as plat, SUM(ABS(amount)) as total")
            ->groupBy('plat')
            ->pluck('total', 'plat');

        // Views & Downloads
        $totalViews    = MovieView::count();
        $todayViews    = MovieView::whereDate('created_at', Carbon::today())->count();
        $weekViews     = MovieView::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $totalWatchHrs = round(MovieView::sum('progress') / 3600, 1);
        $uniqueViewers = MovieView::distinct('user_id')->count('user_id');
        $totalDownloads = MovieDownload::count();

        // ── BATCH: Daily signups by platform (last 30 days) ──
        $thirtyDaysAgo = Carbon::today()->subDays(29)->format('Y-m-d');
        $dailySignupsRaw = DB::table('admin_users')
            ->select(DB::raw('DATE(created_at) as d'), 'app_type', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('d', 'app_type')
            ->get();

        // ── BATCH: Daily views by platform (last 30 days) ──
        $dailyViewsRaw = DB::table('movie_views')
            ->join('admin_users', 'admin_users.id', '=', 'movie_views.user_id')
            ->select(DB::raw('DATE(movie_views.created_at) as d'), 'admin_users.app_type', DB::raw('COUNT(*) as cnt'))
            ->where('movie_views.created_at', '>=', $thirtyDaysAgo)
            ->groupBy('d', 'admin_users.app_type')
            ->get();

        // ── BATCH: Daily downloads (last 30 days) ──
        $dailyDownloadsRaw = DB::table('movie_downloads')
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('d')
            ->get();
        $downloadsMap = [];
        foreach ($dailyDownloadsRaw as $r) {
            $downloadsMap[$r->d] = $r->cnt;
        }

        // ── BATCH: Daily revenue by platform (last 30 days) ──
        $dailyRevenueRaw = DB::table('subscription_transactions')
            ->join('admin_users', 'admin_users.id', '=', 'subscription_transactions.user_id')
            ->select(DB::raw('DATE(subscription_transactions.created_at) as d'), 'admin_users.app_type', DB::raw('SUM(subscription_transactions.amount) as total'))
            ->where('subscription_transactions.status', 'completed')
            ->where('subscription_transactions.transaction_type', '!=', 'Withdrawal')
            ->where('subscription_transactions.created_at', '>=', $thirtyDaysAgo)
            ->groupBy('d', 'admin_users.app_type')
            ->get();

        // Build lookup maps from batch results
        $signupsMap = [];
        foreach ($dailySignupsRaw as $r) {
            $signupsMap[$r->d][$r->app_type ?? 'other'] = $r->cnt;
        }
        $viewsMap = [];
        foreach ($dailyViewsRaw as $r) {
            $viewsMap[$r->d][$r->app_type ?? 'other'] = $r->cnt;
        }
        $revenueMap = [];
        foreach ($dailyRevenueRaw as $r) {
            $revenueMap[$r->d][$r->app_type ?? 'other'] = (float) $r->total;
        }

        // Build daily arrays for charts
        $labels30 = [];
        $signupsMuno = []; $signupsLugaflix = []; $signupsUgflix = []; $signupsWeb = []; $signupsTotal = [];
        $viewsMuno = []; $viewsLugaflix = []; $viewsUgflix = []; $viewsWeb = []; $viewsTotal = [];
        $downloadsDly = [];
        $revMuno = []; $revLugaflix = []; $revUgflix = []; $revWeb = []; $revTotal = [];

        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $dk = $d->format('Y-m-d');
            $labels30[] = $d->format('d M');

            $signupsMuno[] = $signupsMap[$dk]['muno_app'] ?? 0;
            $signupsLugaflix[] = $signupsMap[$dk]['lugaflix'] ?? 0;
            $signupsUgflix[] = $signupsMap[$dk]['ugflix'] ?? 0;
            $signupsWeb[] = $signupsMap[$dk]['web'] ?? 0;
            $signupsTotal[] = isset($signupsMap[$dk]) ? array_sum($signupsMap[$dk]) : 0;

            $viewsMuno[] = $viewsMap[$dk]['muno_app'] ?? 0;
            $viewsLugaflix[] = $viewsMap[$dk]['lugaflix'] ?? 0;
            $viewsUgflix[] = $viewsMap[$dk]['ugflix'] ?? 0;
            $viewsWeb[] = $viewsMap[$dk]['web'] ?? 0;
            $viewsTotal[] = isset($viewsMap[$dk]) ? array_sum($viewsMap[$dk]) : 0;

            $downloadsDly[] = $downloadsMap[$dk] ?? 0;

            $revMuno[] = $revenueMap[$dk]['muno_app'] ?? 0;
            $revLugaflix[] = $revenueMap[$dk]['lugaflix'] ?? 0;
            $revUgflix[] = $revenueMap[$dk]['ugflix'] ?? 0;
            $revWeb[] = $revenueMap[$dk]['web'] ?? 0;
            $revTotal[] = isset($revenueMap[$dk]) ? array_sum($revenueMap[$dk]) : 0;
        }

        // Platform view totals (for comparison table)
        $munoViews    = DB::table('movie_views')->join('admin_users', 'admin_users.id', '=', 'movie_views.user_id')->where('admin_users.app_type', 'muno_app')->count();
        $lugaflixViews = DB::table('movie_views')->join('admin_users', 'admin_users.id', '=', 'movie_views.user_id')->where('admin_users.app_type', 'lugaflix')->count();
        $ugflixViews  = DB::table('movie_views')->join('admin_users', 'admin_users.id', '=', 'movie_views.user_id')->where('admin_users.app_type', 'ugflix')->count();
        $webViews     = DB::table('movie_views')->join('admin_users', 'admin_users.id', '=', 'movie_views.user_id')->where('admin_users.app_type', 'web')->count();

        // Last 30 days subscriptions — daily aggregates (completed payments only, excludes withdrawals)
        $dailySubsRaw = DB::table('subscription_transactions')
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as cnt'), DB::raw('SUM(amount) as total'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('status', 'completed')
            ->where('transaction_type', '!=', 'Withdrawal')
            ->groupBy('d')
            ->orderBy('d', 'desc')
            ->get();
        $subsTotal30 = $dailySubsRaw->sum('cnt');
        $subsAmount30 = $dailySubsRaw->sum('total');

        // User distribution percentages
        $uT = max($totalUsers, 1);
        $ugPct = round(($ugflixUsers / $uT) * 100);
        $lgPct = round(($lugaflixUsers / $uT) * 100);
        $mnPct = round(($munoUsers / $uT) * 100);
        $wbPct = round(($webUsers / $uT) * 100);

        // ═══════════ HTML ═══════════
        $html = '<style>
.db-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:12px}
.db-row{display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap}
.db-card{flex:1;min-width:105px;background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-left:3px solid #ddd;position:relative;transition:box-shadow .15s}
.db-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.12)}
.db-card a{color:inherit;text-decoration:none;display:block}
.db-card .db-val{font-size:18px;font-weight:700;line-height:1.1}
.db-card .db-lbl{font-size:9px;color:#888;margin-top:2px;text-transform:uppercase;letter-spacing:.3px}
.db-card .db-ico{position:absolute;right:8px;top:8px;font-size:14px;opacity:.3}
.db-box{background:#fff;border-radius:6px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.db-box-title{font-size:10px;font-weight:700;color:#555;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px}
.db-tbl{width:100%;border-collapse:collapse;font-size:11px}
.db-tbl th{text-align:left;padding:4px 6px;border-bottom:2px solid #eee;font-weight:700;color:#555;font-size:10px;text-transform:uppercase}
.db-tbl td{padding:4px 6px;border-bottom:1px solid #f5f5f5}
.db-badge{display:inline-block;padding:1px 6px;border-radius:3px;font-size:9px;font-weight:600;color:#fff}
.db-section{margin-bottom:4px;font-size:11px;font-weight:700;color:#333;border-bottom:2px solid #007bff;padding-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
</style>';

        $html .= '<div class="db-wrap">';

        // ── CURRENT TIME (EAT) ──
        $currentTime = Carbon::now('Africa/Nairobi');
        $html .= '<div style="display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;border-radius:8px;padding:12px 18px;margin-bottom:10px;box-shadow:0 2px 8px rgba(0,0,0,.15)">';
        $html .= '<div style="display:flex;align-items:center;gap:10px">';
        $html .= '<i class="fa fa-clock-o" style="font-size:22px;opacity:.8"></i>';
        $html .= '<div>';
        $html .= '<div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.7">East Africa Time (GMT+3)</div>';
        $html .= '<div id="eat-clock" style="font-size:22px;font-weight:700;letter-spacing:1px;font-variant-numeric:tabular-nums">' . $currentTime->format('h:i:s A') . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div style="text-align:right">';
        $html .= '<div style="font-size:13px;font-weight:600">' . $currentTime->format('l') . '</div>';
        $html .= '<div style="font-size:11px;opacity:.7">' . $currentTime->format('d F Y') . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<script>
(function(){
  function pad(n){return n<10?"0"+n:n;}
  function tick(){
    var el=document.getElementById("eat-clock");
    if(!el)return;
    var d=new Date();
    var utc=d.getTime()+d.getTimezoneOffset()*60000;
    var eat=new Date(utc+3*3600000);
    var h=eat.getHours(),m=eat.getMinutes(),s=eat.getSeconds();
    var ap=h>=12?"PM":"AM";
    h=h%12;if(h===0)h=12;
    el.textContent=pad(h)+":"+pad(m)+":"+pad(s)+" "+ap;
  }
  setInterval(tick,1000);
}());
</script>';

        // ── SECTION: Platform Balance Summary ──
        $html .= '<div class="db-section">Platform Balance Summary</div>';
        $html .= '<div class="db-row">';
        $html .= '<div class="db-box" style="flex:1;min-width:100%;padding:0;overflow:hidden">';
        $html .= '<style>
.hpb-table{width:100%;border-collapse:separate;border-spacing:0;font-size:12px}
.hpb-table th{background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;padding:9px 12px;font-size:9px;text-transform:uppercase;letter-spacing:.7px;font-weight:600;white-space:nowrap}
.hpb-table th:first-child{border-radius:6px 0 0 0}
.hpb-table th:last-child{border-radius:0 6px 0 0}
.hpb-table td{padding:8px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle;transition:background .15s}
.hpb-table tr.hpb-row:hover td{background:#f0f5ff}
.hpb-plat{display:flex;align-items:center;gap:6px;font-weight:600}
.hpb-plat .hpb-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;box-shadow:0 0 0 2px rgba(0,0,0,.06)}
.hpb-amt{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.hpb-fee{color:#dc3545;font-size:11px}
.hpb-bar{height:4px;border-radius:2px;margin-top:3px;transition:width .8s cubic-bezier(.4,0,.2,1)}
.hpb-badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;color:#fff;letter-spacing:.2px}
.hpb-total td{background:linear-gradient(135deg,#f8f9fa,#eef1f5)!important;border-top:2px solid #333;font-weight:700;padding:10px 12px}
.hpb-total td:first-child{border-radius:0 0 0 6px}
.hpb-total td:last-child{border-radius:0 0 6px 0}
@media(max-width:768px){.hpb-table{font-size:10px}.hpb-table th,.hpb-table td{padding:5px 6px}.hpb-hide-sm{display:none}}
</style>';
        $html .= '<table class="hpb-table">';
        $html .= '<thead><tr><th style="text-align:left">Platform</th><th style="text-align:right">Revenue</th><th style="text-align:right" class="hpb-hide-sm">Pesapal (3.5%)</th><th style="text-align:right">After Fees</th><th style="text-align:right">Withdrawn</th><th style="text-align:right">Balance</th></tr></thead>';
        $html .= '<tbody>';

        $platDefs = ['lugaflix' => ['LugaFlix', '#3498db', 'fa-play-circle-o'], 'muno_app' => ['Muno App', '#e74c3c', 'fa-fire'], 'ugflix' => ['UgFlix', '#2ecc71', 'fa-bolt'], 'web' => ['Web (Katogo)', '#9b59b6', 'fa-globe']];
        $gRev = 0; $gPesa = 0; $gWith = 0; $gBal = 0;
        $allPlatData = [];

        foreach ($platDefs as $pk => [$pLabel, $pColor, $pIcon]) {
            $pRev = (float) ($platformRevenueRaw[$pk] ?? 0);
            $pFee = round($pRev * $pesapalRate, 2);
            $pAfter = $pRev - $pFee;
            $pWith = (float) ($platformWithdrawalsRaw[$pk] ?? 0);
            $pBal = $pAfter - $pWith;
            $gRev += $pRev; $gPesa += $pFee; $gWith += $pWith; $gBal += $pBal;
            $allPlatData[] = ['label' => $pLabel, 'color' => $pColor, 'icon' => $pIcon, 'rev' => $pRev, 'fee' => $pFee, 'after' => $pAfter, 'with' => $pWith, 'bal' => $pBal, 'key' => $pk];
        }

        // Unassigned
        $uRev = (float) ($platformRevenueRaw['unassigned'] ?? 0);
        $uWith = (float) ($platformWithdrawalsRaw['unassigned'] ?? 0);
        if ($uRev > 0 || $uWith > 0) {
            $uFee = round($uRev * $pesapalRate, 2);
            $uAfter = $uRev - $uFee;
            $uBal = $uAfter - $uWith;
            $gRev += $uRev; $gPesa += $uFee; $gWith += $uWith; $gBal += $uBal;
            $allPlatData[] = ['label' => 'Unassigned', 'color' => '#95a5a6', 'icon' => 'fa-question-circle', 'rev' => $uRev, 'fee' => $uFee, 'after' => $uAfter, 'with' => $uWith, 'bal' => $uBal, 'key' => 'unassigned'];
        }

        $revValues = array_column($allPlatData, 'rev');
        $maxRev = !empty($revValues) ? max(max($revValues), 1) : 1;

        foreach ($allPlatData as $p) {
            $bClr = $p['bal'] >= 0 ? '#28a745' : '#dc3545';
            $barPct = $maxRev > 0 ? round(($p['rev'] / $maxRev) * 100) : 0;
            $html .= '<tr class="hpb-row">';
            $html .= "<td><div class='hpb-plat'><span class='hpb-dot' style='background:{$p['color']}'></span><i class='fa {$p['icon']}' style='color:{$p['color']};font-size:11px;opacity:.6'></i> {$p['label']}</div><div style='width:100%;height:4px;background:#eee;border-radius:2px;margin-top:3px;overflow:hidden'><div class='hpb-bar' style='width:{$barPct}%;background:{$p['color']}'></div></div></td>";
            $html .= "<td class='hpb-amt'><b>UGX " . number_format($p['rev']) . "</b></td>";
            $html .= "<td class='hpb-amt hpb-fee hpb-hide-sm'>- UGX " . number_format($p['fee']) . "</td>";
            $html .= "<td class='hpb-amt'>UGX " . number_format($p['after']) . "</td>";
            $html .= "<td class='hpb-amt hpb-fee'>- UGX " . number_format($p['with']) . "</td>";
            $html .= "<td class='hpb-amt'><span class='hpb-badge' style='background:{$bClr}'>UGX " . number_format($p['bal']) . "</span></td>";
            $html .= '</tr>';
        }

        // Totals
        $tClr = $gBal >= 0 ? '#28a745' : '#dc3545';
        $html .= '<tr class="hpb-total">';
        $html .= '<td><b style="font-size:11px">TOTAL</b></td>';
        $html .= "<td class='hpb-amt'><b>UGX " . number_format($gRev) . "</b></td>";
        $html .= "<td class='hpb-amt hpb-fee hpb-hide-sm'><b>- UGX " . number_format($gPesa) . "</b></td>";
        $html .= "<td class='hpb-amt'><b>UGX " . number_format($gRev - $gPesa) . "</b></td>";
        $html .= "<td class='hpb-amt hpb-fee'><b>- UGX " . number_format($gWith) . "</b></td>";
        $html .= "<td class='hpb-amt'><span class='hpb-badge' style='background:{$tClr};font-size:11px;padding:3px 10px'>UGX " . number_format($gBal) . "</span></td>";
        $html .= '</tr>';
        $html .= '</tbody></table></div>';
        $html .= '</div>';

        // ── SECTION: Content Overview ──
        $html .= '<div class="db-section">Content Overview</div>';
        $html .= '<div class="db-row">';
        $contentCards = [
            ['Total Movies', number_format($totalMovies), '#007bff', 'fa-film', admin_url('movies-movies')],
            ['Active', number_format($activeMovies), '#28a745', 'fa-check-circle', admin_url('movies-movies?status=Active')],
            ['Movies Type', number_format($moviesCount), '#17a2b8', 'fa-video-camera', '#'],
            ['Series Type', number_format($seriesCount), '#6f42c1', 'fa-list', '#'],
            ['Series Episodes', number_format($totalSeries), '#fd7e14', 'fa-tv', admin_url('series-movies')],
            ['Active Episodes', number_format($activeSeries), '#28a745', 'fa-play-circle', '#'],
            ['Errors', number_format($errMovies), '#dc3545', 'fa-exclamation-triangle', '#'],
        ];
        foreach ($contentCards as [$l, $v, $c, $i, $lnk]) {
            $html .= "<div class='db-card' style='border-left-color:{$c}'><a href='{$lnk}'><div class='db-val' style='color:{$c}'>{$v}</div><div class='db-lbl'>{$l}</div><i class='fa {$i} db-ico'></i></a></div>";
        }
        $html .= '</div>';

        // ── SECTION: Users & Subscriptions ──
        $html .= '<div class="db-section">Users & Subscriptions</div>';
        $html .= '<div class="db-row">';
        $userCards = [
            ['Total Users', number_format($totalUsers), '#007bff', 'fa-users', admin_url('users')],
            ['Today', number_format($todayUsers), '#28a745', 'fa-user-plus', '#'],
            ['This Week', number_format($weekUsers), '#6f42c1', 'fa-calendar', '#'],
            ['This Month', number_format($monthUsers), '#17a2b8', 'fa-calendar-o', '#'],
            ['Ugflix', number_format($ugflixUsers), '#2ecc71', 'fa-play-circle', '#'],
            ['Lugaflix', number_format($lugaflixUsers), '#3498db', 'fa-play-circle-o', '#'],
            ['Muno', number_format($munoUsers), '#e74c3c', 'fa-fire', '#'],
            ['Web', number_format($webUsers), '#9b59b6', 'fa-globe', '#'],
            ['Guests', number_format($guestUsers), '#95a5a6', 'fa-user-secret', '#'],
        ];
        foreach ($userCards as [$l, $v, $c, $i, $lnk]) {
            $html .= "<div class='db-card' style='border-left-color:{$c}'><a href='{$lnk}'><div class='db-val' style='color:{$c}'>{$v}</div><div class='db-lbl'>{$l}</div><i class='fa {$i} db-ico'></i></a></div>";
        }
        $html .= '</div>';

        // Engagement row
        $html .= '<div class="db-row">';
        $engCards = [
            ['Total Views', number_format($totalViews), '#007bff', 'fa-eye', admin_url('movie-views')],
            ['Today Views', number_format($todayViews), '#28a745', 'fa-calendar-check-o', '#'],
            ['Week Views', number_format($weekViews), '#6f42c1', 'fa-calendar', '#'],
            ['Downloads', number_format($totalDownloads), '#17a2b8', 'fa-download', admin_url('movie-downloads')],
            ['Watch Hours', number_format($totalWatchHrs) . 'h', '#e83e8c', 'fa-clock-o', '#'],
            ['Active Subs', number_format($activeSubs), '#f39c12', 'fa-star', '#'],
            ['Revenue', 'UGX ' . number_format($netRevenue), '#f39c12', 'fa-money', '#'],
            ['Android / iOS', number_format($androidUsers) . ' / ' . number_format($iosUsers), '#28a745', 'fa-mobile', '#'],
        ];
        foreach ($engCards as [$l, $v, $c, $i, $lnk]) {
            $html .= "<div class='db-card' style='border-left-color:{$c}'><a href='{$lnk}'><div class='db-val' style='color:{$c}'>{$v}</div><div class='db-lbl'>{$l}</div><i class='fa {$i} db-ico'></i></a></div>";
        }
        $html .= '</div>';

        // ── SECTION: Charts & Analytics (uses Chart.js 2.x bundled with Laravel Admin) ──
        $html .= '<div class="db-section">Charts & Analytics</div>';

        // Prepare JSON data
        $jLabels = json_encode($labels30);
        $jSignupsMuno = json_encode($signupsMuno);
        $jSignupsLugaflix = json_encode($signupsLugaflix);
        $jSignupsUgflix = json_encode($signupsUgflix);
        $jSignupsWeb = json_encode($signupsWeb);
        $jSignupsTotal = json_encode($signupsTotal);
        $jViewsMuno = json_encode($viewsMuno);
        $jViewsLugaflix = json_encode($viewsLugaflix);
        $jViewsUgflix = json_encode($viewsUgflix);
        $jViewsWeb = json_encode($viewsWeb);
        $jViewsTotal = json_encode($viewsTotal);
        $jDownloads = json_encode($downloadsDly);
        $jRevMuno = json_encode($revMuno);
        $jRevLugaflix = json_encode($revLugaflix);
        $jRevUgflix = json_encode($revUgflix);
        $jRevWeb = json_encode($revWeb);
        $jRevTotal = json_encode($revTotal);

        // ── CHART ROW 1: User Registrations + Views & Downloads ──
        $html .= '<div class="db-row">';

        $html .= '<div class="db-box" style="flex:1;min-width:460px">';
        $html .= '<div class="db-box-title"><i class="fa fa-user-plus" style="color:#28a745"></i> User Registrations — Last 30 Days</div>';
        $html .= '<div style="position:relative;height:320px"><canvas id="signupsChart"></canvas></div>';
        $html .= '</div>';

        $html .= '<div class="db-box" style="flex:1;min-width:460px">';
        $html .= '<div class="db-box-title"><i class="fa fa-eye" style="color:#007bff"></i> Views & Downloads — Last 30 Days</div>';
        $html .= '<div style="position:relative;height:320px"><canvas id="viewsDownloadsChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>';

        // ── CHART ROW 2: Revenue + Subscriptions List ──
        $html .= '<div class="db-row">';

        $html .= '<div class="db-box" style="flex:1;min-width:460px">';
        $html .= '<div class="db-box-title"><i class="fa fa-money" style="color:#f39c12"></i> Subscription Revenue — Last 30 Days (UGX)</div>';
        $html .= '<div style="position:relative;height:320px"><canvas id="revenueChart"></canvas></div>';
        $html .= '</div>';

        // Daily subscriptions summary (last 30 days)
        $html .= '<div class="db-box" style="flex:1;min-width:460px;max-height:380px;overflow-y:auto">';
        $html .= '<div class="db-box-title"><i class="fa fa-list" style="color:#6f42c1"></i> Subscriptions — Last 30 Days (Total: ' . number_format($subsTotal30) . ' | UGX ' . number_format($subsAmount30) . ')</div>';
        $html .= '<table class="db-tbl">';
        $html .= '<tr><th>Date</th><th style="text-align:right">Count</th><th style="text-align:right">Amount (UGX)</th></tr>';
        foreach ($dailySubsRaw as $row) {
            $dLabel = Carbon::parse($row->d)->format('D, d M Y');
            $html .= '<tr>';
            $html .= '<td>' . $dLabel . '</td>';
            $html .= '<td style="text-align:right"><b>' . number_format($row->cnt) . '</b></td>';
            $html .= '<td style="text-align:right"><b>' . number_format($row->total ?? 0) . '</b></td>';
            $html .= '</tr>';
        }
        if ($dailySubsRaw->isEmpty()) {
            $html .= '<tr><td colspan="3" style="text-align:center;color:#999;padding:20px">No subscriptions in the last 30 days</td></tr>';
        }
        // Totals footer
        $html .= '<tr style="border-top:2px solid #333;font-weight:700;background:#f8f9fa">';
        $html .= '<td>TOTAL</td>';
        $html .= '<td style="text-align:right">' . number_format($subsTotal30) . '</td>';
        $html .= '<td style="text-align:right">UGX ' . number_format($subsAmount30) . '</td>';
        $html .= '</tr>';
        $html .= '</table></div>';

        $html .= '</div>';

        // ── CHART ROW 3: Doughnut Charts ──
        $html .= '<div class="db-row">';

        // Users by Platform doughnut
        $html .= '<div class="db-box" style="flex:1;min-width:200px;text-align:center">';
        $html .= '<div class="db-box-title"><i class="fa fa-pie-chart" style="color:#e74c3c"></i> Users by Platform</div>';
        $html .= '<div style="position:relative;height:220px"><canvas id="usersPieChart"></canvas></div>';
        $html .= '</div>';

        // Device OS doughnut
        $dT = max($androidUsers + $iosUsers, 1);
        $anPct = round(($androidUsers / $dT) * 100);
        $html .= '<div class="db-box" style="flex:1;min-width:200px;text-align:center">';
        $html .= '<div class="db-box-title"><i class="fa fa-mobile" style="color:#555"></i> Device OS</div>';
        $html .= '<div style="position:relative;height:220px"><canvas id="devicePieChart"></canvas></div>';
        $html .= '</div>';

        // Subscription Status doughnut
        $pendingSubs = DB::table('subscriptions')->where('status', 'Pending')->count();
        $html .= '<div class="db-box" style="flex:1;min-width:200px;text-align:center">';
        $html .= '<div class="db-box-title"><i class="fa fa-credit-card" style="color:#f39c12"></i> Subscription Status</div>';
        $html .= '<div style="position:relative;height:220px"><canvas id="subsPieChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>';

        // ── ROW 4: Platform Comparison + Subscription Totals + Quick Links ──
        $html .= '<div class="db-row">';

        // Platform comparison
        $html .= '<div class="db-box" style="flex:2;min-width:400px">';
        $html .= '<div class="db-box-title"><i class="fa fa-trophy" style="color:#f39c12"></i> Platform Comparison</div>';
        $html .= '<table class="db-tbl">';
        $html .= '<tr><th>Platform</th><th>Users</th><th>Total Views</th><th style="text-align:center">Avg</th></tr>';
        $platformCompare = [
            ['Muno', $munoUsers, $munoViews, '#e74c3c', 'fa-fire'],
            ['LugaFlix', $lugaflixUsers, $lugaflixViews, '#3498db', 'fa-star'],
            ['UG Flix', $ugflixUsers, $ugflixViews, '#2ecc71', 'fa-bolt'],
            ['Web', $webUsers, $webViews, '#9b59b6', 'fa-globe'],
        ];
        foreach ($platformCompare as [$pName, $pUsers, $pViews, $pColor, $pIcon]) {
            $avgViews = $pUsers > 0 ? round($pViews / $pUsers, 1) : 0;
            $html .= "<tr><td><i class='fa {$pIcon}' style='color:{$pColor};margin-right:4px'></i><b style='color:{$pColor}'>{$pName}</b></td>";
            $html .= "<td><b>" . number_format($pUsers) . "</b></td>";
            $html .= "<td><b>" . number_format($pViews) . "</b></td>";
            $html .= "<td style='text-align:center'><span class='db-badge' style='background:{$pColor}'>{$avgViews} views/user</span></td></tr>";
        }
        $html .= '</table></div>';

        // Subscription totals
        $html .= '<div class="db-box" style="flex:1;min-width:240px">';
        $html .= '<div class="db-box-title"><i class="fa fa-credit-card" style="color:#f39c12"></i> Subscription Totals</div>';
        $html .= '<table class="db-tbl">';
        $html .= '<tr><th>Metric</th><th>Value</th></tr>';
        $html .= '<tr><td>Total Subscriptions</td><td><b>' . number_format($totalSubs) . '</b></td></tr>';
        $html .= '<tr><td>Active</td><td><span class="db-badge" style="background:#28a745">' . number_format($activeSubs) . '</span></td></tr>';
        $html .= '<tr><td>Expired</td><td><span class="db-badge" style="background:#dc3545">' . number_format($expiredSubs) . '</span></td></tr>';
        $html .= '<tr><td>Last 30 Days</td><td><b>' . number_format($subsTotal30) . '</b></td></tr>';
        $html .= '<tr><td>Gross Revenue</td><td><b style="color:#28a745">UGX ' . number_format($subRevenue) . '</b></td></tr>';
        $html .= '<tr><td>Withdrawals</td><td><b style="color:#dc3545">UGX ' . number_format(abs($totalWithdrawals)) . '</b></td></tr>';
        $html .= '<tr><td>Net Balance</td><td><b style="color:#f39c12">UGX ' . number_format($netRevenue) . '</b></td></tr>';
        $html .= '</table></div>';

        // Quick Links
        $html .= '<div class="db-box" style="flex:1;min-width:200px">';
        $html .= '<div class="db-box-title">Quick Links</div>';
        $links = [
            ['Movies', admin_url('movies-movies'), '#007bff', 'fa-film'],
            ['Series', admin_url('series-movies'), '#6f42c1', 'fa-tv'],
            ['Movie Views', admin_url('movie-views'), '#17a2b8', 'fa-eye'],
            ['Users', admin_url('users'), '#28a745', 'fa-users'],
            ['Subscriptions', admin_url('subscriptions'), '#f39c12', 'fa-credit-card'],
            ['Downloads', admin_url('movie-downloads'), '#17a2b8', 'fa-download'],
            ['Streaming', admin_url('streaming-stations'), '#e74c3c', 'fa-podcast'],
            ['Blog Posts', admin_url('blog-posts'), '#6f42c1', 'fa-newspaper-o'],
        ];
        foreach ($links as [$label, $url, $clr, $ico]) {
            $html .= "<div style='padding:4px 0;border-bottom:1px solid #f5f5f5'><a href='{$url}' style='color:{$clr};text-decoration:none;font-size:11px'><i class='fa {$ico}' style='width:16px'></i> {$label}</a></div>";
        }
        $html .= '</div>';

        $html .= '</div>';

        // ════════ Chart.js 2.x Scripts (bundled with Laravel Admin) ════════
        $otherUsers = max($totalUsers - $ugflixUsers - $lugaflixUsers - $munoUsers - $webUsers, 0);
        $html .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    var labels = ' . $jLabels . ';
    var gc = "rgba(0,0,0,0.04)";

    var defaultOpts = {
        responsive: true,
        maintainAspectRatio: false,
        legend: { position: "top", labels: { usePointStyle: true, padding: 12, fontSize: 11 } },
        tooltips: { mode: "index", intersect: false, backgroundColor: "rgba(0,0,0,0.8)", cornerRadius: 6,
            xPadding: 10, yPadding: 10 },
        hover: { mode: "nearest", intersect: false },
        scales: {
            xAxes: [{ gridLines: { color: gc, drawBorder: false }, ticks: { fontSize: 9, maxRotation: 45 } }],
            yAxes: [{ gridLines: { color: gc, drawBorder: false }, ticks: { fontSize: 10, beginAtZero: true } }]
        }
    };

    function makeLine(label, data, color, width, dash) {
        return {
            label: label, data: data,
            borderColor: color,
            backgroundColor: "transparent",
            pointBackgroundColor: color,
            pointRadius: 2, pointHoverRadius: 5,
            borderWidth: width || 2, lineTension: 0.35, fill: false,
            borderDash: dash || []
        };
    }
    function cloneOpts(o) { return JSON.parse(JSON.stringify(o)); }

    // ─── 1. User Registrations (30 days) — per platform + total ───
    new Chart(document.getElementById("signupsChart"), {
        type: "line",
        data: {
            labels: labels,
            datasets: [
                makeLine("Total", ' . $jSignupsTotal . ', "rgba(0,0,0,0.7)", 3, []),
                makeLine("Muno", ' . $jSignupsMuno . ', "rgb(231,76,60)", 2, []),
                makeLine("LugaFlix", ' . $jSignupsLugaflix . ', "rgb(52,152,219)", 2, []),
                makeLine("UG Flix", ' . $jSignupsUgflix . ', "rgb(46,204,113)", 2, []),
                makeLine("Web", ' . $jSignupsWeb . ', "rgb(155,89,182)", 2, [])
            ]
        },
        options: cloneOpts(defaultOpts)
    });

    // ─── 2. Views & Downloads (30 days) ───
    new Chart(document.getElementById("viewsDownloadsChart"), {
        type: "line",
        data: {
            labels: labels,
            datasets: [
                makeLine("Total Views", ' . $jViewsTotal . ', "rgba(0,0,0,0.7)", 3, []),
                makeLine("Muno Views", ' . $jViewsMuno . ', "rgb(231,76,60)", 2, []),
                makeLine("LugaFlix Views", ' . $jViewsLugaflix . ', "rgb(52,152,219)", 2, []),
                makeLine("UG Flix Views", ' . $jViewsUgflix . ', "rgb(46,204,113)", 2, []),
                makeLine("Web Views", ' . $jViewsWeb . ', "rgb(155,89,182)", 2, []),
                makeLine("Downloads", ' . $jDownloads . ', "rgb(243,156,18)", 2, [5,3])
            ]
        },
        options: cloneOpts(defaultOpts)
    });

    // ─── 3. Revenue (30 days) — per platform + total ───
    var revOpts = cloneOpts(defaultOpts);
    revOpts.tooltips.callbacks = { label: function(item, data) {
        var lbl = data.datasets[item.datasetIndex].label || "";
        return lbl + ": UGX " + (item.yLabel || 0).toLocaleString();
    }};
    revOpts.scales.yAxes[0].ticks.callback = function(v) { return "UGX " + v.toLocaleString(); };
    new Chart(document.getElementById("revenueChart"), {
        type: "line",
        data: {
            labels: labels,
            datasets: [
                makeLine("Total", ' . $jRevTotal . ', "rgba(243,156,18,1)", 3, []),
                makeLine("Muno", ' . $jRevMuno . ', "rgb(231,76,60)", 2, []),
                makeLine("LugaFlix", ' . $jRevLugaflix . ', "rgb(52,152,219)", 2, []),
                makeLine("UG Flix", ' . $jRevUgflix . ', "rgb(46,204,113)", 2, []),
                makeLine("Web", ' . $jRevWeb . ', "rgb(155,89,182)", 2, [])
            ]
        },
        options: revOpts
    });

    // Doughnut defaults (Chart.js 2.x)
    var donutOpts = { responsive: true, maintainAspectRatio: false, cutoutPercentage: 55,
        legend: { position: "bottom", labels: { usePointStyle: true, padding: 8, fontSize: 10 } } };

    // ─── 4. Users by Platform Doughnut ───
    new Chart(document.getElementById("usersPieChart"), {
        type: "doughnut",
        data: {
            labels: ["Muno (' . $mnPct . '%)", "LugaFlix (' . $lgPct . '%)", "UG Flix (' . $ugPct . '%)", "Web (' . $wbPct . '%)", "Other"],
            datasets: [{ data: [' . $munoUsers . ', ' . $lugaflixUsers . ', ' . $ugflixUsers . ', ' . $webUsers . ', ' . $otherUsers . '],
                backgroundColor: ["rgba(231,76,60,0.8)", "rgba(52,152,219,0.8)", "rgba(46,204,113,0.8)", "rgba(155,89,182,0.8)", "rgba(189,195,199,0.6)"],
                borderWidth: 2, borderColor: "#fff" }]
        },
        options: JSON.parse(JSON.stringify(donutOpts))
    });

    // ─── 5. Device OS Doughnut ───
    new Chart(document.getElementById("devicePieChart"), {
        type: "doughnut",
        data: {
            labels: ["Android (' . $anPct . '%)", "iOS (' . (100 - $anPct) . '%)"],
            datasets: [{ data: [' . $androidUsers . ', ' . $iosUsers . '],
                backgroundColor: ["rgba(40,167,69,0.8)", "rgba(85,85,85,0.8)"],
                borderWidth: 2, borderColor: "#fff" }]
        },
        options: JSON.parse(JSON.stringify(donutOpts))
    });

    // ─── 6. Subscription Status Doughnut ───
    new Chart(document.getElementById("subsPieChart"), {
        type: "doughnut",
        data: {
            labels: ["Active", "Expired", "Pending"],
            datasets: [{ data: [' . $activeSubs . ', ' . $expiredSubs . ', ' . $pendingSubs . '],
                backgroundColor: ["rgba(40,167,69,0.8)", "rgba(220,53,69,0.8)", "rgba(255,193,7,0.8)"],
                borderWidth: 2, borderColor: "#fff" }]
        },
        options: JSON.parse(JSON.stringify(donutOpts))
    });
});
</script>';

        $html .= '</div>'; // db-wrap

            return $html;
        });
    }
}
