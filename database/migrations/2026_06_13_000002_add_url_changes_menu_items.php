<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // Find the "Hetzner Storage" parent menu item
        $parentId = \DB::table('admin_menu')
            ->where('title', 'Hetzner Storage')
            ->value('id');

        if (!$parentId) {
            // Create parent if missing
            $parentId = \DB::table('admin_menu')->insertGetId([
                'parent_id' => 0,
                'order'     => 80,
                'title'     => 'Hetzner Storage',
                'icon'      => 'fa-server',
                'uri'       => '',
            ]);
        }

        // URL Changes dashboard link
        \DB::table('admin_menu')->insertOrIgnore([
            'parent_id' => $parentId,
            'order'     => 3,
            'title'     => 'URL Sync',
            'icon'      => 'fa-exchange',
            'uri'       => 'movie-url-changes/dashboard',
        ]);

        // URL Changes list link
        \DB::table('admin_menu')->insertOrIgnore([
            'parent_id' => $parentId,
            'order'     => 4,
            'title'     => 'URL Change Log',
            'icon'      => 'fa-list-alt',
            'uri'       => 'movie-url-changes',
        ]);
    }

    public function down(): void
    {
        \DB::table('admin_menu')
            ->whereIn('uri', ['movie-url-changes/dashboard', 'movie-url-changes'])
            ->delete();
    }
};
