<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add support-system fields to admin_users.
     *
     * account_origin: how the account was created
     *   'manual'       — registered via normal register form
     *   'google'       — created via Google OAuth
     *   'auto_device'  — auto-created on first app launch (device-based)
     *
     * account_state: profile completion state
     *   'auto_created' — account was auto-created, profile not yet set
     *   'registered'   — user completed profile / registered manually
     */
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('account_origin')->default('manual')->nullable()
                ->comment('manual | google | auto_device');
            $table->string('account_state')->default('registered')->nullable()
                ->comment('auto_created | registered');
            $table->string('device_id')->nullable()
                ->comment('Device identifier for auto-created accounts');
            $table->string('device_model')->nullable();
            $table->string('device_platform')->nullable()
                ->comment('android | ios');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['account_origin', 'account_state', 'device_id', 'device_model', 'device_platform']);
        });
    }
};
