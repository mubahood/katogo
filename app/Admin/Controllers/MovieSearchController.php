<?php

namespace App\Admin\Controllers;

use App\Models\MovieSearch;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\DB;

class MovieSearchController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Movie Search Analytics';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new MovieSearch());

        // Disable create button (searches are auto-generated)
        $grid->disableCreateButton();
        
        // Disable batch actions
        $grid->disableBatchActions();

        // Default sort by most recent searches
        $grid->model()->orderBy('last_searched_at', 'desc');

        // Add filters
        $grid->filter(function($filter){
            $filter->disableIdFilter();
            
            $filter->like('search_term', 'Search Term');
            $filter->equal('user_id', 'User ID');
            $filter->equal('has_results', 'Has Results')->select([
                1 => 'Yes',
                0 => 'No'
            ]);
            $filter->between('search_count', 'Search Count');
            $filter->between('results_count', 'Results Count');
            $filter->between('last_searched_at', 'Last Searched')->datetime();
            $filter->between('created_at', 'First Searched')->datetime();
        });

        // Column customization
        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('search_term', __('Search Term'))->display(function ($term) {
            return "<strong style='color: #0066cc;'>" . e($term) . "</strong>";
        })->width(250);
        
        $grid->column('user_id', __('User'))->display(function ($userId) {
            if ($userId) {
                $user = User::find($userId);
                return $user ? $user->name : 'User #' . $userId;
            }
            return '<span style="color: #999;">Guest</span>';
        });
        
        $grid->column('search_count', __('Search Count'))
            ->sortable()
            ->display(function ($count) {
                $color = $count > 10 ? 'red' : ($count > 5 ? 'orange' : 'green');
                return "<span style='color: $color; font-weight: bold;'>$count</span>";
            })->totalRow();
        
        $grid->column('results_count', __('Results'))
            ->sortable()
            ->display(function ($count) {
                return $count > 0 ? 
                    "<span style='color: green;'>✓ $count</span>" : 
                    "<span style='color: red;'>✗ 0</span>";
            });
        
        $grid->column('has_results', __('Status'))->display(function ($hasResults) {
            return $hasResults ? 
                "<span class='label label-success'>Found</span>" : 
                "<span class='label label-danger'>No Results</span>";
        })->filter([
            1 => 'Found',
            0 => 'No Results'
        ]);
        
        $grid->column('click_count', __('Clicks'))
            ->sortable()
            ->display(function ($count) {
                return $count > 0 ? 
                    "<span style='color: blue;'>🖱️ $count</span>" : 
                    "<span style='color: #999;'>0</span>";
            });
        
        $grid->column('platform', __('Platform'))->label([
            'web' => 'info',
            'mobile' => 'success',
        ]);
        
        $grid->column('ip_address', __('IP Address'))->hide();
        
        $grid->column('first_searched_at', __('First Search'))
            ->display(function ($date) {
                return date('M d, Y H:i', strtotime($date));
            })->hide();
        
        $grid->column('last_searched_at', __('Last Search'))
            ->sortable()
            ->display(function ($date) {
                $time = strtotime($date);
                $diff = time() - $time;
                
                if ($diff < 3600) {
                    $mins = floor($diff / 60);
                    return "<span style='color: green;'>{$mins}m ago</span>";
                } elseif ($diff < 86400) {
                    $hours = floor($diff / 3600);
                    return "<span style='color: blue;'>{$hours}h ago</span>";
                } else {
                    return date('M d, H:i', $time);
                }
            });

        // Add quick search
        $grid->quickSearch('search_term', 'ip_address');

        // Export settings
        $grid->export(function ($export) {
            $export->filename('movie_searches_' . date('Y-m-d'));
            $export->except(['user_agent', 'found_movie_ids']);
        });

        return $grid;
    }

    /**
     * Dashboard index with analytics
     */
    public function index(Content $content)
    {
        return $content
            ->title($this->title)
            ->description('Monitor and analyze movie search patterns')
            ->row($this->renderAnalytics())
            ->body($this->grid());
    }

    /**
     * Render analytics boxes
     */
    protected function renderAnalytics()
    {
        $stats = [
            'total_searches' => MovieSearch::count(),
            'unique_terms' => MovieSearch::distinct('search_term_normalized')->count(),
            'searches_today' => MovieSearch::whereDate('created_at', today())->count(),
            'searches_with_no_results' => MovieSearch::where('has_results', false)->count(),
            'avg_results' => MovieSearch::where('has_results', true)->avg('results_count'),
            'most_searched' => MovieSearch::orderBy('search_count', 'desc')->first(),
        ];

        $html = '<div class="row">';
        
        // Total Searches
        $html .= '<div class="col-md-3">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3>' . number_format($stats['total_searches']) . '</h3>
                    <p>Total Searches</p>
                </div>
                <div class="icon"><i class="fa fa-search"></i></div>
            </div>
        </div>';

        // Today's Searches
        $html .= '<div class="col-md-3">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3>' . number_format($stats['searches_today']) . '</h3>
                    <p>Searches Today</p>
                </div>
                <div class="icon"><i class="fa fa-clock-o"></i></div>
            </div>
        </div>';

        // No Results
        $html .= '<div class="col-md-3">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3>' . number_format($stats['searches_with_no_results']) . '</h3>
                    <p>No Results Found</p>
                </div>
                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
            </div>
        </div>';

        // Unique Terms
        $html .= '<div class="col-md-3">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3>' . number_format($stats['unique_terms']) . '</h3>
                    <p>Unique Search Terms</p>
                </div>
                <div class="icon"><i class="fa fa-tags"></i></div>
            </div>
        </div>';

        $html .= '</div>';

        // Top Searches Table
        $topSearches = MovieSearch::orderBy('search_count', 'desc')
            ->take(10)
            ->get();

        $html .= '<div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">🔥 Top 10 Most Searched</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Search Term</th>
                                    <th>Count</th>
                                    <th>Results</th>
                                </tr>
                            </thead>
                            <tbody>';
        
        foreach ($topSearches as $index => $search) {
            $html .= '<tr>
                <td>' . ($index + 1) . '</td>
                <td><strong>' . e($search->search_term) . '</strong></td>
                <td><span class="label label-primary">' . $search->search_count . '</span></td>
                <td>' . ($search->has_results ? 
                    '<span class="label label-success">' . $search->results_count . '</span>' : 
                    '<span class="label label-danger">0</span>') . '</td>
            </tr>';
        }

        $html .= '</tbody></table>
                    </div>
                </div>
            </div>';

        // Failed Searches (No Results)
        $failedSearches = MovieSearch::where('has_results', false)
            ->orderBy('search_count', 'desc')
            ->take(10)
            ->get();

        $html .= '<div class="col-md-6">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">❌ Top 10 Searches With No Results</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Search Term</th>
                                    <th>Attempts</th>
                                    <th>Last Try</th>
                                </tr>
                            </thead>
                            <tbody>';
        
        foreach ($failedSearches as $index => $search) {
            $html .= '<tr>
                <td>' . ($index + 1) . '</td>
                <td><strong>' . e($search->search_term) . '</strong></td>
                <td><span class="label label-warning">' . $search->search_count . '</span></td>
                <td>' . $search->last_searched_at->diffForHumans() . '</td>
            </tr>';
        }

        $html .= '</tbody></table>
                    </div>
                </div>
            </div>
        </div>';

        return $html;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(MovieSearch::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('user_id', __('User'))->as(function ($userId) {
            if ($userId) {
                $user = User::find($userId);
                return $user ? $user->name . ' (' . $user->email . ')' : 'User #' . $userId;
            }
            return 'Guest User';
        });
        $show->field('search_term', __('Search Term'));
        $show->field('search_term_normalized', __('Normalized Term'));
        $show->field('ip_address', __('IP Address'));
        $show->field('user_agent', __('User Agent'));
        $show->field('platform', __('Platform'));
        $show->field('search_count', __('Search Count'));
        $show->field('results_count', __('Results Count'));
        $show->field('has_results', __('Has Results'))->using([
            0 => 'No',
            1 => 'Yes'
        ])->label([
            0 => 'danger',
            1 => 'success'
        ]);
        $show->field('found_movie_ids', __('Found Movie IDs'))->json();
        $show->field('click_count', __('Click Count'));
        $show->field('first_searched_at', __('First Searched'));
        $show->field('last_searched_at', __('Last Searched'));
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
        $form = new Form(new MovieSearch());

        // Most fields should be read-only as they're auto-generated
        $form->display('id', __('ID'));
        $form->text('search_term', __('Search Term'))->readonly();
        $form->text('search_term_normalized', __('Normalized'))->readonly();
        $form->number('search_count', __('Search Count'))->readonly();
        $form->number('results_count', __('Results Count'))->readonly();
        $form->switch('has_results', __('Has Results'))->readonly();
        $form->number('click_count', __('Click Count'));
        $form->datetime('first_searched_at', __('First Searched'))->readonly();
        $form->datetime('last_searched_at', __('Last Searched'))->readonly();

        // Disable delete when editing
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
        });

        return $form;
    }
}
