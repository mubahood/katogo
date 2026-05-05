<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentId = (int) (DB::table('admin_menu')->where('uri', 'support-tickets')->value('id') ?? 0);
        $baseOrder = (int) (DB::table('admin_menu')->where('parent_id', $parentId)->max('order') ?? 0);

        $items = [
            [
                'uri' => 'support-tickets/pending',
                'title' => 'Pending',
                'icon' => 'fa-hourglass-half',
                'order' => $baseOrder + 1,
            ],
            [
                'uri' => 'support-tickets/contacted',
                'title' => 'Contacted',
                'icon' => 'fa-phone',
                'order' => $baseOrder + 2,
            ],
            [
                'uri' => 'support-tickets/contacted-customer-replied',
                'title' => 'Contacted + Replied',
                'icon' => 'fa-comments',
                'order' => $baseOrder + 3,
            ],
        ];

        foreach ($items as $item) {
            if (DB::table('admin_menu')->where('uri', $item['uri'])->exists()) {
                continue;
            }

            DB::table('admin_menu')->insert([
                'parent_id' => $parentId,
                'order' => $item['order'],
                'title' => $item['title'],
                'icon' => $item['icon'],
                'uri' => $item['uri'],
                'permission' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_menu')
            ->whereIn('uri', [
                'support-tickets/pending',
                'support-tickets/contacted',
                'support-tickets/contacted-customer-replied',
            ])
            ->delete();
    }
};
