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

class SeriesMovieController extends AdminController
{
    protected $title = 'Series Movies';

    /**
     * Index with dashboard.
     */
    public function index(Content $content)
    {
        return $content
            ->title('Series (TV Shows)')
            ->description('Manage TV series, seasons, and episodes')
            ->body($this->compactDashboard())
            ->body($this->grid());
    }

    /**
     * Compact dashboard for series overview.
     */
    protected function compactDashboard(): string
    {
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

        // Top 5 series by episode count
        $topSeries = SeriesMovie::orderByDesc('total_episodes')->limit(5)->get();

        $html = '<style>
.smc-row{display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.smc-card{flex:1;min-width:120px;background:#fff;border-radius:6px;padding:12px 14px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:3px solid #ddd;position:relative}
.smc-card .smc-val{font-size:22px;font-weight:700;line-height:1.1}
.smc-card .smc-lbl{font-size:11px;color:#888;margin-top:2px;text-transform:uppercase;letter-spacing:.3px}
.smc-card .smc-icon{position:absolute;right:12px;top:12px;font-size:18px;opacity:.4}
.smc-box{background:#fff;border-radius:6px;padding:14px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.smc-box-title{font-size:12px;font-weight:600;color:#555;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px}
.smc-series-row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #f5f5f5;font-size:12px}
.smc-series-row:last-child{border:0}
.smc-bar{height:14px;border-radius:3px;transition:width .3s}
</style>';

        $html .= '<div class="smc-row">';
        $cards = [
            ['val' => number_format($total),     'lbl' => 'Total Series',   'color' => '#3498db', 'icon' => 'fa-tv'],
            ['val' => number_format($active),    'lbl' => 'Active',         'color' => '#27ae60', 'icon' => 'fa-check-circle'],
            ['val' => number_format($inactive),  'lbl' => 'Inactive',       'color' => '#e67e22', 'icon' => 'fa-pause-circle'],
            ['val' => number_format($failed),    'lbl' => 'Failed',         'color' => '#e74c3c', 'icon' => 'fa-times-circle'],
            ['val' => number_format($muno),      'lbl' => 'Munowatch',      'color' => '#8e44ad', 'icon' => 'fa-cloud'],
            ['val' => number_format($totalEps),  'lbl' => 'Total Episodes', 'color' => '#2c3e50', 'icon' => 'fa-film'],
            ['val' => number_format($activeEps), 'lbl' => 'Active Episodes','color' => '#16a085', 'icon' => 'fa-play'],
            ['val' => number_format($noUrl),     'lbl' => 'No URL (Eps)',   'color' => '#c0392b', 'icon' => 'fa-exclamation-triangle'],
        ];
        foreach ($cards as $c) {
            $html .= "<div class='smc-card' style='border-left-color:{$c['color']}'>";
            $html .= "<i class='fa {$c['icon']} smc-icon'></i>";
            $html .= "<div class='smc-val' style='color:{$c['color']}'>{$c['val']}</div>";
            $html .= "<div class='smc-lbl'>{$c['lbl']}</div>";
            $html .= "</div>";
        }
        $html .= '</div>';

        // Row 2: Top series + Status pie
        $html .= '<div class="smc-row">';

        // Col: Status breakdown pie
        $statusData = [
            ['label' => 'Active',   'cnt' => $active,   'color' => '#27ae60'],
            ['label' => 'Inactive', 'cnt' => $inactive, 'color' => '#e67e22'],
            ['label' => 'Failed',   'cnt' => $failed,   'color' => '#e74c3c'],
        ];
        $stTotal = array_sum(array_column($statusData, 'cnt')) ?: 1;
        $html .= '<div class="smc-box" style="flex:1;min-width:200px">';
        $html .= '<div class="smc-box-title">Series Status</div>';
        $stConic = []; $stLegend = ''; $stCum = 0;
        foreach ($statusData as $st) {
            $pct = round(($st['cnt'] / $stTotal) * 100, 1);
            $stConic[] = "{$st['color']} {$stCum}% " . ($stCum + $pct) . "%";
            $stCum += $pct;
            $stLegend .= "<div style='font-size:11px;line-height:1.8'><span style='display:inline-block;width:8px;height:8px;border-radius:50%;background:{$st['color']};margin-right:5px'></span>{$st['label']} <b>{$st['cnt']}</b> ({$pct}%)</div>";
        }
        $stConicStr = implode(', ', $stConic);
        $html .= "<div style='display:flex;align-items:center;gap:16px'>";
        $html .= "<div style='width:80px;height:80px;border-radius:50%;background:conic-gradient({$stConicStr});flex-shrink:0'></div>";
        $html .= "<div>{$stLegend}</div>";
        $html .= "</div></div>";

        // Col: Top series by episode count
        $html .= '<div class="smc-box" style="flex:2;min-width:300px">';
        $html .= '<div class="smc-box-title">Top Series by Episode Count</div>';
        if ($topSeries->isEmpty()) {
            $html .= '<div style="color:#aaa;font-size:12px;text-align:center;padding:10px">No series</div>';
        } else {
            $maxEps = $topSeries->max('total_episodes') ?: 1;
            foreach ($topSeries as $ts) {
                $pct = round(($ts->total_episodes / $maxEps) * 100);
                $title = mb_strlen($ts->title) > 35 ? mb_substr($ts->title, 0, 35) . '…' : $ts->title;
                $html .= "<div style='display:flex;align-items:center;margin-bottom:5px;font-size:12px'>";
                $html .= "<span style='width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#333' title='" . htmlspecialchars($ts->title) . "'>{$title}</span>";
                $html .= "<div style='flex:1;height:14px;background:#f0f0f0;border-radius:3px;margin:0 8px;overflow:hidden'><div class='smc-bar' style='width:{$pct}%;background:#3498db'></div></div>";
                $html .= "<span style='width:40px;text-align:right;font-weight:600;color:#333'>{$ts->total_episodes}</span>";
                $html .= "</div>";
            }
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new SeriesMovie());

        // Batch actions
        $grid->batchActions(function ($batch) {
            $batch->add(new \App\Admin\Actions\Post\SeriesMovieStatusChange());
            $batch->add(new \App\Admin\Actions\Post\BatchFixSeries());
            $batch->add(new \App\Admin\Actions\Post\BatchResolveDuplicateSeries());
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
            $filter->equal('is_muno', 'Munowatch')->select(['Yes' => 'Yes', 'No' => 'No']);
        });

        $grid->column('thumbnail', __('Thumbnail'))->image('', 50, 50)->sortable();
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
                $url = url('movies?category_id=' . $this->id);
                $color = $real > 0 ? '#27ae60' : '#e74c3c';
                return "<a href='{$url}' target='_blank' style='color:{$color};font-weight:600'>{$real}</a>";
            });

        $grid->column('total_views', __('Views'))->sortable();

        $grid->column('is_active', __('Active'))
            ->sortable()
            ->filter(['Yes' => 'Yes', 'No' => 'No', 'Failed' => 'Failed'])
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No', 'Failed' => 'Failed']);

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
                'id'             => $this->id,
                'title'          => $this->title,
                'thumbnail'      => $this->thumbnail,
                'category'       => $this->Category,
                'total_episodes' => $this->total_episodes,
                'total_seasons'  => $this->total_seasons,
                'is_active'      => $this->is_active,
                'vj'             => $this->vj,
                'genre'          => $this->genre,
                'language'       => $this->language,
                'year'           => $this->year,
                'series_code'    => $this->series_code,
                'munowatch_id'   => $this->munowatch_id,
                'is_muno'        => $this->is_muno ?? 'No',
                'external_url'   => $this->external_url,
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

            return '<button class="btn btn-xs btn-primary ugflix-series-play-btn" data-series="'
                . htmlspecialchars($seriesData, ENT_QUOTES, 'UTF-8')
                . '"><i class="fa fa-play"></i> 📺</button>';
        });

        // Legacy fix links (kept for backward compatibility)
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

    /**
     * Make a show builder.
     */
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

    /**
     * Make a form builder.
     */
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

        return $form;
    }
}
