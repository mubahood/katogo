<?php

namespace App\Admin\Controllers;

use App\Models\MovieView;
use App\Models\MovieModel;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Utils;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Layout\Column;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Facades\DB;

class MovieViewController extends AdminController
{
    protected $title = 'Movie Views';

    /**
     * Dashboard + Grid index
     */
    public function index(Content $content)
    {
        return $content
            ->title('Movie Views Analytics')
            ->description('Real-time viewing activity & platform insights')
            ->body($this->dashboard())
            ->body($this->grid());
    }

    /**
     * Compact CSS dashboard with KPIs, platform breakdown, charts, top lists
     */
    protected function dashboard()
    {
        // ── Gather Stats ──
        $totalViews   = MovieView::count();
        $todayViews   = MovieView::whereDate('created_at', Carbon::today())->count();
        $yesterViews   = MovieView::whereDate('created_at', Carbon::yesterday())->count();
        $weekViews    = MovieView::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $monthViews   = MovieView::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $uniqueUsers  = MovieView::distinct('user_id')->count('user_id');
        $uniqueMovies = MovieView::distinct('movie_model_id')->count('movie_model_id');
        $totalHours   = round(MovieView::sum('progress') / 3600, 1);
        $avgProgress  = round(MovieView::avg('progress') / 60, 1); // minutes

        // Platform breakdown via user join
        $platformData = DB::table('movie_views')
            ->join('admin_users', 'movie_views.user_id', '=', 'admin_users.id')
            ->selectRaw("COALESCE(admin_users.app_type,'unknown') as app_type, COUNT(*) as cnt")
            ->groupBy('admin_users.app_type')
            ->pluck('cnt', 'app_type')->toArray();
        $ugflixViews   = $platformData['ugflix'] ?? 0;
        $lugaflixViews = $platformData['lugaflix'] ?? 0;
        $unknownViews  = $platformData['unknown'] ?? 0 + ($platformData[''] ?? 0);

        // Device platform breakdown
        $deviceData = DB::table('movie_views')
            ->join('admin_users', 'movie_views.user_id', '=', 'admin_users.id')
            ->selectRaw("COALESCE(admin_users.platform,'unknown') as device_platform, COUNT(*) as cnt")
            ->groupBy('admin_users.platform')
            ->pluck('cnt', 'device_platform')->toArray();
        $androidViews = $deviceData['android'] ?? 0;
        $iosViews     = $deviceData['ios'] ?? 0;

        // Subscription status of viewers
        $subData = DB::table('movie_views')
            ->join('admin_users', 'movie_views.user_id', '=', 'admin_users.id')
            ->leftJoin('subscriptions', function ($j) {
                $j->on('admin_users.id', '=', 'subscriptions.user_id')
                  ->where('subscriptions.status', '=', 'Active')
                  ->where('subscriptions.end_date_time', '>=', Carbon::now());
            })
            ->selectRaw("IF(subscriptions.id IS NOT NULL, 'subscribed', 'free') as sub_status, COUNT(DISTINCT movie_views.id) as cnt")
            ->groupBy('sub_status')
            ->pluck('cnt', 'sub_status')->toArray();
        $subscribedViews = $subData['subscribed'] ?? 0;
        $freeViews       = $subData['free'] ?? 0;

        // Daily views last 7 days for bar chart
        $dailyViews = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $dailyViews[] = [
                'label' => $d->format('D'),
                'count' => MovieView::whereDate('created_at', $d)->count(),
            ];
        }
        $maxDaily = max(array_column($dailyViews, 'count') ?: [1]);

        // Trend
        $todayTrend = $todayViews > $yesterViews ? '▲' : ($todayViews < $yesterViews ? '▼' : '—');
        $trendColor = $todayViews >= $yesterViews ? '#28a745' : '#dc3545';

        // Top 5 movies
        $topMovies = MovieView::whereNotNull('movie_model_id')
            ->selectRaw('movie_model_id, COUNT(*) as cnt')
            ->groupBy('movie_model_id')
            ->orderByDesc('cnt')
            ->limit(5)->get();

        // Top 5 users
        $topUsers = MovieView::whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->orderByDesc('cnt')
            ->limit(5)->get();

        // Platform pie percentages
        $pTotal = max($ugflixViews + $lugaflixViews + $unknownViews, 1);
        $ugPct  = round(($ugflixViews / $pTotal) * 100);
        $lgPct  = round(($lugaflixViews / $pTotal) * 100);
        $ukPct  = 100 - $ugPct - $lgPct;

        // Device pie
        $dTotal = max($androidViews + $iosViews, 1);
        $anPct  = round(($androidViews / $dTotal) * 100);
        $ioPct  = 100 - $anPct;

        // Build HTML
        $html = '<style>
.mv-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:12px}
.mv-row{display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap}
.mv-card{flex:1;min-width:120px;background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-left:3px solid #ddd;position:relative;transition:box-shadow .15s}
.mv-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.12)}
.mv-card a{color:inherit;text-decoration:none;display:block}
.mv-card .mv-val{font-size:20px;font-weight:700;line-height:1.1}
.mv-card .mv-lbl{font-size:10px;color:#888;margin-top:2px;text-transform:uppercase;letter-spacing:.3px}
.mv-card .mv-icon{position:absolute;right:10px;top:10px;font-size:16px;opacity:.35}
.mv-box{background:#fff;border-radius:6px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.mv-box-title{font-size:11px;font-weight:700;color:#555;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px}
.mv-pie{width:80px;height:80px;border-radius:50%;margin:0 auto 6px}
.mv-legend{font-size:10px;text-align:center}
.mv-legend span{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:3px}
.mv-bar-wrap{display:flex;align-items:flex-end;gap:4px;height:60px}
.mv-bar{flex:1;background:#007bff;border-radius:2px 2px 0 0;min-width:16px;transition:height .3s;position:relative}
.mv-bar:hover{opacity:.85}
.mv-bar-lbl{text-align:center;font-size:9px;color:#888;margin-top:2px}
.mv-rank{display:flex;align-items:center;padding:4px 0;border-bottom:1px solid #f0f0f0}
.mv-rank:last-child{border-bottom:none}
.mv-rank-num{width:20px;font-weight:700;color:#888;font-size:11px}
.mv-rank-name{flex:1;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mv-rank-cnt{font-weight:700;font-size:11px;color:#007bff;margin-left:6px}
.mv-badge{display:inline-block;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:600;color:#fff}
</style>';

        $html .= '<div class="mv-wrap">';

        // ── Row 1: KPI cards ──
        $html .= '<div class="mv-row">';
        $cards = [
            ['Total Views', number_format($totalViews), '#007bff', 'fa-eye', admin_url('movie-views')],
            ["Today {$todayTrend}", number_format($todayViews), $trendColor, 'fa-calendar-check-o', '#'],
            ['This Week', number_format($weekViews), '#6f42c1', 'fa-calendar', '#'],
            ['This Month', number_format($monthViews), '#17a2b8', 'fa-calendar-o', '#'],
            ['Unique Users', number_format($uniqueUsers), '#28a745', 'fa-users', admin_url('users')],
            ['Unique Movies', number_format($uniqueMovies), '#fd7e14', 'fa-film', admin_url('movies-movies')],
            ['Watch Hours', number_format($totalHours) . 'h', '#e83e8c', 'fa-clock-o', '#'],
            ['Avg Watch', $avgProgress . 'm', '#6c757d', 'fa-bar-chart', '#'],
        ];
        foreach ($cards as [$lbl, $val, $clr, $ico, $lnk]) {
            $html .= "<div class='mv-card' style='border-left-color:{$clr}'><a href='{$lnk}'><div class='mv-val' style='color:{$clr}'>{$val}</div><div class='mv-lbl'>{$lbl}</div><i class='fa {$ico} mv-icon'></i></a></div>";
        }
        $html .= '</div>';

        // ── Row 2: Platform cards ──
        $html .= '<div class="mv-row">';
        $pCards = [
            ['Ugflix Views', number_format($ugflixViews), '#e74c3c', 'fa-play-circle'],
            ['Lugaflix Views', number_format($lugaflixViews), '#3498db', 'fa-play-circle-o'],
            ['Android', number_format($androidViews), '#28a745', 'fa-android'],
            ['iOS', number_format($iosViews), '#555', 'fa-apple'],
            ['Subscribed Views', number_format($subscribedViews), '#f39c12', 'fa-star'],
            ['Free Views', number_format($freeViews), '#95a5a6', 'fa-star-o'],
        ];
        foreach ($pCards as [$lbl, $val, $clr, $ico]) {
            $html .= "<div class='mv-card' style='border-left-color:{$clr}'><div class='mv-val' style='color:{$clr}'>{$val}</div><div class='mv-lbl'>{$lbl}</div><i class='fa {$ico} mv-icon'></i></div>";
        }
        $html .= '</div>';

        // ── Row 3: Charts + Top Lists ──
        $html .= '<div class="mv-row">';

        // 7-day bar chart
        $html .= '<div class="mv-box" style="flex:2;min-width:220px">';
        $html .= '<div class="mv-box-title">Views — Last 7 Days</div>';
        $html .= '<div class="mv-bar-wrap">';
        foreach ($dailyViews as $dv) {
            $h = $maxDaily > 0 ? round(($dv['count'] / $maxDaily) * 55) : 0;
            $html .= "<div style='flex:1;text-align:center'><div class='mv-bar' style='height:{$h}px;background:#007bff;margin:0 auto;width:80%' title='{$dv['count']}'></div><div class='mv-bar-lbl'>{$dv['label']}<br><b>{$dv['count']}</b></div></div>";
        }
        $html .= '</div></div>';

        // App platform pie
        $html .= '<div class="mv-box" style="flex:1;min-width:140px;text-align:center">';
        $html .= '<div class="mv-box-title">App Platform</div>';
        $ugDeg = round(($ugPct / 100) * 360);
        $lgDeg = round(($lgPct / 100) * 360);
        $html .= "<div class='mv-pie' style='background:conic-gradient(#e74c3c 0deg {$ugDeg}deg, #3498db {$ugDeg}deg " . ($ugDeg + $lgDeg) . "deg, #bdc3c7 " . ($ugDeg + $lgDeg) . "deg 360deg)'></div>";
        $html .= "<div class='mv-legend'><span style='background:#e74c3c'></span>Ugflix {$ugPct}% <span style='background:#3498db;margin-left:6px'></span>Lugaflix {$lgPct}%</div>";
        $html .= '</div>';

        // Device pie
        $html .= '<div class="mv-box" style="flex:1;min-width:140px;text-align:center">';
        $html .= '<div class="mv-box-title">Device OS</div>';
        $anDeg = round(($anPct / 100) * 360);
        $html .= "<div class='mv-pie' style='background:conic-gradient(#28a745 0deg {$anDeg}deg, #555 {$anDeg}deg 360deg)'></div>";
        $html .= "<div class='mv-legend'><span style='background:#28a745'></span>Android {$anPct}% <span style='background:#555;margin-left:6px'></span>iOS {$ioPct}%</div>";
        $html .= '</div>';

        // Top 5 movies
        $html .= '<div class="mv-box" style="flex:1.5;min-width:180px">';
        $html .= '<div class="mv-box-title">Top 5 Movies</div>';
        $rank = 1;
        foreach ($topMovies as $tm) {
            $m = MovieModel::find($tm->movie_model_id);
            $t = $m ? (mb_strlen($m->title) > 28 ? mb_substr($m->title, 0, 28) . '…' : $m->title) : '#' . $tm->movie_model_id;
            $html .= "<div class='mv-rank'><div class='mv-rank-num'>#{$rank}</div><div class='mv-rank-name'><a href='" . admin_url("movies-movies/{$tm->movie_model_id}") . "' style='color:#333;text-decoration:none' title='" . htmlspecialchars($m->title ?? '') . "'>{$t}</a></div><div class='mv-rank-cnt'>{$tm->cnt}</div></div>";
            $rank++;
        }
        if ($topMovies->isEmpty()) $html .= '<div style="color:#aaa;text-align:center;padding:10px">No data</div>';
        $html .= '</div>';

        // Top 5 users
        $html .= '<div class="mv-box" style="flex:1.5;min-width:180px">';
        $html .= '<div class="mv-box-title">Top 5 Users</div>';
        $rank = 1;
        foreach ($topUsers as $tu) {
            $u = User::find($tu->user_id);
            $n = $u ? (mb_strlen($u->name) > 22 ? mb_substr($u->name, 0, 22) . '…' : $u->name) : '#' . $tu->user_id;
            $app = $u ? ($u->app_type ?? '?') : '?';
            $appClr = $app === 'ugflix' ? '#e74c3c' : ($app === 'lugaflix' ? '#3498db' : '#999');
            $html .= "<div class='mv-rank'><div class='mv-rank-num'>#{$rank}</div><div class='mv-rank-name'><a href='" . admin_url("users/{$tu->user_id}") . "' style='color:#333;text-decoration:none'>{$n}</a> <span class='mv-badge' style='background:{$appClr}'>{$app}</span></div><div class='mv-rank-cnt'>{$tu->cnt}</div></div>";
            $rank++;
        }
        if ($topUsers->isEmpty()) $html .= '<div style="color:#aaa;text-align:center;padding:10px">No data</div>';
        $html .= '</div>';

        $html .= '</div>'; // end row 3
        $html .= '</div>'; // end mv-wrap

        return $html;
    }

    /**
     * Grid with platform, subscription status, and fixed expand icon
     */
    protected function grid()
    {
        $grid = new Grid(new MovieView());
        $grid->model()->with(['movie', 'user'])->orderBy('updated_at', 'desc');

        $grid->quickSearch('ip_address', 'device', 'platform', 'browser', 'country', 'city');

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->column(1/3, function ($filter) {
                $filter->like('ip_address', 'IP Address');
                $filter->like('device', 'Device');
                $filter->like('platform', 'Platform');
            });
            $filter->column(1/3, function ($filter) {
                $filter->like('browser', 'Browser');
                $filter->like('country', 'Country');
                $filter->like('city', 'City');
            });
            $filter->column(1/3, function ($filter) {
                $filter->equal('status', 'Status')->select(['Active' => 'Active', 'Inactive' => 'Inactive']);
                $filter->equal('movie_model_id', 'Movie ID');
                $filter->equal('user_id', 'User ID');
            });
            $filter->between('created_at', 'Created At')->datetime();
        });

        $grid->disableBatchActions();

        // ── Columns ──
        $grid->column('id', 'ID')->width(50)->sortable();

        $grid->column('created_at', 'Date')->sortable()->display(function ($v) {
            return Carbon::parse($v)->format('d-M-Y H:i');
        });

        $grid->column('movie_model_id', 'Movie')->display(function ($id) {
            $m = $this->movie ?: MovieModel::find($id);
            if (!$m) return '<em class="text-muted">Deleted</em>';
            $t = mb_strlen($m->title) <= 30 ? $m->title : mb_substr($m->title, 0, 30) . '…';
            $st = ($m->status ?? '') === 'Active'
                ? '<span class="label label-success" style="font-size:9px">Active</span>'
                : '<span class="label label-danger" style="font-size:9px">' . ($m->status ?? 'N/A') . '</span>';
            return "<a href='" . admin_url("movies-movies/{$id}") . "' title='" . htmlspecialchars($m->title ?? '') . "'><strong>{$t}</strong></a><br><small class='text-muted'>#{$id} · {$st}</small>";
        })->sortable();

        $grid->column('user_id', 'User')->display(function ($uid) {
            $u = $this->user ?: User::find($uid);
            if (!$u) return '<em class="text-muted">Deleted</em>';
            return "<a href='" . admin_url("users/{$uid}") . "'><strong>{$u->name}</strong></a><br><small class='text-muted'>#{$uid}</small>";
        })->sortable();

        // App Platform (via user)
        $grid->column('app_platform', 'App')->display(function () {
            $u = $this->user ?: User::find($this->user_id);
            if (!$u) return '-';
            $app = $u->app_type ?? 'unknown';
            $colors = ['ugflix' => '#e74c3c', 'lugaflix' => '#3498db'];
            $clr = $colors[$app] ?? '#999';
            $device = $u->platform ?? '?';
            $dIcon = $device === 'android' ? 'fa-android' : ($device === 'ios' ? 'fa-apple' : 'fa-mobile');
            return "<span style='display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:{$clr}'>{$app}</span>"
                 . "<br><small><i class='fa {$dIcon}'></i> {$device}</small>";
        });

        // Subscription status (via user → latest subscription)
        $grid->column('sub_status', 'Subscription')->display(function () {
            $u = $this->user ?: User::find($this->user_id);
            if (!$u) return '-';
            $sub = Subscription::where('user_id', $u->id)
                ->orderBy('end_date_time', 'desc')->first();
            if (!$sub) {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:#95a5a6">Free</span>';
            }
            $statusColors = [
                'Active'    => '#28a745',
                'Expired'   => '#dc3545',
                'Cancelled' => '#6c757d',
                'Pending'   => '#ffc107',
                'Failed'    => '#dc3545',
            ];
            $clr = $statusColors[$sub->status] ?? '#999';
            $endLabel = $sub->end_date_time ? Carbon::parse($sub->end_date_time)->format('d-M-y') : '';
            return "<span style='display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:{$clr}'>{$sub->status}</span>"
                 . ($endLabel ? "<br><small class='text-muted'>{$endLabel}</small>" : '');
        });

        $grid->column('progress', 'Progress')->display(function ($progress) {
            if (!$progress) return '<small class="text-muted">0:00</small>';
            $max = $this->max_progress ?: 1;
            $pct = min(round(($progress / $max) * 100), 100);
            $clr = $pct >= 80 ? '#28a745' : ($pct >= 50 ? '#17a2b8' : ($pct >= 25 ? '#ffc107' : '#dc3545'));
            $time = Utils::secondsToMinutes($progress);
            return "<div style='display:flex;align-items:center;gap:4px'>"
                 . "<div style='flex:1;height:6px;background:#eee;border-radius:3px;min-width:40px'><div style='width:{$pct}%;height:100%;background:{$clr};border-radius:3px'></div></div>"
                 . "<small style='white-space:nowrap'>{$time} ({$pct}%)</small></div>";
        })->sortable();

        $grid->column('country', 'Location')->display(function () {
            $parts = array_filter([$this->city, $this->country]);
            return $parts ? implode(', ', $parts) : '<small class="text-muted">—</small>';
        });

        // Fixed expand icon — no display() before expand() to avoid HTML escaping
        $grid->column('_expand', 'Details')->expand(function ($model) {
            $movie = $model->movie ?: ($model->movie_model_id ? MovieModel::find($model->movie_model_id) : null);
            $user  = $model->user ?: ($model->user_id ? User::find($model->user_id) : null);
            $sub = $user ? Subscription::where('user_id', $user->id)->orderBy('end_date_time', 'desc')->first() : null;

            $html = '<div style="padding:15px;background:#f9f9f9;border-radius:8px;font-size:12px">';

            // ── Movie Info ──
            $html .= '<h4 style="margin-bottom:12px;color:#333;border-bottom:2px solid #007bff;padding-bottom:6px">🎬 Movie</h4>';
            $html .= '<table class="table table-bordered table-condensed" style="margin-bottom:16px">';
            if ($movie) {
                $html .= '<tr><td style="width:150px;font-weight:bold">Title</td><td>' . htmlspecialchars($movie->title ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight:bold">ID / Type</td><td>' . $movie->id . ' · ' . ($movie->type ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight:bold">Status</td><td><span class="label label-' . (($movie->status ?? '') === 'Active' ? 'success' : 'danger') . '">' . ($movie->status ?? 'N/A') . '</span></td></tr>';
                $html .= '<tr><td style="font-weight:bold">Category / VJ</td><td>' . ($movie->Category ?? $movie->category ?? 'N/A') . ' · ' . ($movie->vj ?? 'N/A') . '</td></tr>';
                $videoUrl = $movie->external_url ?: ($movie->url ?? null);
                if ($videoUrl) {
                    $html .= '<tr><td style="font-weight:bold">Video URL</td><td><a href="' . htmlspecialchars($videoUrl) . '" target="_blank" class="btn btn-xs btn-primary"><i class="fa fa-play-circle"></i> Play</a> <code style="font-size:10px;word-break:break-all">' . htmlspecialchars($videoUrl) . '</code></td></tr>';
                }
                if ($movie->thumbnail_url) {
                    $html .= '<tr><td style="font-weight:bold">Thumb</td><td><img src="' . htmlspecialchars($movie->thumbnail_url) . '" style="max-width:120px;max-height:80px;border-radius:4px"></td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="2" class="text-muted text-center">Movie not found (ID: ' . ($model->movie_model_id ?? '?') . ')</td></tr>';
            }
            $html .= '</table>';

            // ── Progress ──
            $progress = $model->progress ?? 0;
            $maxP = $model->max_progress ?: 1;
            $pct = min(round(($progress / $maxP) * 100, 1), 100);
            $barClr = $pct >= 80 ? 'success' : ($pct >= 50 ? 'info' : ($pct >= 25 ? 'warning' : 'danger'));
            $html .= '<h4 style="margin-bottom:12px;color:#333;border-bottom:2px solid #28a745;padding-bottom:6px">📊 Viewing Progress</h4>';
            $html .= '<table class="table table-bordered table-condensed" style="margin-bottom:16px">';
            $html .= '<tr><td style="width:150px;font-weight:bold">Progress / Max</td><td>' . Utils::secondsToMinutes($progress) . ' / ' . Utils::secondsToMinutes($maxP) . '</td></tr>';
            $html .= '<tr><td style="font-weight:bold">Completion</td><td><div class="progress" style="margin:0;height:18px"><div class="progress-bar progress-bar-' . $barClr . '" style="width:' . $pct . '%">' . $pct . '%</div></div></td></tr>';
            $html .= '</table>';

            // ── User + Subscription ──
            $html .= '<h4 style="margin-bottom:12px;color:#333;border-bottom:2px solid #17a2b8;padding-bottom:6px">👤 User & Subscription</h4>';
            $html .= '<table class="table table-bordered table-condensed" style="margin-bottom:16px">';
            if ($user) {
                $appClr = ($user->app_type ?? '') === 'ugflix' ? '#e74c3c' : (($user->app_type ?? '') === 'lugaflix' ? '#3498db' : '#999');
                $html .= '<tr><td style="width:150px;font-weight:bold">User</td><td><a href="' . admin_url("users/{$user->id}") . '">' . htmlspecialchars($user->name ?? 'N/A') . '</a> (ID: ' . $user->id . ')</td></tr>';
                $html .= '<tr><td style="font-weight:bold">Email / Phone</td><td>' . htmlspecialchars($user->email ?? 'N/A') . ' · ' . htmlspecialchars($user->phone_number ?? $user->phone ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight:bold">App / Device</td><td><span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:' . $appClr . '">' . ($user->app_type ?? 'unknown') . '</span> · ' . ($user->platform ?? 'unknown') . '</td></tr>';
                $html .= '<tr><td style="font-weight:bold">Registered</td><td>' . ($user->created_at ? Carbon::parse($user->created_at)->format('M d, Y H:i') : 'N/A') . '</td></tr>';

                // Subscription row
                if ($sub) {
                    $subColors = ['Active' => '#28a745', 'Expired' => '#dc3545', 'Cancelled' => '#6c757d', 'Pending' => '#ffc107', 'Failed' => '#dc3545'];
                    $sClr = $subColors[$sub->status] ?? '#999';
                    $html .= '<tr><td style="font-weight:bold">Subscription</td><td><span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:' . $sClr . '">' . $sub->status . '</span>';
                    $html .= ' · Plan: ' . ($sub->plan_id ?? 'N/A');
                    $html .= ' · Ends: ' . ($sub->end_date_time ? Carbon::parse($sub->end_date_time)->format('M d, Y') : 'N/A');
                    $html .= '</td></tr>';
                } else {
                    $html .= '<tr><td style="font-weight:bold">Subscription</td><td><span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;background:#95a5a6">No Subscription</span></td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="2" class="text-muted text-center">User not found (ID: ' . ($model->user_id ?? '?') . ')</td></tr>';
            }
            $html .= '</table>';

            // ── Device & Location ──
            $html .= '<h4 style="margin-bottom:12px;color:#333;border-bottom:2px solid #6c757d;padding-bottom:6px">📱 Device & Location</h4>';
            $html .= '<table class="table table-bordered table-condensed">';
            $html .= '<tr><td style="width:150px;font-weight:bold">Device / OS</td><td>' . htmlspecialchars($model->device ?? 'N/A') . ' · ' . htmlspecialchars($model->platform ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight:bold">Browser</td><td>' . htmlspecialchars($model->browser ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight:bold">Location</td><td>' . htmlspecialchars($model->city ?? 'N/A') . ', ' . htmlspecialchars($model->country ?? 'N/A') . ' · IP: ' . htmlspecialchars($model->ip_address ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight:bold">Timestamps</td><td>Created: ' . Carbon::parse($model->created_at)->format('M d, Y H:i:s') . ' · Updated: ' . Carbon::parse($model->updated_at)->format('M d, Y H:i:s') . '</td></tr>';
            $html .= '</table>';

            $html .= '</div>';
            return $html;
        });

        $grid->export(function ($export) {
            $export->filename('MovieViews_' . date('Y-m-d_H-i'));
        });

        return $grid;
    }

    /**
     * Show page
     */
    protected function detail($id)
    {
        $show = new Show(MovieView::findOrFail($id));
        $show->panel()->title('Movie View Details');

        $show->divider('View Information');
        $show->field('id', 'ID');
        $show->field('created_at', 'Created');
        $show->field('updated_at', 'Updated');
        $show->field('progress', 'Progress (seconds)');
        $show->field('max_progress', 'Max Progress (seconds)');
        $show->field('status', 'Status');

        $show->divider('Movie');
        $show->field('movie_model_id', 'Movie ID');
        $show->field('movie.title', 'Title');
        $show->field('movie.type', 'Type');
        $show->field('movie.status', 'Status');
        $show->field('movie.url', 'Video URL')->link();
        $show->field('movie.external_url', 'External URL')->link();
        $show->field('movie.thumbnail_url', 'Thumbnail')->image();
        $show->field('movie.vj', 'VJ');
        $show->field('movie.Category', 'Category');

        $show->divider('User');
        $show->field('user_id', 'User ID');
        $show->field('user.name', 'Name');
        $show->field('user.email', 'Email');
        $show->field('user.app_type', 'App Type');
        $show->field('user.platform', 'Device Platform');

        $show->divider('Device & Location');
        $show->field('ip_address', 'IP Address');
        $show->field('device', 'Device');
        $show->field('platform', 'Platform');
        $show->field('browser', 'Browser');
        $show->field('country', 'Country');
        $show->field('city', 'City');

        return $show;
    }

    /**
     * Form
     */
    protected function form()
    {
        $form = new Form(new MovieView());
        $form->number('movie_model_id', 'Movie ID');
        $form->number('user_id', 'User ID');
        $form->text('ip_address', 'IP Address');
        $form->text('device', 'Device');
        $form->text('platform', 'Platform');
        $form->text('browser', 'Browser');
        $form->text('country', 'Country');
        $form->text('city', 'City');
        $form->text('progress', 'Progress');
        $form->text('status', 'Status')->default('Active');
        return $form;
    }
}
