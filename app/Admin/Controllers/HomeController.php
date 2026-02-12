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
            ->where('end_date', '>=', Carbon::now())
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

        // Daily data last 14 days
        $daily = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $daily[] = [
                'label' => $d->format('d'),
                'day'   => $d->format('D'),
                'views' => MovieView::whereDate('created_at', $d)->count(),
                'users' => User::whereDate('created_at', $d)->count(),
            ];
        }
        $maxViews = max(array_column($daily, 'views') ?: [1]);
        $maxUsers = max(array_column($daily, 'users') ?: [1]);

        // User app pie
        $uT = max($ugflixUsers + $lugaflixUsers + max($totalUsers - $ugflixUsers - $lugaflixUsers, 0), 1);
        $ugPct = round(($ugflixUsers / $uT) * 100);
        $lgPct = round(($lugaflixUsers / $uT) * 100);

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
        $html .= '<div class="db-section">Charts & Analytics</div>';
        $html .= '<div class="db-row">';

        // 14-day views bar chart
        $html .= '<div class="db-box" style="flex:3;min-width:300px">';
        $html .= '<div class="db-box-title">Daily Views — Last 14 Days</div>';
        $html .= '<div class="db-bar-wrap">';
        foreach ($daily as $dv) {
            $h = $maxViews > 0 ? round(($dv['views'] / $maxViews) * 45) : 0;
            $html .= "<div style='flex:1;text-align:center'><div style='height:{$h}px;background:#007bff;border-radius:2px 2px 0 0;margin:0 auto;width:70%' title='{$dv['views']} views'></div><div class='db-bar-lbl'>{$dv['label']}<br><b>{$dv['views']}</b></div></div>";
        }
        $html .= '</div></div>';

        // 14-day signups bar
        $html .= '<div class="db-box" style="flex:3;min-width:300px">';
        $html .= '<div class="db-box-title">Daily Signups — Last 14 Days</div>';
        $html .= '<div class="db-bar-wrap">';
        foreach ($daily as $dv) {
            $h = $maxUsers > 0 ? round(($dv['users'] / $maxUsers) * 45) : 0;
            $html .= "<div style='flex:1;text-align:center'><div style='height:{$h}px;background:#28a745;border-radius:2px 2px 0 0;margin:0 auto;width:70%' title='{$dv['users']} users'></div><div class='db-bar-lbl'>{$dv['label']}<br><b>{$dv['users']}</b></div></div>";
        }
        $html .= '</div></div>';

        $html .= '</div>'; // charts row

        // Pie charts row
        $html .= '<div class="db-row">';

        // User app type pie
        $html .= '<div class="db-box" style="flex:1;min-width:130px;text-align:center">';
        $html .= '<div class="db-box-title">Users by App</div>';
        $ugDeg = round(($ugPct / 100) * 360);
        $lgDeg = round(($lgPct / 100) * 360);
        $html .= "<div class='db-pie' style='background:conic-gradient(#e74c3c 0deg {$ugDeg}deg, #3498db {$ugDeg}deg " . ($ugDeg + $lgDeg) . "deg, #bdc3c7 " . ($ugDeg + $lgDeg) . "deg 360deg)'></div>";
        $html .= "<div class='db-legend'><span style='background:#e74c3c'></span>Ugflix {$ugPct}%<br><span style='background:#3498db'></span>Lugaflix {$lgPct}%</div>";
        $html .= '</div>';

        // Device pie
        $dT = max($androidUsers + $iosUsers, 1);
        $anPct = round(($androidUsers / $dT) * 100);
        $html .= '<div class="db-box" style="flex:1;min-width:130px;text-align:center">';
        $html .= '<div class="db-box-title">Device OS</div>';
        $anDeg = round(($anPct / 100) * 360);
        $html .= "<div class='db-pie' style='background:conic-gradient(#28a745 0deg {$anDeg}deg, #555 {$anDeg}deg 360deg)'></div>";
        $html .= "<div class='db-legend'><span style='background:#28a745'></span>Android {$anPct}%<br><span style='background:#555'></span>iOS " . (100 - $anPct) . "%</div>";
        $html .= '</div>';

        // Pipeline pie
        $html .= '<div class="db-box" style="flex:1;min-width:130px;text-align:center">';
        $html .= '<div class="db-box-title">Pipeline Status</div>';
        $d1 = round(($prodPct / 100) * 360);
        $d2 = round(($workPct / 100) * 360);
        $d3 = round(($testPct / 100) * 360);
        $html .= "<div class='db-pie' style='background:conic-gradient(#28a745 0deg {$d1}deg, #17a2b8 {$d1}deg " . ($d1+$d2) . "deg, #ffc107 " . ($d1+$d2) . "deg " . ($d1+$d2+$d3) . "deg, #dc3545 " . ($d1+$d2+$d3) . "deg 360deg)'></div>";
        $html .= "<div class='db-legend'><span style='background:#28a745'></span>Prod {$prodPct}% <span style='background:#17a2b8'></span>Working {$workPct}%<br><span style='background:#ffc107'></span>Tested {$testPct}% <span style='background:#dc3545'></span>Untested {$untPct}%</div>";
        $html .= '</div>';

        // Fix tracking pie
        $allFixItems = $mFixPending + $mFixFixed + $mFixError + $sFixPending + $sFixFixed + $sFixError;
        $allFixed = $mFixFixed + $sFixFixed;
        $allPend  = $mFixPending + $sFixPending;
        $allErr   = $mFixError + $sFixError;
        $fxT = max($allFixItems, 1);
        $fxPct = round(($allFixed / $fxT) * 100);
        $fpPct = round(($allPend / $fxT) * 100);
        $fePct = 100 - $fxPct - $fpPct;
        $html .= '<div class="db-box" style="flex:1;min-width:130px;text-align:center">';
        $html .= '<div class="db-box-title">Fix Tracking</div>';
        $fd1 = round(($fxPct / 100) * 360);
        $fd2 = round(($fpPct / 100) * 360);
        $html .= "<div class='db-pie' style='background:conic-gradient(#28a745 0deg {$fd1}deg, #ffc107 {$fd1}deg " . ($fd1+$fd2) . "deg, #dc3545 " . ($fd1+$fd2) . "deg 360deg)'></div>";
        $html .= "<div class='db-legend'><span style='background:#28a745'></span>Fixed {$fxPct}% <span style='background:#ffc107'></span>Pending {$fpPct}%<br><span style='background:#dc3545'></span>Error {$fePct}%</div>";
        $html .= '</div>';

        // Subscription summary
        $html .= '<div class="db-box" style="flex:1.5;min-width:180px">';
        $html .= '<div class="db-box-title">Subscription Summary</div>';
        $html .= '<table class="db-tbl">';
        $html .= '<tr><th>Metric</th><th>Value</th></tr>';
        $html .= '<tr><td>Total Subscriptions</td><td><b>' . number_format($totalSubs) . '</b></td></tr>';
        $html .= '<tr><td>Active</td><td><span class="db-badge" style="background:#28a745">' . number_format($activeSubs) . '</span></td></tr>';
        $html .= '<tr><td>Expired</td><td><span class="db-badge" style="background:#dc3545">' . number_format($expiredSubs) . '</span></td></tr>';
        $html .= '<tr><td>Total Revenue</td><td><b>UGX ' . number_format($subRevenue) . '</b></td></tr>';
        $html .= '</table></div>';

        $html .= '</div>'; // pie row

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
