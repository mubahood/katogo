<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('admin_menu')->where('uri', 'movie-requests')->exists()) {
            return;
        }

        $nextOrder = (int) (DB::table('admin_menu')
            ->where('parent_id', 0)
            ->max('order') ?? 0) + 1;

        DB::table('admin_menu')->insert([
            'parent_id' => 0,
            'order' => $nextOrder,
            'title' => 'Movie Requests',
            'icon' => 'fa-video-camera',
            'uri' => 'movie-requests',
            'permission' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('admin_menu')->where('uri', 'movie-requests')->delete();
    }
};
