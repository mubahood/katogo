<?php

namespace App\Admin\Controllers;

use App\Models\ScraperModel;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class ScraperModelController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Scraper Models';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ScraperModel());
        $grid->quickSearch('title')->placeholder('Search by title');

        $grid->disableBatchActions();
        $grid->model()->orderBy('id', 'desc');
        $grid->column('id', __('ID'))->sortable();
        $grid->column('created_at', __('Created'))
            ->display(function ($created_at) {
                return date('Y-m-d H:i:s', strtotime($created_at));
            })->sortable();
        $grid->column('type', __('Type'))->sortable();
        $grid->column('url', __('Url'))->hide();
        $grid->column('title', __('Movies Found'))->sortable();
        $grid->column('datae', __('Datae'))->hide();
        $grid->column('status', __('Status'))->sortable();
        $grid->column('error', __('Error'))->sortable();
        $grid->column('error_message', __('Error message'));

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
        $show = new Show(ScraperModel::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('type', __('Type'));
        $show->field('url', __('Url'));
        $show->field('title', __('Title'));
        $show->field('datae', __('Datae'));
        $show->field('status', __('Status'));
        $show->field('error', __('Error'));
        $show->field('error_message', __('Error message'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new ScraperModel());

        $form->text('type', __('Type'));
        $form->textarea('url', __('Url'));
        $form->textarea('title', __('Title'));
        $form->textarea('datae', __('Datae'));
        $form->text('status', __('Status'));
        $form->text('error', __('Error'));
        $form->text('error_message', __('Error message'));

        return $form;
    }
}
