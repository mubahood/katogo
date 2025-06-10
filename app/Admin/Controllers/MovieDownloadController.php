<?php

namespace App\Admin\Controllers;

use App\Models\MovieDownload;
use App\Models\Utils;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class MovieDownloadController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'MovieDownload';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        //description
        $recs = MovieDownload::all();
        //set unlimited timeout for this operation
        /* set_time_limit(0);
        foreach ($recs as $key => $value) {
            $value->description .= " (ID: " . $value->id . ")";
            $value->save();
        } */ 
        $grid = new Grid(new MovieDownload());
        $grid->model()->orderBy('created_at', 'desc');
        $grid->quickSearch('title', 'url', 'image_url', 'local_image_url', 'thumbnail_url', 'description', 'genre', 'vj', 'content_type', 'content_is_video', 'is_premium', 'episode_number', 'is_first_episode');

        $grid->column('created_at', __('Created at'))->sortable()
            ->display(function ($created_at) {
                return date('Y-m-d H:i:s', strtotime($created_at)); 
            });

        $grid->column('title', __('Title'))->sortable()
            ->display(function ($title) {
                return "<a href='" . $this->url . "' target='_blank'>" . $title . "</a>";
            });
        $grid->column('url', __('Url'))
            ->display(function ($url) {
                return "<a href='" . $url . "' target='_blank'>PLAY</a>";
            }); 
        $grid->column('user_id', __('User'))
            ->display(function ($user_id) {
                if ($this->user != null) {
                    return $this->user->name;
                }
                return "<span style='color: red; font-weight: bold;'>NOT FOUND</span>";
            });
        $grid->column('movie_model_id', __('Movie Model id'))->hide()->sortable();
        $grid->column('status', __('Status'))->hide();
        $grid->column('error_message', __('Error message'))->hide();
        $grid->column('local_video_link', __('Local video link'))->hide();
        $grid->column('download_started_at', __('Download started at'))
            ->display(function ($download_started_at) {
                if ($this->download_started_at != null) {
                    return date('Y-m-d H:i:s', strtotime($download_started_at));
                }
                return "<span style='color: red; font-weight: bold;'>NOT STARTED</span>";
            })->sortable();
        $grid->column('download_completed_at', __('Download completed at'))
            ->display(function ($download_completed_at) {
                if ($this->download_completed_at != null) {
                    //in minutes
                    $diff = strtotime($this->download_completed_at) - strtotime($this->download_started_at);
                    $minutes = round($diff / 60, 2);
                    return date('Y-m-d H:i:s', strtotime($download_completed_at)) . " ($minutes minutes)";
                }
                return "<span style='color: red; font-weight: bold;'>NOT COMPLETED</span>";
            })->sortable();
        $grid->column('download_duration', __('Download duration'))
            ->display(function ($download_duration) {
                if ($this->download_completed_at != null && $this->download_started_at != null) {
                    $diff = strtotime($this->download_completed_at) - strtotime($this->download_started_at);
                    $minutes = round($diff / 60, 2);
                    $hours = floor($minutes / 60);
                    if ($hours > 0) {
                        $minutes = $minutes - ($hours * 60);
                        return number_format($hours) . " hours " . number_format($minutes) . " minutes";
                    } 
                    return number_format($minutes) . " minutes";
                }
                return "<span style='color: red; font-weight: bold;'>NOT COMPLETED</span>";
            })->sortable();
        $grid->column('file_size', __('File size'))->hide();
        $grid->column('download_progress', __('Download progress'))->hide();
        $grid->column('watch_progress', __('Watch progress'))->hide();

        $grid->column('image_url', __('Image url'))->hide();
        $grid->column('local_image_url', __('Local image url'))->hide();
        $grid->column('thumbnail_url', __('Thumbnail url'))->hide();
        $grid->column('description', __('Description'))->hide();
        $grid->column('genre', __('Genre'))->hide();
        $grid->column('vj', __('Vj'))->hide();
        $grid->column('content_type', __('Content type'))->hide();
        $grid->column('content_is_video', __('Content is video'))->hide();
        $grid->column('is_premium', __('Is premium'))->hide();
        $grid->column('episode_number', __('Episode number'))->hide();
        $grid->column('is_first_episode', __('Is first episode'))->hide();

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
        $show = new Show(MovieDownload::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('local_id', __('Local id'));
        $show->field('user_id', __('User id'));
        $show->field('movie_model_id', __('Movie model id'));
        $show->field('status', __('Status'));
        $show->field('error_message', __('Error message'));
        $show->field('local_video_link', __('Local video link'));
        $show->field('download_started_at', __('Download started at'));
        $show->field('download_completed_at', __('Download completed at'));
        $show->field('download_duration', __('Download duration'));
        $show->field('file_size', __('File size'));
        $show->field('download_progress', __('Download progress'));
        $show->field('watch_progress', __('Watch progress'));
        $show->field('title', __('Title'));
        $show->field('url', __('Url'));
        $show->field('image_url', __('Image url'));
        $show->field('local_image_url', __('Local image url'));
        $show->field('thumbnail_url', __('Thumbnail url'));
        $show->field('description', __('Description'));
        $show->field('genre', __('Genre'));
        $show->field('vj', __('Vj'));
        $show->field('content_type', __('Content type'));
        $show->field('content_is_video', __('Content is video'));
        $show->field('is_premium', __('Is premium'));
        $show->field('episode_number', __('Episode number'));
        $show->field('is_first_episode', __('Is first episode'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new MovieDownload());

        $form->textarea('local_id', __('Local id'));
        $form->number('user_id', __('User id'));
        $form->number('movie_model_id', __('Movie model id'));
        $form->text('status', __('Status'))->default('Pending');
        $form->textarea('error_message', __('Error message'));
        $form->textarea('local_video_link', __('Local video link'));
        $form->datetime('download_started_at', __('Download started at'))->default(date('Y-m-d H:i:s'));
        $form->datetime('download_completed_at', __('Download completed at'))->default(date('Y-m-d H:i:s'));
        $form->number('download_duration', __('Download duration'));
        $form->text('file_size', __('File size'));
        $form->textarea('download_progress', __('Download progress'));
        $form->textarea('watch_progress', __('Watch progress'));
        $form->textarea('title', __('Title'));
        $form->textarea('url', __('Url'));
        $form->textarea('image_url', __('Image url'));
        $form->textarea('local_image_url', __('Local image url'));
        $form->textarea('thumbnail_url', __('Thumbnail url'));
        $form->textarea('description', __('Description'));
        $form->textarea('genre', __('Genre'));
        $form->textarea('vj', __('Vj'));
        $form->textarea('content_type', __('Content type'));
        $form->textarea('content_is_video', __('Content is video'));
        $form->textarea('is_premium', __('Is premium'));
        $form->textarea('episode_number', __('Episode number'));
        $form->textarea('is_first_episode', __('Is first episode'));

        return $form;
    }
}
