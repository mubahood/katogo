<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Subscription Transactions Table
     * Audit trail for all subscription payment transactions
     */
    public function up(): void
    {
        // Schema::DropIfExists('subscription_transactions');
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            // Relations
            $table->unsignedBigInteger('subscription_id')->comment('Related subscription');
            $table->unsignedBigInteger('user_id')->comment('User who made the transaction');

            // Transaction Details
            $table->enum('transaction_type', ['Initial', 'Renewal', 'Upgrade', 'Downgrade', 'Refund'])
                ->default('Initial')
                ->comment('Type of transaction');
            $table->decimal('amount', 15, 2)->comment('Transaction amount');
            $table->string('currency', 10)->default('UGX')->comment('Currency code');
            $table->enum('status', ['Pending', 'Processing', 'Completed', 'Failed', 'Refunded'])
                ->default('Pending')
                ->comment('Transaction status');

            // Pesapal Details (mirrored from main transaction)
            $table->string('pesapal_tracking_id', 191)->nullable()->comment('Pesapal order_tracking_id');
            $table->string('merchant_reference', 191)->nullable()->comment('Pesapal merchant reference');
            $table->string('payment_method', 50)->nullable()->comment('Payment method used (Visa, MTN, etc)');
            $table->string('confirmation_code', 191)->nullable()->comment('Pesapal confirmation code');
            $table->text('payment_account')->nullable()->comment('Payment account details (masked)');

            // Audit Trail
            $table->json('request_payload')->nullable()->comment('Original payment request data');
            $table->json('response_payload')->nullable()->comment('Payment gateway response');
            $table->text('error_message')->nullable()->comment('Error message if transaction failed');
            $table->string('ip_address', 45)->nullable()->comment('User IP address');
            $table->text('user_agent')->nullable()->comment('User browser/device info');

            // Refund Details
            $table->unsignedBigInteger('refunded_by')->nullable()->comment('Admin who processed refund');
            $table->dateTime('refunded_at')->nullable()->comment('When refund was processed');
            $table->text('refund_reason')->nullable()->comment('Reason for refund');

            // Indexes
            $table->index('subscription_id', 'idx_subscription');
            $table->index('user_id', 'idx_user');
            $table->index('status', 'idx_status');
            $table->index('pesapal_tracking_id', 'idx_pesapal_tracking');
            $table->index('merchant_reference', 'idx_merchant_ref');
            $table->index(['user_id', 'created_at'], 'idx_user_date');
            $table->index('transaction_type', 'idx_transaction_type');

          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
    }
};
