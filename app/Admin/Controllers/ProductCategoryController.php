<?php

namespace App\Admin\Controllers;

use App\Models\ProductCategory;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class ProductCategoryController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Categories';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ProductCategory());
        $grid->disableBatchActions();

        $grid->column('id', __('#ID'))->sortable();
        $grid->column('category', __('Category'))->sortable();
        $grid->column('is_parent', __('Cateogry Type'))
            ->display(function ($is_parent) {
                return $is_parent == 'Yes'
                    ? "<span style='color: green; font-weight: bold;'>Main Category</span>"
                    : "<span style='color: red; font-weight: bold;'>Sub Category</span>";
            })
            ->filter(['Yes' => 'Main Category', 'No' => 'Sub Category'])
            ->sortable();
        $grid->column('show_in_banner', __('Show in Banner'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable();
        $grid->column('show_in_categories', __('Show in Categories'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable();
        //is_first_banner
        $grid->column('is_first_banner', __('Is First Banner'))
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
        $show = new Show(ProductCategory::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('category', __('Category'));
        $show->field('status', __('Status'));
        $show->field('user', __('User'));
        $show->field('date_created', __('Date created'));
        $show->field('date_updated', __('Date updated'));
        $show->field('url', __('Url'));
        $show->field('default_amount', __('Default amount'));
        $show->field('image', __('Image'));
        $show->field('image_origin', __('Image origin'));
        $show->field('banner_image', __('Banner image'));
        $show->field('show_in_banner', __('Show in banner'));
        $show->field('show_in_categories', __('Show in categories'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new ProductCategory());



        $form->text('category', __('Category Name'))->required();

        $form->radio('is_parent', __('Is Main Category'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->when('No', function (Form $form) {
                $parentCategories = ProductCategory::where('is_parent', 'Yes')
                    ->get()
                    ->pluck('category', 'id');
                $form->select('parent_id', __('Select Parent Category'))
                    ->options($parentCategories)
                    ->rules('required');
            })->rules('required');


        // $form->list('attributes', __('Category Attributes'))->required();
        $form->image('image', __('Main Photo'))->required()->uniqueName();
        $form->image('banner_image', __('Banner image'))->uniqueName();


        $form->radio('show_in_banner', __('Show in banner'))->options(['Yes' => 'Yes', 'No' => 'No'])->required();
        $form->radio('show_in_categories', __('Show in categories'))->options(['Yes' => 'Yes', 'No' => 'No'])->required();
        //is_first_banner
        $form->radio('is_first_banner', __('Is First Banner'))->options(['Yes' => 'Yes', 'No' => 'No'])
            ->when('Yes', function (Form $form) {
                $form->image('first_banner_image', __('First Banner Image'))
                    ->uniqueName();
            });
        /* 
                    $table->string('is_first_banner')->default('No')->nullable();
            $table->text('first_banner_image')->nullable()->nullable();
        */

        return $form;
    }
}
