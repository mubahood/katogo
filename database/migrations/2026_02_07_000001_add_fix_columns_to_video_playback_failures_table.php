<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_playback_failures', function (Blueprint $table) {
            $table->string('fix_status', 20)->default('PENDING')->after('admin_notes')
                ->comment('PENDING, FIXED, FAILED');
            $table->text('fix_status_message')->nullable()->after('fix_status')
                ->comment('Explanation of what happened during fix attempt');
            $table->unsignedInteger('number_of_fix_attempts')->default(0)->after('fix_status_message')
                ->comment('Incremented on every fix attempt');
            $table->timestamp('last_fix_attempt_at')->nullable()->after('number_of_fix_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('video_playback_failures', function (Blueprint $table) {
            $table->dropColumn([
                'fix_status',
                'fix_status_message',
                'number_of_fix_attempts',
                'last_fix_attempt_at',
            ]);
        });
    }
};
