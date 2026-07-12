<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Uses INSERT ... ON DUPLICATE KEY UPDATE so it's safe to run on both servers.
        // Explicit IDs ensure live→Hetzner sync stays consistent (both rows use same id).
        $items = [
            // Under "Hetzner Storage" group (id=89) ─────────────────────────────────
            [
                'id'        => 101,
                'title'     => 'DB Sync Dashboard',
                'uri'       => 'sync-dashboard',
                'icon'      => 'fa-refresh',
                'parent_id' => 89,
                'order'     => 22,
            ],
            [
                'id'        => 102,
                'title'     => 'Sync Logs',
                'uri'       => 'sync-dashboard',
                'icon'      => 'fa-history',
                'parent_id' => 89,
                'order'     => 23,
            ],
            // Top-level items that were routed but missing from menu ─────────────────
            [
                'id'        => 103,
                'title'     => 'Movie Wishlists',
                'uri'       => 'movie-wishlists',
                'icon'      => 'fa-heart',
                'parent_id' => 0,
                'order'     => 38,
            ],
            [
                'id'        => 104,
                'title'     => 'Safemode Views',
                'uri'       => 'safemode-views',
                'icon'      => 'fa-shield',
                'parent_id' => 0,
                'order'     => 39,
            ],
        ];

        foreach ($items as $item) {
            DB::table('admin_menu')->upsert(
                [array_merge($item, ['created_at' => now(), 'updated_at' => now()])],
                ['id'],
                ['title', 'uri', 'icon', 'parent_id', 'order', 'updated_at'],
            );
        }
    }

    public function down(): void
    {
        DB::table('admin_menu')->whereIn('id', [101, 102, 103, 104])->delete();
    }
};
