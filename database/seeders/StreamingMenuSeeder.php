<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StreamingMenuSeeder extends Seeder
{
    /**
     * Add Streaming Module menu items to the Laravel-Admin menu.
     * SAFE TO RUN MULTIPLE TIMES — skips if already exists.
     */
    public function run(): void
    {
        $this->command->info('📡 Starting Streaming Menu Seeder...');

        $existingParent = DB::table('admin_menu')
            ->where('title', 'Streaming')
            ->first();

        if ($existingParent) {
            $this->command->info("✅ Streaming menu already exists (ID: {$existingParent->id}). Skipping.");
            return;
        }

        $maxOrder = DB::table('admin_menu')->max('order') ?? 0;

        $parentId = DB::table('admin_menu')->insertGetId([
            'parent_id' => 0,
            'order' => $maxOrder + 1,
            'title' => 'Streaming',
            'icon' => 'fa-podcast',
            'uri' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("✅ Created parent menu: Streaming (ID: {$parentId})");

        $menuItems = [
            ['title' => '📺 All Stations', 'icon' => 'fa-list', 'uri' => 'streaming-stations', 'order' => 1],
            ['title' => '📺 TV Channels', 'icon' => 'fa-tv', 'uri' => 'streaming-tv', 'order' => 2],
            ['title' => '📻 Radio Stations', 'icon' => 'fa-podcast', 'uri' => 'streaming-radio', 'order' => 3],
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

        try {
            if (class_exists('\Encore\Admin\Auth\Database\Menu') && method_exists('\Encore\Admin\Auth\Database\Menu', 'flushCache')) {
                \Encore\Admin\Auth\Database\Menu::flushCache();
            }
        } catch (\Throwable $e) {
            // Cache flush not available, OK
        }

        $this->command->info('✅ Streaming menu seeder complete!');
    }
}
