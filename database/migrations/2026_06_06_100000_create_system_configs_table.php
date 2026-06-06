<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_configs')) {
            Schema::create('system_configs', function (Blueprint $table) {
                $table->id();
                // iOS App Store review mode — when ON, iOS app shows simplified review version
                $table->boolean('ios_review_mode')->default(false);
                // JSON array of movie IDs shown during iOS review (empty = show defaults)
                $table->text('ios_review_movie_ids')->nullable();
                // Optional message shown on iOS review home screen
                $table->string('ios_review_message')->nullable()->default('Welcome to LugaFlix');
                // App maintenance mode (future use)
                $table->boolean('maintenance_mode')->default(false);
                $table->string('maintenance_message')->nullable();
                // Minimum app versions before force-update (future use)
                $table->integer('min_android_version')->default(1);
                $table->integer('min_ios_version')->default(1);
                $table->timestamps();
            });
        }

        // Seed the single config row if not already present
        if (DB::table('system_configs')->count() === 0) {
            DB::table('system_configs')->insert([
                'ios_review_mode'       => false,
                'ios_review_movie_ids'  => json_encode([]),
                'ios_review_message'    => 'Welcome to LugaFlix',
                'maintenance_mode'      => false,
                'maintenance_message'   => null,
                'min_android_version'   => 1,
                'min_ios_version'       => 1,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }

        // Add admin menu item under Settings
        $settingsMenuId = DB::table('admin_menu')
            ->where('title', 'Settings')
            ->value('id');

        $uri = 'system-configs';

        if (!DB::table('admin_menu')->where('uri', $uri)->exists()) {
            DB::table('admin_menu')->insert([
                'parent_id'  => $settingsMenuId ?: 0,
                'order'      => 99,
                'title'      => 'System Config',
                'icon'       => 'fa-cogs',
                'uri'        => $uri,
                'permission' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configs');
        DB::table('admin_menu')->where('uri', 'system-configs')->delete();
    }
};
