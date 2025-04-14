<?php

namespace App\Admin\Controllers;

use App\Models\SeriesMovie;
use App\Models\Utils;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SeriesMovieController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Series Movies';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new SeriesMovie());
        $grid->disableBatchActions();

        $grid->column('thumbnail', __('Thumbnail'))->image('', 50, 50)->sortable();
        $grid->column('id', __('Id'))->sortable()->hide();
        $grid->column('title', __('Title'))->sortable();
        $grid->column('Category', __('Category'))->sortable();
        $grid->column('description', __('Description'))->hide();
        $grid->column('total_seasons', __('Total seasons'));
        $grid->column('total_episodes', __('Total episodes'));
        $grid->column('total_views', __('Total views')); 
        $grid->column('is_active', __('Total rating'))->editable('select', ['Yes' => 'Yes', 'No' => 'No']);

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
        $show = new Show(SeriesMovie::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('title', __('Title'));
        $show->field('Category', __('Category'));
        $show->field('description', __('Description'));
        $show->field('thumbnail', __('Thumbnail'));
        $show->field('total_seasons', __('Total seasons'));
        $show->field('total_episodes', __('Total episodes'));
        $show->field('total_views', __('Total views'));
        $show->field('total_rating', __('Total rating')); 

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new SeriesMovie());

        $form->text('title', __('Title'))->creationRules('required|unique:series_movies')->updateRules('required|unique:series_movies,title,{{id}}');
        $form->select('Category', __('Category'))
            ->options(
                Utils::$CATEGORIES
            )->rules('required');
        $form->image('thumbnail', __('Thumbnail'));
        $form->quill('description', __('Description'));
        $form->decimal('total_seasons', __('Total seasons'));
        $form->decimal('total_episodes', __('Total episodes'));
        $form->decimal('total_views', __('Total views'));
        $form->decimal('total_rating', __('Total rating'));
        $form->radio('is_active', __('Are you UPLOADING movies?'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');

        return $form;
    }
}
