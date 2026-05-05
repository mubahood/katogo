<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->insertMenuIfMissing('support-team', 'Support Team', 'fa-users', 0);
        $this->insertMenuIfMissing('support-tickets', 'Support Tickets', 'fa-life-ring', 0);
    }

    public function down(): void
    {
        DB::table('admin_menu')
            ->whereIn('uri', ['support-team', 'support-tickets'])
            ->delete();
    }

    private function insertMenuIfMissing(string $uri, string $title, string $icon, int $parentId): void
    {
        if (DB::table('admin_menu')->where('uri', $uri)->exists()) {
            return;
        }

        $nextOrder = (int) (DB::table('admin_menu')
            ->where('parent_id', $parentId)
            ->max('order') ?? 0) + 1;

        DB::table('admin_menu')->insert([
            'parent_id' => $parentId,
            'order' => $nextOrder,
            'title' => $title,
            'icon' => $icon,
            'uri' => $uri,
            'permission' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
