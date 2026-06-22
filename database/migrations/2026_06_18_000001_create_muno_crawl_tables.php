<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('muno_crawl_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('running'); // running, completed, stopped, failed
            $table->unsignedBigInteger('category_id')->nullable(); // MunowatchMovieCategory.id (null = all)
            $table->string('category_name', 200)->nullable();
            $table->integer('pages_fetched')->default(0);      // list API pages fetched
            $table->integer('movies_discovered')->default(0);  // crawler pages created/found
            $table->integer('movies_processed')->default(0);   // preview API calls made
            $table->integer('movies_new')->default(0);
            $table->integer('movies_updated')->default(0);
            $table->integer('movies_skipped')->default(0);
            $table->integer('movies_failed')->default(0);
            $table->integer('series_new')->default(0);
            $table->string('triggered_by', 50)->default('manual'); // manual, scheduler, admin
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
        });

        Schema::create('muno_crawl_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->nullable()->index();
            $table->unsignedBigInteger('munowatch_id');           // munowatch video ID
            $table->string('url', 600)->nullable();               // preview API URL used
            $table->string('status', 20)->default('pending');     // pending, success, skipped, failed
            $table->string('result', 40)->nullable();             // new_movie, new_series, updated, existing, error
            $table->string('title', 300)->nullable();
            $table->unsignedBigInteger('movie_id')->nullable();
            $table->unsignedBigInteger('series_id')->nullable();
            $table->text('video_url')->nullable();
            $table->string('video_status', 20)->default('pending'); // pending, found, not_found
            $table->text('error_message')->nullable();
            $table->tinyInteger('attempts')->default(1);
            $table->timestamps();

            $table->index('munowatch_id');
            $table->index('status');
            $table->index('video_status');
            $table->index(['munowatch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muno_crawl_logs');
        Schema::dropIfExists('muno_crawl_sessions');
    }
};
