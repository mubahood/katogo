<?php

namespace App\Admin\Controllers;

use App\Models\SubscriptionTransaction;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SubscriptionTransactionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'SubscriptionTransaction';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new SubscriptionTransaction());
        $grid->model()->orderBy('id', 'desc');

        $grid->column('id', __('Id'))->sortable();
        $grid->column('created_at', __('Created at'))->sortable()
            ->display(function ($created_at) {
                return date('Y-m-d H:i:s', strtotime($created_at));
            });
        $grid->column('subscription_id', __('Subscription id'))->sortable();
        $grid->column('user_id', __('User'))
            ->display(function ($user_id) {
                $user = \App\Models\User::find($user_id);
                if ($user) {
                    return $user->name . " (ID: {$user->id})";
                } else {
                    return "N/A";
                }
            })
            ->sortable();
        $grid->column('transaction_type', __('Transaction type'))->sortable();
        $grid->column('amount', __('Amount'))->sortable();
        // $grid->column('currency', __('Currency'));
        $grid->column('status', __('Status'))
            ->label([
                'Pending' => 'warning',
                'Completed' => 'success',
                'Failed' => 'danger',
                'Refunded' => 'info',
            ])
            ->sortable()
            ->filter([
                'Pending' => 'Pending',
                'Completed' => 'Completed',
                'Failed' => 'Failed',
                'Refunded' => 'Refunded',
            ]);
        $grid->column('pesapal_tracking_id', __('Pesapal tracking id'))->sortable();
        $grid->column('merchant_reference', __('Merchant reference'))->sortable();
        $grid->column('payment_method', __('Payment method'))->sortable();
        $grid->column('confirmation_code', __('Confirmation code'))->sortable();
        $grid->column('payment_account', __('Payment account'))->sortable();
        $grid->column('request_payload', __('Request payload'))->sortable();
        $grid->column('response_payload', __('Response payload'))->sortable();
        $grid->column('error_message', __('Error message'))->sortable();
        $grid->column('ip_address', __('Ip address'))->sortable();
        $grid->column('user_agent', __('User agent'))->sortable()->sortable();
        $grid->column('refunded_by', __('Refunded by'))->sortable();
        $grid->column('refunded_at', __('Refunded at'))->sortable();
        $grid->column('refund_reason', __('Refund reason'))->sortable();

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
        $show = new Show(SubscriptionTransaction::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('subscription_id', __('Subscription id'));
        $show->field('user_id', __('User id'));
        $show->field('transaction_type', __('Transaction type'));
        $show->field('amount', __('Amount'));
        $show->field('currency', __('Currency'));
        $show->field('status', __('Status'));
        $show->field('pesapal_tracking_id', __('Pesapal tracking id'));
        $show->field('merchant_reference', __('Merchant reference'));
        $show->field('payment_method', __('Payment method'));
        $show->field('confirmation_code', __('Confirmation code'));
        $show->field('payment_account', __('Payment account'));
        $show->field('request_payload', __('Request payload'));
        $show->field('response_payload', __('Response payload'));
        $show->field('error_message', __('Error message'));
        $show->field('ip_address', __('Ip address'));
        $show->field('user_agent', __('User agent'));
        $show->field('refunded_by', __('Refunded by'));
        $show->field('refunded_at', __('Refunded at'));
        $show->field('refund_reason', __('Refund reason'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new SubscriptionTransaction());

        $form->number('subscription_id', __('Subscription id'));
        $form->number('user_id', __('User id'));
        $form->text('transaction_type', __('Transaction type'))->default('Initial');
        $form->decimal('amount', __('Amount'));
        $form->text('currency', __('Currency'))->default('UGX');
        $form->text('status', __('Status'))->default('Pending');
        $form->text('pesapal_tracking_id', __('Pesapal tracking id'));
        $form->text('merchant_reference', __('Merchant reference'));
        $form->text('payment_method', __('Payment method'));
        $form->text('confirmation_code', __('Confirmation code'));
        $form->textarea('payment_account', __('Payment account'));
        // $form->text('request_payload', __('Request payload'));
        // $form->text('response_payload', __('Response payload'));
        // $form->textarea('error_message', __('Error message'));
        // $form->text('ip_address', __('Ip address'));
        // $form->textarea('user_agent', __('User agent'));
        // $form->number('refunded_by', __('Refunded by'));
        // $form->datetime('refunded_at', __('Refunded at'))->default(date('Y-m-d H:i:s'));
        // $form->textarea('refund_reason', __('Refund reason'));
        return $form;
    }
}
