<?php

namespace App\Admin\Controllers;

use App\Models\ContentModerationLog;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class ContentModerationLogController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'ContentModerationLog';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ContentModerationLog());

        $grid->column('id', __('Id'));
        $grid->column('content_type', __('Content type'));
        $grid->column('content_id', __('Content id'));
        $grid->column('user_id', __('User id'));
        $grid->column('moderator_id', __('Moderator id'));
        $grid->column('action_type', __('Action type'));
        $grid->column('reason', __('Reason'));
        $grid->column('filter_result', __('Filter result'));
        $grid->column('automated', __('Automated'));
        $grid->column('severity_level', __('Severity level'));
        $grid->column('metadata', __('Metadata'));
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
        $show = new Show(ContentModerationLog::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('content_type', __('Content type'));
        $show->field('content_id', __('Content id'));
        $show->field('user_id', __('User id'));
        $show->field('moderator_id', __('Moderator id'));
        $show->field('action_type', __('Action type'));
        $show->field('reason', __('Reason'));
        $show->field('filter_result', __('Filter result'));
        $show->field('automated', __('Automated'));
        $show->field('severity_level', __('Severity level'));
        $show->field('metadata', __('Metadata'));
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
        $form = new Form(new ContentModerationLog());

        $form->text('content_type', __('Content type'));
        $form->number('content_id', __('Content id'));
        $form->number('user_id', __('User id'));
        $form->number('moderator_id', __('Moderator id'));
        $form->text('action_type', __('Action type'));
        $form->text('reason', __('Reason'));
        $form->text('filter_result', __('Filter result'));
        $form->switch('automated', __('Automated'));
        $form->text('severity_level', __('Severity level'))->default('low');
        $form->text('metadata', __('Metadata'));

        return $form;
    }
}
