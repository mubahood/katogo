<?php

namespace App\Admin\Controllers;

use App\Models\MyCounter;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class MyCounterController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'MyCounter';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new MyCounter());
        $grid->quickSearch('type','count_value','data','status_message')->placeholder('Search by type, count_value, status, status_message');

        $grid->column('id', __('Id'))->sortable();

        $grid->column('updated_at', __('Updated at'))->sortable();
        $grid->column('type', __('Type'))->sortable();
        $grid->column('count_value', __('Count value'))->sortable();
        $grid->column('status', __('Status'));
        $grid->column('status_message', __('Status message'));
        $grid->column('data', __('Data'));

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
        $show = new Show(MyCounter::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('type', __('Type'));
        $show->field('count_value', __('Count value'));
        $show->field('status', __('Status'));
        $show->field('status_message', __('Status message'));
        $show->field('data', __('Data'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new MyCounter());

        $form->text('type', __('Type'));
        $form->number('count_value', __('Count value'));
        $form->text('status', __('Status'))->default('SUCCESS');
        $form->textarea('status_message', __('Status message'));
        $form->textarea('data', __('Data'));

        return $form;
    }
}
