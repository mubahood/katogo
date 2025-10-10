<?php

namespace App\Admin\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Box;

class SubscriptionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Subscription Management';

    /**
     * Custom index method with dashboard
     */
    public function index(Content $content)
    {
        return $content
            ->title($this->title())
            ->description('Manage and monitor all subscriptions')
            ->row($this->dashboardBoxes())
            ->body($this->grid());
    }

    /**
     * Dashboard analytics boxes
     */
    protected function dashboardBoxes()
    {
        $totalRevenue = Subscription::where('payment_status', 'Completed')
            ->sum('amount_paid');

        $activeSubscriptions = Subscription::where('status', 'Active')->count();

        $pendingPayments = Subscription::where('payment_status', 'Pending')->count();

        $monthlyRevenue = Subscription::where('payment_status', 'Completed')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount_paid');

        return "<div class='row'>
            <div class='col-lg-3 col-6'>
                <div class='small-box bg-info'>
                    <div class='inner'>
                        <h3>UGX " . number_format($totalRevenue, 0) . "</h3>
                        <p>Total Revenue</p>
                    </div>
                    <div class='icon'>
                        <i class='ion ion-bag'></i>
                    </div>
                </div>
            </div>
            <div class='col-lg-3 col-6'>
                <div class='small-box bg-success'>
                    <div class='inner'>
                        <h3>{$activeSubscriptions}</h3>
                        <p>Active Subscriptions</p>
                    </div>
                    <div class='icon'>
                        <i class='ion ion-stats-bars'></i>
                    </div>
                </div>
            </div>
            <div class='col-lg-3 col-6'>
                <div class='small-box bg-warning'>
                    <div class='inner'>
                        <h3>UGX " . number_format($monthlyRevenue, 0) . "</h3>
                        <p>This Month</p>
                    </div>
                    <div class='icon'>
                        <i class='ion ion-person-add'></i>
                    </div>
                </div>
            </div>
            <div class='col-lg-3 col-6'>
                <div class='small-box bg-danger'>
                    <div class='inner'>
                        <h3>{$pendingPayments}</h3>
                        <p>Pending Payments</p>
                    </div>
                    <div class='icon'>
                        <i class='ion ion-pie-graph'></i>
                    </div>
                </div>
            </div>
        </div>";
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Subscription());
        $grid->model()->with(['user', 'plan'])->orderBy('id', 'desc');

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->equal('status', 'Status')->select([
                'Pending' => 'Pending',
                'Active' => 'Active',
                'Expired' => 'Expired',
                'Cancelled' => 'Cancelled',
                'Failed' => 'Failed',
            ]);

            $filter->equal('payment_status', 'Payment Status')->select([
                'Pending' => 'Pending',
                'Processing' => 'Processing',
                'Completed' => 'Completed',
                'Failed' => 'Failed',
                'Refunded' => 'Refunded',
            ]);

            $filter->equal('plan_id', 'Plan')->select(
                SubscriptionPlan::pluck('name', 'id')->toArray()
            );

            $filter->like('user.name', 'User Name');
            $filter->like('user.email', 'User Email');
            $filter->between('created_at', 'Created Date')->datetime();
            $filter->between('amount_paid', 'Amount Range');
        });

        // Columns
        $grid->column('id', __('ID'))->sortable();

        $grid->column('user.name', __('User'))
            ->display(function ($name) {
                $model = $this;
                if ($model->user) {
                    return "<a href='/admin/users/{$model->user->id}'>{$model->user->name}</a><br><small>{$model->user->email}</small>";
                }
                return '<span class="text-danger">User not found</span>';
            });

        $grid->column('plan.name', __('Plan'))
            ->display(function ($planName) {
                $model = $this; 
                if ($model->plan) {
                    return "<strong>{$model->plan->name}</strong><br><small>{$model->plan->duration_days} days</small>";
                }
                return '<span class="text-danger">Plan not found</span>';
            });

        $grid->column('amount_paid', __('Amount'))
            ->display(function ($amount) {
                $model = $this;
                return "<strong>{$model->currency} " . number_format($amount, 2) . "</strong>";
            })->sortable();

        $grid->column('status', __('Status'))
            ->display(function ($status) {
                $colors = [
                    'Active' => 'success',
                    'Pending' => 'warning',
                    'Expired' => 'danger',
                    'Cancelled' => 'secondary',
                    'Failed' => 'danger',
                ];
                $color = $colors[$status] ?? 'info';
                return "<span class='btn btn-sm btn-{$color}'>{$status}</span>";
            })->sortable();

        $grid->column('payment_status', __('Payment'))
            ->display(function ($payment_status) {
                $colors = [
                    'Completed' => 'success',
                    'Pending' => 'warning',
                    'Processing' => 'info',
                    'Failed' => 'danger',
                    'Refunded' => 'secondary',
                ];
                $color = $colors[$payment_status] ?? 'light';
                return "<span class='badge badge-{$color}'>{$payment_status}</span>";
            })->sortable();

        $grid->column('start_date_time', __('Start Date'))
            ->display(function ($date) {
                return $date ? Carbon::parse($date)->format('M j, Y H:i') : '-';
            })->sortable();

        $grid->column('end_date_time', __('End Date'))
            ->display(function ($date) {
                return $date ? Carbon::parse($date)->format('M j, Y H:i') : '-';
            })->sortable();

        $grid->column('days_remaining', __('Days Left'))
            ->display(function ($value) {
                $model = $this;
                if ($model->status === 'Active' && $model->end_date_time) {
                    $days = Carbon::now()->diffInDays(Carbon::parse($model->end_date_time), false);
                    if ($days > 0) {
                        return "<span class='badge badge-success'>{$days} days</span>";
                    } elseif ($days >= -3) {
                        return "<span class='badge badge-warning'>Grace period</span>";
                    } else {
                        return "<span class='badge badge-danger'>Expired</span>";
                    }
                }
                return '-';
            });

        $grid->column('created_at', __('Created'))
            ->display(function ($date) {
                return Carbon::parse($date)->format('M j, Y H:i');
            })->sortable();

        // Export
        $grid->export(function ($export) {
            $export->filename('Subscriptions_' . date('Y-m-d'));
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
        $show = new Show(Subscription::findOrFail($id));
        $show->resource('/admin/subscriptions');

        $show->field('id', __('ID'));
        $show->field('user.name', __('User Name'));
        $show->field('user.email', __('User Email'));
        $show->field('plan.name', __('Plan Name'));
        $show->field('plan.duration_days', __('Plan Duration'));
        $show->field('amount_paid', __('Amount Paid'));
        $show->field('currency', __('Currency'));
        $show->field('status', __('Status'));
        $show->field('payment_status', __('Payment Status'));
        $show->field('start_date_time', __('Start Date'));
        $show->field('end_date_time', __('End Date'));
        $show->field('pesapal_order_tracking_id', __('Pesapal Order ID'));
        $show->field('pesapal_merchant_reference', __('Merchant Reference'));
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
        $form = new Form(new Subscription());

        $form->text('user_id', __('User'))
            ->rules('required');

        $form->radio('plan_id', __('Plan'))
            ->options(SubscriptionPlan::pluck('name', 'id'))
            ->rules('required');

        $form->decimal('amount_paid', __('Amount Paid'))
            ->rules('required|numeric|min:0');

        $form->text('currency', __('Currency'))
            ->default('UGX')
            ->rules('required');

        $form->radio('status', __('Status'))
            ->options([
                'Pending' => 'Pending',
                'Active' => 'Active',
                'Expired' => 'Expired',
                'Cancelled' => 'Cancelled',
                'Failed' => 'Failed',
            ])
            ->default('Pending')
            ->rules('required');

        $form->radio('payment_status', __('Payment Status'))
            ->options([
                'Pending' => 'Pending',
                'Processing' => 'Processing',
                'Completed' => 'Completed',
                'Failed' => 'Failed',
                'Refunded' => 'Refunded',
            ])
            ->default('Pending')
            ->rules('required');

        $form->datetime('start_date_time', __('Start Date'));
        $form->datetime('end_date_time', __('End Date')); 

        return $form;
    }
}
