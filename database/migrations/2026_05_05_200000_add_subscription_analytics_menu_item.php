<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make the top-level Subscriptions entry (id=39) a parent if not already,
        // then add Analytics + All Subscriptions children under it.
        $parentId = DB::table('admin_menu')->where('uri', 'subscriptions')->where('parent_id', 0)->value('id');

        if (!$parentId) {
            return; // safety guard — parent not found
        }

        // Already has children? skip analytics if already seeded
        $exists = DB::table('admin_menu')
            ->where('parent_id', $parentId)
            ->where('uri', 'subscriptions/analytics')
            ->exists();

        if ($exists) {
            return;
        }

        // Child: All Subscriptions (listing)
        $listingExists = DB::table('admin_menu')
            ->where('parent_id', $parentId)
            ->where('uri', 'subscriptions')
            ->exists();

        if (!$listingExists) {
            DB::table('admin_menu')->insert([
                'parent_id'  => $parentId,
                'order'      => 1,
                'title'      => 'All Subscriptions',
                'icon'       => 'fa-list',
                'uri'        => 'subscriptions',
                'permission' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Child: Data Analytics
        DB::table('admin_menu')->insert([
            'parent_id'  => $parentId,
            'order'      => 2,
            'title'      => 'Data Analytics',
            'icon'       => 'fa-line-chart',
            'uri'        => 'subscriptions/analytics',
            'permission' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $parentId = DB::table('admin_menu')->where('uri', 'subscriptions')->where('parent_id', 0)->value('id');
        if ($parentId) {
            DB::table('admin_menu')
                ->where('parent_id', $parentId)
                ->whereIn('uri', ['subscriptions/analytics', 'subscriptions'])
                ->delete();
        }
    }
};
