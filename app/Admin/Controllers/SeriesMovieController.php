<?php

namespace App\Admin\Controllers;

use App\Models\SeriesMovie;
use App\Models\MovieModel;
use App\Models\Utils;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\DB;

/**
 * SeriesMovieController — manages series_movies with slug-based auto-filtering.
 *
 * Routes (all pointing here):
 *   series-movies           → all records
 *   series-movies-pending   → fix_status = pending
 *   series-movies-fixed     → fix_status = fixed
 *   series-movies-failed    → fix_status = error
 */
class SeriesMovieController extends AdminController
{
    protected $title = 'Series Movies';

    // ─── Slug detection helper ────────────────────────────
    private function detectSlug(): string
    {
        $segments = request()->segments();
        foreach ($segments as $seg) {
            if (in_array($seg, ['series-movies-pending', 'series-movies-fixed', 'series-movies-failed', 'series-movies'])) {
                return $seg;
            }
        }
        return 'series-movies';
    }

    private function slugLabel(): string
    {
        return match ($this->detectSlug()) {
            'series-movies-pending' => 'Pending Fix',
            'series-movies-fixed'   => 'Fixed',
            'series-movies-failed'  => 'Failed / Error',
            default                 => 'All Series',
        };
    }

    // ═══════════════════════════════════════════════════════
    //  INDEX — Compact Dashboard + Grid
    // ═══════════════════════════════════════════════════════

    public function index(Content $content)
    {
        $slug = $this->detectSlug();
        return $content
            ->title('Series — ' . $this->slugLabel())
            ->description('Manage TV series, seasons, episodes & fix tracking')
            ->body($this->compactDashboard($slug))
            ->body($this->grid());
    }

    // ═══════════════════════════════════════════════════════
    //  COMPACT DASHBOARD
    // ═══════════════════════════════════════════════════════

    protected function compactDashboard(string $slug): string
    {
        // ── Core counts ──
        $total     = SeriesMovie::count();
        $active    = SeriesMovie::where('is_active', 'Yes')->count();
        $inactive  = SeriesMovie::where('is_active', 'No')->count();
        $failed    = SeriesMovie::where('is_active', 'Failed')->count();
        $muno      = SeriesMovie::where('is_muno', 'Yes')->count();
        $totalEps  = MovieModel::where('type', 'Series')->count();
        $activeEps = MovieModel::where('type', 'Series')->where('status', 'Active')->count();
        $noUrl     = MovieModel::where('type', 'Series')
            ->where(function ($q) { $q->whereNull('url')->orWhere('url', ''); })
            ->count();

        // ── Fix tracking counts ──
        $fixPending = SeriesMovie::where('fix_status', 'pending')->count();
        $fixFixed   = SeriesMovie::where('fix_status', 'fixed')->count();
        $fixError   = SeriesMovie::where('fix_status', 'error')->count();
        $fixNone    = SeriesMovie::whereNull('fix_status')->count();
        $totalFixes = SeriesMovie::where('fix_counter', '>', 0)->sum('fix_counter');
        $lastFixed  = SeriesMovie::whereNotNull('fix_date')->max('fix_date');
        $fixRate    = $total > 0 ? round(($fixFixed / $total) * 100, 1) : 0;

        // ── Episode fix counts ──
        $epFixPending = MovieModel::where('type', 'Series')->where('fix_status', 'pending')->count();
        $epFixFixed   = MovieModel::where('type', 'Series')->where('fix_status', 'fixed')->count();
        $epFixError   = MovieModel::where('type', 'Series')->where('fix_status', 'error')->count();

        // ── Top 5 series ──
        $topSeries = SeriesMovie::orderByDesc('total_episodes')->limit(5)->get();

        // ── Top 5 most-fixed series ──
        $mostFixed = SeriesMovie::where('fix_counter', '>', 0)
            ->orderByDesc('fix_counter')->limit(5)->get();

        // ━━━ CSS ━━━
        $html = '<style>
.smc-row{display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap}
.smc-card{flex:1;min-width:110px;background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-left:3px solid #ddd;position:relative;transition:box-shadow .15s}
.smc-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.12)}
.smc-card a{color:inherit;text-decoration:none;display:block}
.smc-card .smc-val{font-size:20px;font-weight:700;line-height:1.1}
.smc-card .smc-lbl{font-size:10px;color:#888;margin-top:2px;text-transform:uppercase;letter-spacing:.3px}
.smc-card .smc-icon{position:absolute;right:10px;top:10px;font-size:16px;opacity:.35}
.smc-box{background:#fff;border-radius:6px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.smc-box-title{font-size:11px;font-weight:700;color:#555;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px}
.smc-bar{height:12px;border-radius:3px;transition:width .3s}
.smc-badge{display:inline-block;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:600;color:#fff}
.smc-nav{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap}
.smc-nav a{display:inline-block;padding:5px 12px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;border:1px solid #ddd;color:#555;transition:all .15s}
.smc-nav a:hover{border-color:#3498db;color:#3498db}
.smc-nav a.active{background:#3498db;color:#fff;border-color:#3498db}
</style>';

        // ── Slug navigation tabs ──
        $slugs = [
            'series-movies'         => ['All ('.$total.')', 'fa-tv'],
            'series-movies-pending' => ['Pending ('.$fixPending.')', 'fa-clock-o'],
            'series-movies-fixed'   => ['Fixed ('.$fixFixed.')', 'fa-check-circle'],
            'series-movies-failed'  => ['Failed ('.$fixError.')', 'fa-times-circle'],
        ];
        $html .= '<div class="smc-nav">';
        foreach ($slugs as $s => [$label, $icon]) {
            $cls = ($slug === $s) ? 'active' : '';
            $html .= "<a href='" . admin_url($s) . "' class='{$cls}'><i class='fa {$icon}'></i> {$label}</a>";
        }
        $html .= '</div>';

        // ━━━ ROW 1: Primary KPI Cards ━━━
        $html .= '<div class="smc-row">';
        $cards = [
            ['val' => number_format($total),     'lbl' => 'Total Series',    'color' => '#3498db', 'icon' => 'fa-tv',               'link' => 'series-movies'],
            ['val' => number_format($active),    'lbl' => 'Active',          'color' => '#27ae60', 'icon' => 'fa-check-circle',     'link' => 'series-movies?is_active=Yes'],
            ['val' => number_format($inactive),  'lbl' => 'Inactive',        'color' => '#e67e22', 'icon' => 'fa-pause-circle',     'link' => 'series-movies?is_active=No'],
            ['val' => number_format($failed),    'lbl' => 'Failed',          'color' => '#e74c3c', 'icon' => 'fa-times-circle',     'link' => 'series-movies?is_active=Failed'],
            ['val' => number_format($muno),      'lbl' => 'Munowatch',       'color' => '#8e44ad', 'icon' => 'fa-cloud',            'link' => 'series-movies?is_muno=Yes'],
            ['val' => number_format($totalEps),  'lbl' => 'Total Episodes',  'color' => '#2c3e50', 'icon' => 'fa-film',             'link' => 'movies-movies'],
            ['val' => number_format($activeEps), 'lbl' => 'Active Episodes', 'color' => '#16a085', 'icon' => 'fa-play',             'link' => 'movies-movies?status=Active'],
            ['val' => number_format($noUrl),     'lbl' => 'No URL (Eps)',    'color' => '#c0392b', 'icon' => 'fa-exclamation-triangle', 'link' => 'movies-movies'],
        ];
        foreach ($cards as $c) {
            $href = admin_url($c['link']);
            $html .= "<div class='smc-card' style='border-left-color:{$c['color']}'><a href='{$href}'>";
            $html .= "<i class='fa {$c['icon']} smc-icon'></i>";
            $html .= "<div class='smc-val' style='color:{$c['color']}'>{$c['val']}</div>";
            $html .= "<div class='smc-lbl'>{$c['lbl']}</div>";
            $html .= "</a></div>";
        }
        $html .= '</div>';

        // ━━━ ROW 2: Fix Tracking Cards ━━━
        $html .= '<div class="smc-row">';
        $fixCards = [
            ['val' => number_format($fixPending), 'lbl' => 'Fix Pending',    'color' => '#f39c12', 'icon' => 'fa-hourglass-half', 'link' => 'series-movies-pending'],
            ['val' => number_format($fixFixed),   'lbl' => 'Fix Completed',  'color' => '#27ae60', 'icon' => 'fa-wrench',         'link' => 'series-movies-fixed'],
            ['val' => number_format($fixError),   'lbl' => 'Fix Errors',     'color' => '#e74c3c', 'icon' => 'fa-bug',            'link' => 'series-movies-failed'],
            ['val' => $fixRate . '%',             'lbl' => 'Fix Rate',       'color' => '#3498db', 'icon' => 'fa-line-chart',      'link' => 'series-movies'],
            ['val' => number_format($totalFixes), 'lbl' => 'Total Attempts', 'color' => '#8e44ad', 'icon' => 'fa-repeat',         'link' => 'series-movies'],
            ['val' => $lastFixed ? \Carbon\Carbon::parse($lastFixed)->diffForHumans() : '—', 'lbl' => 'Last Fixed', 'color' => '#16a085', 'icon' => 'fa-clock-o', 'link' => 'series-movies-fixed'],
        ];
        foreach ($fixCards as $c) {
            $href = admin_url($c['link']);
            $html .= "<div class='smc-card' style='border-left-color:{$c['color']}'><a href='{$href}'>";
            $html .= "<i class='fa {$c['icon']} smc-icon'></i>";
            $html .= "<div class='smc-val' style='color:{$c['color']}'>{$c['val']}</div>";
            $html .= "<div class='smc-lbl'>{$c['lbl']}</div>";
            $html .= "</a></div>";
        }
        $html .= '</div>';

        // ━━━ ROW 3: Episode Fix Tracking Cards ━━━
        $html .= '<div class="smc-row">';
        $epFixCards = [
            ['val' => number_format($epFixPending), 'lbl' => 'Ep Fix Pending', 'color' => '#f39c12', 'icon' => 'fa-hourglass-half', 'link' => 'movies-movies'],
            ['val' => number_format($epFixFixed),   'lbl' => 'Ep Fix Done',    'color' => '#27ae60', 'icon' => 'fa-check',          'link' => 'movies-movies'],
            ['val' => number_format($epFixError),   'lbl' => 'Ep Fix Errors',  'color' => '#e74c3c', 'icon' => 'fa-exclamation',    'link' => 'movies-movies'],
        ];
        foreach ($epFixCards as $c) {
            $href = admin_url($c['link']);
            $html .= "<div class='smc-card' style='border-left-color:{$c['color']}'><a href='{$href}'>";
            $html .= "<i class='fa {$c['icon']} smc-icon'></i>";
            $html .= "<div class='smc-val' style='color:{$c['color']}'>{$c['val']}</div>";
            $html .= "<div class='smc-lbl'>{$c['lbl']}</div>";
            $html .= "</a></div>";
        }
        $html .= '</div>';

        // ━━━ ROW 4: Charts ━━━
        $html .= '<div class="smc-row">';

        // ── Status breakdown pie ──
        $statusData = [
            ['label' => 'Active',   'cnt' => $active,   'color' => '#27ae60'],
            ['label' => 'Inactive', 'cnt' => $inactive, 'color' => '#e67e22'],
            ['label' => 'Failed',   'cnt' => $failed,   'color' => '#e74c3c'],
        ];
        $stTotal = array_sum(array_column($statusData, 'cnt')) ?: 1;
        $html .= '<div class="smc-box" style="flex:1;min-width:180px">';
        $html .= '<div class="smc-box-title"><i class="fa fa-pie-chart"></i> Series Status</div>';
        $stConic = []; $stLegend = ''; $stCum = 0;
        foreach ($statusData as $st) {
            $pct = round(($st['cnt'] / $stTotal) * 100, 1);
            $stConic[] = "{$st['color']} {$stCum}% " . ($stCum + $pct) . "%";
            $stCum += $pct;
            $stLegend .= "<div style='font-size:10px;line-height:1.7'><span style='display:inline-block;width:7px;height:7px;border-radius:50%;background:{$st['color']};margin-right:4px'></span>{$st['label']} <b>{$st['cnt']}</b> ({$pct}%)</div>";
        }
        $stConicStr = implode(', ', $stConic);
        $html .= "<div style='display:flex;align-items:center;gap:12px'>";
        $html .= "<div style='width:70px;height:70px;border-radius:50%;background:conic-gradient({$stConicStr});flex-shrink:0'></div>";
        $html .= "<div>{$stLegend}</div>";
        $html .= "</div></div>";

        // ── Fix status pie ──
        $fixData = [
            ['label' => 'Pending', 'cnt' => $fixPending, 'color' => '#f39c12'],
            ['label' => 'Fixed',   'cnt' => $fixFixed,   'color' => '#27ae60'],
            ['label' => 'Error',   'cnt' => $fixError,   'color' => '#e74c3c'],
            ['label' => 'None',    'cnt' => $fixNone,    'color' => '#bdc3c7'],
        ];
        $fxTotal = array_sum(array_column($fixData, 'cnt')) ?: 1;
        $html .= '<div class="smc-box" style="flex:1;min-width:180px">';
        $html .= '<div class="smc-box-title"><i class="fa fa-wrench"></i> Fix Status</div>';
        $fxConic = []; $fxLegend = ''; $fxCum = 0;
        foreach ($fixData as $fx) {
            $pct = round(($fx['cnt'] / $fxTotal) * 100, 1);
            $fxConic[] = "{$fx['color']} {$fxCum}% " . ($fxCum + $pct) . "%";
            $fxCum += $pct;
            $fxLegend .= "<div style='font-size:10px;line-height:1.7'><span style='display:inline-block;width:7px;height:7px;border-radius:50%;background:{$fx['color']};margin-right:4px'></span>{$fx['label']} <b>{$fx['cnt']}</b> ({$pct}%)</div>";
        }
        $fxConicStr = implode(', ', $fxConic);
        $html .= "<div style='display:flex;align-items:center;gap:12px'>";
        $html .= "<div style='width:70px;height:70px;border-radius:50%;background:conic-gradient({$fxConicStr});flex-shrink:0'></div>";
        $html .= "<div>{$fxLegend}</div>";
        $html .= "</div></div>";

        // ── Top series by episode count ──
        $html .= '<div class="smc-box" style="flex:2;min-width:260px">';
        $html .= '<div class="smc-box-title"><i class="fa fa-bar-chart"></i> Top Series by Episode Count</div>';
        if ($topSeries->isEmpty()) {
            $html .= '<div style="color:#aaa;font-size:11px;text-align:center;padding:8px">No series</div>';
        } else {
            $maxEps = $topSeries->max('total_episodes') ?: 1;
            foreach ($topSeries as $ts) {
                $pct = round(($ts->total_episodes / $maxEps) * 100);
                $title = mb_strlen($ts->title) > 30 ? mb_substr($ts->title, 0, 30) . '…' : $ts->title;
                $fixBadge = match ($ts->fix_status ?? 'pending') {
                    'fixed' => '<span class="smc-badge" style="background:#27ae60">&#10003;</span>',
                    'error' => '<span class="smc-badge" style="background:#e74c3c">&#10007;</span>',
                    default => '<span class="smc-badge" style="background:#f39c12">...</span>',
                };
                $html .= "<div style='display:flex;align-items:center;margin-bottom:4px;font-size:11px'>";
                $html .= "<span style='width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#333' title='" . htmlspecialchars($ts->title) . "'>{$title}</span>";
                $html .= "<div style='flex:1;height:12px;background:#f0f0f0;border-radius:3px;margin:0 6px;overflow:hidden'><div class='smc-bar' style='width:{$pct}%;background:#3498db'></div></div>";
                $html .= "<span style='width:35px;text-align:right;font-weight:600;color:#333'>{$ts->total_episodes}</span> {$fixBadge}";
                $html .= "</div>";
            }
        }
        $html .= '</div>';

        $html .= '</div>'; // end ROW 4

        // ━━━ ROW 5: Most fixed series ━━━
        if ($mostFixed->isNotEmpty()) {
            $html .= '<div class="smc-row">';
            $html .= '<div class="smc-box" style="flex:1">';
            $html .= '<div class="smc-box-title"><i class="fa fa-repeat"></i> Most Fixed Series (by attempt count)</div>';
            $mfMax = $mostFixed->max('fix_counter') ?: 1;
            foreach ($mostFixed as $mf) {
                $pct = round(($mf->fix_counter / $mfMax) * 100);
                $title = mb_strlen($mf->title) > 40 ? mb_substr($mf->title, 0, 40) . '…' : $mf->title;
                $statusColor = match ($mf->fix_status ?? 'pending') {
                    'fixed' => '#27ae60',
                    'error' => '#e74c3c',
                    default => '#f39c12',
                };
                $html .= "<div style='display:flex;align-items:center;margin-bottom:4px;font-size:11px'>";
                $html .= "<span style='width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#333' title='" . htmlspecialchars($mf->title) . "'>{$title}</span>";
                $html .= "<div style='flex:1;height:12px;background:#f0f0f0;border-radius:3px;margin:0 6px;overflow:hidden'><div class='smc-bar' style='width:{$pct}%;background:{$statusColor}'></div></div>";
                $html .= "<span style='width:30px;text-align:right;font-weight:600;color:#333'>{$mf->fix_counter}</span>";
                $html .= "</div>";
            }
            $html .= '</div></div>';
        }

        return $html;
    }

    // ═══════════════════════════════════════════════════════
    //  GRID — with slug-based auto-filtering
    // ═══════════════════════════════════════════════════════

    protected function grid()
    {
        $grid = new Grid(new SeriesMovie());

        // ── Auto-filter by slug ──
        $slug = $this->detectSlug();
        switch ($slug) {
            case 'series-movies-pending':
                $grid->model()->where('fix_status', 'pending');
                break;
            case 'series-movies-fixed':
                $grid->model()->where('fix_status', 'fixed');
                break;
            case 'series-movies-failed':
                $grid->model()->where('fix_status', 'error');
                break;
        }

        // Batch actions
        $grid->batchActions(function ($batch) {
            $batch->add(new \App\Admin\Actions\Post\SeriesMovieStatusChange());
            $batch->add(new \App\Admin\Actions\Post\BatchFixSeries());
            $batch->add(new \App\Admin\Actions\Post\BatchResolveDuplicateSeries());
            $batch->add(new \App\Admin\Actions\Post\BatchFixSeriesType());
        });

        $grid->quickSearch('title')->placeholder('Search by title');
        $grid->model()->orderBy('id', 'desc');

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('title', 'Title');
            $filter->equal('Category', 'Category')->select(Utils::$CATEGORIES);
            $filter->equal('is_active', 'Status')->select([
                'Yes' => 'Active',
                'No' => 'Inactive',
                'Failed' => 'Failed',
            ]);
            $filter->equal('fix_status', 'Fix Status')->select([
                'pending' => 'Pending',
                'fixed'   => 'Fixed',
                'error'   => 'Error',
            ]);
            $filter->equal('is_muno', 'Munowatch')->select(['Yes' => 'Yes', 'No' => 'No']);
            $filter->where(function ($query) {
                if ($this->input === 'zero') {
                    $query->whereRaw('(SELECT COUNT(*) FROM movie_models WHERE movie_models.category_id = series_movies.id) = 0');
                } elseif ($this->input === 'has') {
                    $query->whereRaw('(SELECT COUNT(*) FROM movie_models WHERE movie_models.category_id = series_movies.id) > 0');
                }
            }, 'Episodes')->select(['zero' => '0 Episodes', 'has' => 'Has Episodes']);
        });

        // ── Columns ──
        $grid->column('thumbnail', __('Thumb'))->image('', 50, 50)->sortable();
        $grid->column('id', __('Id'))->sortable();
        $grid->column('title', __('Title'))->sortable()
            ->display(function ($title) {
                $display = mb_strlen($title) > 40 ? mb_substr($title, 0, 40) . '…' : $title;
                return "<strong title='" . htmlspecialchars($title) . "'>{$display}</strong>";
            });
        $grid->column('Category', __('Category'))->sortable();

        $grid->column('total_episodes', __('Episodes'))->sortable()
            ->display(function ($total_episodes) {
                $real = $this->episodes()->count();
                if ($real != $total_episodes || $real < 3) {
                    $this->total_episodes = $real;
                    $this->save();
                }
                $url = url('movies-movies?category_id=' . $this->id);
                $color = $real > 0 ? '#27ae60' : '#e74c3c';
                return "<a href='{$url}' target='_blank' style='color:{$color};font-weight:600'>{$real}</a>";
            });

        $grid->column('total_views', __('Views'))->sortable();

        $grid->column('is_active', __('Active'))
            ->sortable()
            ->filter(['Yes' => 'Yes', 'No' => 'No', 'Failed' => 'Failed'])
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No', 'Failed' => 'Failed']);

        // ── Fix status columns ──
        $grid->column('fix_status', __('Fix'))
            ->sortable()
            ->editable('select', ['pending' => 'Pending', 'fixed' => 'Fixed', 'error' => 'Error'])
            ->filter(['pending' => 'Pending', 'fixed' => 'Fixed', 'error' => 'Error']);

        $grid->column('fix_counter', __('#Fix'))
            ->sortable()
            ->display(function ($val) {
                $color = $val > 5 ? '#e74c3c' : ($val > 0 ? '#f39c12' : '#bdc3c7');
                return "<span style='color:{$color};font-weight:600'>{$val}</span>";
            });

        $grid->column('fix_date', __('Fixed At'))
            ->sortable()
            ->display(function ($val) {
                if (!$val) return '<span class="text-muted">—</span>';
                return \Carbon\Carbon::parse($val)->diffForHumans();
            });

        $grid->column('fix_error_message', __('Fix Error'))
            ->display(function ($val) {
                if (empty($val)) return '<span class="text-muted">—</span>';
                $short = mb_strlen($val) > 50 ? mb_substr($val, 0, 50) . '…' : $val;
                return "<span style='color:#e74c3c;font-size:11px' title='" . htmlspecialchars($val) . "'>{$short}</span>";
            })->hide();

        $grid->column('vj', __('VJ'))->display(function ($vj) {
            return $vj ?: '<span class="text-muted">—</span>';
        })->sortable();

        $grid->column('series_code', __('Code'))->display(function ($code) {
            if (!$code) return '<span class="text-muted">—</span>';
            return "<code style='font-size:11px'>{$code}</code>";
        });

        $grid->column('external_url', __('Source URL'))
            ->filter('like')
            ->sortable()
            ->display(function ($url) {
                if (empty($url)) return '<span class="text-muted">—</span>';
                $short = mb_strlen($url) > 30 ? mb_substr($url, 0, 30) . '…' : $url;
                return "<a href='{$url}' target='_blank' style='font-size:11px' title='" . htmlspecialchars($url) . "'>{$short}</a>";
            });

        // Debug Play button — opens series player with episode sidebar
        $grid->column('debug_play', __('Debug'))->display(function () {
            $seriesData = json_encode([
                'id'              => $this->id,
                'title'           => $this->title,
                'thumbnail'       => $this->thumbnail,
                'category'        => $this->Category,
                'total_episodes'  => $this->total_episodes,
                'total_seasons'   => $this->total_seasons,
                'is_active'       => $this->is_active,
                'vj'              => $this->vj,
                'genre'           => $this->genre,
                'language'        => $this->language,
                'year'            => $this->year,
                'series_code'     => $this->series_code,
                'munowatch_id'    => $this->munowatch_id,
                'is_muno'         => $this->is_muno ?? 'No',
                'external_url'    => $this->external_url,
                'fix_status'      => $this->fix_status,
                'fix_counter'     => $this->fix_counter,
                'fix_date'        => $this->fix_date,
                'fix_error_message' => $this->fix_error_message,
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

            return '<button class="btn btn-xs btn-primary ugflix-series-play-btn" data-series="'
                . htmlspecialchars($seriesData, ENT_QUOTES, 'UTF-8')
                . '"><i class="fa fa-play"></i> 📺</button>';
        });

        // Legacy fix links
        $grid->column('fix', __('Fix'))
            ->display(function () {
                $ur = url('fix-serries-movies?id=' . $this->id);
                return '<a href="' . $ur . '" target="_blank" class="btn btn-xs btn-default">Fix</a>';
            });

        $grid->column('fix_munowatch', __('Muno Fix'))
            ->display(function () {
                $ur = url('fix-munowatch-series?id=' . $this->id);
                return '<a href="' . $ur . '" target="_blank" class="btn btn-xs btn-success">MW Fix</a>';
            });

        $grid->perPages([10, 20, 50, 100, 200, 500, 1000]);

        $grid->export(function ($export) {
            $export->filename('Series_' . date('Y-m-d'));
        });

        return $grid;
    }

    // ═══════════════════════════════════════════════════════
    //  DETAIL
    // ═══════════════════════════════════════════════════════

    protected function detail($id)
    {
        $show = new Show(SeriesMovie::findOrFail($id));
        $show->panel()->title('Series Details');

        $show->field('id', __('Id'));
        $show->field('title', __('Title'));
        $show->field('Category', __('Category'));
        $show->field('description', __('Description'));
        $show->field('thumbnail', __('Thumbnail'))->image();
        $show->field('total_seasons', __('Total seasons'));
        $show->field('total_episodes', __('Total episodes'));
        $show->field('total_views', __('Total views'));
        $show->field('total_rating', __('Total rating'));
        $show->field('is_active', __('Active'));

        $show->divider();
        $show->field('fix_status', __('Fix Status'));
        $show->field('fix_error_message', __('Fix Error Message'));
        $show->field('fix_date', __('Fix Date'));
        $show->field('fix_counter', __('Fix Attempts'));

        $show->divider();
        $show->field('vj', __('VJ'));
        $show->field('genre', __('Genre'));
        $show->field('language', __('Language'));
        $show->field('year', __('Year'));
        $show->field('series_code', __('Series Code'));
        $show->field('munowatch_id', __('Munowatch ID'));
        $show->field('external_url', __('External URL'))->link();
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    // ═══════════════════════════════════════════════════════
    //  FORM
    // ═══════════════════════════════════════════════════════

    protected function form()
    {
        $form = new Form(new SeriesMovie());

        $form->text('title', __('Title'))
            ->creationRules('required|unique:series_movies')
            ->updateRules('required|unique:series_movies,title,{{id}}');
        $form->image('thumbnail', __('Thumbnail'));
        $form->radio('is_active', __('Active'))
            ->options(['Yes' => 'Yes', 'No' => 'No', 'Failed' => 'Failed'])
            ->default('Yes');
        $form->select('Category', __('Category'))->options(Utils::$CATEGORIES);
        $form->textarea('description', __('Description'))->rows(3);
        $form->text('vj', __('VJ'));
        $form->text('genre', __('Genre'));
        $form->text('series_code', __('Series Code'));
        $form->url('external_url', __('External URL'));

        $form->divider('Fix Tracking');
        $form->select('fix_status', __('Fix Status'))
            ->options(['pending' => 'Pending', 'fixed' => 'Fixed', 'error' => 'Error'])
            ->default('pending');
        $form->textarea('fix_error_message', __('Fix Error Message'))->rows(2);
        $form->datetime('fix_date', __('Fix Date'));
        $form->number('fix_counter', __('Fix Attempts'))->default(0);

        return $form;
    }
}
