<?php

use App\Models\MovieModel;
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
        Schema::create('movie_downloads', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('local_id');
            $table->foreignId('user_id')->nullable();
            $table->foreignIdFor(MovieModel::class, 'movie_model_id')->nullable();
            $table->string('status')->nullable()->default('Pending');
            $table->text('error_message')->nullable();
            $table->text('local_video_link')->nullable();
            $table->datetime('download_started_at')->nullable();
            $table->datetime('download_completed_at')->nullable();
            $table->integer('download_duration')->nullable();
            $table->integer('file_size')->nullable();
            $table->text('download_progress')->nullable();
            $table->text('watch_progress')->nullable();
            $table->text('title')->nullable();
            $table->text('url')->nullable();
            $table->text('image_url')->nullable();
            $table->text('local_image_url')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('description')->nullable();
            $table->text('genre')->nullable();
            $table->text('vj')->nullable();
            $table->text('content_type')->nullable();
            $table->text('content_is_video')->nullable();
            $table->text('is_premium')->nullable();
            $table->text('episode_number')->nullable();
            $table->text('is_first_episode')->nullable();
        });
   
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_downloads');
    }
};
