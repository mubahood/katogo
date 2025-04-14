<?php

namespace App\Admin\Controllers;

use App\Models\LearningMaterialCategory;
use App\Models\LearningMaterialPost;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class LearningMaterialPostController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'LearningMaterialPost';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new LearningMaterialPost());
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $cats = [];
            foreach (LearningMaterialCategory::all() as $key => $value) {
                $cats[$value->id] = $value->name;
            }
            $filter->equal('learning_material_category_id', 'Category')
                ->select($cats);
        });
        $grid->model()->orderBy('id', 'desc');
        $grid->quickSearch('title');
        $grid->column('id', __('Id'))->sortable();
        $grid->column('title', __('Title'))->sortable()->limit(30);
        $grid->column('learning_material_category_id', __('Category'))->display(function ($learning_material_category_id) {
            return LearningMaterialPost::find($learning_material_category_id)->title;
        })->sortable();
        $grid->column('external_id', __('External id'))->sortable()->hide();
        $grid->column('short_description', __('Short description'))->hide();
        $grid->column('description', __('Description'))->hide();
        $grid->column('image', __('Image'))->hide();
        $grid->column('slug', __('Slug'))->hide();
        $grid->column('external_url', __('External url'))
            ->display(function ($external_url) {
                if ($external_url == null) {
                    return "N/A";
                }
                return "<a href='$external_url' title='$external_url' target='_blank'>VISIT</a>";
            })->sortable();
        $grid->column('external_download_url', __('Download'))
            ->display(function ($external_download_url) {
                if ($external_download_url == null) {
                    return "N/A";
                }
                return "<a href='$external_download_url' title='$external_download_url' target='_blank'>DOWNLOAD</a>";
            })->sortable();
        $grid->column('download_url', __('Download Link'))->display(function ($download_url) {
            if ($download_url == null) {
                return "N/A";
            }
            //if not contain http
            if (!str_contains($download_url, 'http')) {
                $download_url = url('storage') . '/' . $download_url;
            }

            return "<a href='$download_url' title='$download_url' target='_blank'>DOWNLOAD</a>";
        })->sortable();

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
        $show = new Show(LearningMaterialPost::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('learning_material_category_id', __('Learning material category id'));
        $show->field('title', __('Title'));
        $show->field('external_id', __('External id'));
        $show->field('short_description', __('Short description'));
        $show->field('description', __('Description'));
        $show->field('image', __('Image'));
        $show->field('slug', __('Slug'));
        $show->field('external_url', __('External url'));
        $show->field('external_download_url', __('External download url'));
        $show->field('download_url', __('Download url'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new LearningMaterialPost());

        $form->number('learning_material_category_id', __('Learning material category id'));
        $form->textarea('title', __('Title'));
        $form->textarea('external_id', __('External id'));
        $form->textarea('short_description', __('Short description'));
        $form->textarea('description', __('Description'));
        $form->textarea('image', __('Image'));
        $form->textarea('slug', __('Slug'));
        $form->textarea('external_url', __('External url'));
        $form->textarea('external_download_url', __('External download url'));
        $form->textarea('download_url', __('Download url'));

        return $form;
    }
}
