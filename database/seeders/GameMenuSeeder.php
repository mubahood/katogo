<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Adds Game Module menu items to the Laravel-Admin menu
     * 
     * SAFE TO RUN MULTIPLE TIMES - will skip if already exists
     */
    public function run(): void
    {
        $this->command->info('🎮 Starting Game Menu Seeder...');
        
        // Check if Game Module parent menu already exists
        $existingParent = DB::table('admin_menu')
            ->where('title', 'Game Module')
            ->first();

        if ($existingParent) {
            $this->command->info("✅ Game Module menu already exists (ID: {$existingParent->id}). Skipping seeder.");
            Log::info("GameMenuSeeder: Game Module menu already exists, skipping.");
            return;
        }

        // Get the maximum order value to place our menu at the end
        $maxOrder = DB::table('admin_menu')->max('order') ?? 0;
        $baseOrder = $maxOrder + 1;

        // Create parent menu item: Game Module
        $parentId = DB::table('admin_menu')->insertGetId([
            'parent_id' => 0,
            'order' => $baseOrder,
            'title' => 'Game Module',
            'icon' => 'fa-gamepad',
            'uri' => null, // Parent menu has no URI
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("✅ Created parent menu: Game Module (ID: {$parentId})");

        // Create child menu items
        $menuItems = [
            [
                'title' => '📊 Dashboard',
                'icon' => 'fa-dashboard',
                'uri' => 'game-dashboard',
                'order' => 1,
            ],
            [
                'title' => '🃏 Matatu Sessions',
                'icon' => 'fa-th',
                'uri' => 'game-sessions',
                'order' => 2,
            ],
            [
                'title' => '🎲 Ludo Sessions',
                'icon' => 'fa-circle-o',
                'uri' => 'ludo-sessions',
                'order' => 3,
            ],
            [
                'title' => '📨 Invitations',
                'icon' => 'fa-envelope',
                'uri' => 'game-invitations',
                'order' => 4,
            ],
            [
                'title' => '🪙 Coin Transactions',
                'icon' => 'fa-bitcoin',
                'uri' => 'coin-transactions',
                'order' => 5,
            ],
        ];

        foreach ($menuItems as $item) {
            DB::table('admin_menu')->insert([
                'parent_id' => $parentId,
                'order' => $item['order'],
                'title' => $item['title'],
                'icon' => $item['icon'],
                'uri' => $item['uri'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("  ✅ Created child menu: {$item['title']}");
        }

        // Clear the admin menu cache
        try {
            if (class_exists('\Encore\Admin\Auth\Database\Menu')) {
                \Encore\Admin\Auth\Database\Menu::flushCache();
                $this->command->info("✅ Admin menu cache cleared.");
            }
        } catch (\Exception $e) {
            // Cache clearing is optional, continue if it fails
            $this->command->info("ℹ️ Cache clearing skipped (non-critical).");
        }

        $this->command->info('');
        $this->command->info('🎉 Game Menu Seeder completed successfully!');
        $this->command->info('');
        $this->command->info('📋 Menu items added:');
        $this->command->info('   • Game Module (Parent)');
        $this->command->info('     ├── 📊 Dashboard');
        $this->command->info('     ├── 🃏 Matatu Sessions');
        $this->command->info('     ├── 🎲 Ludo Sessions');
        $this->command->info('     ├── 📨 Invitations');
        $this->command->info('     └── 🪙 Coin Transactions');
        $this->command->info('');
        $this->command->info('🔗 Admin URLs:');
        $this->command->info('   • /admin/game-dashboard');
        $this->command->info('   • /admin/game-sessions');
        $this->command->info('   • /admin/ludo-sessions');
        $this->command->info('   • /admin/game-invitations');
        $this->command->info('   • /admin/coin-transactions');

        Log::info("GameMenuSeeder: Successfully created Game Module menu with {$parentId} as parent ID.");
    }
}
