<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subtitle_files')) {
            Schema::create('subtitle_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('movie_id');
                $table->string('language', 10)->default('en');
                $table->string('label', 50)->nullable()->comment('English, Luganda, etc.');
                $table->string('url', 500)->comment('WebVTT or SRT file URL');
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index('movie_id');
                $table->index(['movie_id', 'language']);
            });
        }

        // Add quick-lookup subtitle_url to movie_models (for single-subtitle movies)
        if (Schema::hasTable('movie_models') && !Schema::hasColumn('movie_models', 'subtitle_url')) {
            Schema::table('movie_models', function (Blueprint $table) {
                $table->string('subtitle_url', 500)->nullable()->after('avg_rating');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subtitle_files');
        if (Schema::hasTable('movie_models') && Schema::hasColumn('movie_models', 'subtitle_url')) {
            Schema::table('movie_models', function (Blueprint $table) {
                $table->dropColumn('subtitle_url');
            });
        }
    }
};
