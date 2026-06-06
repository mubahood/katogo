<?php

namespace Database\Seeders;

use App\Models\SystemConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the system_configs singleton row and the admin menu entry.
 *
 * Safe to run multiple times — uses updateOrCreate / conditional inserts.
 *
 * Run: php artisan db:seed --class=SystemConfigSeeder
 */
class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Ensure the singleton config row exists ──────────────────────
        SystemConfig::instance();
        $this->command->info('✅ system_configs singleton row ensured.');

        // ── 2. Ensure the admin menu entry exists ──────────────────────────
        $exists = DB::table('admin_menu')
            ->where('uri', 'system-configs')
            ->exists();

        if (!$exists) {
            DB::table('admin_menu')->insert([
                'parent_id'  => 0,
                'order'      => 99,
                'title'      => 'System Config',
                'icon'       => 'fa-cog',
                'uri'        => 'system-configs',
                'permission' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info('✅ Admin menu entry "System Config" created.');
        } else {
            $this->command->info('ℹ️  Admin menu entry "System Config" already exists — skipped.');
        }
    }
}
