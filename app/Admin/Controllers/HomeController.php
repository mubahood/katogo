<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\User;
use App\Models\MovieView;
use App\Models\SeriesMovie;
use App\Models\Subscription;
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
        // ═══════════ DATA ═══════════
        $totalMovies   = MovieModel::count();
        $activeMovies  = MovieModel::where('status', 'Active')->count();
        $moviesCount   = MovieModel::where('type', 'Movie')->count();
        $seriesCount   = MovieModel::where('type', 'Series')->count();
        $totalSeries   = SeriesMovie::count();
        $activeSeries  = SeriesMovie::where('is_active', 'Yes')->count();

        // URL Pipeline
        $urlTested     = MovieModel::where('video_url_tested_by_curl', 'Yes')->count();
        $urlNotTested  = $totalMovies - $urlTested;
        $urlsWorking   = MovieModel::where('video_url_tested_by_curl_works', 'Yes')->count();
        $urlSuccessRate = $urlTested > 0 ? round(($urlsWorking / $urlTested) * 100, 1) : 0;

        // Firebase
        $fbTransferred = MovieModel::where('firebase_transfer_successful', 'Yes')->count();
        $fbWorking     = MovieModel::where('firebase_video_tested_by_curl_works', 'Yes')->count();
        $readyForTransfer = MovieModel::where('video_url_tested_by_curl_works', 'Yes')
            ->whereNotIn('firebase_transfer_successful', ['Yes'])->count();
        $productionReady = MovieModel::where('video_url_tested_by_curl_works', 'Yes')
            ->where('firebase_transfer_successful', 'Yes')
            ->where('firebase_video_tested_by_curl_works', 'Yes')
            ->where('status', 'Active')->count();
        $pipelinePct = $totalMovies > 0 ? round(($productionReady / $totalMovies) * 100, 1) : 0;

        // Fix Tracking
        $mFixPending = MovieModel::where('fix_status', 'pending')->count();
        $mFixFixed   = MovieModel::where('fix_status', 'fixed')->count();
        $mFixError   = MovieModel::where('fix_status', 'error')->count();
        $sFixPending = SeriesMovie::where('fix_status', 'pending')->count();
        $sFixFixed   = SeriesMovie::where('fix_status', 'fixed')->count();
        $sFixError   = SeriesMovie::where('fix_status', 'error')->count();
        $mFixTotal   = $mFixPending + $mFixFixed + $mFixError;
        $sFixTotal   = $sFixPending + $sFixFixed + $sFixError;
        $mFixRate    = $mFixTotal > 0 ? round(($mFixFixed / $mFixTotal) * 100, 1) : 0;
        $sFixRate    = $sFixTotal > 0 ? round(($sFixFixed / $sFixTotal) * 100, 1) : 0;

        // Users
        $totalUsers    = User::count();
        $todayUsers    = User::whereDate('created_at', Carbon::today())->count();
        $weekUsers     = User::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $monthUsers    = User::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $ugflixUsers   = User::where('app_type', 'ugflix')->count();
        $lugaflixUsers = User::where('app_type', 'lugaflix')->count();
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

        // Views
        $totalViews    = MovieView::count();
        $todayViews    = MovieView::whereDate('created_at', Carbon::today())->count();
        $weekViews     = MovieView::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $totalWatchHrs = round(MovieView::sum('progress') / 3600, 1);
        $uniqueViewers = MovieView::distinct('user_id')->count('user_id');
        $errMovies     = MovieModel::whereNotNull('error_message')->count();

        // Daily data last 30 days
        $daily = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $daily[] = [
                'label' => $d->format('d M'),
                'day'   => $d->format('D'),
                'views' => MovieView::whereDate('created_at', $d)->count(),
                'users' => User::whereDate('created_at', $d)->count(),
            ];
        }
        $maxViews = max(array_column($daily, 'views') ?: [1]);
        $maxUsers = max(array_column($daily, 'users') ?: [1]);

        // Per-platform daily data (last 30 days) — Muno, LugaFlix, UG Flix
        $platformDaily = [];
        $munoUsers = User::where('app_type', 'muno_app')->count();
        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $platformDaily[] = [
                'label'        => $d->format('d M'),
                'muno_views'   => MovieView::whereDate('created_at', $d)->whereHas('user', function($q) { $q->where('app_type', 'muno_app'); })->count(),
                'lugaflix_views'=> MovieView::whereDate('created_at', $d)->whereHas('user', function($q) { $q->where('app_type', 'lugaflix'); })->count(),
                'ugflix_views'  => MovieView::whereDate('created_at', $d)->whereHas('user', function($q) { $q->where('app_type', 'ugflix'); })->count(),
                'muno_users'   => User::whereDate('created_at', $d)->where('app_type', 'muno_app')->count(),
                'lugaflix_users'=> User::whereDate('created_at', $d)->where('app_type', 'lugaflix')->count(),
                'ugflix_users'  => User::whereDate('created_at', $d)->where('app_type', 'ugflix')->count(),
            ];
        }

        // Weekly aggregated data (last 12 weeks)
        $weeklyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd   = Carbon::now()->subWeeks($i)->endOfWeek();
            $weeklyData[] = [
                'label' => $weekStart->format('d M'),
                'muno_views'   => MovieView::whereBetween('created_at', [$weekStart, $weekEnd])->whereHas('user', function($q) { $q->where('app_type', 'muno_app'); })->count(),
                'lugaflix_views'=> MovieView::whereBetween('created_at', [$weekStart, $weekEnd])->whereHas('user', function($q) { $q->where('app_type', 'lugaflix'); })->count(),
                'ugflix_views'  => MovieView::whereBetween('created_at', [$weekStart, $weekEnd])->whereHas('user', function($q) { $q->where('app_type', 'ugflix'); })->count(),
                'muno_users'   => User::whereBetween('created_at', [$weekStart, $weekEnd])->where('app_type', 'muno_app')->count(),
                'lugaflix_users'=> User::whereBetween('created_at', [$weekStart, $weekEnd])->where('app_type', 'lugaflix')->count(),
                'ugflix_users'  => User::whereBetween('created_at', [$weekStart, $weekEnd])->where('app_type', 'ugflix')->count(),
                'muno_watch_hrs'   => round(MovieView::whereBetween('created_at', [$weekStart, $weekEnd])->whereHas('user', function($q) { $q->where('app_type', 'muno_app'); })->sum('progress') / 3600, 1),
                'lugaflix_watch_hrs'=> round(MovieView::whereBetween('created_at', [$weekStart, $weekEnd])->whereHas('user', function($q) { $q->where('app_type', 'lugaflix'); })->sum('progress') / 3600, 1),
                'ugflix_watch_hrs'  => round(MovieView::whereBetween('created_at', [$weekStart, $weekEnd])->whereHas('user', function($q) { $q->where('app_type', 'ugflix'); })->sum('progress') / 3600, 1),
            ];
        }

        // Platform totals
        $munoViews    = MovieView::whereHas('user', function($q) { $q->where('app_type', 'muno_app'); })->count();
        $lugaflixViews = MovieView::whereHas('user', function($q) { $q->where('app_type', 'lugaflix'); })->count();
        $ugflixViews  = MovieView::whereHas('user', function($q) { $q->where('app_type', 'ugflix'); })->count();

        // User app pie
        $uT = max($ugflixUsers + $lugaflixUsers + $munoUsers + max($totalUsers - $ugflixUsers - $lugaflixUsers - $munoUsers, 0), 1);
        $ugPct = round(($ugflixUsers / $uT) * 100);
        $lgPct = round(($lugaflixUsers / $uT) * 100);
        $mnPct = round(($munoUsers / $uT) * 100);

        // Pipeline pie
        $pipT = max($totalMovies, 1);
        $prodPct = round(($productionReady / $pipT) * 100);
        $workPct = round((($urlsWorking - $productionReady) / $pipT) * 100);
        $testPct = round((($urlTested - $urlsWorking) / $pipT) * 100);
        $untPct = 100 - $prodPct - $workPct - $testPct;
        if ($untPct < 0) $untPct = 0;

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
.db-pie{width:72px;height:72px;border-radius:50%;margin:0 auto 6px}
.db-legend{font-size:9px;text-align:center;line-height:1.6}
.db-legend span{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:2px}
.db-bar-wrap{display:flex;align-items:flex-end;gap:2px;height:50px}
.db-bar-lbl{text-align:center;font-size:8px;color:#888;margin-top:1px}
.db-tbl{width:100%;border-collapse:collapse;font-size:11px}
.db-tbl th{text-align:left;padding:4px 6px;border-bottom:2px solid #eee;font-weight:700;color:#555;font-size:10px;text-transform:uppercase}
.db-tbl td{padding:4px 6px;border-bottom:1px solid #f5f5f5}
.db-badge{display:inline-block;padding:1px 6px;border-radius:3px;font-size:9px;font-weight:600;color:#fff}
.db-section{margin-bottom:4px;font-size:11px;font-weight:700;color:#333;border-bottom:2px solid #007bff;padding-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
</style>';

        $html .= '<div class="db-wrap">';

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
            ['Production Ready', number_format($productionReady), '#e83e8c', 'fa-rocket', '#'],
            ['Errors', number_format($errMovies), '#dc3545', 'fa-exclamation-triangle', '#'],
        ];
        foreach ($contentCards as [$l, $v, $c, $i, $lnk]) {
            $html .= "<div class='db-card' style='border-left-color:{$c}'><a href='{$lnk}'><div class='db-val' style='color:{$c}'>{$v}</div><div class='db-lbl'>{$l}</div><i class='fa {$i} db-ico'></i></a></div>";
        }
        $html .= '</div>';

        // ── SECTION: Pipeline + Fix Tracking ──
        $html .= '<div class="db-section">Pipeline & Fix Tracking</div>';
        $html .= '<div class="db-row">';
        $pipeCards = [
            ['URLs Not Tested', number_format($urlNotTested), '#ffc107', 'fa-question-circle'],
            ['URLs Tested', number_format($urlTested), '#17a2b8', 'fa-check'],
            ['URLs Working', number_format($urlsWorking), '#28a745', 'fa-link'],
            ['URL Success', $urlSuccessRate . '%', $urlSuccessRate >= 80 ? '#28a745' : '#ffc107', 'fa-tachometer'],
            ['Firebase Done', number_format($fbTransferred), '#007bff', 'fa-cloud-upload'],
            ['FB Working', number_format($fbWorking), '#28a745', 'fa-cloud'],
            ['Ready Transfer', number_format($readyForTransfer), '#fd7e14', 'fa-exchange'],
            ['Pipeline %', $pipelinePct . '%', '#6f42c1', 'fa-tasks'],
        ];
        foreach ($pipeCards as [$l, $v, $c, $i]) {
            $html .= "<div class='db-card' style='border-left-color:{$c}'><div class='db-val' style='color:{$c}'>{$v}</div><div class='db-lbl'>{$l}</div><i class='fa {$i} db-ico'></i></div>";
        }
        $html .= '</div>';

        // Fix tracking cards
        $html .= '<div class="db-row">';
        $fixCards = [
            ['Movies Pending', number_format($mFixPending), '#ffc107', 'fa-hourglass-half', admin_url('movies-movies-pending')],
            ['Movies Fixed', number_format($mFixFixed), '#28a745', 'fa-wrench', admin_url('movies-movies-fixed')],
            ['Movies Errors', number_format($mFixError), '#dc3545', 'fa-bug', admin_url('movies-movies-failed')],
            ['Movie Fix Rate', $mFixRate . '%', '#007bff', 'fa-line-chart', '#'],
            ['Series Pending', number_format($sFixPending), '#ffc107', 'fa-clock-o', admin_url('series-movies-pending')],
            ['Series Fixed', number_format($sFixFixed), '#28a745', 'fa-check', admin_url('series-movies-fixed')],
            ['Series Errors', number_format($sFixError), '#dc3545', 'fa-times', admin_url('series-movies-failed')],
            ['Series Fix Rate', $sFixRate . '%', '#007bff', 'fa-line-chart', '#'],
        ];
        foreach ($fixCards as [$l, $v, $c, $i, $lnk]) {
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
            ['Ugflix', number_format($ugflixUsers), '#e74c3c', 'fa-play-circle', '#'],
            ['Lugaflix', number_format($lugaflixUsers), '#3498db', 'fa-play-circle-o', '#'],
            ['Guests', number_format($guestUsers), '#95a5a6', 'fa-user-secret', '#'],
            ['Active Subs', number_format($activeSubs), '#f39c12', 'fa-star', '#'],
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
            ['Watch Hours', number_format($totalWatchHrs) . 'h', '#e83e8c', 'fa-clock-o', '#'],
            ['Unique Viewers', number_format($uniqueViewers), '#fd7e14', 'fa-users', '#'],
            ['Android', number_format($androidUsers), '#28a745', 'fa-android', '#'],
            ['iOS', number_format($iosUsers), '#555', 'fa-apple', '#'],
            ['Revenue', 'UGX ' . number_format($subRevenue), '#f39c12', 'fa-money', '#'],
        ];
        foreach ($engCards as [$l, $v, $c, $i, $lnk]) {
            $html .= "<div class='db-card' style='border-left-color:{$c}'><a href='{$lnk}'><div class='db-val' style='color:{$c}'>{$v}</div><div class='db-lbl'>{$l}</div><i class='fa {$i} db-ico'></i></a></div>";
        }
        $html .= '</div>';

        // ── SECTION: Charts & Analytics ──
        $html .= '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        $html .= '<div class="db-section">Charts & Analytics</div>';

        // ── ROW 1: Platform Views Line Chart + Platform Signups Line Chart ──
        $html .= '<div class="db-row">';

        // Prepare JSON data for Chart.js
        $chartLabels = json_encode(array_column($platformDaily, 'label'));
        $munoViewsArr = json_encode(array_column($platformDaily, 'muno_views'));
        $lugaflixViewsArr = json_encode(array_column($platformDaily, 'lugaflix_views'));
        $ugflixViewsArr = json_encode(array_column($platformDaily, 'ugflix_views'));
        $munoUsersArr = json_encode(array_column($platformDaily, 'muno_users'));
        $lugaflixUsersArr = json_encode(array_column($platformDaily, 'lugaflix_users'));
        $ugflixUsersArr = json_encode(array_column($platformDaily, 'ugflix_users'));
        $totalViewsArr = json_encode(array_column($daily, 'views'));
        $totalUsersArr = json_encode(array_column($daily, 'users'));
        $dailyLabels = json_encode(array_column($daily, 'label'));

        // Weekly JSON data
        $weeklyLabels = json_encode(array_column($weeklyData, 'label'));
        $wkMunoViews = json_encode(array_column($weeklyData, 'muno_views'));
        $wkLugaflixViews = json_encode(array_column($weeklyData, 'lugaflix_views'));
        $wkUgflixViews = json_encode(array_column($weeklyData, 'ugflix_views'));
        $wkMunoUsers = json_encode(array_column($weeklyData, 'muno_users'));
        $wkLugaflixUsers = json_encode(array_column($weeklyData, 'lugaflix_users'));
        $wkUgflixUsers = json_encode(array_column($weeklyData, 'ugflix_users'));
        $wkMunoHrs = json_encode(array_column($weeklyData, 'muno_watch_hrs'));
        $wkLugaflixHrs = json_encode(array_column($weeklyData, 'lugaflix_watch_hrs'));
        $wkUgflixHrs = json_encode(array_column($weeklyData, 'ugflix_watch_hrs'));

        // Platform Views Line Chart (30 days)
        $html .= '<div class="db-box" style="flex:1;min-width:460px">';
        $html .= '<div class="db-box-title"><i class="fa fa-line-chart" style="color:#007bff"></i> Daily Views by Platform — Last 30 Days</div>';
        $html .= '<div style="position:relative;height:320px"><canvas id="platformViewsChart"></canvas></div>';
        $html .= '</div>';

        // Platform Signups Line Chart (30 days)
        $html .= '<div class="db-box" style="flex:1;min-width:460px">';
        $html .= '<div class="db-box-title"><i class="fa fa-user-plus" style="color:#28a745"></i> Daily Signups by Platform — Last 30 Days</div>';
        $html .= '<div style="position:relative;height:320px"><canvas id="platformSignupsChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>'; // row 1

        // ── ROW 2: Combined Views/Signups bar chart + Weekly Trends area chart ──
        $html .= '<div class="db-row">';

        $html .= '<div class="db-box" style="flex:1;min-width:460px">';
        $html .= '<div class="db-box-title"><i class="fa fa-bar-chart" style="color:#6f42c1"></i> Total Views & Signups — Last 30 Days</div>';
        $html .= '<div style="position:relative;height:300px"><canvas id="combinedBarChart"></canvas></div>';
        $html .= '</div>';

        $html .= '<div class="db-box" style="flex:1;min-width:460px">';
        $html .= '<div class="db-box-title"><i class="fa fa-area-chart" style="color:#e83e8c"></i> Weekly Views Trend — Last 12 Weeks</div>';
        $html .= '<div style="position:relative;height:300px"><canvas id="weeklyTrendChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>'; // row 2

        // ── ROW 3: Weekly Signups + Watch Hours + Doughnut Charts ──
        $html .= '<div class="db-row">';

        $html .= '<div class="db-box" style="flex:1;min-width:340px">';
        $html .= '<div class="db-box-title"><i class="fa fa-users" style="color:#17a2b8"></i> Weekly Signups by Platform — 12 Weeks</div>';
        $html .= '<div style="position:relative;height:280px"><canvas id="weeklySignupsChart"></canvas></div>';
        $html .= '</div>';

        $html .= '<div class="db-box" style="flex:1;min-width:340px">';
        $html .= '<div class="db-box-title"><i class="fa fa-clock-o" style="color:#fd7e14"></i> Weekly Watch Hours by Platform</div>';
        $html .= '<div style="position:relative;height:280px"><canvas id="watchHoursChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>'; // row 3

        // ── ROW 4: Doughnut Charts + Platform Comparison + Subscription Summary ──
        $html .= '<div class="db-row">';

        // Users by App doughnut
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

        // Pipeline Status doughnut
        $html .= '<div class="db-box" style="flex:1;min-width:200px;text-align:center">';
        $html .= '<div class="db-box-title"><i class="fa fa-tasks" style="color:#17a2b8"></i> Pipeline Status</div>';
        $html .= '<div style="position:relative;height:220px"><canvas id="pipelinePieChart"></canvas></div>';
        $html .= '</div>';

        // Fix Tracking doughnut
        $allFixItems = $mFixPending + $mFixFixed + $mFixError + $sFixPending + $sFixFixed + $sFixError;
        $allFixed = $mFixFixed + $sFixFixed;
        $allPend  = $mFixPending + $sFixPending;
        $allErr   = $mFixError + $sFixError;
        $fxT = max($allFixItems, 1);
        $fxPct = round(($allFixed / $fxT) * 100);
        $fpPct = round(($allPend / $fxT) * 100);
        $fePct = 100 - $fxPct - $fpPct;
        $html .= '<div class="db-box" style="flex:1;min-width:200px;text-align:center">';
        $html .= '<div class="db-box-title"><i class="fa fa-wrench" style="color:#28a745"></i> Fix Tracking</div>';
        $html .= '<div style="position:relative;height:220px"><canvas id="fixPieChart"></canvas></div>';
        $html .= '</div>';

        $html .= '</div>'; // row 4

        // ── ROW 5: Platform Comparison Scorecards + Subscription Summary ──
        $html .= '<div class="db-row">';

        // Platform comparison cards
        $html .= '<div class="db-box" style="flex:2;min-width:400px">';
        $html .= '<div class="db-box-title"><i class="fa fa-trophy" style="color:#f39c12"></i> Platform Comparison</div>';
        $html .= '<table class="db-tbl">';
        $html .= '<tr><th>Platform</th><th>Users</th><th>Total Views</th><th style="text-align:center">Trend</th></tr>';
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

        // Subscription summary
        $html .= '<div class="db-box" style="flex:1;min-width:240px">';
        $html .= '<div class="db-box-title"><i class="fa fa-credit-card" style="color:#f39c12"></i> Subscription Summary</div>';
        $html .= '<table class="db-tbl">';
        $html .= '<tr><th>Metric</th><th>Value</th></tr>';
        $html .= '<tr><td>Total Subscriptions</td><td><b>' . number_format($totalSubs) . '</b></td></tr>';
        $html .= '<tr><td>Active</td><td><span class="db-badge" style="background:#28a745">' . number_format($activeSubs) . '</span></td></tr>';
        $html .= '<tr><td>Expired</td><td><span class="db-badge" style="background:#dc3545">' . number_format($expiredSubs) . '</span></td></tr>';
        $html .= '<tr><td>Total Revenue</td><td><b style="color:#f39c12">UGX ' . number_format($subRevenue) . '</b></td></tr>';
        $html .= '</table></div>';

        $html .= '</div>'; // row 5

        // ════════ Chart.js Initialization Scripts ════════
        $html .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    const platformColors = {
        muno: { bg: "rgba(231,76,60,0.15)", border: "#e74c3c", point: "#c0392b" },
        lugaflix: { bg: "rgba(52,152,219,0.15)", border: "#3498db", point: "#2980b9" },
        ugflix: { bg: "rgba(46,204,113,0.15)", border: "#2ecc71", point: "#27ae60" },
    };
    const chartFont = { family: "-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif" };
    const gridColor = "rgba(0,0,0,0.04)";
    const defaultOpts = {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: "top", labels: { usePointStyle: true, pointStyle: "circle", padding: 15, font: { size: 11, ...chartFont } } },
            tooltip: { mode: "index", intersect: false, backgroundColor: "rgba(0,0,0,0.8)", titleFont: { size: 12 }, bodyFont: { size: 11 }, padding: 10, cornerRadius: 6, displayColors: true }
        },
        interaction: { mode: "nearest", axis: "x", intersect: false },
        scales: {
            x: { grid: { color: gridColor, drawBorder: false }, ticks: { font: { size: 10, ...chartFont }, maxRotation: 45 } },
            y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, ticks: { font: { size: 10, ...chartFont } } }
        }
    };
    function lineDataset(label, data, colorKey, dashed) {
        return {
            label: label, data: data,
            borderColor: platformColors[colorKey].border,
            backgroundColor: platformColors[colorKey].bg,
            pointBackgroundColor: platformColors[colorKey].point,
            pointRadius: 3, pointHoverRadius: 6,
            borderWidth: 2.5, tension: 0.35, fill: true,
            borderDash: dashed ? [5, 3] : []
        };
    }

    // ─── 1. Platform Views (30 days) ───
    new Chart(document.getElementById("platformViewsChart"), {
        type: "line",
        data: {
            labels: ' . $chartLabels . ',
            datasets: [
                lineDataset("Muno", ' . $munoViewsArr . ', "muno", false),
                lineDataset("LugaFlix", ' . $lugaflixViewsArr . ', "lugaflix", false),
                lineDataset("UG Flix", ' . $ugflixViewsArr . ', "ugflix", false)
            ]
        },
        options: { ...defaultOpts, plugins: { ...defaultOpts.plugins, title: { display: false } } }
    });

    // ─── 2. Platform Signups (30 days) ───
    new Chart(document.getElementById("platformSignupsChart"), {
        type: "line",
        data: {
            labels: ' . $chartLabels . ',
            datasets: [
                lineDataset("Muno", ' . $munoUsersArr . ', "muno", false),
                lineDataset("LugaFlix", ' . $lugaflixUsersArr . ', "lugaflix", false),
                lineDataset("UG Flix", ' . $ugflixUsersArr . ', "ugflix", false)
            ]
        },
        options: defaultOpts
    });

    // ─── 3. Combined Bar (Views + Signups Total) ───
    new Chart(document.getElementById("combinedBarChart"), {
        type: "bar",
        data: {
            labels: ' . $dailyLabels . ',
            datasets: [
                { label: "Views", data: ' . $totalViewsArr . ', backgroundColor: "rgba(0,123,255,0.7)", borderColor: "#007bff", borderWidth: 1, borderRadius: 4, barPercentage: 0.7 },
                { label: "Signups", data: ' . $totalUsersArr . ', backgroundColor: "rgba(40,167,69,0.7)", borderColor: "#28a745", borderWidth: 1, borderRadius: 4, barPercentage: 0.7 }
            ]
        },
        options: { ...defaultOpts, scales: { ...defaultOpts.scales, x: { ...defaultOpts.scales.x, stacked: false }, y: { ...defaultOpts.scales.y, stacked: false } } }
    });

    // ─── 4. Weekly Views Trend (area chart) ───
    new Chart(document.getElementById("weeklyTrendChart"), {
        type: "line",
        data: {
            labels: ' . $weeklyLabels . ',
            datasets: [
                { ...lineDataset("Muno", ' . $wkMunoViews . ', "muno", false), fill: "origin", backgroundColor: "rgba(231,76,60,0.12)" },
                { ...lineDataset("LugaFlix", ' . $wkLugaflixViews . ', "lugaflix", false), fill: "origin", backgroundColor: "rgba(52,152,219,0.12)" },
                { ...lineDataset("UG Flix", ' . $wkUgflixViews . ', "ugflix", false), fill: "origin", backgroundColor: "rgba(46,204,113,0.12)" }
            ]
        },
        options: { ...defaultOpts, elements: { line: { tension: 0.4 } } }
    });

    // ─── 5. Weekly Signups (stacked bar) ───
    new Chart(document.getElementById("weeklySignupsChart"), {
        type: "bar",
        data: {
            labels: ' . $weeklyLabels . ',
            datasets: [
                { label: "Muno", data: ' . $wkMunoUsers . ', backgroundColor: "rgba(231,76,60,0.75)", borderRadius: 3, barPercentage: 0.8 },
                { label: "LugaFlix", data: ' . $wkLugaflixUsers . ', backgroundColor: "rgba(52,152,219,0.75)", borderRadius: 3, barPercentage: 0.8 },
                { label: "UG Flix", data: ' . $wkUgflixUsers . ', backgroundColor: "rgba(46,204,113,0.75)", borderRadius: 3, barPercentage: 0.8 }
            ]
        },
        options: { ...defaultOpts, scales: { ...defaultOpts.scales, x: { ...defaultOpts.scales.x, stacked: true }, y: { ...defaultOpts.scales.y, stacked: true } } }
    });

    // ─── 6. Watch Hours (grouped bar) ───
    new Chart(document.getElementById("watchHoursChart"), {
        type: "bar",
        data: {
            labels: ' . $weeklyLabels . ',
            datasets: [
                { label: "Muno (hrs)", data: ' . $wkMunoHrs . ', backgroundColor: "rgba(231,76,60,0.65)", borderRadius: 3 },
                { label: "LugaFlix (hrs)", data: ' . $wkLugaflixHrs . ', backgroundColor: "rgba(52,152,219,0.65)", borderRadius: 3 },
                { label: "UG Flix (hrs)", data: ' . $wkUgflixHrs . ', backgroundColor: "rgba(46,204,113,0.65)", borderRadius: 3 }
            ]
        },
        options: defaultOpts
    });

    // ─── 7. Users by Platform Doughnut ───
    new Chart(document.getElementById("usersPieChart"), {
        type: "doughnut",
        data: {
            labels: ["Muno (' . $mnPct . '%)", "LugaFlix (' . $lgPct . '%)", "UG Flix (' . $ugPct . '%)", "Other (' . (100 - $ugPct - $lgPct - $mnPct) . '%)"],
            datasets: [{ data: [' . $munoUsers . ', ' . $lugaflixUsers . ', ' . $ugflixUsers . ', ' . max($totalUsers - $ugflixUsers - $lugaflixUsers - $munoUsers, 0) . '],
                backgroundColor: ["rgba(231,76,60,0.8)", "rgba(52,152,219,0.8)", "rgba(46,204,113,0.8)", "rgba(189,195,199,0.6)"],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 10, ...chartFont } } } } }
    });

    // ─── 8. Device OS Doughnut ───
    new Chart(document.getElementById("devicePieChart"), {
        type: "doughnut",
        data: {
            labels: ["Android (' . $anPct . '%)", "iOS (' . (100 - $anPct) . '%)"],
            datasets: [{ data: [' . $androidUsers . ', ' . $iosUsers . '],
                backgroundColor: ["rgba(40,167,69,0.8)", "rgba(85,85,85,0.8)"],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 10, ...chartFont } } } } }
    });

    // ─── 9. Pipeline Status Doughnut ───
    new Chart(document.getElementById("pipelinePieChart"), {
        type: "doughnut",
        data: {
            labels: ["Production (' . $prodPct . '%)", "Working (' . $workPct . '%)", "Tested (' . $testPct . '%)", "Untested (' . $untPct . '%)"],
            datasets: [{ data: [' . $productionReady . ', ' . ($urlsWorking - $productionReady) . ', ' . ($urlTested - $urlsWorking) . ', ' . $urlNotTested . '],
                backgroundColor: ["rgba(40,167,69,0.8)", "rgba(23,162,184,0.8)", "rgba(255,193,7,0.8)", "rgba(220,53,69,0.7)"],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 10, ...chartFont } } } } }
    });

    // ─── 10. Fix Tracking Doughnut ───
    new Chart(document.getElementById("fixPieChart"), {
        type: "doughnut",
        data: {
            labels: ["Fixed (' . $fxPct . '%)", "Pending (' . $fpPct . '%)", "Error (' . $fePct . '%)"],
            datasets: [{ data: [' . $allFixed . ', ' . $allPend . ', ' . $allErr . '],
                backgroundColor: ["rgba(40,167,69,0.8)", "rgba(255,193,7,0.8)", "rgba(220,53,69,0.8)"],
                borderWidth: 2, borderColor: "#fff", hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "55%",
            plugins: { legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", padding: 8, font: { size: 10, ...chartFont } } } } }
    });
});
</script>';

        // ── SECTION: Summary Tables ──
        $html .= '<div class="db-section">Summary Tables</div>';
        $html .= '<div class="db-row">';

        // Pipeline table
        $html .= '<div class="db-box" style="flex:1;min-width:280px">';
        $html .= '<div class="db-box-title">Processing Pipeline</div>';
        $html .= '<table class="db-tbl">';
        $html .= '<tr><th>Stage</th><th>Count</th><th>%</th><th>Status</th></tr>';
        $stages = [
            ['Not Tested', $urlNotTested, $totalMovies > 0 ? round(($urlNotTested/$totalMovies)*100,1) : 0, $urlNotTested > 1000 ? '#dc3545' : '#ffc107'],
            ['Tested', $urlTested, $totalMovies > 0 ? round(($urlTested/$totalMovies)*100,1) : 0, '#17a2b8'],
            ['Working', $urlsWorking, $urlSuccessRate, '#28a745'],
            ['FB Transferred', $fbTransferred, $totalMovies > 0 ? round(($fbTransferred/$totalMovies)*100,1) : 0, '#007bff'],
            ['FB Working', $fbWorking, $totalMovies > 0 ? round(($fbWorking/$totalMovies)*100,1) : 0, '#28a745'],
            ['Production', $productionReady, $pipelinePct, '#e83e8c'],
        ];
        foreach ($stages as [$label, $count, $pct, $clr]) {
            $html .= "<tr><td>{$label}</td><td><b>" . number_format($count) . "</b></td><td>{$pct}%</td><td><span class='db-badge' style='background:{$clr}'>" . ($pct >= 80 ? 'Good' : ($pct >= 40 ? 'OK' : 'Low')) . "</span></td></tr>";
        }
        $html .= '</table></div>';

        // Fix tracking table
        $html .= '<div class="db-box" style="flex:1;min-width:280px">';
        $html .= '<div class="db-box-title">Fix Tracking Detail</div>';
        $html .= '<table class="db-tbl">';
        $html .= '<tr><th>Type</th><th>Pending</th><th>Fixed</th><th>Error</th><th>Rate</th></tr>';
        $html .= "<tr><td>Movies</td><td><span class='db-badge' style='background:#ffc107'>{$mFixPending}</span></td><td><span class='db-badge' style='background:#28a745'>{$mFixFixed}</span></td><td><span class='db-badge' style='background:#dc3545'>{$mFixError}</span></td><td><b>{$mFixRate}%</b></td></tr>";
        $html .= "<tr><td>Series</td><td><span class='db-badge' style='background:#ffc107'>{$sFixPending}</span></td><td><span class='db-badge' style='background:#28a745'>{$sFixFixed}</span></td><td><span class='db-badge' style='background:#dc3545'>{$sFixError}</span></td><td><b>{$sFixRate}%</b></td></tr>";
        $totalFP = $mFixPending + $sFixPending; $totalFF = $mFixFixed + $sFixFixed; $totalFE = $mFixError + $sFixError;
        $totalFT = $totalFP + $totalFF + $totalFE;
        $totalFR = $totalFT > 0 ? round(($totalFF / $totalFT) * 100, 1) : 0;
        $html .= "<tr style='border-top:2px solid #ddd'><td><b>Total</b></td><td><b>{$totalFP}</b></td><td><b>{$totalFF}</b></td><td><b>{$totalFE}</b></td><td><b>{$totalFR}%</b></td></tr>";
        $html .= '</table></div>';

        // Quick Links / Actions
        $html .= '<div class="db-box" style="flex:1;min-width:200px">';
        $html .= '<div class="db-box-title">Quick Links</div>';
        $links = [
            ['Movies', admin_url('movies-movies'), '#007bff', 'fa-film'],
            ['Series', admin_url('series-movies'), '#6f42c1', 'fa-tv'],
            ['Movie Views', admin_url('movie-views'), '#17a2b8', 'fa-eye'],
            ['Users', admin_url('users'), '#28a745', 'fa-users'],
            ['Pending Movies Fix', admin_url('movies-movies-pending'), '#ffc107', 'fa-hourglass-half'],
            ['Pending Series Fix', admin_url('series-movies-pending'), '#ffc107', 'fa-clock-o'],
            ['Failed Movies Fix', admin_url('movies-movies-failed'), '#dc3545', 'fa-bug'],
            ['Failed Series Fix', admin_url('series-movies-failed'), '#dc3545', 'fa-times'],
        ];
        foreach ($links as [$label, $url, $clr, $ico]) {
            $html .= "<div style='padding:4px 0;border-bottom:1px solid #f5f5f5'><a href='{$url}' style='color:{$clr};text-decoration:none;font-size:11px'><i class='fa {$ico}' style='width:16px'></i> {$label}</a></div>";
        }
        $html .= '</div>';

        $html .= '</div>'; // summary tables row

        $html .= '</div>'; // db-wrap

        return $html;
    }
}
