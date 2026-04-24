<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_users', 'preferred_payment_gateway')) {
                $table->string('preferred_payment_gateway', 30)
                    ->default('pesapal')
                    ->after('remember_token');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'flutterwave_reference')) {
                $table->string('flutterwave_reference', 191)->nullable()->after('pesapal_merchant_reference');
            }

            if (!Schema::hasColumn('subscriptions', 'flutterwave_transaction_id')) {
                $table->string('flutterwave_transaction_id', 191)->nullable()->after('flutterwave_reference');
            }

            if (!Schema::hasColumn('subscriptions', 'flutterwave_response')) {
                $table->json('flutterwave_response')->nullable()->after('flutterwave_transaction_id');
            }

            if (!Schema::hasColumn('subscriptions', 'payment_gateway')) {
                $table->string('payment_gateway', 30)->default('pesapal')->after('payment_method');
            }

            $table->index('flutterwave_reference', 'idx_flutterwave_reference');
            $table->index('payment_gateway', 'idx_sub_payment_gateway');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Indexes are removed automatically with their columns on MySQL.
            if (Schema::hasColumn('subscriptions', 'flutterwave_response')) {
                $table->dropColumn('flutterwave_response');
            }
            if (Schema::hasColumn('subscriptions', 'flutterwave_transaction_id')) {
                $table->dropColumn('flutterwave_transaction_id');
            }
            if (Schema::hasColumn('subscriptions', 'flutterwave_reference')) {
                $table->dropColumn('flutterwave_reference');
            }
            if (Schema::hasColumn('subscriptions', 'payment_gateway')) {
                $table->dropColumn('payment_gateway');
            }
        });

        Schema::table('admin_users', function (Blueprint $table) {
            if (Schema::hasColumn('admin_users', 'preferred_payment_gateway')) {
                $table->dropColumn('preferred_payment_gateway');
            }
        });
    }
};
