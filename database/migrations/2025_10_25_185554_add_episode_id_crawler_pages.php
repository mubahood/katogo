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
            $table->string('is_episode')->default('No')->nullable();
            $table->string('episodes_data_fetched')->default('No')->nullable();
            $table->text('episode_data')->nullable();
            $table->string('series_code')->nullable();
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
