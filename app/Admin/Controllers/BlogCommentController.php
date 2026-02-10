<?php

namespace App\Admin\Controllers;

use App\Models\BlogComment;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class BlogCommentController extends AdminController
{
    protected $title = 'Blog Comments';

    protected function grid()
    {
        $grid = new Grid(new BlogComment());

        $grid->model()->orderByDesc('created_at');

        $grid->column('id', __('ID'))->sortable();
        $grid->column('blog_post_id', __('Post ID'))->sortable();
        $grid->column('user_name', __('User'));
        $grid->column('content', __('Comment'))->limit(80);
        $grid->column('status', __('Status'))->label([
            'Active'   => 'success',
            'Hidden'   => 'danger',
            'Reported' => 'warning',
        ])->filter([
            'Active'   => 'Active',
            'Hidden'   => 'Hidden',
            'Reported' => 'Reported',
        ]);
        $grid->column('likes_count', __('Likes'))->sortable();
        $grid->column('created_at', __('Created'))->sortable();

        $grid->filter(function ($filter) {
            $filter->equal('blog_post_id', 'Post ID');
            $filter->equal('status', 'Status')->select([
                'Active'   => 'Active',
                'Hidden'   => 'Hidden',
                'Reported' => 'Reported',
            ]);
            $filter->like('user_name', 'User');
            $filter->like('content', 'Content');
        });

        // Batch actions
        $grid->batchActions(function ($batch) {
            $batch->disableDelete(false);
        });

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(BlogComment::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('blog_post_id', __('Post ID'));
        $show->field('user_id', __('User ID'));
        $show->field('user_name', __('User Name'));
        $show->field('content', __('Comment'));
        $show->field('status', __('Status'));
        $show->field('likes_count', __('Likes'));
        $show->field('created_at', __('Created'));
        $show->field('updated_at', __('Updated'));

        return $show;
    }

    protected function form()
    {
        $form = new Form(new BlogComment());

        $form->display('blog_post_id', __('Post ID'));
        $form->display('user_name', __('User'));
        $form->textarea('content', __('Comment'))->rows(4);
        $form->select('status', __('Status'))->options([
            'Active'   => 'Active',
            'Hidden'   => 'Hidden',
            'Reported' => 'Reported',
        ])->default('Active');

        return $form;
    }
}
