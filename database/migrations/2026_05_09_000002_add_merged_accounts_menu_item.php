<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $uri = 'merged-accounts';

        if (DB::table('admin_menu')->where('uri', $uri)->exists()) {
            return;
        }

        $usersMenuId = DB::table('admin_menu')
            ->where('uri', 'users')
            ->value('id');

        if (!$usersMenuId) {
            $usersMenuId = DB::table('admin_menu')
                ->where('title', 'Users')
                ->where('parent_id', 0)
                ->value('id');
        }

        $nextOrder = (int) (DB::table('admin_menu')
            ->where('parent_id', $usersMenuId ?: 0)
            ->max('order') ?? 0) + 1;

        DB::table('admin_menu')->insert([
            'parent_id' => $usersMenuId ?: 0,
            'order' => $nextOrder,
            'title' => 'Merged Accounts',
            'icon' => 'fa-random',
            'uri' => $uri,
            'permission' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('admin_menu')
            ->where('uri', 'merged-accounts')
            ->delete();
    }
};
