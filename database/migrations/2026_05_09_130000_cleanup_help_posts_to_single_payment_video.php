<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('blog_posts')
            ->where('category', 'Help')
            ->where('title', '!=', 'How to fix payment issues in the app')
            ->delete();

        DB::table('blog_posts')->updateOrInsert(
            ['title' => 'How to fix payment issues in the app'],
            [
                'excerpt' => 'A quick video guide for common subscription payment failures and how to retry safely.',
                'content' => '<p>This help post explains how to resolve common payment errors, retry a pending payment, and confirm activation from the app.</p>',
                'category' => 'Help',
                'youtube_url' => 'https://www.youtube.com/watch?v=nnMNyQY_D80',
                'author_name' => 'Admin',
                'status' => 'Active',
                'is_pinned' => true,
                'comments_enabled' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->where('category', 'Help')
            ->where('title', 'How to fix payment issues in the app')
            ->delete();
    }
};
