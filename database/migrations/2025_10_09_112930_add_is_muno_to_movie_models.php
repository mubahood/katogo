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
        Schema::table('movie_models', function (Blueprint $table) {
            $table->string('is_muno')->default('No')->nullable();
            $table->string('muno_processed')->default('No')->nullable();
            $table->string('munowatch_id')->nullable();
        });
        Schema::table('movie_crawler_pages', function (Blueprint $table) {
            $table->string('is_muno')->default('No')->nullable();
            $table->string('muno_processed')->default('No')->nullable();
            $table->string('munowatch_id')->nullable();
        });
        Schema::table('series_movies', function (Blueprint $table) {
            $table->string('is_muno')->default('No')->nullable();
            $table->string('muno_processed')->default('No')->nullable();
            $table->string('munowatch_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_models', function (Blueprint $table) {
            //
        });
    }
};
