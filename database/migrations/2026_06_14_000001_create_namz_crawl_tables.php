<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('namz_crawl_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('running'); // running, completed, stopped, failed
            $table->integer('id_from')->default(47);
            $table->integer('id_to')->default(9200);
            $table->integer('id_current')->default(47);
            $table->integer('total_processed')->default(0);
            $table->integer('total_success')->default(0);
            $table->integer('total_skipped')->default(0);
            $table->integer('total_failed')->default(0);
            $table->integer('total_new_movies')->default(0);
            $table->integer('total_new_series')->default(0);
            $table->integer('total_url_pending')->default(0);
            $table->string('triggered_by', 50)->default('scheduler'); // scheduler, manual, admin
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
        });

        Schema::create('namz_crawl_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->nullable()->index();
            $table->integer('namz_id');
            $table->string('url', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending, success, skipped, failed
            $table->string('result', 30)->nullable(); // new_movie, new_series, existing, no_title, no_video, error
            $table->string('title', 300)->nullable();
            $table->unsignedBigInteger('movie_id')->nullable();
            $table->unsignedBigInteger('series_id')->nullable();
            $table->text('video_url')->nullable();
            $table->string('video_status', 20)->default('pending'); // pending, found, not_found
            $table->text('error_message')->nullable();
            $table->tinyInteger('attempts')->default(1);
            $table->timestamps();

            $table->index('namz_id');
            $table->index('status');
            $table->index('video_status');
            $table->index(['namz_id', 'status']);
        });

        // Add namz-specific columns to movie_models if not exists
        if (!Schema::hasColumn('movie_models', 'namz_id')) {
            Schema::table('movie_models', function (Blueprint $table) {
                $table->integer('namz_id')->nullable()->index()->after('imdb_id');
                $table->string('namz_url_status', 20)->nullable()->index()->after('namz_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('namz_crawl_logs');
        Schema::dropIfExists('namz_crawl_sessions');

        if (Schema::hasColumn('movie_models', 'namz_id')) {
            Schema::table('movie_models', function (Blueprint $table) {
                $table->dropColumn(['namz_id', 'namz_url_status']);
            });
        }
    }
};
