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
        Schema::create('movie_models', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('title')->nullable();
            $table->text('external_url')->nullable();
            $table->text('url')->nullable();
            $table->text('image_url')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('description')->nullable();
            $table->text('year')->nullable();
            $table->text('rating')->nullable();
            $table->integer('duration')->nullable();
            $table->float('size')->nullable();
            $table->text('genre')->nullable();
            $table->text('director')->nullable();
            $table->text('stars')->nullable();
            $table->text('country')->nullable();
            $table->text('language')->nullable();
            $table->text('imdb_url')->nullable();
            $table->float('imdb_rating')->nullable();
            $table->float('imdb_votes')->nullable();
            $table->text('imdb_id')->nullable();
            $table->string('type')->nullable()->default('movie'); //movie, tv, episode
            $table->text('status')->nullable();
            $table->text('error')->nullable();
            $table->text('error_message')->nullable();
            $table->text('downloads_count')->nullable();
            $table->text('views_count')->nullable();
            $table->text('likes_count')->nullable();
            $table->text('dislikes_count')->nullable();
            $table->text('comments_count')->nullable();
            $table->text('comments')->nullable();
            $table->string('video_is_downloaded_to_server')->nullable()->default('no');
            $table->string('video_downloaded_to_server_start_time')->nullable();
            $table->string('video_downloaded_to_server_end_time')->nullable();
            $table->string('video_downloaded_to_server_duration')->nullable();
            $table->string('video_is_downloaded_to_server_status')->nullable();
            $table->string('video_is_downloaded_to_server_error_message')->nullable();
            $table->string('category')->nullable();
            $table->string('category_id')->nullable();
            $table->string('is_processed')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_models');
    }
};
