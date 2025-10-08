<?php

namespace App\Admin\Controllers;

use App\Models\MunowatchCategory;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class MunowatchCategoryController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'MunowatchCategory';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new MunowatchCategory());

        $grid->column('id', __('Id'));
        $grid->column('munowatch_id', __('Munowatch id'));
        $grid->column('name', __('Name'));
        $grid->column('slug', __('Slug'));
        $grid->column('description', __('Description'));
        $grid->column('api_endpoint', __('Api endpoint'));
        $grid->column('api_parameters', __('Api parameters'));
        $grid->column('status', __('Status'));
        $grid->column('is_featured', __('Is featured'));
        $grid->column('sort_order', __('Sort order'));
        $grid->column('total_pages', __('Total pages'));
        $grid->column('current_page', __('Current page'));
        $grid->column('total_videos_found', __('Total videos found'));
        $grid->column('new_videos_last_crawl', __('New videos last crawl'));
        $grid->column('crawl_status', __('Crawl status'));
        $grid->column('error_message', __('Error message'));
        $grid->column('last_crawled_at', __('Last crawled at'));
        $grid->column('next_crawl_at', __('Next crawl at'));
        $grid->column('crawl_frequency_hours', __('Crawl frequency hours'));
        $grid->column('videos_per_page', __('Videos per page'));
        $grid->column('success_rate', __('Success rate'));
        $grid->column('metadata', __('Metadata'));
        $grid->column('parent_category_id', __('Parent category id'));
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
        $show = new Show(MunowatchCategory::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('munowatch_id', __('Munowatch id'));
        $show->field('name', __('Name'));
        $show->field('slug', __('Slug'));
        $show->field('description', __('Description'));
        $show->field('api_endpoint', __('Api endpoint'));
        $show->field('api_parameters', __('Api parameters'));
        $show->field('status', __('Status'));
        $show->field('is_featured', __('Is featured'));
        $show->field('sort_order', __('Sort order'));
        $show->field('total_pages', __('Total pages'));
        $show->field('current_page', __('Current page'));
        $show->field('total_videos_found', __('Total videos found'));
        $show->field('new_videos_last_crawl', __('New videos last crawl'));
        $show->field('crawl_status', __('Crawl status'));
        $show->field('error_message', __('Error message'));
        $show->field('last_crawled_at', __('Last crawled at'));
        $show->field('next_crawl_at', __('Next crawl at'));
        $show->field('crawl_frequency_hours', __('Crawl frequency hours'));
        $show->field('videos_per_page', __('Videos per page'));
        $show->field('success_rate', __('Success rate'));
        $show->field('metadata', __('Metadata'));
        $show->field('parent_category_id', __('Parent category id'));
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
        $form = new Form(new MunowatchCategory());

        $form->number('munowatch_id', __('Munowatch id'));
        $form->text('name', __('Name'));
        $form->text('slug', __('Slug'));
        $form->textarea('description', __('Description'));
        $form->text('api_endpoint', __('Api endpoint'));
        $form->text('api_parameters', __('Api parameters'));
        $form->text('status', __('Status'))->default('active');
        $form->switch('is_featured', __('Is featured'));
        $form->number('sort_order', __('Sort order'));
        $form->number('total_pages', __('Total pages'));
        $form->number('current_page', __('Current page'));
        $form->number('total_videos_found', __('Total videos found'));
        $form->number('new_videos_last_crawl', __('New videos last crawl'));
        $form->text('crawl_status', __('Crawl status'))->default('pending');
        $form->textarea('error_message', __('Error message'));
        $form->datetime('last_crawled_at', __('Last crawled at'))->default(date('Y-m-d H:i:s'));
        $form->datetime('next_crawl_at', __('Next crawl at'))->default(date('Y-m-d H:i:s'));
        $form->number('crawl_frequency_hours', __('Crawl frequency hours'))->default(24);
        $form->number('videos_per_page', __('Videos per page'))->default(10);
        $form->decimal('success_rate', __('Success rate'))->default(100.00);
        $form->text('metadata', __('Metadata'));
        $form->number('parent_category_id', __('Parent category id'));

        return $form;
    }
}
