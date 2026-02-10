<?php

namespace App\Admin\Controllers;

use App\Models\BlogPost;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class BlogPostController extends AdminController
{
    protected $title = 'Blog Posts';

    private const CATEGORIES = [
        'General'      => 'General',
        'News'         => 'News',
        'Update'       => 'Update',
        'Announcement' => 'Announcement',
        'Tips'         => 'Tips',
    ];

    private const STATUSES = [
        'Active'   => 'Active',
        'Draft'    => 'Draft',
        'Archived' => 'Archived',
    ];

    protected function grid()
    {
        $grid = new Grid(new BlogPost());

        $grid->model()->orderByDesc('is_pinned')->orderByDesc('created_at');

        $grid->column('id', __('ID'))->sortable();
        $grid->column('image_url', __('Thumb'))->image('', 50, 50);
        $grid->column('title', __('Title'))->limit(50)->sortable();
        $grid->column('category', __('Category'))->label('primary')->sortable()->filter(self::CATEGORIES);
        $grid->column('author_name', __('Author'));
        $grid->column('status', __('Status'))->label([
            'Active'   => 'success',
            'Draft'    => 'warning',
            'Archived' => 'default',
        ])->filter(self::STATUSES);
        $grid->column('is_pinned', __('Pinned'))->switch()->sortable();
        $grid->column('comments_enabled', __('Comments'))->switch();
        $grid->column('views_count', __('Views'))->sortable();
        $grid->column('likes_count', __('Likes'))->sortable();
        $grid->column('comments_count', __('Cmts'))->sortable();
        $grid->column('created_at', __('Created'))->sortable();

        // Filters
        $grid->filter(function ($filter) {
            $filter->like('title', 'Title');
            $filter->equal('status', 'Status')->select(self::STATUSES);
            $filter->equal('category', 'Category')->select(self::CATEGORIES);
            $filter->equal('is_pinned', 'Pinned')->select([0 => 'No', 1 => 'Yes']);
        });

        // Quick create
        $grid->quickCreate(function (Grid\Tools\QuickCreate $create) {
            $create->text('title', 'Title');
            $create->select('category', 'Category')->options(self::CATEGORIES)->default('General');
            $create->select('status', 'Status')->options(self::STATUSES)->default('Active');
        });

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(BlogPost::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('title', __('Title'));
        $show->field('excerpt', __('Excerpt'));
        $show->field('content', __('Content'))->unescape();
        $show->field('image_url', __('Image'))->image();
        $show->field('category', __('Category'))->label('primary');
        $show->field('author_name', __('Author'));
        $show->field('status', __('Status'))->label([
            'Active'   => 'success',
            'Draft'    => 'warning',
            'Archived' => 'default',
        ]);
        $show->field('is_pinned', __('Pinned'));
        $show->field('comments_enabled', __('Comments Enabled'));
        $show->field('views_count', __('Views'));
        $show->field('likes_count', __('Likes'));
        $show->field('comments_count', __('Comments'));
        $show->field('created_at', __('Created'));
        $show->field('updated_at', __('Updated'));

        return $show;
    }

    protected function form()
    {
        $form = new Form(new BlogPost());

        // ── Main content ─────────────────
        $form->text('title', __('Title'))->required()->rules('max:500');
        $form->text('excerpt', __('Excerpt'))
            ->help('Short summary for list views (max 300 chars). Leave blank to auto-generate from content.')
            ->rules('max:300');
        $form->quill('content', __('Content'))
            ->required()
            ->help('Full blog post content (HTML)');
        $form->image('image_url', __('Featured Image'))
            ->removable()
            ->help('Recommended: 16:9 ratio, at least 800×450px');

        // ── Meta ─────────────────────────
        $form->select('category', __('Category'))->options(self::CATEGORIES)
            ->default('General')->required();
        $form->text('author_name', __('Author Name'))->default('Admin');
        $form->select('status', __('Status'))->options(self::STATUSES)
            ->default('Active')->required();

        // ── Toggles ──────────────────────
        $form->switch('is_pinned', __('Pinned'))->default(0)
            ->help('Pinned posts appear first in the list and as hero cards');
        $form->switch('comments_enabled', __('Comments Enabled'))->default(1);

        // ── Stats (read-only) ────────────
        $form->display('views_count', __('Views'));
        $form->display('likes_count', __('Likes'));
        $form->display('comments_count', __('Comments'));

        // ── Auto-generate excerpt on save ──
        $form->saving(function (Form $form) {
            // If excerpt is empty, auto-generate from content
            if (empty($form->excerpt) && !empty($form->content)) {
                $plain = strip_tags($form->content);
                $form->excerpt = mb_substr($plain, 0, 200) . (mb_strlen($plain) > 200 ? '…' : '');
            }
        });

        return $form;
    }
}
