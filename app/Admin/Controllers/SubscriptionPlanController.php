<?php

namespace App\Admin\Controllers;

use App\Models\SubscriptionPlan;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SubscriptionPlanController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'SubscriptionPlan';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new SubscriptionPlan());

        $grid->column('id', __('Id'));
        $grid->column('created_at', __('Created at'));
        $grid->column('updated_at', __('Updated at'));
        $grid->column('name', __('Name'));
        $grid->column('name_luganda', __('Name luganda'));
        $grid->column('name_swahili', __('Name swahili'));
        $grid->column('slug', __('Slug'));
        $grid->column('description', __('Description'));
        $grid->column('description_luganda', __('Description luganda'));
        $grid->column('description_swahili', __('Description swahili'));
        $grid->column('price', __('Price'));
        $grid->column('currency', __('Currency'));
        $grid->column('duration_days', __('Duration days'));
        $grid->column('features', __('Features'));
        $grid->column('features_luganda', __('Features luganda'));
        $grid->column('features_swahili', __('Features swahili'));
        $grid->column('status', __('Status'));
        $grid->column('is_featured', __('Is featured'));
        $grid->column('sort_order', __('Sort order'));
        $grid->column('discount_percentage', __('Discount percentage'));
        $grid->column('is_trial', __('Is trial'));
        $grid->column('max_downloads', __('Max downloads'));
        $grid->column('max_watchlist', __('Max watchlist'));
        $grid->column('ad_free', __('Ad free'));
        $grid->column('hd_streaming', __('Hd streaming'));
        $grid->column('created_by', __('Created by'));
        $grid->column('updated_by', __('Updated by'));

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
        $show = new Show(SubscriptionPlan::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('name', __('Name'));
        $show->field('name_luganda', __('Name luganda'));
        $show->field('name_swahili', __('Name swahili'));
        $show->field('slug', __('Slug'));
        $show->field('description', __('Description'));
        $show->field('description_luganda', __('Description luganda'));
        $show->field('description_swahili', __('Description swahili'));
        $show->field('price', __('Price'));
        $show->field('currency', __('Currency'));
        $show->field('duration_days', __('Duration days'));
        $show->field('features', __('Features'));
        $show->field('features_luganda', __('Features luganda'));
        $show->field('features_swahili', __('Features swahili'));
        $show->field('status', __('Status'));
        $show->field('is_featured', __('Is featured'));
        $show->field('sort_order', __('Sort order'));
        $show->field('discount_percentage', __('Discount percentage'));
        $show->field('is_trial', __('Is trial'));
        $show->field('max_downloads', __('Max downloads'));
        $show->field('max_watchlist', __('Max watchlist'));
        $show->field('ad_free', __('Ad free'));
        $show->field('hd_streaming', __('Hd streaming'));
        $show->field('created_by', __('Created by'));
        $show->field('updated_by', __('Updated by'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new SubscriptionPlan());

        $form->text('name', __('Name'));
        $form->text('name_luganda', __('Name luganda'));
        $form->text('name_swahili', __('Name swahili'));
        $form->text('slug', __('Slug'));
        $form->textarea('description', __('Description'));
        $form->textarea('description_luganda', __('Description luganda'));
        $form->textarea('description_swahili', __('Description swahili'));
        $form->decimal('price', __('Price'));
        $form->text('currency', __('Currency'))->default('UGX');
        $form->number('duration_days', __('Duration days'));
        $form->textarea('features', __('Features'));
        $form->textarea('features_luganda', __('Features luganda'));
        $form->textarea('features_swahili', __('Features swahili'));
        $form->text('status', __('Status'))->default('Active');
        $form->switch('is_featured', __('Is featured'));
        $form->number('sort_order', __('Sort order'));
        $form->decimal('discount_percentage', __('Discount percentage'))->default(0.00);
        $form->switch('is_trial', __('Is trial'));
        $form->number('max_downloads', __('Max downloads'));
        $form->number('max_watchlist', __('Max watchlist'));
        $form->switch('ad_free', __('Ad free'))->default(1);
        $form->switch('hd_streaming', __('Hd streaming'))->default(1);
        $form->number('created_by', __('Created by'));
        $form->number('updated_by', __('Updated by'));

        return $form;
    }
}
