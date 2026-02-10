<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AdminMenuFixColumnsSeeder
 *
 * Adds slug-filtered menu items for series and movies fix tracking views.
 * The admin_menu table uses a parent_id → children nesting pattern.
 *
 * Run:  php artisan db:seed --class=AdminMenuFixColumnsSeeder
 */
class AdminMenuFixColumnsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ─── Find or create "Series Management" parent ───
        $seriesParent = DB::table('admin_menu')
            ->where('title', 'Series Management')
            ->first();

        if (!$seriesParent) {
            // Try finding the existing series-movies entry to use as reference
            $existingSeries = DB::table('admin_menu')
                ->where('uri', 'series-movies')
                ->first();

            $parentId = 0;
            if ($existingSeries) {
                $parentId = $existingSeries->parent_id;
            }

            $seriesParentId = DB::table('admin_menu')->insertGetId([
                'parent_id'  => $parentId,
                'order'      => 50,
                'title'      => 'Series Management',
                'icon'       => 'fa-tv',
                'uri'        => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $seriesParentId = $seriesParent->id;
        }

        // ─── Series slug menu items ───
        $seriesItems = [
            ['title' => 'All Series',          'uri' => 'series-movies',         'icon' => 'fa-list',          'order' => 1],
            ['title' => 'Series — Pending Fix', 'uri' => 'series-movies-pending', 'icon' => 'fa-clock-o',       'order' => 2],
            ['title' => 'Series — Fixed',       'uri' => 'series-movies-fixed',   'icon' => 'fa-check-circle',  'order' => 3],
            ['title' => 'Series — Failed',      'uri' => 'series-movies-failed',  'icon' => 'fa-times-circle',  'order' => 4],
        ];

        foreach ($seriesItems as $item) {
            $exists = DB::table('admin_menu')->where('uri', $item['uri'])->exists();
            if (!$exists) {
                DB::table('admin_menu')->insert(array_merge($item, [
                    'parent_id'  => $seriesParentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // ─── Find or create "Movies Management" parent ───
        $moviesParent = DB::table('admin_menu')
            ->where('title', 'Movies Management')
            ->first();

        if (!$moviesParent) {
            $existingMovies = DB::table('admin_menu')
                ->where('uri', 'movies-movies')
                ->first();

            $movieParentId = 0;
            if ($existingMovies) {
                $movieParentId = $existingMovies->parent_id;
            }

            $moviesParentId = DB::table('admin_menu')->insertGetId([
                'parent_id'  => $movieParentId,
                'order'      => 51,
                'title'      => 'Movies Management',
                'icon'       => 'fa-film',
                'uri'        => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $moviesParentId = $moviesParent->id;
        }

        // ─── Movie slug menu items ───
        $movieItems = [
            ['title' => 'All Movies',          'uri' => 'movies-movies',          'icon' => 'fa-list',          'order' => 1],
            ['title' => 'Movies — Pending Fix', 'uri' => 'movies-movies-pending',  'icon' => 'fa-clock-o',       'order' => 2],
            ['title' => 'Movies — Fixed',       'uri' => 'movies-movies-fixed',    'icon' => 'fa-check-circle',  'order' => 3],
            ['title' => 'Movies — Failed',      'uri' => 'movies-movies-failed',   'icon' => 'fa-times-circle',  'order' => 4],
            ['title' => 'Movies — Active',      'uri' => 'movies-active',          'icon' => 'fa-check',         'order' => 5],
            ['title' => 'Movies — Inactive',    'uri' => 'movies-inactive',        'icon' => 'fa-pause',         'order' => 6],
            ['title' => 'Movies — Series Type', 'uri' => 'movies-series',          'icon' => 'fa-television',    'order' => 7],
        ];

        foreach ($movieItems as $item) {
            $exists = DB::table('admin_menu')->where('uri', $item['uri'])->exists();
            if (!$exists) {
                DB::table('admin_menu')->insert(array_merge($item, [
                    'parent_id'  => $moviesParentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        $this->command->info('✅ Admin menu items for fix tracking slug views seeded successfully.');
    }
}
