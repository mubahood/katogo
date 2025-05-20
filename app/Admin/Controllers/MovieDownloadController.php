<?php

namespace App\Admin\Controllers;

use App\Models\MovieDownload;
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
        $grid = new Grid(new MovieDownload());

        $grid->column('id', __('Id'));
        $grid->column('created_at', __('Created at'));
        $grid->column('updated_at', __('Updated at'));
        $grid->column('local_id', __('Local id'));
        $grid->column('user_id', __('User id'));
        $grid->column('movie_model_id', __('Movie model id'));
        $grid->column('status', __('Status'));
        $grid->column('error_message', __('Error message'));
        $grid->column('local_video_link', __('Local video link'));
        $grid->column('download_started_at', __('Download started at'));
        $grid->column('download_completed_at', __('Download completed at'));
        $grid->column('download_duration', __('Download duration'));
        $grid->column('file_size', __('File size'));
        $grid->column('download_progress', __('Download progress'));
        $grid->column('watch_progress', __('Watch progress'));
        $grid->column('title', __('Title'));
        $grid->column('url', __('Url'));
        $grid->column('image_url', __('Image url'));
        $grid->column('local_image_url', __('Local image url'));
        $grid->column('thumbnail_url', __('Thumbnail url'));
        $grid->column('description', __('Description'));
        $grid->column('genre', __('Genre'));
        $grid->column('vj', __('Vj'));
        $grid->column('content_type', __('Content type'));
        $grid->column('content_is_video', __('Content is video'));
        $grid->column('is_premium', __('Is premium'));
        $grid->column('episode_number', __('Episode number'));
        $grid->column('is_first_episode', __('Is first episode'));

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
