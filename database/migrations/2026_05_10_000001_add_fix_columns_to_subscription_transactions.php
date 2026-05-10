<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_transactions', function (Blueprint $table) {
            $table->enum('is_fixed', ['No', 'Yes'])
                ->nullable()
                ->default('No')
                ->after('error_message')
                ->comment('Whether a fix attempt was made on this transaction');

            $table->dateTime('fix_time')
                ->nullable()
                ->after('is_fixed')
                ->comment('When the last fix attempt was made');

            $table->text('api_gateway_response')
                ->nullable()
                ->after('fix_time')
                ->comment('Raw gateway API response captured during fix attempt');

            $table->enum('fix_successful', ['No', 'Yes'])
                ->nullable()
                ->default('No')
                ->after('api_gateway_response')
                ->comment('Whether the fix resulted in a successful payment activation');

            $table->index('is_fixed', 'idx_sub_tx_is_fixed');
            $table->index('fix_successful', 'idx_sub_tx_fix_successful');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_sub_tx_is_fixed');
            $table->dropIndex('idx_sub_tx_fix_successful');
            $table->dropColumn(['is_fixed', 'fix_time', 'api_gateway_response', 'fix_successful']);
        });
    }
};
