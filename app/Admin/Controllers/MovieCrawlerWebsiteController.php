<?php

namespace App\Admin\Controllers;

use App\Models\MovieCrawlerWebsite;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class MovieCrawlerWebsiteController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'MovieCrawlerWebsite';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new MovieCrawlerWebsite());
        $grid->model()->orderBy('id', 'desc');

        $grid->tools(function ($tools) {
            $tools->append(
                '<a href="' . admin_url('movie-crawler-websites/trigger-crawl') . '"'
                . ' class="btn btn-sm btn-success"'
                . ' onclick="return confirm(\'Start MunoWatch crawl now? This runs in background.\')">'
                . '<i class="fa fa-refresh"></i> Trigger Crawl'
                . '</a>'
            );
        });

        $grid->column('id', __('Id'))->sortable()->hide();
        $grid->column('name', __('Name'))->sortable();
        $grid->column('url', __('Url'))->hide();
        $grid->column('about', __('About'))->hide();
        $grid->column('priority', __('Priority'))->hide();
        $grid->column('last_fetched_at', __('Last fetched at'))->sortable();
        $grid->column('page_number', __('Page number'))->sortable();
        $grid->column('total_movies_found', __('Total movies found'))->sortable();
        $grid->column('new_movies_found', __('New movies found'))->sortable();
        $grid->column('status', __('Status'));
        $grid->column('fetch_status', __('Fetch status'))
            ->sortable();
        $grid->column('failed_message', __('Failed message'));
        $grid->column('response_data', __('Response data'))->hide()
            ->sortable();
        $grid->column('slug', __('Slug'))->sortable();
        $grid->column('last_page_url', __('Last page url'));
        $grid->column('max_page', __('Max page'))->hide();
        $grid->column('error_message', __('Error message'))->sortable();
        return $grid;
    }

    public function triggerCrawl()
    {
        $log = storage_path('logs/munowatch_crawl.log');
        $cmd = 'php ' . base_path('artisan') . ' munowatch:crawl --delay=300 --batch=20 --limit=200 >> ' . $log . ' 2>&1 &';
        exec($cmd);

        admin_success('Crawl triggered', 'MunoWatch crawl is running in background. Check logs/munowatch_crawl.log for progress.');
        return redirect(admin_url('movie-crawler-websites'));
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(MovieCrawlerWebsite::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('name', __('Name'));
        $show->field('url', __('Url'));
        $show->field('about', __('About'));
        $show->field('priority', __('Priority'));
        $show->field('last_fetched_at', __('Last fetched at'));
        $show->field('page_number', __('Page number'));
        $show->field('total_movies_found', __('Total movies found'));
        $show->field('new_movies_found', __('New movies found'));
        $show->field('status', __('Status'));
        $show->field('fetch_status', __('Fetch status'));
        $show->field('failed_message', __('Failed message'));
        $show->field('response_data', __('Response data'));
        $show->field('slug', __('Slug'));
        $show->field('last_page_url', __('Last page url'));
        $show->field('max_page', __('Max page'));
        $show->field('error_message', __('Error message'));
        $show->field('email', __('Email'));
        $show->field('password', __('Password'));
        $show->field('token', __('Token'));
        $show->field('token_expiry', __('Token expiry'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new MovieCrawlerWebsite());

        $form->text('name', __('Name'));
        $form->text('url', __('Url'));
        $form->text('about', __('About'));
        $form->number('priority', __('Priority'))->default(1);
        $form->datetime('last_fetched_at', __('Last fetched at'))->default(date('Y-m-d H:i:s'));
        $form->number('page_number', __('Page number'));
        $form->number('total_movies_found', __('Total movies found'));
        $form->number('new_movies_found', __('New movies found'));
        $form->radio('status', __('Status'))
            ->options(['Active' => 'Active', 'Inactive' => 'Inactive'])
            ->default('Active');
        $form->text('fetch_status', __('Fetch status'));
        $form->text('failed_message', __('Failed message'));
        $form->text('response_data', __('Response data'));
        $form->text('slug', __('Slug'));
        $form->text('last_page_url', __('Last page url'));
        $form->text('max_page', __('Max page'));
        $form->text('error_message', __('Error message'));
        $form->text('email', __('Email'));
        $form->text('password', __('Password'));
        $form->text('token', __('Token'));
        $form->text('token_expiry', __('Token expiry'));

        return $form;
    }
}
