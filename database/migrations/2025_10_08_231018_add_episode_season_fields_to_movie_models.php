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
            $table->integer('season_number')->nullable()->default(1);
            $table->text('series_title')->nullable(); // For storing the main series title
            $table->text('episode_title')->nullable(); // For storing individual episode title
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_models', function (Blueprint $table) {
            $table->dropColumn(['season_number', 'series_title', 'episode_title']);
        });
    }
};
