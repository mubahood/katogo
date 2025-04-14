<?php

namespace App\Admin\Controllers;

use App\Models\Link;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Termwind\Components\Li;

class LinkController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Link';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        //Link::where('school_type', 'Nursary')->update(['school_type' => 'Nursery']);
        $grid = new Grid(new Link());
        $grid->quickSearch('title');
        $grid->model()->orderBy('id', 'desc');
        $grid->column('id', __('Id'))->sortable();
        $grid->column('created_at', __('Created at'))->hide();
        $grid->column('updated_at', __('Updated at'))->hide();
        $grid->column('title', __('Title'))->sortable();
        $grid->column('url', __('Url'));
        $grid->column('external_id', __('External id'));
        $grid->column('thumbnail', __('Thumbnail'));
        $grid->column('success', __('success'))->sortable();
        $grid->column('error', __('error'))->sortable();
        $grid->column('processed', __('processed'))
            ->filter(['No' => 'No', 'Yes' => 'Yes']);
        $grid->column('type', __('Type'))
            ->label([
                'Movie' => 'info',
                'School' => 'success',
                'SHAREBILITY_RESOURCE' => 'warning',
            ])
            ->filter([
                'Movie' => 'Movie',
                'School' => 'School',
                'SHAREBILITY_RESOURCE' => 'SHAREBILITY_RESOURCE'
            ])
            ->sortable();
        $grid->column('school_type', __('School type'))
            ->label([
                'Nursery' => 'info',
                'Primary' => 'info',
                'Secondary' => 'success',
                'Tertiary' => 'danger',
                'University' => 'warning',
            ])
            ->filter([
                'Nursery' => 'Nursery',
                'Primary' => 'Primary',
                'Secondary' => 'Secondary',
                'Tertiary' => 'Tertiary',
                'University' => 'University',
            ])
            ->sortable();

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
        $show = new Show(Link::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('title', __('Title'));
        $show->field('url', __('Url'));
        $show->field('external_id', __('External id'));
        $show->field('thumbnail', __('Thumbnail'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Link());

        $form->textarea('title', __('Title'));
        $form->textarea('url', __('Url'));
        $form->textarea('external_id', __('External id'));
        $form->textarea('thumbnail', __('Thumbnail'));

        return $form;
    }
}
