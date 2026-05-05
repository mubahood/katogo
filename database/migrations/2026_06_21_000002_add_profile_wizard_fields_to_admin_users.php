<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_users', 'preferred_genres')) {
                $table->json('preferred_genres')->nullable()->after('completed_profile_pct');
            }
            if (!Schema::hasColumn('admin_users', 'content_maturity_level')) {
                $table->string('content_maturity_level', 20)->nullable()->after('preferred_genres');
            }
            if (!Schema::hasColumn('admin_users', 'profile_completion_step')) {
                $table->unsignedTinyInteger('profile_completion_step')->default(0)->after('content_maturity_level');
            }
            if (!Schema::hasColumn('admin_users', 'profile_photo_skipped')) {
                $table->boolean('profile_photo_skipped')->default(false)->after('profile_completion_step');
            }
            if (!Schema::hasColumn('admin_users', 'profile_completed_at')) {
                $table->timestamp('profile_completed_at')->nullable()->after('profile_photo_skipped');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $drops = [];
            foreach ([
                'preferred_genres',
                'content_maturity_level',
                'profile_completion_step',
                'profile_photo_skipped',
                'profile_completed_at',
            ] as $column) {
                if (Schema::hasColumn('admin_users', $column)) {
                    $drops[] = $column;
                }
            }

            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
