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
        Schema::table('series_movies', function (Blueprint $table) {
            // Only add fields that don't already exist
            // external_url, is_active, is_premium already exist
            
            if (!Schema::hasColumn('series_movies', 'external_id')) {
                $table->text('external_id')->nullable(); // Munowatch series ID
            }
            if (!Schema::hasColumn('series_movies', 'vj')) {
                $table->text('vj')->nullable(); // VJ who uploaded the series
            }
            if (!Schema::hasColumn('series_movies', 'genre')) {
                $table->text('genre')->nullable(); // Series genre
            }
            if (!Schema::hasColumn('series_movies', 'language')) {
                $table->text('language')->nullable(); // Series language
            }
            if (!Schema::hasColumn('series_movies', 'country')) {
                $table->text('country')->nullable(); // Series country
            }
            if (!Schema::hasColumn('series_movies', 'year')) {
                $table->string('year', 4)->nullable(); // Series year
            }
            if (!Schema::hasColumn('series_movies', 'status')) {
                $table->string('status')->nullable(); // Series status
            }
            if (!Schema::hasColumn('series_movies', 'rating')) {
                $table->string('rating')->nullable(); // Series rating
            }
            if (!Schema::hasColumn('series_movies', 'poster_url')) {
                $table->text('poster_url')->nullable(); // Series poster URL
            }
            if (!Schema::hasColumn('series_movies', 'metadata')) {
                $table->json('metadata')->nullable(); // Additional munowatch metadata
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series_movies', function (Blueprint $table) {
            $columns = ['external_id', 'vj', 'genre', 'language', 'country', 'year', 'status', 'rating', 'poster_url', 'metadata'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('series_movies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
