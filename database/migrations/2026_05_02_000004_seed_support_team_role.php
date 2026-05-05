<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the support_team role into admin_roles.
     * Safe to run multiple times — checks for existence first.
     *
     * Role matrix (for reference):
     *   id=1 — Administrator (slug: administrator) — full admin panel access
     *   id=2 — Normal User   (slug: normal_user)   — app users, no panel access
     *   id=3 — Support Team  (slug: support_team)  — can reply to support tickets
     */
    public function up(): void
    {
        $exists = DB::table('admin_roles')->where('slug', 'support_team')->exists();
        if (!$exists) {
            DB::table('admin_roles')->insert([
                'name'       => 'Support Team',
                'slug'       => 'support_team',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_roles')->where('slug', 'support_team')->delete();
    }
};
