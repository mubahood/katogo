<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_posts') && !Schema::hasColumn('blog_posts', 'youtube_url')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->string('youtube_url')->nullable()->after('image_url');
                $table->index('youtube_url');
            });
        }

        $now = now();

        $posts = [
            [
                'title' => 'How to fix payment issues in the app',
                'excerpt' => 'A quick video guide for common subscription payment failures and how to retry safely.',
                'content' => '<p>This help post explains how to resolve common payment errors, retry a pending payment, and confirm activation from the app.</p>',
                'category' => 'Help',
                'youtube_url' => 'https://www.youtube.com/watch?v=nnMNyQY_D80',
            ],
        ];

        foreach ($posts as $post) {
            DB::table('blog_posts')->updateOrInsert(
                ['title' => $post['title']],
                [
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'category' => $post['category'],
                    'youtube_url' => $post['youtube_url'],
                    'author_name' => 'Admin',
                    'status' => 'Active',
                    'is_pinned' => false,
                    'comments_enabled' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_posts') && Schema::hasColumn('blog_posts', 'youtube_url')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->dropIndex(['youtube_url']);
                $table->dropColumn('youtube_url');
            });
        }
    }
};
