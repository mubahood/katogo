<?php

namespace App\Admin\Controllers;

use App\Models\VideoTransfer;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\Log;

class VideoTransferController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Video Transfer to Google Drive';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new VideoTransfer());

        // Default sort by newest first
        $grid->model()->orderBy('id', 'desc');

        // Disable batch deletion for safety
        $grid->disableBatchActions();

        // Add filter for status
        $grid->filter(function($filter){
            $filter->disableIdFilter();
            
            $filter->equal('status', 'Status')->select([
                'pending' => 'Pending',
                'downloading' => 'Downloading',
                'uploading' => 'Uploading',
                'completed' => 'Completed',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
            ]);
            
            $filter->like('video_title', 'Video Title');
            $filter->like('source_url', 'Source URL');
            $filter->between('created_at', 'Created')->datetime();
        });

        // ID column
        $grid->column('id', 'ID')->sortable();

        // Video title with icon
        $grid->column('video_title', 'Video Title')->display(function($title) {
            $title = $title ?: 'Untitled';
            $icon = '<i class="fa fa-video-camera"></i>';
            return "{$icon} {$title}";
        });

        // Source URL (shortened)
        $grid->column('source_url', 'Source URL')->display(function($url) {
            $shortened = strlen($url) > 50 ? substr($url, 0, 50) . '...' : $url;
            return "<a href='{$url}' target='_blank' title='{$url}'>{$shortened}</a>";
        });

        // Status with color badge
        $grid->column('status', 'Status')->display(function($status) {
            $colors = [
                'pending' => 'default',
                'downloading' => 'info',
                'uploading' => 'primary',
                'completed' => 'success',
                'failed' => 'danger',
                'cancelled' => 'warning',
            ];
            $color = $colors[$status] ?? 'default';
            return "<span class='label label-{$color}'>" . strtoupper($status) . "</span>";
        });

        // Progress bar
        $grid->column('progress', 'Progress')->progressBar([
            'info' => 40,
            'primary' => 70,
            'success' => 100,
        ], 'sm');

        // File size
        $grid->column('source_size', 'Size')->display(function($size) {
            if (!$size) return 'N/A';
            
            $units = ['B', 'KB', 'MB', 'GB'];
            $bytes = $size;
            
            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }
            
            return round($bytes, 2) . ' ' . $units[$i];
        });

        // Google Drive file ID with link
        $grid->column('drive_file_id', 'Drive File ID')->display(function($fileId) {
            if (!$fileId) return '<span class="text-muted">Not uploaded</span>';
            
            $url = "https://drive.google.com/file/d/{$fileId}/view";
            return "<a href='{$url}' target='_blank'><i class='fa fa-external-link'></i> {$fileId}</a>";
        });

        // Duration
        $grid->column('duration_seconds', 'Duration')->display(function($seconds) {
            if (!$seconds) return 'N/A';
            
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            
            if ($hours > 0) {
                return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
            } elseif ($minutes > 0) {
                return sprintf('%dm %ds', $minutes, $secs);
            }
            return sprintf('%ds', $secs);
        });

        // Created date
        $grid->column('created_at', 'Created')->display(function($date) {
            return $date ? date('Y-m-d H:i', strtotime($date)) : 'N/A';
        })->sortable();

        // Custom action column with buttons
        $grid->column('actions', 'Actions')->display(function () {
            $row = $this;
            $html = '';
            
            // Add "Start Transfer" button for pending transfers
            if (strtolower($row->status) === 'pending') {
                $html .= '<a href="' . url('transfer/process/' . $row->id) . '" 
                    target="_blank" 
                    class="btn btn-xs btn-primary" style="margin: 2px;">
                    <i class="fa fa-cloud-upload"></i> Start Transfer
                </a>';
            }
            
            // Add retry button for failed transfers
            if (strtolower($row->status) === 'failed') {
                $html .= '<a href="' . url('transfer/process/' . $row->id) . '" 
                    target="_blank" 
                    class="btn btn-xs btn-warning" style="margin: 2px;">
                    <i class="fa fa-refresh"></i> Retry
                </a>';
            }
            
            // Add play button for completed transfers
            if (strtolower($row->status) === 'completed' && $row->drive_public_url) {
                $html .= '<a href="' . $row->drive_public_url . '" 
                    target="_blank" 
                    class="btn btn-xs btn-success" style="margin: 2px;">
                    <i class="fa fa-play"></i> Play
                </a>';
            }
            
            // Add cancel button for active transfers
            if (in_array(strtolower($row->status), ['downloading', 'uploading'])) {
                $html .= '<a href="' . admin_url('video-transfers/' . $row->id . '/cancel') . '" 
                    class="btn btn-xs btn-danger" style="margin: 2px;"
                    onclick="return confirm(\'Cancel this transfer?\')">
                    <i class="fa fa-times"></i> Cancel
                </a>';
            }
            
            return $html;
        });

        // Statistics at the top
        $grid->header(function ($query) {
            $total = VideoTransfer::count();
            $completed = VideoTransfer::where('status', 'completed')->count();
            $failed = VideoTransfer::where('status', 'failed')->count();
            $active = VideoTransfer::whereIn('status', ['downloading', 'uploading'])->count();
            
            return view('admin.video-transfer.stats', compact('total', 'completed', 'failed', 'active'));
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
        $show = new Show(VideoTransfer::findOrFail($id));

        $show->panel()
            ->title('Video Transfer Details')
            ->tools(function ($tools) use ($show) {
                $model = $show->model();
                
                // Add retry button
                if ($model->status === 'failed') {
                    $tools->append('<a href="' . admin_url('video-transfers/' . $model->id . '/retry') . '" 
                        class="btn btn-sm btn-warning" 
                        onclick="return confirm(\'Retry this transfer?\')">
                        <i class="fa fa-refresh"></i> Retry Transfer
                    </a>');
                }
                
                // Add play button
                if ($model->status === 'completed' && $model->drive_public_url) {
                    $tools->append('<a href="' . $model->drive_public_url . '" 
                        target="_blank" 
                        class="btn btn-sm btn-success">
                        <i class="fa fa-play"></i> Play Video
                    </a>');
                }
            });

        // Basic Information
        $show->divider('Basic Information');
        $show->field('id', 'ID');
        $show->field('video_title', 'Video Title');
        $show->field('video_description', 'Description');
        $show->field('status', 'Status')->as(function($status) {
            return strtoupper($status);
        })->label([
            'pending' => 'default',
            'downloading' => 'info',
            'uploading' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'warning',
        ]);

        // Source Information
        $show->divider('Source Information');
        $show->field('source_url', 'Source URL')->link();
        $show->field('source_type', 'Source Type');
        $show->field('source_size', 'File Size')->as(function($size) {
            if (!$size) return 'N/A';
            
            $units = ['B', 'KB', 'MB', 'GB'];
            $bytes = $size;
            
            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }
            
            return round($bytes, 2) . ' ' . $units[$i];
        });

        // Google Drive Information
        $show->divider('Google Drive Information');
        $show->field('drive_file_id', 'Drive File ID');
        $show->field('drive_file_name', 'File Name in Drive');
        $show->field('drive_public_url', 'Public URL')->link();
        $show->field('drive_download_url', 'Download URL')->link();
        $show->field('drive_folder_id', 'Folder ID');
        $show->field('embed_url', 'Embed URL (For App)')->link();

        // Progress Information
        $show->divider('Progress Information');
        $show->field('progress', 'Progress')->as(function($progress) {
            return $progress . '%';
        })->progressBar();
        $show->field('bytes_transferred', 'Bytes Transferred')->as(function($bytes) {
            return number_format($bytes) . ' bytes';
        });
        $show->field('total_bytes', 'Total Bytes')->as(function($bytes) {
            return number_format($bytes) . ' bytes';
        });

        // Timing Information
        $show->divider('Timing Information');
        $show->field('started_at', 'Started At');
        $show->field('completed_at', 'Completed At');
        $show->field('duration_seconds', 'Duration')->as(function($seconds) {
            if (!$seconds) return 'N/A';
            
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            
            if ($hours > 0) {
                return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
            } elseif ($minutes > 0) {
                return sprintf('%dm %ds', $minutes, $secs);
            }
            return sprintf('%ds', $secs);
        });

        // Error Information
        if ($show->model()->status === 'failed') {
            $show->divider('Error Information');
            $show->field('error_message', 'Error Message');
            $show->field('error_details', 'Error Details')->as(function($details) {
                return '<pre style="max-height: 300px; overflow-y: auto;">' . htmlspecialchars($details) . '</pre>';
            });
            $show->field('retry_count', 'Retry Count');
            $show->field('last_retry_at', 'Last Retry At');
        }

        // Video Metadata
        $show->divider('Video Metadata');
        $show->field('video_duration', 'Video Duration');
        $show->field('video_quality', 'Quality');
        $show->field('video_format', 'Format');

        // Additional Information
        $show->divider('Additional Information');
        $show->field('average_speed_mbps', 'Average Speed')->as(function($speed) {
            return $speed ? $speed . ' Mbps' : 'N/A';
        });
        $show->field('transferred_by', 'Transferred By');
        $show->field('server_location', 'Server Location');
        $show->field('notes', 'Notes');
        $show->field('transfer_metadata', 'Metadata')->json();
        
        $show->field('created_at', 'Created At');
        $show->field('updated_at', 'Updated At');

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new VideoTransfer());

        $form->tab('Basic Information', function ($form) {
            $form->text('video_title', 'Video Title')
                ->placeholder('Enter video title (optional)')
                ->help('Friendly name for this video');
            
            $form->url('source_url', 'Source Video URL')
                ->required()
                ->placeholder('https://example.com/video.mp4')
                ->help('Direct URL to the video file you want to transfer');
            
            $form->textarea('video_description', 'Video Description')
                ->rows(3)
                ->placeholder('Enter video description (optional)');
            
            $form->select('source_type', 'Source Type')
                ->options([
                    'direct_url' => 'Direct URL',
                    'streaming_url' => 'Streaming URL',
                    'other' => 'Other',
                ])
                ->default('direct_url')
                ->help('Type of video source');
        });

        $form->tab('Video Details', function ($form) {
            $form->text('video_duration', 'Video Duration')
                ->placeholder('e.g., 02:15:30')
                ->help('Duration in HH:MM:SS format (optional)');
            
            $form->text('video_quality', 'Video Quality')
                ->placeholder('e.g., 1080p, 720p, 4K')
                ->help('Video quality/resolution (optional)');
            
            $form->text('video_format', 'Video Format')
                ->placeholder('e.g., mp4, mkv, avi')
                ->help('Video file format (optional)');
        });

        $form->tab('Google Drive Settings', function ($form) {
            $form->text('drive_folder_id', 'Drive Folder ID')
                ->default(env('GOOGLE_DRIVE_FOLDER_ID'))
                ->placeholder('Leave empty to use default from .env')
                ->help('Specific Google Drive folder ID (optional)');
            
            $form->divider();
            
            $form->html('<div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                <strong>Note:</strong> Google Drive credentials are configured in the .env file. 
                Make sure GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET, and GOOGLE_DRIVE_REFRESH_TOKEN are set.
            </div>');
        });

        $form->tab('Additional', function ($form) {
            $form->text('transferred_by', 'Transferred By')
                ->default(auth()->user()->name ?? 'Admin')
                ->help('Who initiated this transfer');
            
            $form->textarea('notes', 'Admin Notes')
                ->rows(3)
                ->placeholder('Add any notes about this transfer (optional)');
        });

        // Hide fields that are auto-populated
        $form->hidden('status')->default('pending');
        $form->hidden('progress')->default(0);

        // Save callback - trigger processing
        $form->saved(function (Form $form) {
            $model = $form->model();
            
            admin_toastr('Video transfer created! Processing will start automatically.', 'success');
            
            // Log the creation
            Log::info('VideoTransfer created via admin', [
                'id' => $model->id,
                'source_url' => $model->source_url,
                'admin' => auth()->user()->name ?? 'Unknown',
            ]);
        });

        // Disable delete and view buttons on form
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
        });

        return $form;
    }

    /**
     * Retry a failed transfer
     */
    public function retry($id)
    {
        $transfer = VideoTransfer::findOrFail($id);
        
        try {
            $transfer->retry();
            admin_toastr('Transfer retry initiated successfully!', 'success');
        } catch (\Exception $e) {
            admin_toastr('Failed to retry transfer: ' . $e->getMessage(), 'error');
        }
        
        return redirect()->back();
    }

    /**
     * Cancel an active transfer
     */
    public function cancel($id)
    {
        $transfer = VideoTransfer::findOrFail($id);
        
        try {
            $transfer->cancel();
            admin_toastr('Transfer cancelled successfully!', 'success');
        } catch (\Exception $e) {
            admin_toastr('Failed to cancel transfer: ' . $e->getMessage(), 'error');
        }
        
        return redirect()->back();
    }
}
