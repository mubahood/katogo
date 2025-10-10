<?php

namespace App\Admin\Controllers;

use App\Models\Subscription;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SubscriptionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Subscriptions';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Subscription());
        $grid->model()->orderBy('id', 'desc');

        $grid->column('id', __('Id'))->sortable();
        $grid->column('created_at', __('Created'))->display(function ($created_at) {
            return "<span>" . date('Y-m-d H:i:s', strtotime($created_at)) . "</span>";
        })->sortable();
        $grid->column('user_id', __('User'))
            ->display(function ($user_id) {
                $user = \App\Models\User::find($user_id);
                if ($user) {
                    return "<span>{$user->name} ({$user->email})</span>";
                } else {
                    return "<span style='color:red;'>User ID {$user_id} not found</span>";
                }
            })
            ->sortable();
        $grid->column('plan_id', __('Plan'))
            ->display(function ($plan_id) {
                $plan = $this->plan; // Using the relationship defined in the Subscription model
                if ($plan) {
                    return "<span>{$plan->name} ({$plan->price} {$plan->currency})</span>";
                } else {
                    return "<span style='color:red;'>Plan ID {$plan_id} not found</span>";
                }
            })
            ->sortable();
        $grid->column('days', __('Days'))->sortable();
        $grid->column('start_date_time', __('Starts'))->sortable()
            ->display(function ($end_date_time) {
                $ends = Carbon::parse($end_date_time);
                $formated_end = $ends->format('Y-m-d H:i:s');
                $now = Carbon::now();
                if ($ends->isPast()) {
                    return "<span style='color:red;'>{$formated_end} (Expired)</span>";
                } else {
                    $diff = $now->diffInDays($ends);
                    return "<span style='color:green;'>{$formated_end} (in {$diff} days)</span>";
                }
            });
        $grid->column('end_date_time', __('Ends'))->sortable()
            ->display(function ($end_date_time) {
                $ends = Carbon::parse($end_date_time);
                $formated_end = $ends->format('Y-m-d H:i:s');
                $now = Carbon::now();
                if ($ends->isPast()) {
                    return "<span style='color:red;'>{$formated_end} (Expired)</span>";
                } else {
                    $diff = $now->diffInDays($ends);
                    return "<span style='color:green;'>{$formated_end} (in {$diff} days)</span>";
                }
            });
        $grid->column('grace_period_end', __('Grace period end'))->sortable()->hide();
        $grid->column('status', __('Status'))
            ->sortable()
            ->display(function ($status) {
                $color = 'gray';
                if (strtolower($status) == 'active') {
                    $color = 'green';
                } elseif (strtolower($status) == 'expired') {
                    $color = 'red';
                } elseif (strtolower($status) == 'cancelled') {
                    $color = 'orange';
                } elseif (strtolower($status) == 'pending') {
                    $color = 'blue';
                }
                return "<span style='color:{$color};font-weight:bold;'>{$status}</span>";
            })->filter([
                'Active' => 'Active',
                'Expired' => 'Expired',
                'Cancelled' => 'Cancelled',
                'Pending' => 'Pending',
            ]);
        $grid->column('auto_renew', __('Auto renew'))->hide()->sortable();
        $grid->column('payment_method', __('Payment method'))->hide()->sortable();
        $grid->column('payment_status', __('Payment status'))->sortable();
        $grid->column('pesapal_transaction_id', __('Pesapal Transaction'))->hide()->sortable();
        $grid->column('pesapal_tracking_id', __('Pesapal tracking id'))->hide()->sortable();
        $grid->column('pesapal_merchant_reference', __('Pesapal merchant reference'))->hide()->sortable();
        $grid->column('pesapal_signature', __('Pesapal signature'))->hide()->sortable();
        $grid->column('pesapal_response', __('Pesapal response'))->hide()->sortable();
        $grid->column('payment_url', __('Payment'))->hide()->sortable();
        $grid->column('payment_confirmed_at', __('Payment confirmed at'))->hide()->sortable();
        $grid->column('failed_at', __('Failed at'))->hide()->sortable();
        $grid->column('amount_paid', __('Amount'))->sortable();
        $grid->column('currency', __('Currency'))->hide()->sortable();
        $grid->column('is_extension', __('Is extension'))->hide()->sortable();
        $grid->column('extended_from_id', __('Extended from id'))->hide()->sortable();
        $grid->column('cancelled_at', __('Cancelled at'))->hide()->sortable();
        $grid->column('cancelled_reason', __('Cancelled reason'))->hide()->sortable();
        $grid->column('cancelled_by', __('Cancelled by'))->hide()->sortable();
        $grid->column('ip_address', __('Ip address'))->hide()->sortable();
        $grid->column('user_agent', __('User agent'))->hide()->sortable();
        $grid->column('expiry_notification_sent', __('Expiry notification sent'))->hide()->sortable();
        $grid->column('expiry_notification_at', __('Expiry notification at'))->hide()->sortable();

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

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('user_id', __('User id'));
        $show->field('plan_id', __('Plan id'));
        $show->field('days', __('Days'));
        $show->field('start_date_time', __('Start date time'));
        $show->field('end_date_time', __('End date time'));
        $show->field('grace_period_end', __('Grace period end'));
        $show->field('status', __('Status'));
        $show->field('auto_renew', __('Auto renew'));
        $show->field('payment_method', __('Payment method'));
        $show->field('payment_status', __('Payment status'));
        $show->field('pesapal_transaction_id', __('Pesapal transaction id'));
        $show->field('pesapal_tracking_id', __('Pesapal tracking id'));
        $show->field('pesapal_merchant_reference', __('Pesapal merchant reference'));
        $show->field('pesapal_signature', __('Pesapal signature'));
        $show->field('pesapal_response', __('Pesapal response'));
        $show->field('payment_url', __('Payment url'));
        $show->field('payment_confirmed_at', __('Payment confirmed at'));
        $show->field('failed_at', __('Failed at'));
        $show->field('amount_paid', __('Amount paid'));
        $show->field('currency', __('Currency'));
        $show->field('is_extension', __('Is extension'));
        $show->field('extended_from_id', __('Extended from id'));
        $show->field('cancelled_at', __('Cancelled at'));
        $show->field('cancelled_reason', __('Cancelled reason'));
        $show->field('cancelled_by', __('Cancelled by'));
        $show->field('ip_address', __('Ip address'));
        $show->field('user_agent', __('User agent'));
        $show->field('expiry_notification_sent', __('Expiry notification sent'));
        $show->field('expiry_notification_at', __('Expiry notification at'));

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

        $form->number('user_id', __('User id'));
        $form->number('plan_id', __('Plan id'));
        $form->number('days', __('Days'));
        $form->datetime('start_date_time', __('Start date time'))->default(date('Y-m-d H:i:s'));
        $form->datetime('end_date_time', __('End date time'))->default(date('Y-m-d H:i:s'));
        $form->datetime('grace_period_end', __('Grace period end'))->default(date('Y-m-d H:i:s'));
        $form->text('status', __('Status'))->default('Pending');
        $form->switch('auto_renew', __('Auto renew'));
        $form->text('payment_method', __('Payment method'))->default('pesapal');
        $form->text('payment_status', __('Payment status'))->default('Pending');
        $form->text('pesapal_transaction_id', __('Pesapal transaction id'));
        $form->text('pesapal_tracking_id', __('Pesapal tracking id'));
        $form->text('pesapal_merchant_reference', __('Pesapal merchant reference'));
        $form->textarea('pesapal_signature', __('Pesapal signature'));
        $form->text('pesapal_response', __('Pesapal response'));
        $form->textarea('payment_url', __('Payment url'));
        $form->datetime('payment_confirmed_at', __('Payment confirmed at'))->default(date('Y-m-d H:i:s'));
        $form->datetime('failed_at', __('Failed at'))->default(date('Y-m-d H:i:s'));
        $form->decimal('amount_paid', __('Amount paid'));
        $form->text('currency', __('Currency'))->default('UGX');
        $form->switch('is_extension', __('Is extension'));
        $form->number('extended_from_id', __('Extended from id'));
        $form->datetime('cancelled_at', __('Cancelled at'))->default(date('Y-m-d H:i:s'));
        $form->textarea('cancelled_reason', __('Cancelled reason'));
        $form->number('cancelled_by', __('Cancelled by'));
        $form->text('ip_address', __('Ip address'));
        $form->textarea('user_agent', __('User agent'));
        $form->switch('expiry_notification_sent', __('Expiry notification sent'));
        $form->datetime('expiry_notification_at', __('Expiry notification at'))->default(date('Y-m-d H:i:s'));

        return $form;
    }
}
