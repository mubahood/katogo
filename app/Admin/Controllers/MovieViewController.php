<?php

namespace App\Admin\Controllers;

use App\Models\MovieView;
use App\Models\MovieModel;
use App\Models\User;
use App\Models\Utils;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Facades\DB;

class MovieViewController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Movie Views';

    /**
     * Index interface with dashboard.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->title('🎬 Movie Views')
            ->description('Monitor movie viewing activity')
            ->row(function (Row $row) {
                $row->column(3, $this->totalViewsBox());
                $row->column(3, $this->todayViewsBox());
                $row->column(3, $this->uniqueUsersBox());
                $row->column(3, $this->uniqueMoviesBox());
            })
            ->row(function (Row $row) {
                $row->column(6, $this->topViewedMoviesBox());
                $row->column(6, $this->topUsersBox());
            })
            ->body($this->grid());
    }

    /**
     * Total views info box
     */
    protected function totalViewsBox()
    {
        $count = MovieView::count();
        return new InfoBox('Total Views', 'eye', 'blue', '/admin/movie-views', number_format($count));
    }

    /**
     * Today's views info box
     */
    protected function todayViewsBox()
    {
        $count = MovieView::whereDate('created_at', Carbon::today())->count();
        $yesterday = MovieView::whereDate('created_at', Carbon::yesterday())->count();
        $trend = $count > $yesterday ? '↑' : ($count < $yesterday ? '↓' : '→');
        return new InfoBox("Today {$trend}", 'calendar', 'aqua', '#', number_format($count));
    }

    /**
     * Unique users info box
     */
    protected function uniqueUsersBox()
    {
        $count = MovieView::distinct('user_id')->count('user_id');
        return new InfoBox('Unique Users', 'users', 'green', '#', number_format($count));
    }

    /**
     * Unique movies info box
     */
    protected function uniqueMoviesBox()
    {
        $count = MovieView::distinct('movie_model_id')->count('movie_model_id');
        return new InfoBox('Unique Movies', 'film', 'yellow', '#', number_format($count));
    }

    /**
     * Top viewed movies box
     */
    protected function topViewedMoviesBox()
    {
        $views = MovieView::whereNotNull('movie_model_id')
            ->selectRaw('movie_model_id, COUNT(*) as view_count')
            ->groupBy('movie_model_id')
            ->orderByDesc('view_count')
            ->limit(10)
            ->get();

        $rows = [];
        $rank = 1;
        foreach ($views as $view) {
            $movie = MovieModel::find($view->movie_model_id);
            $movieTitle = $movie ? $movie->title : 'Movie #' . $view->movie_model_id;
            $displayTitle = mb_strlen($movieTitle) > 35 ? mb_substr($movieTitle, 0, 35) . '...' : $movieTitle;
            $statusClass = $rank <= 3 ? 'success' : ($rank <= 6 ? 'info' : 'default');
            $rows[] = [
                "#{$rank}",
                "<a href='movies-movies/{$view->movie_model_id}' title='" . htmlspecialchars($movieTitle) . "'>{$displayTitle}</a>",
                "<span class='label label-{$statusClass}'>{$view->view_count}</span>",
            ];
            $rank++;
        }

        if (empty($rows)) {
            $rows[] = ['-', 'No data yet', '-'];
        }

        $table = new Table(['#', 'Movie', 'Views'], $rows);
        $box = new Box('🔥 Top Viewed Movies', $table);
        $box->style('success');
        $box->solid();

        return $box;
    }

    /**
     * Top users box
     */
    protected function topUsersBox()
    {
        $users = MovieView::whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as view_count')
            ->groupBy('user_id')
            ->orderByDesc('view_count')
            ->limit(10)
            ->get();

        $rows = [];
        $rank = 1;
        foreach ($users as $userData) {
            $user = User::find($userData->user_id);
            $userName = $user ? $user->name : 'User #' . $userData->user_id;
            $displayName = mb_strlen($userName) > 25 ? mb_substr($userName, 0, 25) . '...' : $userName;
            $rows[] = [
                "#{$rank}",
                "<a href='users/{$userData->user_id}' title='" . htmlspecialchars($userName) . "'>{$displayName}</a>",
                "<span class='label label-info'>{$userData->view_count}</span>",
            ];
            $rank++;
        }

        if (empty($rows)) {
            $rows[] = ['-', 'No data yet', '-'];
        }

        $table = new Table(['#', 'User', 'Views'], $rows);
        $box = new Box('👤 Top Users by Views', $table);
        $box->style('info');
        $box->solid();

        return $box;
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new MovieView());
        $grid->model()->with(['movie'])->orderBy('updated_at', 'desc');
        
        // Quick search
        $grid->quickSearch('ip_address', 'device', 'platform', 'browser', 'country', 'city');
        
        $grid->filter(function($filter){
            // Remove the default id filter
            $filter->disableIdFilter();
            
            $filter->column(1/3, function ($filter) {
                $filter->like('ip_address', 'Ip Address');
                $filter->like('device', 'Device');
                $filter->like('platform', 'Platform');
            });
            
            $filter->column(1/3, function ($filter) {
                $filter->like('browser', 'Browser');
                $filter->like('country', 'Country');
                $filter->like('city', 'City');
            });
            
            $filter->column(1/3, function ($filter) {
                $filter->equal('status', 'Status')->select([
                    'Active' => 'Active',
                    'Inactive' => 'Inactive',
                ]);
                $filter->equal('movie_model_id', 'Movie ID');
                $filter->equal('user_id', 'User ID');
            });
            
            $filter->between('created_at', 'Created At')->datetime();
            $filter->between('updated_at', 'Updated At')->datetime();
        });
        $grid->disableBatchActions();
        $grid->column('id', __('Id'))->width(40);
        $grid->column('created_at', __('Date'))->sortable()
            ->display(function ($created_at) {
                //disaplay date and time
                return date('d-m-Y H:i:s', strtotime($created_at));
            })->sortable();
        $grid->column('updated_at', __('Updated'))->display(function ($created_at) {
            //disaplay date and time
            return date('d-m-Y H:i:s', strtotime($created_at));
        })->sortable();
        $grid->column('progress', __('Progress'))
            ->display(function ($progress) {
                //convert from seconds to minutes
                if ($progress == null || $progress == 0) {
                    return '0:00';
                }

                $pecentage = ($progress / $this->max_progress) * 100;
                if ($pecentage > 100) {
                    $pecentage = 100;
                }
                $progress = Utils::secondsToMinutes($progress);
                return "<span class='badge bg-success' style='font-size: 14px; padding: 5px;'>" . $progress . " (" . round($pecentage, 2) . "%)</span>";
            })->sortable();
        //max_progress
        $grid->column('max_progress', __('Max progress'))
            ->display(function ($max_progress) {
                //convert from seconds to minutes
                return Utils::secondsToMinutes($max_progress);
            })->sortable();


        $grid->column('movie_model_id', __('Movie'))
            ->display(function ($movie_model_id) {
                $m = $this->movie;
                if (!$m) {
                    $m = \App\Models\MovieModel::find($movie_model_id);
                }
                if ($m) {
                    $title = strlen($m->title) <= 35 ? $m->title : substr($m->title, 0, 35) . '...';
                    $status = ($m->status ?? '') === 'Active' ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">' . ($m->status ?? 'N/A') . '</span>';
                    $type = $m->type ?? 'N/A';
                    
                    $html = "<a href='movies-movies/{$movie_model_id}' title='" . htmlspecialchars($m->title ?? '') . "'><strong>{$title}</strong></a>";
                    $html .= "<br><small class='text-muted'>ID: {$movie_model_id} | {$type} | {$status}</small>";
                    
                    return $html;
                }
                return '<em class="text-muted">Deleted</em>';
            })->sortable();
        $grid->column('user_id', __('User'))
            ->display(function ($user_id) {
                $u = \App\Models\User::find($user_id);
                if ($u) {
                    return "<a href='users/{$user_id}'><strong>{$u->name}</strong></a> <small class='text-muted'>(ID: {$user_id})</small>";
                }
                return '<em class="text-muted">Deleted</em>';
            })->sortable();
        $grid->column('ip_address', __('Ip address'))->hide();
        $grid->column('device', __('Device'))->hide();
        $grid->column('platform', __('Platform'))->hide();
        $grid->column('browser', __('Browser'))->hide();
        $grid->column('country', __('Country'))->hide();
        $grid->column('city', __('City'))->hide();
        $grid->column('status', __('Status'))->hide();
        $grid->column('user_reg_date', __('User reg date'))
            ->display(function ($user_id) {
                $u = \App\Models\User::find($this->user_id);
                if ($u) {
                    $reg_date = Carbon::parse($u->created_at);
                    $now = Carbon::now();
                    $diff = $reg_date->diffInDays($now);
                    return date('d-m-Y H:i:s', strtotime($u->created_at)) . ' (' . $diff . ' days ago)';
                }
                return 'Deleted';
            });

        // Video URL column
        $grid->column('video_url', __('Video Link'))->display(function () {
            $movie = $this->movie;
            if (!$movie) {
                $movie = \App\Models\MovieModel::find($this->movie_model_id);
            }
            if ($movie) {
                $videoUrl = $movie->url ?? $movie->external_url ?? null;
                if ($videoUrl) {
                    return '<a href="' . htmlspecialchars($videoUrl) . '" target="_blank" class="btn btn-xs btn-primary"><i class="fa fa-play"></i> Play</a>';
                }
            }
            return '<span class="text-muted">-</span>';
        });

        // Expandable row with full movie details and video link
        $grid->column('details', __('Details'))->display(function () {
            return '<i class="fa fa-chevron-down"></i>';
        })->expand(function ($model) {
            // Get movie from relationship or fetch by ID
            $movie = $model->movie;
            if (!$movie && $model->movie_model_id) {
                $movie = \App\Models\MovieModel::find($model->movie_model_id);
            }
            
            // Get user
            $user = \App\Models\User::find($model->user_id);
            
            $html = '<div style="padding: 15px; background: #f9f9f9; border-radius: 8px;">';
            
            // === MOVIE INFORMATION ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px;">🎬 Movie Information</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            
            if ($movie) {
                $html .= '<tr><td style="width: 180px; font-weight: bold;">Movie Title</td><td>' . htmlspecialchars($movie->title ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Movie ID</td><td>' . ($movie->id ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Status</td><td><span class="label label-' . (($movie->status ?? '') === 'Active' ? 'success' : 'danger') . '">' . ($movie->status ?? 'N/A') . '</span></td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Type</td><td>' . ($movie->type ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Category</td><td>' . ($movie->Category ?? $movie->category ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">VJ</td><td>' . ($movie->vj ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Views Count</td><td>' . number_format($movie->views_count ?? 0) . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Downloads</td><td>' . number_format($movie->downloads ?? 0) . '</td></tr>';
                
                // Video URL with clickable link
                $videoUrl = $movie->url ?? $movie->external_url ?? null;
                if ($videoUrl) {
                    $html .= '<tr><td style="font-weight: bold;">🔗 Video URL</td><td>';
                    $html .= '<a href="' . htmlspecialchars($videoUrl) . '" target="_blank" class="btn btn-sm btn-primary" style="margin-right: 10px;">';
                    $html .= '<i class="fa fa-play-circle"></i> Play Video</a>';
                    $html .= '<code style="word-break: break-all; font-size: 11px;">' . htmlspecialchars($videoUrl) . '</code>';
                    $html .= '</td></tr>';
                }
                
                // External URL if different
                if ($movie->external_url && $movie->external_url !== $videoUrl) {
                    $html .= '<tr><td style="font-weight: bold;">🌐 External URL</td><td>';
                    $html .= '<a href="' . htmlspecialchars($movie->external_url) . '" target="_blank" class="btn btn-sm btn-info" style="margin-right: 10px;">';
                    $html .= '<i class="fa fa-external-link"></i> Open External</a>';
                    $html .= '<code style="word-break: break-all; font-size: 11px;">' . htmlspecialchars($movie->external_url) . '</code>';
                    $html .= '</td></tr>';
                }
                
                // Page Source URL
                if ($movie->page_source_url) {
                    $html .= '<tr><td style="font-weight: bold;">📄 Page Source URL</td><td>';
                    $html .= '<a href="' . htmlspecialchars($movie->page_source_url) . '" target="_blank" class="btn btn-sm btn-warning" style="margin-right: 10px;">';
                    $html .= '<i class="fa fa-link"></i> Open Source</a>';
                    $html .= '<code style="word-break: break-all; font-size: 11px;">' . htmlspecialchars($movie->page_source_url) . '</code>';
                    $html .= '</td></tr>';
                }
                
                // Thumbnail
                if ($movie->thumbnail_url) {
                    $html .= '<tr><td style="font-weight: bold;">Thumbnail</td><td>';
                    $html .= '<img src="' . htmlspecialchars($movie->thumbnail_url) . '" style="max-width: 150px; max-height: 100px; border-radius: 4px;">';
                    $html .= '</td></tr>';
                }
                
                // Video test status
                if ($movie->video_url_tested_by_curl) {
                    $workingStatus = ($movie->video_url_tested_by_curl_works ?? 'No') === 'Yes' ? 'success' : 'danger';
                    $workingText = ($movie->video_url_tested_by_curl_works ?? 'No') === 'Yes' ? '✅ Working' : '❌ Not Working';
                    $html .= '<tr><td style="font-weight: bold;">Video URL Status</td><td><span class="label label-' . $workingStatus . '">' . $workingText . '</span></td></tr>';
                }
                
                $html .= '<tr><td style="font-weight: bold;">Duration</td><td>' . ($movie->duration ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Created At</td><td>' . ($movie->created_at ? Carbon::parse($movie->created_at)->format('M d, Y H:i') : 'N/A') . '</td></tr>';
            } else {
                $html .= '<tr><td colspan="2" class="text-center text-muted">Movie not found in database (ID: ' . ($model->movie_model_id ?? 'N/A') . ')</td></tr>';
            }
            $html .= '</table>';
            
            // === VIEWING PROGRESS ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #28a745; padding-bottom: 8px;">📊 Viewing Progress</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            $progress = $model->progress ?? 0;
            $maxProgress = $model->max_progress ?? 1;
            $percentage = $maxProgress > 0 ? round(($progress / $maxProgress) * 100, 2) : 0;
            if ($percentage > 100) $percentage = 100;
            
            $html .= '<tr><td style="width: 180px; font-weight: bold;">Progress</td><td>' . Utils::secondsToMinutes($progress) . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Max Progress</td><td>' . Utils::secondsToMinutes($maxProgress) . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Completion</td><td>';
            $html .= '<div class="progress" style="margin-bottom: 0; height: 20px;">';
            $barColor = $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'info' : ($percentage >= 25 ? 'warning' : 'danger'));
            $html .= '<div class="progress-bar progress-bar-' . $barColor . '" role="progressbar" style="width: ' . $percentage . '%">' . $percentage . '%</div>';
            $html .= '</div>';
            $html .= '</td></tr>';
            $html .= '</table>';
            
            // === USER DETAILS ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #17a2b8; padding-bottom: 8px;">👤 User Details</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            if ($user) {
                $html .= '<tr><td style="width: 180px; font-weight: bold;">User ID</td><td>' . ($user->id ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Name</td><td>' . htmlspecialchars($user->name ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Email</td><td>' . htmlspecialchars($user->email ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Phone</td><td>' . htmlspecialchars($user->phone_number ?? $user->phone ?? 'N/A') . '</td></tr>';
                $html .= '<tr><td style="font-weight: bold;">Registered</td><td>' . ($user->created_at ? Carbon::parse($user->created_at)->format('M d, Y H:i') : 'N/A') . '</td></tr>';
            } else {
                $html .= '<tr><td colspan="2" class="text-center text-muted">User not found (ID: ' . ($model->user_id ?? 'N/A') . ')</td></tr>';
            }
            $html .= '</table>';
            
            // === DEVICE & LOCATION INFO ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #6c757d; padding-bottom: 8px;">📱 Device & Location Info</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            $html .= '<tr><td style="width: 180px; font-weight: bold;">Device</td><td>' . htmlspecialchars($model->device ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Platform</td><td>' . htmlspecialchars($model->platform ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Browser</td><td>' . htmlspecialchars($model->browser ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">IP Address</td><td>' . htmlspecialchars($model->ip_address ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Country</td><td>' . htmlspecialchars($model->country ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">City</td><td>' . htmlspecialchars($model->city ?? 'N/A') . '</td></tr>';
            $html .= '</table>';
            
            // === TIMESTAMPS ===
            $html .= '<h4 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #ffc107; padding-bottom: 8px;">🕐 Timestamps</h4>';
            $html .= '<table class="table table-bordered" style="margin-bottom: 20px;">';
            $html .= '<tr><td style="width: 180px; font-weight: bold;">Status</td><td><span class="label label-' . (($model->status ?? '') === 'Active' ? 'success' : 'default') . '">' . ($model->status ?? 'N/A') . '</span></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Created At</td><td>' . Carbon::parse($model->created_at)->format('M d, Y H:i:s') . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Updated At</td><td>' . Carbon::parse($model->updated_at)->format('M d, Y H:i:s') . '</td></tr>';
            $html .= '</table>';
            
            $html .= '</div>';
            
            return $html;
        });

        // Export
        $grid->export(function ($export) {
            $export->filename('MovieViews_' . date('Y-m-d_H-i'));
        });

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
        $show = new Show(MovieView::findOrFail($id));

        $show->panel()->title('Movie View Details');

        // View Information
        $show->divider('View Information');
        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('progress', __('Progress (seconds)'));
        $show->field('max_progress', __('Max Progress (seconds)'));
        $show->field('status', __('Status'));

        // Movie Information
        $show->divider('Movie Information');
        $show->field('movie_model_id', __('Movie ID'));
        $show->field('movie.title', __('Movie Title'));
        $show->field('movie.type', __('Movie Type'));
        $show->field('movie.status', __('Movie Status'));
        $show->field('movie.url', __('Video URL'))->link();
        $show->field('movie.external_url', __('External URL'))->link();
        $show->field('movie.page_source_url', __('Page Source URL'))->link();
        $show->field('movie.thumbnail_url', __('Thumbnail'))->image();
        $show->field('movie.vj', __('VJ'));
        $show->field('movie.Category', __('Category'));
        $show->field('movie.views_count', __('Total Views'));
        
        // User Information
        $show->divider('User Information');
        $show->field('user_id', __('User ID'));
        $show->field('user.name', __('User Name'));
        $show->field('user.email', __('User Email'));
        
        // Device & Location
        $show->divider('Device & Location');
        $show->field('ip_address', __('Ip address'));
        $show->field('device', __('Device'));
        $show->field('platform', __('Platform'));
        $show->field('browser', __('Browser'));
        $show->field('country', __('Country'));
        $show->field('city', __('City'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new MovieView());

        $form->number('movie_model_id', __('Movie model id'));
        $form->number('user_id', __('User id'));
        $form->text('ip_address', __('Ip address'));
        $form->text('device', __('Device'));
        $form->text('platform', __('Platform'));
        $form->text('browser', __('Browser'));
        $form->text('country', __('Country'));
        $form->text('city', __('City'));
        $form->text('progress', __('progress'));
        $form->text('status', __('Status'))->default('Active');

        return $form;
    }
}
