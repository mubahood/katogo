<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('movie_ratings')) {
            Schema::create('movie_ratings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('movie_id');
                $table->tinyInteger('rating')->unsigned()->comment('1–5 stars');
                $table->text('review')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'movie_id']);
                $table->index('movie_id');
                $table->index('rating');
            });
        }

        // Add aggregate columns to movie_models for fast display
        if (Schema::hasTable('movie_models')) {
            Schema::table('movie_models', function (Blueprint $table) {
                if (!Schema::hasColumn('movie_models', 'avg_rating')) {
                    $table->decimal('avg_rating', 3, 2)->default(0)->after('views_count');
                }
                if (!Schema::hasColumn('movie_models', 'rating_count')) {
                    $table->unsignedInteger('rating_count')->default(0)->after('avg_rating');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_ratings');
        if (Schema::hasTable('movie_models')) {
            Schema::table('movie_models', function (Blueprint $table) {
                foreach (['avg_rating', 'rating_count'] as $col) {
                    if (Schema::hasColumn('movie_models', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
