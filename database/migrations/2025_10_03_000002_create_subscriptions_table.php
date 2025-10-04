<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Subscriptions Table
     * Stores user subscriptions with payment details and Pesapal integration
     */
    public function up(): void
    {
        // Schema::dropIfExists('subscriptions');
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            // Foreign Keys (NO CASCADE - handle in code)
            $table->unsignedBigInteger('user_id')->comment('User who owns this subscription');
            $table->unsignedBigInteger('plan_id')->comment('Subscription plan');

            // Subscription Period
            $table->integer('days')->comment('Number of days in this subscription');
            $table->dateTime('start_date_time')->comment('Subscription start date and time');
            $table->dateTime('end_date_time')->comment('Subscription end date and time');
            $table->dateTime('grace_period_end')->nullable()->comment('Grace period end (3 days after end_date_time)');

            // Status
            $table->enum('status', ['Pending', 'Active', 'Expired', 'Cancelled', 'Failed'])
                ->default('Pending')
                ->comment('Subscription status');
            $table->boolean('auto_renew')->default(false)->comment('Automatically renew subscription?');

            // Payment Details
            $table->string('payment_method', 50)->default('pesapal')->comment('Payment method used');
            $table->enum('payment_status', ['Pending', 'Processing', 'Completed', 'Failed', 'Refunded'])
                ->default('Pending')
                ->comment('Payment status');

            // Pesapal Integration Fields
            $table->string('pesapal_transaction_id', 191)->nullable()->comment('Internal transaction ID');
            $table->string('pesapal_tracking_id', 191)->nullable()->comment('Pesapal order_tracking_id');
            $table->string('pesapal_merchant_reference', 191)->nullable()->unique()->comment('Unique merchant reference');
            $table->text('pesapal_signature')->nullable()->comment('Pesapal signature for verification');
            $table->json('pesapal_response')->nullable()->comment('Full Pesapal response JSON');

            // Amount & Currency
            $table->decimal('amount_paid', 15, 2)->comment('Amount paid for this subscription');
            $table->string('currency', 10)->default('UGX')->comment('Currency code');

            // Extension Tracking (for renewals)
            $table->boolean('is_extension')->default(false)->comment('Is this an extension of existing subscription?');
            $table->unsignedBigInteger('extended_from_id')->nullable()->comment('Previous subscription ID if extension');

            // Cancellation Details
            $table->dateTime('cancelled_at')->nullable()->comment('When subscription was cancelled');
            $table->text('cancelled_reason')->nullable()->comment('Reason for cancellation');
            $table->unsignedBigInteger('cancelled_by')->nullable()->comment('User/Admin who cancelled');

            // Request Metadata (for audit)
            $table->string('ip_address', 45)->nullable()->comment('User IP address during purchase');
            $table->text('user_agent')->nullable()->comment('User browser/device info');

            // Notification Tracking
            $table->boolean('expiry_notification_sent')->default(false)->comment('Has expiry notification been sent?');
            $table->dateTime('expiry_notification_at')->nullable()->comment('When expiry notification was sent');

            // Indexes for performance
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index(['status', 'end_date_time'], 'idx_status_end_date');
            $table->index('pesapal_tracking_id', 'idx_pesapal_tracking');
            $table->index('pesapal_merchant_reference', 'idx_merchant_reference');
            $table->index('payment_status', 'idx_payment_status');
            $table->index(['status', 'end_date_time', 'expiry_notification_sent'], 'idx_expiry_check');
            $table->index('plan_id', 'idx_plan');
            $table->index('extended_from_id', 'idx_extended_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
