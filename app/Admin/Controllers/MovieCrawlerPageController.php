<?php

namespace App\Admin\Controllers;

use App\Models\MovieCrawlerPage;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class MovieCrawlerPageController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'MovieCrawlerPage';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new MovieCrawlerPage());

        $grid->column('id', __('Id'));
        $grid->column('created_at', __('Created at'));
        $grid->column('updated_at', __('Updated at'));
        $grid->column('movie_crawler_website_id', __('Movie crawler website id'));
        $grid->column('title', __('Title'));
        $grid->column('slug', __('Slug'));
        $grid->column('url', __('Url'));
        $grid->column('movie_id', __('Movie id'));
        $grid->column('page_content', __('Page content'));
        $grid->column('error_message', __('Error message'));
        $grid->column('status', __('Status'));
        $grid->column('last_fetched_at', __('Last fetched at'));
        $grid->column('type', __('Type'));
        $grid->column('row_id', __('Row id'));
        $grid->column('img_port_muno_file_name', __('Img port muno file name'));
        $grid->column('bunny_file_name', __('Bunny file name'));
        $grid->column('tmdb_poster_path', __('Tmdb poster path'));
        $grid->column('vj', __('Vj'));

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
        $show = new Show(MovieCrawlerPage::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('movie_crawler_website_id', __('Movie crawler website id'));
        $show->field('title', __('Title'));
        $show->field('slug', __('Slug'));
        $show->field('url', __('Url'));
        $show->field('movie_id', __('Movie id'));
        $show->field('page_content', __('Page content'));
        $show->field('error_message', __('Error message'));
        $show->field('status', __('Status'));
        $show->field('last_fetched_at', __('Last fetched at'));
        $show->field('type', __('Type'));
        $show->field('row_id', __('Row id'));
        $show->field('img_port_muno_file_name', __('Img port muno file name'));
        $show->field('bunny_file_name', __('Bunny file name'));
        $show->field('tmdb_poster_path', __('Tmdb poster path'));
        $show->field('vj', __('Vj'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new MovieCrawlerPage());

        $form->number('movie_crawler_website_id', __('Movie crawler website id'));
        $form->textarea('title', __('Title'));
        $form->textarea('slug', __('Slug'));
        $form->textarea('url', __('Url'));
        $form->textarea('movie_id', __('Movie id'));
        $form->textarea('page_content', __('Page content'));
        $form->textarea('error_message', __('Error message'));
        $form->text('status', __('Status'))->default('Pending');
        $form->datetime('last_fetched_at', __('Last fetched at'))->default(date('Y-m-d H:i:s'));
        $form->text('type', __('Type'));
        $form->text('row_id', __('Row id'));
        $form->textarea('img_port_muno_file_name', __('Img port muno file name'));
        $form->textarea('bunny_file_name', __('Bunny file name'));
        $form->textarea('tmdb_poster_path', __('Tmdb poster path'));
        $form->textarea('vj', __('Vj'));

        return $form;
    }
}
