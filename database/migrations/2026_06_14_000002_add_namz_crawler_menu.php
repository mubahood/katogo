<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // Find or create "Content Sources" parent menu
        $parentId = \DB::table('admin_menu')
            ->where('title', 'Content Sources')
            ->value('id');

        if (!$parentId) {
            $parentId = \DB::table('admin_menu')->insertGetId([
                'parent_id' => 0,
                'order'     => 75,
                'title'     => 'Content Sources',
                'icon'      => 'fa-film',
                'uri'       => '',
            ]);
        }

        // Namz Crawler dashboard
        \DB::table('admin_menu')->insertOrIgnore([
            'parent_id' => $parentId,
            'order'     => 1,
            'title'     => 'Namz Crawler',
            'icon'      => 'fa-spider',
            'uri'       => 'namz-crawler',
        ]);

        // Namz Crawl Logs
        \DB::table('admin_menu')->insertOrIgnore([
            'parent_id' => $parentId,
            'order'     => 2,
            'title'     => 'Crawl Logs',
            'icon'      => 'fa-list-alt',
            'uri'       => 'namz-crawl-logs',
        ]);
    }

    public function down(): void
    {
        \DB::table('admin_menu')
            ->whereIn('uri', ['namz-crawler', 'namz-crawl-logs'])
            ->delete();
    }
};
