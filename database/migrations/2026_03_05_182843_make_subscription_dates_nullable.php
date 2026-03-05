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
            // Make dates nullable so Pending subscriptions don't need premature dates
            // Dates will be set when payment actually succeeds
            $table->datetime('start_date_time')->nullable()->change();
            $table->datetime('end_date_time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->datetime('start_date_time')->nullable(false)->change();
            $table->datetime('end_date_time')->nullable(false)->change();
        });
    }
};
