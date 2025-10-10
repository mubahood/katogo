<?php

namespace App\Admin\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;

class SubscriptionPlanController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Subscription Plans';

    /**
     * Custom index method with enhanced display
     */
    public function index(Content $content)
    {
        return $content
            ->title($this->title())
            ->description('Manage subscription plans and pricing')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new SubscriptionPlan());
        $grid->model()->orderBy('sort_order', 'asc');

        // Filters
        $grid->filter(function($filter) {
            $filter->disableIdFilter();
            
            $filter->equal('status', 'Status')->select([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ]);
            
            $filter->equal('is_trial', 'Trial Plan')->select([
                1 => 'Yes',
                0 => 'No',
            ]);
            
            $filter->equal('is_featured', 'Featured')->select([
                1 => 'Yes',
                0 => 'No',
            ]);
            
            $filter->between('price', 'Price Range');
            $filter->between('duration_days', 'Duration Range (Days)');
        });

        // Columns
        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('name', __('Plan Name'))
            ->display(function ($name, $model) {
                $badges = '';
                if ($model->is_trial) {
                    $badges .= ' <span class="badge badge-info">Trial</span>';
                }
                if ($model->is_featured) {
                    $badges .= ' <span class="badge badge-warning">Featured</span>';
                }
                return "<strong>{$name}</strong>{$badges}";
            })->sortable();

        $grid->column('price', __('Price'))
            ->display(function ($price, $model) {
                if ($price == 0) {
                    return '<span class="badge badge-success">FREE</span>';
                }
                return "<strong>{$model->currency} " . number_format($price, 0) . "</strong>";
            })->sortable();

        $grid->column('duration_days', __('Duration'))
            ->display(function ($days) {
                if ($days >= 365) {
                    $years = round($days / 365, 1);
                    return "<span class='badge badge-primary'>{$years} Year(s)</span>";
                } elseif ($days >= 30) {
                    $months = round($days / 30, 1);
                    return "<span class='badge badge-info'>{$months} Month(s)</span>";
                } else {
                    return "<span class='badge badge-secondary'>{$days} Days</span>";
                }
            })->sortable();

        $grid->column('subscribers_count', __('Subscribers'))
            ->display(function ($value, $model) {
                $count = Subscription::where('plan_id', $model->id)
                    ->where('status', 'Active')
                    ->count();
                return "<span class='badge badge-success'>{$count} Active</span>";
            });

        $grid->column('status', __('Status'))
            ->display(function ($status) {
                $color = $status === 'Active' ? 'success' : 'danger';
                return "<span class='badge badge-{$color}'>{$status}</span>";
            })->sortable();

        $grid->column('max_downloads', __('Max Downloads'))
            ->display(function ($downloads) {
                return $downloads == -1 ? '<span class="badge badge-success">Unlimited</span>' : number_format($downloads);
            });

        $grid->column('sort_order', __('Order'))->sortable();

        $grid->column('created_at', __('Created'))
            ->display(function ($date) {
                return date('M j, Y', strtotime($date));
            })->sortable();

        // Actions
        $grid->actions(function ($actions) {
            $actions->disableView();
        });

        // Export
        $grid->export(function ($export) {
            $export->filename('Subscription_Plans_' . date('Y-m-d'));
            $export->except(['actions']);
        });

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

        $show->field('id', __('ID'));
        $show->field('name', __('Plan Name'));
        $show->field('description', __('Description'));
        $show->field('price', __('Price'));
        $show->field('currency', __('Currency'));
        $show->field('duration_days', __('Duration (Days)'));
        $show->field('features', __('Features'));
        $show->field('status', __('Status'));
        $show->field('is_featured', __('Featured'));
        $show->field('is_trial', __('Trial Plan'));
        $show->field('max_downloads', __('Max Downloads'));
        $show->field('max_watchlist', __('Max Watchlist'));
        $show->field('ad_free', __('Ad Free'));
        $show->field('hd_streaming', __('HD Streaming'));
        $show->field('sort_order', __('Sort Order'));
        $show->field('discount_percentage', __('Discount %'));
        $show->field('created_at', __('Created At'));
        $show->field('updated_at', __('Updated At'));

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

        $form->text('name', __('Plan Name'))->rules('required');
        $form->text('name_luganda', __('Name (Luganda)'));
        $form->text('name_swahili', __('Name (Swahili)'));
        $form->text('slug', __('Slug'))->rules('required|unique:subscription_plans,slug');
        
        $form->textarea('description', __('Description'))->rules('required');
        $form->textarea('description_luganda', __('Description (Luganda)'));
        $form->textarea('description_swahili', __('Description (Swahili)'));
        
        $form->decimal('price', __('Price'))->rules('required|numeric|min:0')->default(0);
        $form->text('currency', __('Currency'))->default('UGX')->rules('required');
        $form->number('duration_days', __('Duration (Days)'))->rules('required|integer|min:1')->default(30);
        
        $form->textarea('features', __('Features (JSON)'))->help('Enter features as JSON array');
        $form->textarea('features_luganda', __('Features (Luganda - JSON)'));
        $form->textarea('features_swahili', __('Features (Swahili - JSON)'));
        
        $form->select('status', __('Status'))->options([
            'Active' => 'Active',
            'Inactive' => 'Inactive',
        ])->default('Active')->rules('required');
        
        $form->switch('is_featured', __('Featured Plan'))->default(0);
        $form->switch('is_trial', __('Trial Plan'))->default(0);
        $form->number('sort_order', __('Sort Order'))->default(0);
        $form->number('discount_percentage', __('Discount %'))->min(0)->max(100)->default(0);
        
        $form->number('max_downloads', __('Max Downloads'))->help('-1 for unlimited')->default(-1);
        $form->number('max_watchlist', __('Max Watchlist Items'))->help('-1 for unlimited')->default(-1);
        $form->switch('ad_free', __('Ad Free'))->default(1);
        $form->switch('hd_streaming', __('HD Streaming'))->default(1);

        return $form;
    }
}
