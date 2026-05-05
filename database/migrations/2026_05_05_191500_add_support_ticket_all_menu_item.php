<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentId = (int) (DB::table('admin_menu')->where('uri', 'support-tickets')->value('id') ?? 0);
        $exists = DB::table('admin_menu')->where('uri', 'support-tickets/all')->exists();

        if (!$exists) {
            $nextOrder = (int) (DB::table('admin_menu')->where('parent_id', $parentId)->max('order') ?? 0) + 1;

            DB::table('admin_menu')->insert([
                'parent_id' => $parentId,
                'order' => $nextOrder,
                'title' => 'All Tickets',
                'icon' => 'fa-list',
                'uri' => 'support-tickets/all',
                'permission' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_menu')
            ->where('uri', 'support-tickets/all')
            ->delete();
    }
};
