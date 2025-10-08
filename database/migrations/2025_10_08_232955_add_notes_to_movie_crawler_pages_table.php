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
            $table->text('notes')->nullable(); // Processing notes and statistics
            $table->bigInteger('series_id')->nullable(); // Link to series if this is a series page
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_crawler_pages', function (Blueprint $table) {
            $table->dropColumn(['notes', 'series_id']);
        });
    }
};
