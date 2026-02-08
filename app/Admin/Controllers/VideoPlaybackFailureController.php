<?php

namespace App\Admin\Controllers;

use App\Models\VideoPlaybackFailure;
use App\Models\MovieModel;
use App\Models\User;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\DB;

class VideoPlaybackFailureController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Video Playback Failures';

    /**
     * Index interface with dashboard.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->title('Video Playback Failures')
            ->description('Monitor and resolve video playback issues')
            ->body($this->compactDashboard())
            ->body($this->grid());
    }

    /**
     * Compact dashboard — small stat cards + charts, focused on unfixed failures.
     */
    protected function compactDashboard(): string
    {
        // ── Gather all stats in one go ──
        $total      = VideoPlaybackFailure::count();
        $unfixed    = VideoPlaybackFailure::where('status', '!=', 'resolved')
                        ->where(function ($q) { $q->where('fix_status', '!=', 'FIXED')->orWhereNull('fix_status'); })
                        ->count();
        $fixed      = VideoPlaybackFailure::where('fix_status', 'FIXED')->count();
        $fixFailed  = VideoPlaybackFailure::where('fix_status', 'FAILED')->count();
        $fixPending = VideoPlaybackFailure::where('fix_status', 'PENDING')->count();
        $neverAttempted = VideoPlaybackFailure::where(function ($q) { $q->whereNull('fix_status')->orWhere('fix_status', ''); })->count();
        $today      = VideoPlaybackFailure::whereDate('created_at', Carbon::today())->count();
        $yesterday  = VideoPlaybackFailure::whereDate('created_at', Carbon::yesterday())->count();
        $subscribers = VideoPlaybackFailure::where('has_subscription', true)
                        ->where('status', '!=', 'resolved')->count();

        $todayTrend = $today > $yesterday ? '▲' : ($today < $yesterday ? '▼' : '—');
        $todayColor = $today > $yesterday ? '#e74c3c' : ($today < $yesterday ? '#27ae60' : '#95a5a6');
        $fixAttempted = $fixed + $fixFailed + $fixPending;
        $fixRate    = $fixAttempted > 0 ? round(($fixed / $fixAttempted) * 100) : 0;

        // ── Error type breakdown (unfixed only) ──
        $errorTypes = VideoPlaybackFailure::where('status', '!=', 'resolved')
            ->where(function ($q) { $q->where('fix_status', '!=', 'FIXED')->orWhereNull('fix_status'); })
            ->selectRaw('COALESCE(error_type, "unknown") as etype, COUNT(*) as cnt')
            ->groupBy('etype')->orderByDesc('cnt')->get();
        $etColors = ['network'=>'#e74c3c','playback'=>'#e67e22','http_error'=>'#9b59b6','timeout'=>'#3498db','format'=>'#1abc9c','unknown'=>'#95a5a6'];

        // ── 7-day trend (unfixed only) ──
        $trendDays = []; $trendCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $trendDays[] = $d->format('D');
            $trendCounts[] = VideoPlaybackFailure::whereDate('created_at', $d)
                ->where(function ($q) { $q->where('fix_status', '!=', 'FIXED')->orWhereNull('fix_status'); })
                ->count();
        }
        $trendMax = max($trendCounts) ?: 1;

        // ── Top 8 failing movies (unfixed only) ──
        $topMovies = VideoPlaybackFailure::whereNotNull('movie_id')
            ->where('status', '!=', 'resolved')
            ->where(function ($q) { $q->where('fix_status', '!=', 'FIXED')->orWhereNull('fix_status'); })
            ->selectRaw('movie_id, movie_title, COUNT(*) as cnt')
            ->groupBy('movie_id', 'movie_title')
            ->orderByDesc('cnt')->limit(8)->get();

        // ── Device breakdown (unfixed only) ──
        $devices = VideoPlaybackFailure::where('status', '!=', 'resolved')
            ->where(function ($q) { $q->where('fix_status', '!=', 'FIXED')->orWhereNull('fix_status'); })
            ->selectRaw('COALESCE(device_os, "Unknown") as os, COUNT(*) as cnt')
            ->groupBy('os')->orderByDesc('cnt')->limit(5)->get();
        $devTotal = $devices->sum('cnt') ?: 1;
        $devColors = ['#3498db','#e67e22','#2ecc71','#9b59b6','#e74c3c'];

        // ── Build HTML ──
        $html = '<style>
.sf-row{display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.sf-card{flex:1;min-width:130px;background:#fff;border-radius:6px;padding:12px 14px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:3px solid #ddd;position:relative}
.sf-card .sf-val{font-size:22px;font-weight:700;line-height:1.1}
.sf-card .sf-lbl{font-size:11px;color:#888;margin-top:2px;text-transform:uppercase;letter-spacing:.3px}
.sf-card .sf-icon{position:absolute;right:12px;top:12px;font-size:18px;opacity:.4}
.sf-chart-box{background:#fff;border-radius:6px;padding:14px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.sf-chart-title{font-size:12px;font-weight:600;color:#555;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px}
.sf-bar-row{display:flex;align-items:center;margin-bottom:6px;font-size:12px}
.sf-bar-label{width:70px;color:#666;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sf-bar-track{flex:1;height:16px;background:#f0f0f0;border-radius:3px;margin:0 8px;overflow:hidden}
.sf-bar-fill{height:100%;border-radius:3px;transition:width .3s}
.sf-bar-val{width:35px;text-align:right;color:#333;font-weight:600}
.sf-pie-wrap{display:flex;align-items:center;gap:16px}
.sf-pie-legend{font-size:11px;line-height:1.8}
.sf-pie-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px}
.sf-movie-row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #f5f5f5;font-size:12px}
.sf-movie-row:last-child{border:0}
.sf-movie-title{color:#333;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sf-movie-cnt{font-weight:700;color:#e74c3c}
.sf-dev-row{display:flex;align-items:center;margin-bottom:5px;font-size:12px}
.sf-dev-bar{height:10px;border-radius:2px;margin:0 8px}
</style>';

        // ── Row 1: Key stat cards ──
        $html .= '<div class="sf-row">';
        $cards = [
            ['val'=>number_format($unfixed), 'lbl'=>'Unfixed', 'color'=>'#e74c3c', 'icon'=>'fa-exclamation-circle'],
            ['val'=>number_format($neverAttempted), 'lbl'=>'Never Attempted', 'color'=>'#e67e22', 'icon'=>'fa-question-circle'],
            ['val'=>number_format($today)." <small style='font-size:12px;color:{$todayColor}'>{$todayTrend}</small>", 'lbl'=>'Today', 'color'=>'#3498db', 'icon'=>'fa-clock-o'],
            ['val'=>number_format($fixFailed), 'lbl'=>'Fix Failed', 'color'=>'#c0392b', 'icon'=>'fa-times'],
            ['val'=>number_format($fixed), 'lbl'=>'Auto-Fixed', 'color'=>'#27ae60', 'icon'=>'fa-check'],
            ['val'=>"{$fixRate}%", 'lbl'=>'Fix Rate', 'color'=>'#2ecc71', 'icon'=>'fa-wrench'],
            ['val'=>number_format($subscribers), 'lbl'=>'Subscribers ⭐', 'color'=>'#8e44ad', 'icon'=>'fa-star'],
            ['val'=>number_format($total), 'lbl'=>'Total All Time', 'color'=>'#95a5a6', 'icon'=>'fa-database'],
        ];
        foreach ($cards as $c) {
            $html .= "<div class='sf-card' style='border-left-color:{$c['color']}'>";
            $html .= "<i class='fa {$c['icon']} sf-icon'></i>";
            $html .= "<div class='sf-val' style='color:{$c['color']}'>{$c['val']}</div>";
            $html .= "<div class='sf-lbl'>{$c['lbl']}</div>";
            $html .= "</div>";
        }
        $html .= '</div>';

        // ── Row 2: Charts (3 columns) ──
        $html .= '<div class="sf-row">';

        // ── Col 1: Error Type Pie + Fix Status Pie (stacked) ──
        $html .= '<div style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:10px">';

        // Error Type Pie
        $html .= '<div class="sf-chart-box">';
        $html .= '<div class="sf-chart-title">Error Types (Unfixed)</div>';
        $etTotal = $errorTypes->sum('cnt') ?: 1;
        $conicParts = []; $legendHtml = ''; $cumPct = 0;
        foreach ($errorTypes as $et) {
            $pct = round(($et->cnt / $etTotal) * 100, 1);
            $color = $etColors[$et->etype] ?? '#bdc3c7';
            $conicParts[] = "{$color} {$cumPct}% " . ($cumPct + $pct) . "%";
            $cumPct += $pct;
            $name = ucfirst($et->etype);
            $legendHtml .= "<div><span class='sf-pie-dot' style='background:{$color}'></span>{$name} <b>{$et->cnt}</b> ({$pct}%)</div>";
        }
        $conicStr = implode(', ', $conicParts);
        $html .= "<div class='sf-pie-wrap'>";
        $html .= "<div style='width:80px;height:80px;border-radius:50%;background:conic-gradient({$conicStr});flex-shrink:0'></div>";
        $html .= "<div class='sf-pie-legend'>{$legendHtml}</div>";
        $html .= "</div></div>";

        // Fix Status Pie
        $fixStatusData = [
            ['label' => 'Fixed',   'cnt' => $fixed,    'color' => '#27ae60'],
            ['label' => 'Failed',  'cnt' => $fixFailed, 'color' => '#e74c3c'],
            ['label' => 'Pending', 'cnt' => $fixPending, 'color' => '#e67e22'],
        ];
        $fsTotal = array_sum(array_column($fixStatusData, 'cnt')) ?: 1;
        $html .= '<div class="sf-chart-box">';
        $html .= '<div class="sf-chart-title">Fix Status</div>';
        $fsConic = []; $fsLegend = ''; $fsCum = 0;
        foreach ($fixStatusData as $fs) {
            $fsPct = round(($fs['cnt'] / $fsTotal) * 100, 1);
            $fsConic[] = "{$fs['color']} {$fsCum}% " . ($fsCum + $fsPct) . "%";
            $fsCum += $fsPct;
            $fsLegend .= "<div><span class='sf-pie-dot' style='background:{$fs['color']}'></span>{$fs['label']} <b>{$fs['cnt']}</b> ({$fsPct}%)</div>";
        }
        $fsConicStr = implode(', ', $fsConic);
        $html .= "<div class='sf-pie-wrap'>";
        $html .= "<div style='width:80px;height:80px;border-radius:50%;background:conic-gradient({$fsConicStr});flex-shrink:0'></div>";
        $html .= "<div class='sf-pie-legend'>{$fsLegend}</div>";
        $html .= "</div></div>";

        $html .= '</div>'; // end col 1

        // ── Col 2: 7-Day Trend Bar Chart ──
        $html .= '<div class="sf-chart-box" style="flex:1.2;min-width:240px">';
        $html .= '<div class="sf-chart-title">7-Day Trend (Unfixed)</div>';
        foreach ($trendDays as $i => $day) {
            $cnt = $trendCounts[$i];
            $pct = round(($cnt / $trendMax) * 100);
            $barColor = $cnt > ($trendMax * 0.7) ? '#e74c3c' : ($cnt > ($trendMax * 0.4) ? '#e67e22' : '#3498db');
            $html .= "<div class='sf-bar-row'>";
            $html .= "<div class='sf-bar-label'>{$day}</div>";
            $html .= "<div class='sf-bar-track'><div class='sf-bar-fill' style='width:{$pct}%;background:{$barColor}'></div></div>";
            $html .= "<div class='sf-bar-val'>{$cnt}</div>";
            $html .= "</div>";
        }
        $html .= '</div>';

        // ── Col 3: Top Failing Movies + Device Split ──
        $html .= '<div style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:10px">';

        // Top failing movies
        $html .= '<div class="sf-chart-box" style="flex:1">';
        $html .= '<div class="sf-chart-title">Top Failing Movies (Unfixed)</div>';
        if ($topMovies->isEmpty()) {
            $html .= '<div style="color:#aaa;font-size:12px;text-align:center;padding:10px">No unfixed failures</div>';
        } else {
            foreach ($topMovies as $tm) {
                $title = $tm->movie_title ?: 'Movie #' . $tm->movie_id;
                $displayTitle = mb_strlen($title) > 28 ? mb_substr($title, 0, 28) . '…' : $title;
                $html .= "<div class='sf-movie-row'>";
                $html .= "<a class='sf-movie-title' href='movies-movies/{$tm->movie_id}' title='" . htmlspecialchars($title) . "'>{$displayTitle}</a>";
                $html .= "<span class='sf-movie-cnt'>{$tm->cnt}</span>";
                $html .= "</div>";
            }
        }
        $html .= '</div>';

        // Device breakdown mini
        $html .= '<div class="sf-chart-box">';
        $html .= '<div class="sf-chart-title">Devices (Unfixed)</div>';
        foreach ($devices as $di => $dev) {
            $pct = round(($dev->cnt / $devTotal) * 100);
            $dc = $devColors[$di] ?? '#bdc3c7';
            $osIcon = stripos($dev->os, 'android') !== false ? '🤖' : (stripos($dev->os, 'ios') !== false ? '🍎' : '📱');
            $html .= "<div class='sf-dev-row'>";
            $html .= "<span style='width:80px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis'>{$osIcon} {$dev->os}</span>";
            $html .= "<div class='sf-dev-bar' style='flex:1;background:{$dc};width:{$pct}%;max-width:{$pct}%'></div>";
            $html .= "<span style='width:50px;text-align:right;font-weight:600'>{$dev->cnt}</span>";
            $html .= "</div>";
        }
        $html .= '</div>';

        $html .= '</div>'; // end col 3
        $html .= '</div>'; // end row 2

        return $html;
    }
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new VideoPlaybackFailure());
        $grid->model()->with(['user', 'movie'])->orderBy('created_at', 'desc');

        // Quick search
        $grid->quickSearch('user_name', 'user_email', 'movie_title', 'error_message');

        // Columns
        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('user_name', __('User'))->display(function () {
            // Try stored user_name first, then fall back to related user
            $userName = $this->user_name;
            if (empty($userName) || $userName === 'Unknown') {
                if ($this->user) {
                    $userName = $this->user->name;
                }
            }
            $userName = $userName ?: 'Unknown';
            
            // Get email from stored field or related user
            $email = $this->user_email;
            if (empty($email) && $this->user) {
                $email = $this->user->email;
            }
            
            $userId = $this->user_id ? "(ID: {$this->user_id})" : '';
            $subscribed = $this->has_subscription ? '⭐' : '';
            $emailDisplay = $email ? "<br><small class='text-muted'>{$email}</small>" : '';
            
            if ($this->user_id && $userName !== 'Unknown') {
                return "{$subscribed} <a href='users/{$this->user_id}'><strong>{$userName}</strong></a> <small class='text-muted'>{$userId}</small>{$emailDisplay}";
            }
            return "{$subscribed} <strong>{$userName}</strong> <small class='text-muted'>{$userId}</small>{$emailDisplay}";
        });
        
        $grid->column('movie_title', __('Movie'))->display(function () {
            // Try stored movie_title first, then fall back to related movie
            $title = $this->movie_title;
            if (empty($title) || $title === 'Unknown Movie') {
                if ($this->movie) {
                    $title = $this->movie->title;
                }
            }
            
            if ($title) {
                $displayTitle = mb_strlen($title) > 35 ? mb_substr($title, 0, 35) . '...' : $title;
                $movieId = $this->movie_id ? " <small class='text-muted'>(ID: {$this->movie_id})</small>" : '';
                
                if ($this->movie_id) {
                    return "<a href='movies-movies/{$this->movie_id}' title='" . htmlspecialchars($title) . "'><strong>{$displayTitle}</strong></a>{$movieId}";
                }
                return "<strong>{$displayTitle}</strong>{$movieId}";
            }
            return '<em class="text-muted">Unknown Movie</em>' . ($this->movie_id ? " <small>(ID: {$this->movie_id})</small>" : '');
        });
        
        $grid->column('url', __('Video URL'))->display(function () {
            $movie = $this->movie;
            if (!$movie && $this->movie_id) $movie = \App\Models\MovieModel::find($this->movie_id);
            $url = $movie->url ?? null;
            if (!$url) return '<span class="text-muted">—</span>';
            $short = mb_strlen($url) > 40 ? mb_substr($url, 0, 40) . '…' : $url;
            return "<a href='" . htmlspecialchars($url) . "' target='_blank' title='" . htmlspecialchars($url) . "' style='font-size:11px;word-break:break-all'>{$short}</a>";
        });

        $grid->column('external_url', __('Page URL'))->display(function () {
            $movie = $this->movie;
            if (!$movie && $this->movie_id) $movie = \App\Models\MovieModel::find($this->movie_id);
            $url = $movie->external_url ?? null;
            if (!$url) return '<span class="text-muted">—</span>';
            $short = mb_strlen($url) > 40 ? mb_substr($url, 0, 40) . '…' : $url;
            return "<a href='" . htmlspecialchars($url) . "' target='_blank' title='" . htmlspecialchars($url) . "' style='font-size:11px;word-break:break-all'>{$short}</a>";
        });

        $grid->column('error_type', __('Error Type'))
            ->display(function ($type) {
                $icons = [
                    'network' => '📡',
                    'playback' => '▶️',
                    'timeout' => '⏱️',
                    'http_error' => '🌐',
                    'format' => '📁',
                    'unknown' => '❓',
                ];
                return ($icons[$type] ?? '❓') . ' ' . ucfirst($type ?? 'Unknown');
            })
            ->label([
                'network' => 'danger',
                'playback' => 'warning',
                'timeout' => 'info',
                'http_error' => 'danger',
                'format' => 'warning',
                'unknown' => 'default',
            ])
            ->sortable()
            ->filter([
                'network' => 'Network',
                'playback' => 'Playback',
                'timeout' => 'Timeout',
                'http_error' => 'HTTP Error',
                'format' => 'Format',
                'unknown' => 'Unknown',
            ]);
        
        $grid->column('error_message', __('Error'))->display(function ($message) {
            if (!$message) return '-';
            $short = mb_substr($message, 0, 50);
            $full = htmlspecialchars($message);
            return "<span title='{$full}'>{$short}" . (mb_strlen($message) > 50 ? '...' : '') . "</span>";
        });
        
        $grid->column('device_os', __('Device'))->display(function () {
            $os = $this->device_os ?? 'Unknown';
            $model = $this->device_model ?? '';
            $icons = ['android' => '🤖', 'ios' => '🍎'];
            $icon = '📱';
            foreach ($icons as $key => $emoji) {
                if (stripos($os, $key) !== false) {
                    $icon = $emoji;
                    break;
                }
            }
            return "{$icon} {$os}" . ($model ? "<br><small>{$model}</small>" : '');
        });
        
        $grid->column('retry_count', __('Retries'))
            ->display(function ($count) {
                $color = $count > 3 ? 'danger' : ($count > 1 ? 'warning' : 'default');
                return "<span class='label label-{$color}'>{$count}</span>";
            })->sortable();
        
        $grid->column('has_subscription', __('Sub'))
            ->display(function ($has) {
                return $has ? '⭐ Yes' : 'No';
            })
            ->sortable()
            ->filter([
                1 => 'Subscribed',
                0 => 'Free User',
            ]);
        
        $grid->column('status', __('Status'))
            ->display(function ($status) {
                $icons = [
                    'pending' => '⏳',
                    'investigating' => '🔍',
                    'resolved' => '✅',
                    'ignored' => '🚫',
                ];
                return ($icons[$status] ?? '') . ' ' . ucfirst($status);
            })
            ->editable('select', [
                'pending' => 'Pending',
                'investigating' => 'Investigating',
                'resolved' => 'Resolved',
                'ignored' => 'Ignored',
            ])
            ->sortable()
            ->filter([
                'pending' => 'Pending',
                'investigating' => 'Investigating',
                'resolved' => 'Resolved',
                'ignored' => 'Ignored',
            ]);
        
        // Fix status columns
        $grid->column('fix_status', __('Fix Status'))
            ->display(function ($status) {
                $icons = ['FIXED' => '✅', 'FAILED' => '❌', 'PENDING' => '⏳'];
                $colors = ['FIXED' => 'success', 'FAILED' => 'danger', 'PENDING' => 'warning'];
                if (empty($status)) return '<span class="label label-default">—</span>';
                $icon = $icons[$status] ?? '';
                $color = $colors[$status] ?? 'default';
                return "<span class='label label-{$color}'>{$icon} {$status}</span>";
            })
            ->sortable()
            ->filter([
                'FIXED' => 'Fixed',
                'FAILED' => 'Failed',
                'PENDING' => 'Pending',
            ]);

        $grid->column('number_of_fix_attempts', __('Fix Tries'))
            ->display(function ($count) {
                if (!$count) return '<span class="text-muted">0</span>';
                $color = $count > 3 ? 'danger' : ($count > 1 ? 'warning' : 'info');
                return "<span class='label label-{$color}'>{$count}</span>";
            })->sortable();

        $grid->column('last_fix_attempt_at', __('Last Fix'))
            ->display(function ($val) {
                if (!$val) return '<span class="text-muted">—</span>';
                return Carbon::parse($val)->format('M d, H:i');
            })->sortable();

        // Debug Player — Play button to test the movie's video URL
        $grid->column('debug_play', __('Debug Play'))->display(function () {
            if (!$this->movie_id) return '<span class="text-muted">—</span>';
            $movie = $this->movie;
            if (!$movie) $movie = \App\Models\MovieModel::find($this->movie_id);
            if (!$movie) return '<span class="text-muted">No movie</span>';

            $movieData = json_encode([
                'id' => $movie->id,
                'title' => $movie->title,
                'url' => $movie->url,
                'external_url' => $movie->external_url,
                'firebase_video_url' => $movie->firebase_video_url,
                'old_video_url' => $movie->old_video_url,
                'type' => $movie->type,
                'status' => $movie->status,
                'genre' => $movie->genre,
                'vj' => $movie->vj,
                'thumbnail_url' => $movie->thumbnail_url,
                'content_type' => $movie->content_type,
                'content_is_video' => $movie->content_is_video,
                'firebase_transfer_successful' => $movie->firebase_transfer_successful,
                'category' => $movie->category,
                'episode_number' => $movie->episode_number,
                'season_number' => $movie->season_number,
                'series_title' => $movie->series_title,
                'episode_title' => $movie->episode_title,
                'duration' => $movie->duration,
                'year' => $movie->year,
                'language' => $movie->language,
                'munowatch_id' => $movie->munowatch_id,
                'views_count' => $movie->views_count,
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
            return '<button class="btn btn-xs btn-primary ugflix-debug-play-btn" data-movie="' . htmlspecialchars($movieData, ENT_QUOTES, 'UTF-8') . '"><i class="fa fa-play"></i></button>';
        });

        $grid->column('created_at', __('Failed At'))
            ->display(function ($createdAt) {
                return Carbon::parse($createdAt)->format('M d, H:i');
            })->sortable();

        // Expandable row with full movie details
        $grid->column('expand', __('Details'))->expand(function ($model) {
            // Get movie from relationship or fetch by ID
            $movie = $model->movie;
            if (!$movie && $model->movie_id) {
                $movie = \App\Models\MovieModel::find($model->movie_id);
            }
            
            $html = '<div style="padding: 15px; background: #f9f9f9; border-radius: 8px;">';
            
            // === MOVIE INFORMATION ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px;">🎬 Movie Information</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            
            if ($movie) {
                $html .= '<tr><td style="width: 180px; font-weight: bold;">Movie Title</td><td>' . htmlspecialchars($movie->title ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Movie ID</td><td>' . ($movie->id ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Status</td><td><span class="label label-' . ($movie->status === 'Active' ? 'success' : 'danger') . '">' . ($movie->status ?? 'N/A') . '</span></td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Type</td><td>' . ($movie->type ?? 'N/A') . '</td></tr>';
                
                // Playback URL with clickable link
                $playbackUrl = $movie->url ?? $model->original_url ?? $model->transformed_url;
                if ($playbackUrl) {
                    $html .= '<tr><td style="font-weight: bold;">🔗 Playback URL</td><td>';
                    $html .= '<a href="' . htmlspecialchars($playbackUrl) . '" target="_blank" class="btn btn-sm btn-primary" style="margin-right: 10px;">';
                    $html .= '<i class="fa fa-external-link"></i> Open Video</a>';
                    $html .= '<code style="word-break: break-all; font-size: 11px;">' . htmlspecialchars($playbackUrl) . '</code>';
                    $html .= '</td></tr>';
                }
                
                // Thumbnail
                if ($movie->thumbnail_url) {
                    $html .= '<tr><td style="font-weight: bold;">Thumbnail</td><td>';
                    $html .= '<img src="' . htmlspecialchars($movie->thumbnail_url) . '" style="max-width: 150px; max-height: 100px; border-radius: 4px;">';
                    $html .= '</td></tr>';
                }
                
                $html .= '<tr><td style="font-weight: bold;">Views</td><td>' . number_format($movie->views ?? 0) . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Category</td><td>' . ($movie->Category ?? 'N/A') . '</td></tr>';
            } else {
                $html .= '<tr><td colspan="2" class="text-center text-muted">Movie not found in database</td></tr>';
                
                // Still show URLs from the failure record
                if ($model->original_url) {
                    $html .= '<tr><td style="font-weight: bold;">🔗 Original URL</td><td>';
                    $html .= '<a href="' . htmlspecialchars($model->original_url) . '" target="_blank" class="btn btn-sm btn-warning">';
                    $html .= '<i class="fa fa-external-link"></i> Open</a> ';
                    $html .= '<code style="word-break: break-all; font-size: 11px;">' . htmlspecialchars($model->original_url) . '</code>';
                    $html .= '</td></tr>';
                }
            }
            $html .= '</table>';
            
            // === URL DETAILS ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #28a745; padding-bottom: 8px;">🔗 URL Details (from failure report)</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            
            if ($model->original_url) {
                $html .= '<tr><td style="width: 180px; font-weight: bold;">Original URL</td><td>';
                $html .= '<a href="' . htmlspecialchars($model->original_url) . '" target="_blank" class="btn btn-xs btn-info"><i class="fa fa-external-link"></i></a> ';
                $html .= '<code style="word-break: break-all; font-size: 11px;">' . htmlspecialchars($model->original_url) . '</code>';
                $html .= '</td></tr>';
            }
            
            if ($model->transformed_url) {
                $html .= '<tr><td style="font-weight: bold;">Transformed URL</td><td>';
                $html .= '<a href="' . htmlspecialchars($model->transformed_url) . '" target="_blank" class="btn btn-xs btn-success"><i class="fa fa-external-link"></i></a> ';
                $html .= '<code style="word-break: break-all; font-size: 11px;">' . htmlspecialchars($model->transformed_url) . '</code>';
                $html .= '</td></tr>';
            }
            $html .= '</table>';
            
            // === ERROR DETAILS ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #dc3545; padding-bottom: 8px;">❌ Error Details</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            $html .= '<tr><td style="width: 180px; font-weight: bold;">Error Type</td><td><span class="label label-danger">' . ucfirst($model->error_type ?? 'Unknown') . '</span></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Error Code</td><td>' . ($model->error_code ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Error Message</td><td><pre style="white-space: pre-wrap; word-wrap: break-word; max-height: 200px; overflow-y: auto; background: #fff3cd; padding: 10px; border-radius: 4px;">' . htmlspecialchars($model->error_message ?? 'N/A') . '</pre></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Retry Count</td><td>' . ($model->retry_count ?? 0) . '</td></tr>';
            $html .= '</table>';
            
            // === USER DETAILS ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #17a2b8; padding-bottom: 8px;">👤 User Details</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            $html .= '<tr><td style="width: 180px; font-weight: bold;">User ID</td><td>' . ($model->user_id ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Name</td><td>' . htmlspecialchars($model->user_name ?? ($model->user->name ?? 'Unknown')) . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Email</td><td>' . htmlspecialchars($model->user_email ?? ($model->user->email ?? 'N/A')) . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Phone</td><td>' . htmlspecialchars($model->user_phone ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Has Subscription</td><td>' . ($model->has_subscription ? '<span class="label label-success">⭐ Yes</span>' : '<span class="label label-default">No</span>') . '</td></tr>';
            if ($model->has_subscription) {
                $html .= '<tr><td style="font-weight: bold;">Subscription Type</td><td>' . ($model->subscription_type ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Expires At</td><td>' . ($model->subscription_expires_at ? Carbon::parse($model->subscription_expires_at)->format('M d, Y H:i') : 'N/A') . '</td></tr>';
            }
            $html .= '</table>';
            
            // === DEVICE & APP INFO ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #6c757d; padding-bottom: 8px;">📱 Device & App Info</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            $html .= '<tr><td style="width: 180px; font-weight: bold;">Device Model</td><td>' . htmlspecialchars($model->device_model ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Device OS</td><td>' . htmlspecialchars($model->device_os ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">OS Version</td><td>' . htmlspecialchars($model->device_os_version ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">App Version</td><td>' . htmlspecialchars($model->app_version ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Player Type</td><td>' . htmlspecialchars($model->player_type ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Screen Name</td><td>' . htmlspecialchars($model->screen_name ?? 'N/A') . '</td></tr>';
            $html .= '</table>';
            
            // === NETWORK INFO ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #ffc107; padding-bottom: 8px;">🌐 Network Info</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            $html .= '<tr><td style="width: 180px; font-weight: bold;">Network Type</td><td>' . htmlspecialchars($model->network_type ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">IP Address</td><td>' . htmlspecialchars($model->ip_address ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">User Agent</td><td><small>' . htmlspecialchars($model->user_agent ?? 'N/A') . '</small></td></tr>';
            $html .= '</table>';
            
            // === RESOLUTION STATUS ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #28a745; padding-bottom: 8px;">📋 Resolution Status</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            $statusColors = ['pending' => 'warning', 'investigating' => 'info', 'resolved' => 'success', 'ignored' => 'default'];
            $html .= '<tr><td style="width: 180px; font-weight: bold;">Status</td><td><span class="label label-' . ($statusColors[$model->status] ?? 'default') . '">' . ucfirst($model->status ?? 'N/A') . '</span></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Admin Notes</td><td>' . htmlspecialchars($model->admin_notes ?? 'No notes yet') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Resolved At</td><td>' . ($model->resolved_at ? Carbon::parse($model->resolved_at)->format('M d, Y H:i') : 'Not resolved') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Created At</td><td>' . Carbon::parse($model->created_at)->format('M d, Y H:i:s') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Updated At</td><td>' . Carbon::parse($model->updated_at)->format('M d, Y H:i:s') . '</td></tr>';
            $html .= '</table>';
            
            // === ADDITIONAL DATA ===
            if ($model->additional_data) {
                $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #6f42c1; padding-bottom: 8px;">📦 Additional Data</h4>';
                $html .= '<pre style="white-space: pre-wrap; word-wrap: break-word; max-height: 300px; overflow-y: auto; background: #e9ecef; padding: 10px; border-radius: 4px;">' . htmlspecialchars(json_encode($model->additional_data, JSON_PRETTY_PRINT)) . '</pre>';
            }
            
            $html .= '</div>';
            
            return $html;
        });

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1/3, function ($filter) {
                $filter->like('user_name', 'User Name');
                $filter->like('user_email', 'User Email');
                $filter->equal('user_id', 'User ID');
            });

            $filter->column(1/3, function ($filter) {
                $filter->like('movie_title', 'Movie Title');
                $filter->equal('movie_id', 'Movie ID');
                $filter->equal('error_type', 'Error Type')->select([
                    'network' => 'Network',
                    'playback' => 'Playback',
                    'timeout' => 'Timeout',
                    'http_error' => 'HTTP Error',
                    'format' => 'Format',
                    'unknown' => 'Unknown',
                ]);
            });

            $filter->column(1/3, function ($filter) {
                $filter->equal('status', 'Status')->select([
                    'pending' => 'Pending',
                    'investigating' => 'Investigating',
                    'resolved' => 'Resolved',
                    'ignored' => 'Ignored',
                ]);
                $filter->equal('has_subscription', 'Subscription')->select([
                    1 => 'Subscribed',
                    0 => 'Free User',
                ]);
                $filter->between('created_at', 'Date Range')->datetime();
            });

            $filter->like('device_os', 'Device OS');
            $filter->like('error_message', 'Error Contains');
        });

        // Batch actions
        $grid->batchActions(function ($batch) {
            $batch->disableDelete();
            $batch->add(new \App\Admin\Actions\BatchFixFailureMovies());
            $batch->add(new \App\Admin\Actions\BatchResolveFailures());
            $batch->add(new \App\Admin\Actions\BatchIgnoreFailures());
        });

        $grid->perPages([10, 20, 50, 100, 200, 500]);

        // Export
        $grid->export(function ($export) {
            $export->filename('VideoFailures_' . date('Y-m-d_H-i'));
        });

        // Disable create (failures are auto-logged)
        $grid->disableCreateButton();

        return $grid;
    }


    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(VideoPlaybackFailure::findOrFail($id));

        $show->panel()->title('Video Playback Failure Details');

        // User Information
        $show->divider('User Information');
        $show->field('user_id', __('User ID'));
        $show->field('user_name', __('User Name'));
        $show->field('user_email', __('User Email'));
        $show->field('user_phone', __('User Phone'));

        // Movie Information
        $show->divider('Movie Information');
        $show->field('movie_id', __('Movie ID'));
        $show->field('movie_title', __('Movie Title'));
        $show->field('original_url', __('Original URL'))->link();
        $show->field('transformed_url', __('Transformed URL'))->link();

        // Failure Details
        $show->divider('Failure Details');
        $show->field('error_type', __('Error Type'))->label();
        $show->field('error_code', __('Error Code'));
        $show->field('error_message', __('Error Message'))->as(function ($message) {
            return "<pre style='white-space: pre-wrap;'>{$message}</pre>";
        });
        $show->field('retry_count', __('Retry Count'));

        // Device & App Information
        $show->divider('Device & App Information');
        $show->field('device_model', __('Device Model'));
        $show->field('device_os', __('Device OS'));
        $show->field('device_os_version', __('OS Version'));
        $show->field('app_version', __('App Version'));
        $show->field('player_type', __('Player Type'))->label();

        // Network Information
        $show->divider('Network Information');
        $show->field('network_type', __('Network Type'));
        $show->field('ip_address', __('IP Address'));
        $show->field('user_agent', __('User Agent'));

        // Subscription Status
        $show->divider('Subscription Status');
        $show->field('has_subscription', __('Has Subscription'))->as(function ($value) {
            return $value ? '⭐ Yes (Subscribed User)' : 'No (Free User)';
        });
        $show->field('subscription_type', __('Subscription Type'));
        $show->field('subscription_expires_at', __('Subscription Expires'));

        // Context
        $show->divider('Context');
        $show->field('screen_name', __('Screen Name'));
        $show->field('additional_data', __('Additional Data'))->json();

        // Resolution Status
        $show->divider('Resolution Status');
        $show->field('status', __('Status'))->as(function ($status) {
            $icons = ['pending' => '⏳', 'investigating' => '🔍', 'resolved' => '✅', 'ignored' => '🚫'];
            return ($icons[$status] ?? '') . ' ' . ucfirst($status);
        });
        $show->field('admin_notes', __('Admin Notes'));
        $show->field('resolved_at', __('Resolved At'));

        // Timestamps
        $show->divider('Timestamps');
        $show->field('created_at', __('Created At'));
        $show->field('updated_at', __('Updated At'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new VideoPlaybackFailure());

        $form->tab('Resolution', function ($form) {
            $form->select('status', __('Status'))->options([
                'pending' => '⏳ Pending',
                'investigating' => '🔍 Investigating',
                'resolved' => '✅ Resolved',
                'ignored' => '🚫 Ignored',
            ])->default('pending');

            $form->textarea('admin_notes', __('Admin Notes'))
                ->rows(5)
                ->help('Add notes about the investigation or resolution');

            $form->datetime('resolved_at', __('Resolved At'))
                ->help('Auto-filled when status is set to "Resolved"');
        });

        $form->tab('Failure Details (Read-Only)', function ($form) {
            $form->display('id', __('ID'));
            $form->display('user_name', __('User'));
            $form->display('user_email', __('Email'));
            $form->display('movie_title', __('Movie'));
            $form->display('error_type', __('Error Type'));
            $form->display('error_message', __('Error Message'));
            $form->display('error_code', __('Error Code'));
            $form->display('retry_count', __('Retry Count'));
            $form->display('device_os', __('Device OS'));
            $form->display('device_model', __('Device Model'));
            $form->display('app_version', __('App Version'));
            $form->display('has_subscription', __('Has Subscription'))->with(function ($value) {
                return $value ? 'Yes' : 'No';
            });
            $form->display('created_at', __('Failed At'));
        });

        $form->tab('URLs (Read-Only)', function ($form) {
            $form->display('original_url', __('Original URL'));
            $form->display('transformed_url', __('Transformed URL'));
        });

        // Auto-fill resolved_at when status is resolved
        $form->saving(function (Form $form) {
            if ($form->status === 'resolved' && !$form->resolved_at) {
                $form->resolved_at = now();
            }
        });

        return $form;
    }
}
