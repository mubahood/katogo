<?php

namespace App\Admin\Controllers;

use App\Models\VideoPlaybackFailure;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class VideoPlaybackFailureController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Video Playback Failures';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new VideoPlaybackFailure());

        // Default sort by latest first
        $grid->model()->orderBy('created_at', 'desc');

        // Columns
        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('user_name', __('User'))->display(function () {
            $userName = $this->user_name ?? 'Unknown';
            $userId = $this->user_id ? " (ID: {$this->user_id})" : '';
            return $userName . $userId;
        })->width(150);
        
        $grid->column('movie_title', __('Movie'))->display(function () {
            if ($this->movie_title) {
                $movieId = $this->movie_id ? " (ID: {$this->movie_id})" : '';
                return "<strong>{$this->movie_title}</strong>{$movieId}";
            }
            return '<em>Unknown Movie</em>';
        })->width(200);
        
        $grid->column('error_type', __('Error Type'))->label([
            'network' => 'danger',
            'playback' => 'warning',
            'timeout' => 'info',
            'http_error' => 'danger',
            'format' => 'warning',
            'unknown' => 'default',
        ])->sortable()->filter([
            'network' => 'Network',
            'playback' => 'Playback',
            'timeout' => 'Timeout',
            'http_error' => 'HTTP Error',
            'format' => 'Format',
            'unknown' => 'Unknown',
        ]);
        
        $grid->column('error_message', __('Error Message'))->display(function ($message) {
            if (!$message) return '-';
            $short = mb_substr($message, 0, 60);
            return $short . (mb_strlen($message) > 60 ? '...' : '');
        })->width(250);
        
        $grid->column('device_os', __('Device'))->display(function () {
            $os = $this->device_os ?? 'Unknown';
            $model = $this->device_model ?? '';
            return "{$os}<br><small>{$model}</small>";
        })->width(120);
        
        $grid->column('player_type', __('Player'))->label([
            'full_screen' => 'primary',
            'preview' => 'success',
            'mini' => 'info',
        ])->sortable();
        
        $grid->column('has_subscription', __('Subscribed'))->bool()->sortable()->filter([
            1 => 'Yes',
            0 => 'No',
        ]);
        
        $grid->column('status', __('Status'))->select([
            'pending' => 'Pending',
            'investigating' => 'Investigating',
            'resolved' => 'Resolved',
            'ignored' => 'Ignored',
        ])->sortable()->filter([
            'pending' => 'Pending',
            'investigating' => 'Investigating',
            'resolved' => 'Resolved',
            'ignored' => 'Ignored',
        ]);
        
        $grid->column('created_at', __('Failed At'))->display(function ($createdAt) {
            return date('Y-m-d H:i:s', strtotime($createdAt));
        })->sortable();

        // Filters
        $grid->filter(function ($filter) {
            // Remove the default id filter
            $filter->disableIdFilter();

            // Add filters
            $filter->like('user_name', 'User Name');
            $filter->like('user_email', 'User Email');
            $filter->like('movie_title', 'Movie Title');
            $filter->equal('movie_id', 'Movie ID');
            $filter->equal('user_id', 'User ID');
            
            $filter->equal('error_type', 'Error Type')->select([
                'network' => 'Network',
                'playback' => 'Playback',
                'timeout' => 'Timeout',
                'http_error' => 'HTTP Error',
                'format' => 'Format',
                'unknown' => 'Unknown',
            ]);
            
            $filter->equal('status', 'Status')->select([
                'pending' => 'Pending',
                'investigating' => 'Investigating',
                'resolved' => 'Resolved',
                'ignored' => 'Ignored',
            ]);
            
            $filter->equal('has_subscription', 'Has Subscription')->select([
                1 => 'Yes',
                0 => 'No',
            ]);
            
            $filter->between('created_at', 'Failed Date')->datetime();
        });

        // Actions
        $grid->actions(function ($actions) {
            $actions->disableDelete();
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
            return $value ? 'Yes' : 'No';
        });
        $show->field('subscription_type', __('Subscription Type'));
        $show->field('subscription_expires_at', __('Subscription Expires'));

        // Context
        $show->divider('Context');
        $show->field('screen_name', __('Screen Name'));
        $show->field('additional_data', __('Additional Data'))->json();

        // Resolution Status
        $show->divider('Resolution Status');
        $show->field('status', __('Status'))->label();
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

        // Only allow editing status and admin notes
        $form->select('status', __('Status'))->options([
            'pending' => 'Pending',
            'investigating' => 'Investigating',
            'resolved' => 'Resolved',
            'ignored' => 'Ignored',
        ])->default('pending');

        $form->textarea('admin_notes', __('Admin Notes'))->rows(5);

        $form->datetime('resolved_at', __('Resolved At'))->help('Leave empty for auto-fill when status is "Resolved"');

        // Display read-only fields
        $form->display('id', __('ID'));
        $form->display('user_name', __('User'));
        $form->display('movie_title', __('Movie'));
        $form->display('error_message', __('Error Message'));
        $form->display('created_at', __('Failed At'));

        // Auto-fill resolved_at when status is resolved
        $form->saving(function (Form $form) {
            if ($form->status === 'resolved' && !$form->resolved_at) {
                $form->resolved_at = now();
            }
        });

        return $form;
    }
}
