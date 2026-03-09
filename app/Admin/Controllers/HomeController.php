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
        // ═══════════ DATA (efficient batch queries) ═══════════
        $totalMovies   = MovieModel::count();
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
        $subRevenue  = DB::table('subscription_transactions')
            ->where('status', 'completed')->sum('amount');

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
        $signupsMuno = []; $signupsLugaflix = []; $signupsUgflix = []; $signupsTotal = [];
        $viewsMuno = []; $viewsLugaflix = []; $viewsUgflix = []; $viewsTotal = [];
        $downloadsDly = [];
        $revMuno = []; $revLugaflix = []; $revUgflix = []; $revTotal = [];

        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $dk = $d->format('Y-m-d');
            $labels30[] = $d->format('d M');

            $signupsMuno[] = $signupsMap[$dk]['muno_app'] ?? 0;
            $signupsLugaflix[] = $signupsMap[$dk]['lugaflix'] ?? 0;
            $signupsUgflix[] = $signupsMap[$dk]['ugflix'] ?? 0;
            $signupsTotal[] = isset($signupsMap[$dk]) ? array_sum($signupsMap[$dk]) : 0;

            $viewsMuno[] = $viewsMap[$dk]['muno_app'] ?? 0;
            $viewsLugaflix[] = $viewsMap[$dk]['lugaflix'] ?? 0;
            $viewsUgflix[] = $viewsMap[$dk]['ugflix'] ?? 0;
            $viewsTotal[] = isset($viewsMap[$dk]) ? array_sum($viewsMap[$dk]) : 0;

            $downloadsDly[] = $downloadsMap[$dk] ?? 0;

            $revMuno[] = $revenueMap[$dk]['muno_app'] ?? 0;
            $revLugaflix[] = $revenueMap[$dk]['lugaflix'] ?? 0;
            $revUgflix[] = $revenueMap[$dk]['ugflix'] ?? 0;
            $revTotal[] = isset($revenueMap[$dk]) ? array_sum($revenueMap[$dk]) : 0;
        }

        // Platform view totals (for comparison table)
        $munoViews    = DB::table('movie_views')->join('admin_users', 'admin_users.id', '=', 'movie_views.user_id')->where('admin_users.app_type', 'muno_app')->count();
        $lugaflixViews = DB::table('movie_views')->join('admin_users', 'admin_users.id', '=', 'movie_views.user_id')->where('admin_users.app_type', 'lugaflix')->count();
        $ugflixViews  = DB::table('movie_views')->join('admin_users', 'admin_users.id', '=', 'movie_views.user_id')->where('admin_users.app_type', 'ugflix')->count();

        // Last 30 days subscriptions list
        $recentSubs = Subscription::with(['user', 'plan'])
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get();

        // User distribution percentages
        $uT = max($totalUsers, 1);
        $ugPct = round(($ugflixUsers / $uT) * 100);
        $lgPct = round(($lugaflixUsers / $uT) * 100);
        $mnPct = round(($munoUsers / $uT) * 100);

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
            ['Revenue', 'UGX ' . number_format($subRevenue), '#f39c12', 'fa-money', '#'],
            ['Android / iOS', number_format($androidUsers) . ' / ' . number_format($iosUsers), '#28a745', 'fa-mobile', '#'],
        ];
        foreach ($engCards as [$l, $v, $c, $i, $lnk]) {
            $html .= "<div class='db-card' style='border-left-color:{$c}'><a href='{$lnk}'><div class='db-val' style='color:{$c}'>{$v}</div><div class='db-lbl'>{$l}</div><i class='fa {$i} db-ico'></i></a></div>";
        }
        $html .= '</div>';

        // ── SECTION: Charts & Analytics ──
        $html .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
        $html .= '<div class="db-section">Charts & Analytics</div>';

        // Prepare JSON data
        $jLabels = json_encode($labels30);
        $jSignupsMuno = json_encode($signupsMuno);
        $jSignupsLugaflix = json_encode($signupsLugaflix);
        $jSignupsUgflix = json_encode($signupsUgflix);
        $jSignupsTotal = json_encode($signupsTotal);
        $jViewsMuno = json_encode($viewsMuno);
        $jViewsLugaflix = json_encode($viewsLugaflix);
        $jViewsUgflix = json_encode($viewsUgflix);
        $jViewsTotal = json_encode($viewsTotal);
        $jDownloads = json_encode($downloadsDly);
        $jRevMuno = json_encode($revMuno);
        $jRevLugaflix = json_encode($revLugaflix);
        $jRevUgflix = json_encode($revUgflix);
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

        // Recent 30 days subscriptions list
        $html .= '<div class="db-box" style="flex:1;min-width:460px;max-height:380px;overflow-y:auto">';
        $html .= '<div class="db-box-title"><i class="fa fa-list" style="color:#6f42c1"></i> Subscriptions — Last 30 Days (' . $recentSubs->count() . ')</div>';
        $html .= '<table class="db-tbl">';
        $html .= '<tr><th>#</th><th>User</th><th>Plan</th><th>Amount</th><th>Status</th><th>Platform</th><th>Date</th></tr>';
        foreach ($recentSubs as $idx => $sub) {
            $userName = $sub->user ? htmlspecialchars($sub->user->name) : 'N/A';
            $planName = $sub->plan ? htmlspecialchars($sub->plan->name) : 'N/A';
            $amount = 'UGX ' . number_format($sub->amount_paid ?? 0);
            $statusClr = match($sub->status) {
                'Active' => '#28a745', 'Expired' => '#dc3545', 'Pending' => '#ffc107', 'Cancelled' => '#6c757d', default => '#17a2b8',
            };
            $appType = $sub->user->app_type ?? 'N/A';
            $appClr = match($appType) {
                'muno_app' => '#e74c3c', 'lugaflix' => '#3498db', 'ugflix' => '#2ecc71', default => '#888',
            };
            $appLabel = match($appType) {
                'muno_app' => 'Muno', 'lugaflix' => 'LugaFlix', 'ugflix' => 'UG Flix', default => $appType,
            };
            $date = $sub->created_at ? $sub->created_at->format('d M, H:i') : '';
            $html .= "<tr>";
            $html .= "<td>" . ($idx + 1) . "</td>";
            $html .= "<td style='max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap'>{$userName}</td>";
            $html .= "<td>{$planName}</td>";
            $html .= "<td><b>{$amount}</b></td>";
            $html .= "<td><span class='db-badge' style='background:{$statusClr}'>{$sub->status}</span></td>";
            $html .= "<td><span class='db-badge' style='background:{$appClr}'>{$appLabel}</span></td>";
            $html .= "<td style='font-size:10px;color:#888'>{$date}</td>";
            $html .= "</tr>";
        }
        if ($recentSubs->isEmpty()) {
            $html .= '<tr><td colspan="7" style="text-align:center;color:#999;padding:20px">No subscriptions in the last 30 days</td></tr>';
        }
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
        $html .= '<tr><td>Last 30 Days</td><td><b>' . $recentSubs->count() . '</b></td></tr>';
        $html .= '<tr><td>Total Revenue</td><td><b style="color:#f39c12">UGX ' . number_format($subRevenue) . '</b></td></tr>';
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

        // ════════ Chart.js Scripts ════════
        $otherUsers = max($totalUsers - $ugflixUsers - $lugaflixUsers - $munoUsers, 0);
        $html .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    var labels = ' . $jLabels . ';
    var gridColor = "rgba(0,0,0,0.04)";

    var defaultOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: "top", labels: { usePointStyle: true, pointStyle: "circle", padding: 12, font: { size: 11 } } },
            tooltip: { mode: "index", intersect: false, backgroundColor: "rgba(0,0,0,0.8)", cornerRadius: 6, padding: 10 }
        },
        interaction: { mode: "nearest", axis: "x", intersect: false },
        scales: {
            x: { grid: { color: gridColor, drawBorder: false }, ticks: { font: { size: 9 }, maxRotation: 45 } },
            y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, ticks: { font: { size: 10 } } }
        }
    };

    function makeLine(label, data, color, width, dash) {
        return {
            label: label, data: data,
            borderColor: color,
            backgroundColor: color.replace("1)", "0.1)").replace("rgb(", "rgba("),
            pointBackgroundColor: color,
            pointRadius: 2, pointHoverRadius: 5,
            borderWidth: width || 2, tension: 0.35, fill: false,
            borderDash: dash || []
        };
    }

    // ─── 1. User Registrations (30 days) — per platform + total ───
    new Chart(document.getElementById("signupsChart"), {
        type: "line",
        data: {
            labels: labels,
            datasets: [
                makeLine("Total", ' . $jSignupsTotal . ', "rgba(0,0,0,0.7)", 3, []),
                makeLine("Muno", ' . $jSignupsMuno . ', "rgb(231,76,60)", 2, []),
                makeLine("LugaFlix", ' . $jSignupsLugaflix . ', "rgb(52,152,219)", 2, []),
                makeLine("UG Flix", ' . $jSignupsUgflix . ', "rgb(46,204,113)", 2, [])
            ]
        },
        options: defaultOpts
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
                makeLine("Downloads", ' . $jDownloads . ', "rgb(155,89,182)", 2, [5,3])
            ]
        },
        options: defaultOpts
    });

    // ─── 3. Revenue (30 days) — per platform + total ───
    new Chart(document.getElementById("revenueChart"), {
        type: "line",
        data: {
            labels: labels,
            datasets: [
                makeLine("Total", ' . $jRevTotal . ', "rgba(243,156,18,1)", 3, []),
                makeLine("Muno", ' . $jRevMuno . ', "rgb(231,76,60)", 2, []),
                makeLine("LugaFlix", ' . $jRevLugaflix . ', "rgb(52,152,219)", 2, []),
                makeLine("UG Flix", ' . $jRevUgflix . ', "rgb(46,204,113)", 2, [])
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: defaultOpts.plugins.legend,
                tooltip: {
                    mode: "index", intersect: false, backgroundColor: "rgba(0,0,0,0.8)", cornerRadius: 6, padding: 10,
                    callbacks: { label: function(ctx) { return ctx.dataset.label + ": UGX " + (ctx.parsed.y || 0).toLocaleString(); } }
                }
            },
            interaction: defaultOpts.interaction,
            scales: {
                x: defaultOpts.scales.x,
                y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, ticks: { font: { size: 10 }, callback: function(v) { return "UGX " + v.toLocaleString(); } } }
            }
        }
    });

    // ─── 4. Users by Platform Doughnut ───
    new Chart(document.getElementById("usersPieChart"), {
        type: "doughnut",
        data: {
            labels: ["Muno (' . $mnPct . '%)", "LugaFlix (' . $lgPct . '%)", "UG Flix (' . $ugPct . '%)", "Other (' . (100 - $ugPct - $lgPct - $mnPct) . '%)"],
            datasets: [{ data: [' . $munoUsers . ', ' . $lugaflixUsers . ', ' . $ugflixUsers . ', ' . $otherUsers . '],
                backgroundColor: ["rgba(231,76,60,0.8)", "rgba(52,152,219,0.8)", "rgba(46,204,113,0.8)", "rgba(189,195,199,0.6)"],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 10 } } } } }
    });

    // ─── 5. Device OS Doughnut ───
    new Chart(document.getElementById("devicePieChart"), {
        type: "doughnut",
        data: {
            labels: ["Android (' . $anPct . '%)", "iOS (' . (100 - $anPct) . '%)"],
            datasets: [{ data: [' . $androidUsers . ', ' . $iosUsers . '],
                backgroundColor: ["rgba(40,167,69,0.8)", "rgba(85,85,85,0.8)"],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 10 } } } } }
    });

    // ─── 6. Subscription Status Doughnut ───
    new Chart(document.getElementById("subsPieChart"), {
        type: "doughnut",
        data: {
            labels: ["Active", "Expired", "Pending"],
            datasets: [{ data: [' . $activeSubs . ', ' . $expiredSubs . ', ' . $pendingSubs . '],
                backgroundColor: ["rgba(40,167,69,0.8)", "rgba(220,53,69,0.8)", "rgba(255,193,7,0.8)"],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 10 } } } } }
    });
});
</script>';

        $html .= '</div>'; // db-wrap

        return $html;
    }
}
