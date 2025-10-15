<?php

namespace App\Admin\Controllers;

use App\Models\TrendingNotification;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class TrendingNotificationController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'TrendingNotification';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new TrendingNotification());
        $grid->model()->orderBy('id', 'desc');

        $grid->column('id', __('Id'))->sortable();
        $grid->column('created_at', __('Created at'))->sortable()
            ->display(function ($created_at) {
                return date('Y-m-d H:i:s', strtotime($created_at));
            });
        $grid->column('movie_model_id', __('Movie model'))
            ->display(function ($movie_model_id) {
                $movie = \App\Models\Movie::find($movie_model_id);
                if ($movie) {
                    return $movie->title . " (ID: {$movie->id})";
                } else {
                    return "N/A";
                }
            })
            ->sortable();
        $grid->column('type', __('Type'))->hide();
        $grid->column('image_url', __('Image url'))->lightbox(['width' => 50, 'height' => 50])->sortable();
        $grid->column('description', __('Description'))->limit(50)->$grid->column('views_count', __('Views count'));
        $grid->column('views_time', __('Views time'))->sortable()
            ->display(function ($views_time) {
                //minutes to hours
                $hours = floor($views_time / 3600);
                $minutes = floor(($views_time % 3600) / 60);
                $seconds = $views_time % 60;
                return "{$hours}h {$minutes}m {$seconds}s";
            });
        $grid->column('url', __('Url'))->hide();
        $grid->column('trending_time', __('Trending time'))->sortable()
            ->display(function ($trending_time) {
                return date('Y-m-d H:i:s', strtotime($trending_time));
            });
        $grid->column('day_time', __('Day time'))->sortable();
        $grid->column('is_sent', __('Is sent'))
            ->label([
                'Yes' => 'success',
                'No' => 'default',
            ])
            ->sortable()
            ->filter([
                'Yes' => 'Yes',
                'No' => 'No',
            ]);
        $grid->column('sent_time', __('Sent time'))
            ->sortable()
            ->display(function ($sent_time) {
                return $sent_time ? date('Y-m-d H:i:s', strtotime($sent_time)) : 'N/A';
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
        $show = new Show(TrendingNotification::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('movie_model_id', __('Movie model id'));
        $show->field('title', __('Title'));
        $show->field('type', __('Type'));
        $show->field('image_url', __('Image url'));
        $show->field('description', __('Description'));
        $show->field('views_count', __('Views count'));
        $show->field('views_time', __('Views time'));
        $show->field('url', __('Url'));
        $show->field('trending_time', __('Trending time'));
        $show->field('day_time', __('Day time'));
        $show->field('is_sent', __('Is sent'));
        $show->field('sent_time', __('Sent time'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new TrendingNotification());

        $form->number('movie_model_id', __('Movie model id'));
        $form->textarea('title', __('Title'));
        $form->text('type', __('Type'));
        $form->textarea('image_url', __('Image url'));
        $form->textarea('description', __('Description'));
        $form->number('views_count', __('Views count'));
        $form->number('views_time', __('Views time'));
        $form->textarea('url', __('Url'));
        $form->datetime('trending_time', __('Trending time'))->default(date('Y-m-d H:i:s'));
        $form->text('day_time', __('Day time'));
        $form->text('is_sent', __('Is sent'))->default('No');
        $form->datetime('sent_time', __('Sent time'))->default(date('Y-m-d H:i:s'));

        return $form;
    }
}
