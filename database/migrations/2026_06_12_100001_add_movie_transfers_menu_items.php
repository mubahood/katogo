<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Parent menu group: "Movie Transfers" ──────────────────────────
        if (!DB::table('admin_menu')->where('uri', 'movie-file-transfers')->exists()) {
            $parentOrder = (int)(DB::table('admin_menu')
                ->where('parent_id', 0)
                ->max('order') ?? 0) + 1;

            $parentId = DB::table('admin_menu')->insertGetId([
                'parent_id'  => 0,
                'order'      => $parentOrder,
                'title'      => 'Movie Transfers',
                'icon'       => 'fa-exchange',
                'uri'        => 'movie-file-transfers',
                'permission' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ── Child: Transfer List ──────────────────────────────────────
            DB::table('admin_menu')->insert([
                'parent_id'  => $parentId,
                'order'      => 1,
                'title'      => 'All Transfers',
                'icon'       => 'fa-list',
                'uri'        => 'movie-file-transfers',
                'permission' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ── Child: Monitor / Stats ────────────────────────────────────
            DB::table('admin_menu')->insert([
                'parent_id'  => $parentId,
                'order'      => 2,
                'title'      => 'Transfer Monitor',
                'icon'       => 'fa-dashboard',
                'uri'        => 'movie-file-transfers/monitor',
                'permission' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ── Child: Backfill Tool ──────────────────────────────────────
            DB::table('admin_menu')->insert([
                'parent_id'  => $parentId,
                'order'      => 3,
                'title'      => 'Queue Backfill',
                'icon'       => 'fa-database',
                'uri'        => 'movie-file-transfers/backfill',
                'permission' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $parent = DB::table('admin_menu')->where('uri', 'movie-file-transfers')
            ->where('parent_id', 0)->first();

        if ($parent) {
            DB::table('admin_menu')->where('parent_id', $parent->id)->delete();
            DB::table('admin_menu')->where('id', $parent->id)->delete();
        }
    }
};
