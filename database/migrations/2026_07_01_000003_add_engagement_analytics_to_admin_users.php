<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $add = [
                'total_watch_minutes'    => fn() => $table->unsignedInteger('total_watch_minutes')->default(0),
                'total_movies_watched'   => fn() => $table->unsignedInteger('total_movies_watched')->default(0),
                'total_series_completed' => fn() => $table->unsignedInteger('total_series_completed')->default(0),
                'average_completion_pct' => fn() => $table->decimal('average_completion_pct', 5, 2)->default(0),
                'favourite_genre'        => fn() => $table->string('favourite_genre', 80)->nullable(),
                'favourite_vj'           => fn() => $table->string('favourite_vj', 80)->nullable(),
                'last_watched_movie_id'  => fn() => $table->unsignedInteger('last_watched_movie_id')->nullable(),
                'watch_streak_days'      => fn() => $table->unsignedInteger('watch_streak_days')->default(0),
                'last_active_at'         => fn() => $table->timestamp('last_active_at')->nullable(),
            ];

            foreach ($add as $col => $definition) {
                if (!Schema::hasColumn('admin_users', $col)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $cols = [
                'total_watch_minutes','total_movies_watched','total_series_completed',
                'average_completion_pct','favourite_genre','favourite_vj',
                'last_watched_movie_id','watch_streak_days','last_active_at',
            ];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('admin_users', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
    }
};
