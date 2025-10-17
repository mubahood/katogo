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
        Schema::table('movie_crawler_pages', function (Blueprint $table) {
            $table->string('muno_series_processed')->nullable()->default('No');
            $table->string('muno_series_success')->nullable()->default('No');
            $table->string('muno_series_group_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_crawler_pages', function (Blueprint $table) {
            //
        });
    }
};
