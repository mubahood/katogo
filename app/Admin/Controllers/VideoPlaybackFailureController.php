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
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Table;
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
            ->title('🎬 Video Playback Failures')
            ->description('Monitor and resolve video playback issues')
            ->row(function (Row $row) {
                $row->column(3, $this->totalFailuresBox());
                $row->column(3, $this->pendingBox());
                $row->column(3, $this->todayFailuresBox());
                $row->column(3, $this->resolvedBox());
            })
            ->row(function (Row $row) {
                $row->column(3, $this->networkErrorsBox());
                $row->column(3, $this->playbackErrorsBox());
                $row->column(3, $this->httpErrorsBox());
                $row->column(3, $this->subscribedUsersBox());
            })
            ->row(function (Row $row) {
                $row->column(6, $this->topFailingMoviesBox());
                $row->column(6, $this->errorTypeBreakdownBox());
            })
            ->row(function (Row $row) {
                $row->column(6, $this->deviceBreakdownBox());
                $row->column(6, $this->failuresTrendBox());
            })
            ->body($this->grid());
    }

    /**
     * Total failures info box
     */
    protected function totalFailuresBox()
    {
        $count = VideoPlaybackFailure::count();
        return new InfoBox('Total Failures', 'exclamation-triangle', 'red', '/admin/video-playback-failures', number_format($count));
    }

    /**
     * Pending failures info box
     */
    protected function pendingBox()
    {
        $count = VideoPlaybackFailure::where('status', 'pending')->count();
        return new InfoBox('Pending Review', 'clock-o', 'yellow', '/admin/video-playback-failures?status=pending', number_format($count));
    }

    /**
     * Today's failures info box
     */
    protected function todayFailuresBox()
    {
        $count = VideoPlaybackFailure::whereDate('created_at', Carbon::today())->count();
        $yesterday = VideoPlaybackFailure::whereDate('created_at', Carbon::yesterday())->count();
        $trend = $count > $yesterday ? '↑' : ($count < $yesterday ? '↓' : '→');
        return new InfoBox("Today {$trend}", 'calendar', 'aqua', '#', number_format($count));
    }

    /**
     * Resolved failures info box
     */
    protected function resolvedBox()
    {
        $count = VideoPlaybackFailure::where('status', 'resolved')->count();
        $total = VideoPlaybackFailure::count() ?: 1;
        $percent = round(($count / $total) * 100, 1);
        return new InfoBox("Resolved ({$percent}%)", 'check-circle', 'green', '/admin/video-playback-failures?status=resolved', number_format($count));
    }

    /**
     * Network errors info box
     */
    protected function networkErrorsBox()
    {
        $count = VideoPlaybackFailure::where('error_type', 'network')->count();
        return new InfoBox('Network Errors', 'wifi', 'red', '/admin/video-playback-failures?error_type=network', number_format($count));
    }

    /**
     * Playback errors info box
     */
    protected function playbackErrorsBox()
    {
        $count = VideoPlaybackFailure::where('error_type', 'playback')->count();
        return new InfoBox('Playback Errors', 'play-circle', 'orange', '/admin/video-playback-failures?error_type=playback', number_format($count));
    }

    /**
     * HTTP errors info box
     */
    protected function httpErrorsBox()
    {
        $count = VideoPlaybackFailure::where('error_type', 'http_error')->count();
        return new InfoBox('HTTP Errors', 'server', 'maroon', '/admin/video-playback-failures?error_type=http_error', number_format($count));
    }

    /**
     * Subscribed users failures info box
     */
    protected function subscribedUsersBox()
    {
        $count = VideoPlaybackFailure::where('has_subscription', true)->count();
        $total = VideoPlaybackFailure::count() ?: 1;
        $percent = round(($count / $total) * 100, 1);
        return new InfoBox("Subscribers ({$percent}%)", 'star', 'purple', '/admin/video-playback-failures?has_subscription=1', number_format($count));
    }

    /**
     * Top failing movies box
     */
    protected function topFailingMoviesBox()
    {
        $failures = VideoPlaybackFailure::whereNotNull('movie_id')
            ->selectRaw('movie_id, movie_title, COUNT(*) as failure_count')
            ->groupBy('movie_id', 'movie_title')
            ->orderByDesc('failure_count')
            ->limit(10)
            ->get();

        $rows = [];
        $rank = 1;
        foreach ($failures as $failure) {
            // Try to get movie title from stored field or fetch from MovieModel
            $movieTitle = $failure->movie_title;
            if (empty($movieTitle) || $movieTitle === 'Unknown Movie') {
                $movie = MovieModel::find($failure->movie_id);
                $movieTitle = $movie ? $movie->title : 'Movie #' . $failure->movie_id;
            }
            
            $displayTitle = mb_strlen($movieTitle) > 35 ? mb_substr($movieTitle, 0, 35) . '...' : $movieTitle;
            $statusClass = $rank <= 3 ? 'danger' : ($rank <= 6 ? 'warning' : 'default');
            $rows[] = [
                "#{$rank}",
                "<a href='/admin/movies/{$failure->movie_id}' title='" . htmlspecialchars($movieTitle) . "'>{$displayTitle}</a>",
                "<span class='label label-{$statusClass}'>{$failure->failure_count}</span>",
            ];
            $rank++;
        }

        if (empty($rows)) {
            $rows[] = ['-', 'No data yet', '-'];
        }

        $table = new Table(['#', 'Movie', 'Failures'], $rows);
        $box = new Box('🎬 Top Failing Movies (Fix These First!)', $table);
        $box->style('danger');
        $box->solid();

        return $box;
    }

    /**
     * Error type breakdown box
     */
    protected function errorTypeBreakdownBox()
    {
        $types = VideoPlaybackFailure::selectRaw('error_type, COUNT(*) as count')
            ->groupBy('error_type')
            ->orderByDesc('count')
            ->get();

        $total = $types->sum('count') ?: 1;
        $rows = [];
        $icons = [
            'network' => '📡',
            'playback' => '▶️',
            'timeout' => '⏱️',
            'http_error' => '🌐',
            'format' => '📁',
            'unknown' => '❓',
        ];

        foreach ($types as $type) {
            $icon = $icons[$type->error_type] ?? '❓';
            $percent = round(($type->count / $total) * 100, 1);
            $bar = str_repeat('█', intval($percent / 5));
            $rows[] = [
                $icon . ' ' . ucfirst($type->error_type ?? 'Unknown'),
                number_format($type->count),
                "{$percent}%",
                "<span style='color:#dc3545'>{$bar}</span>",
            ];
        }

        if (empty($rows)) {
            $rows[] = ['No data', '-', '-', '-'];
        }

        $table = new Table(['Type', 'Count', '%', 'Distribution'], $rows);
        $box = new Box('📊 Error Type Breakdown', $table);
        $box->style('warning');
        $box->solid();

        return $box;
    }

    /**
     * Device breakdown box
     */
    protected function deviceBreakdownBox()
    {
        $devices = VideoPlaybackFailure::selectRaw('device_os, COUNT(*) as count')
            ->groupBy('device_os')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $rows = [];
        $icons = [
            'android' => '🤖',
            'ios' => '🍎',
            'windows' => '🪟',
            'macos' => '🍏',
            'linux' => '🐧',
        ];

        foreach ($devices as $device) {
            $os = strtolower($device->device_os ?? '');
            $icon = '📱';
            foreach ($icons as $key => $emoji) {
                if (strpos($os, $key) !== false) {
                    $icon = $emoji;
                    break;
                }
            }
            $rows[] = [
                $icon . ' ' . ucfirst($device->device_os ?? 'Unknown'),
                number_format($device->count),
            ];
        }

        if (empty($rows)) {
            $rows[] = ['No data', '-'];
        }

        $table = new Table(['Device OS', 'Failures'], $rows);
        $box = new Box('📱 Device Breakdown', $table);
        $box->style('info');
        $box->solid();

        return $box;
    }

    /**
     * Failures trend (last 7 days) box
     */
    protected function failuresTrendBox()
    {
        $days = [];
        $counts = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $days[] = $date->format('M d');
            $counts[] = VideoPlaybackFailure::whereDate('created_at', $date)->count();
        }

        $maxCount = max($counts) ?: 1;
        $rows = [];
        foreach ($days as $idx => $day) {
            $count = $counts[$idx];
            $barLength = intval(($count / $maxCount) * 20);
            $bar = str_repeat('█', max(1, $barLength));
            $color = $count > ($maxCount * 0.7) ? '#dc3545' : ($count > ($maxCount * 0.4) ? '#ffc107' : '#28a745');
            $rows[] = [$day, number_format($count), "<span style='color:{$color}'>{$bar}</span>"];
        }

        $table = new Table(['Date', 'Failures', 'Trend'], $rows);
        $box = new Box('📈 Failures Trend (Last 7 Days)', $table);
        $box->style('default');
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
                return "{$subscribed} <a href='/admin/users/{$this->user_id}'><strong>{$userName}</strong></a> <small class='text-muted'>{$userId}</small>{$emailDisplay}";
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
                    return "<a href='/admin/movies/{$this->movie_id}' title='" . htmlspecialchars($title) . "'><strong>{$displayTitle}</strong></a>{$movieId}";
                }
                return "<strong>{$displayTitle}</strong>{$movieId}";
            }
            return '<em class="text-muted">Unknown Movie</em>' . ($this->movie_id ? " <small>(ID: {$this->movie_id})</small>" : '');
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
        
        $grid->column('created_at', __('Failed At'))
            ->display(function ($createdAt) {
                return Carbon::parse($createdAt)->format('M d, H:i');
            })->sortable();

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

        // Batch actions - disable delete since failures are logs
        $grid->batchActions(function ($batch) {
            $batch->disableDelete();
        });

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
