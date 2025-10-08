<?php

namespace App\Admin\Controllers;

use App\Models\MunowatchMovieCategory;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class MunowatchMovieCategoryController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'MunowatchMovieCategory';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new MunowatchMovieCategory());

        $grid->column('id', __('Id'));
        $grid->column('munowatch_category_id', __('Munowatch category id'));
        $grid->column('category_name', __('Category name'));
        $grid->column('category_description', __('Category description'));
        $grid->column('api_endpoint_type', __('Api endpoint type'));
        $grid->column('api_parameters', __('Api parameters'));
        $grid->column('status', __('Status'));
        $grid->column('is_dynamic', __('Is dynamic'));
        $grid->column('sort_order', __('Sort order'));
        $grid->column('total_movies_in_category', __('Total movies in category'));
        $grid->column('movies_fetched', __('Movies fetched'));
        $grid->column('last_fetched_from_dashboard_at', __('Last fetched from dashboard at'));
        $grid->column('last_movies_fetched_at', __('Last movies fetched at'));
        $grid->column('sample_movies', __('Sample movies'));
        $grid->column('category_metadata', __('Category metadata'));
        $grid->column('has_pagination', __('Has pagination'));
        $grid->column('pagination_endpoint', __('Pagination endpoint'));
        $grid->column('current_page', __('Current page'));
        $grid->column('max_pages', __('Max pages'));
        $grid->column('last_error_message', __('Last error message'));
        $grid->column('next_fetch_at', __('Next fetch at'));
        $grid->column('fetch_frequency_hours', __('Fetch frequency hours'));
        $grid->column('created_at', __('Created at'));
        $grid->column('updated_at', __('Updated at'));

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
        $show = new Show(MunowatchMovieCategory::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('munowatch_category_id', __('Munowatch category id'));
        $show->field('category_name', __('Category name'));
        $show->field('category_description', __('Category description'));
        $show->field('api_endpoint_type', __('Api endpoint type'));
        $show->field('api_parameters', __('Api parameters'));
        $show->field('status', __('Status'));
        $show->field('is_dynamic', __('Is dynamic'));
        $show->field('sort_order', __('Sort order'));
        $show->field('total_movies_in_category', __('Total movies in category'));
        $show->field('movies_fetched', __('Movies fetched'));
        $show->field('last_fetched_from_dashboard_at', __('Last fetched from dashboard at'));
        $show->field('last_movies_fetched_at', __('Last movies fetched at'));
        $show->field('sample_movies', __('Sample movies'));
        $show->field('category_metadata', __('Category metadata'));
        $show->field('has_pagination', __('Has pagination'));
        $show->field('pagination_endpoint', __('Pagination endpoint'));
        $show->field('current_page', __('Current page'));
        $show->field('max_pages', __('Max pages'));
        $show->field('last_error_message', __('Last error message'));
        $show->field('next_fetch_at', __('Next fetch at'));
        $show->field('fetch_frequency_hours', __('Fetch frequency hours'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new MunowatchMovieCategory());

        $form->number('munowatch_category_id', __('Munowatch category id'));
        $form->text('category_name', __('Category name'));
        $form->textarea('category_description', __('Category description'));
        $form->text('api_endpoint_type', __('Api endpoint type'))->default('dashboard');
        $form->text('api_parameters', __('Api parameters'));
        $form->text('status', __('Status'))->default('active');
        $form->switch('is_dynamic', __('Is dynamic'))->default(1);
        $form->number('sort_order', __('Sort order'));
        $form->number('total_movies_in_category', __('Total movies in category'));
        $form->number('movies_fetched', __('Movies fetched'));
        $form->datetime('last_fetched_from_dashboard_at', __('Last fetched from dashboard at'))->default(date('Y-m-d H:i:s'));
        $form->datetime('last_movies_fetched_at', __('Last movies fetched at'))->default(date('Y-m-d H:i:s'));
        $form->text('sample_movies', __('Sample movies'));
        $form->text('category_metadata', __('Category metadata'));
        $form->switch('has_pagination', __('Has pagination'));
        $form->text('pagination_endpoint', __('Pagination endpoint'));
        $form->number('current_page', __('Current page'))->default(1);
        $form->number('max_pages', __('Max pages'));
        $form->textarea('last_error_message', __('Last error message'));
        $form->datetime('next_fetch_at', __('Next fetch at'))->default(date('Y-m-d H:i:s'));
        $form->number('fetch_frequency_hours', __('Fetch frequency hours'))->default(6);

        return $form;
    }
}
