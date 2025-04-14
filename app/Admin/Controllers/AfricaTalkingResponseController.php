<?php

namespace App\Admin\Controllers;

use App\Models\AfricaTalkingResponse;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class AfricaTalkingResponseController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'AfricaTalkingResponse';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AfricaTalkingResponse());

        $grid->column('id', __('Id'));
        $grid->column('created_at', __('Created at'));
        $grid->column('updated_at', __('Updated at'));
        $grid->column('sessionId', __('SessionId'));
        $grid->column('status', __('Status'));
        $grid->column('phoneNumber', __('PhoneNumber'));
        $grid->column('errorMessage', __('ErrorMessage'));
        $grid->column('post', __('Post'));
        $grid->column('get', __('Get'));
        $grid->column('recording_url', __('Recording url'));

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
        $show = new Show(AfricaTalkingResponse::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('sessionId', __('SessionId'));
        $show->field('status', __('Status'));
        $show->field('phoneNumber', __('PhoneNumber'));
        $show->field('errorMessage', __('ErrorMessage'));
        $show->field('post', __('Post'));
        $show->field('get', __('Get'));
        $show->field('recording_url', __('Recording url'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new AfricaTalkingResponse());

        $form->text('sessionId', __('SessionId'));
        $form->text('status', __('Status'));
        $form->textarea('phoneNumber', __('PhoneNumber'));
        $form->textarea('errorMessage', __('ErrorMessage'));
        $form->textarea('post', __('Post'));
        $form->textarea('get', __('Get'));
        $form->textarea('recording_url', __('Recording url'));

        return $form;
    }
}
