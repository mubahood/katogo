<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->string('image_url')->nullable();
            $table->string('category')->default('General');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_name')->default('Admin');
            $table->string('status')->default('Active'); // Active, Draft, Archived
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('comments_enabled')->default(true);
            $table->timestamps();

            $table->index('status');
            $table->index('category');
            $table->index('is_pinned');
            $table->index('created_at');
        });

        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_post_id');
            $table->unsignedBigInteger('user_id');
            $table->string('user_name');
            $table->text('content');
            $table->string('status')->default('Active'); // Active, Hidden, Reported
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();

            $table->foreign('blog_post_id')->references('id')->on('blog_posts')->onDelete('cascade');
            $table->index('blog_post_id');
            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('blog_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('likeable_type'); // blog_post or blog_comment
            $table->unsignedBigInteger('likeable_id');
            $table->timestamps();

            $table->unique(['user_id', 'likeable_type', 'likeable_id']);
            $table->index(['likeable_type', 'likeable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_likes');
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_posts');
    }
};
