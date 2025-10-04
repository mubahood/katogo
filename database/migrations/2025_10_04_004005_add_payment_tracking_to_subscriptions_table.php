<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->text('payment_url')->nullable()->after('pesapal_response');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_url');
            $table->timestamp('failed_at')->nullable()->after('payment_confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_url', 'payment_confirmed_at', 'failed_at']);
        });
    }
};
