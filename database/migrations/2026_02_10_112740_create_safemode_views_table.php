<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('safemode_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('external_video_id')->comment('MunoWatch video id');
            $table->string('video_title', 500)->nullable();
            $table->string('category')->nullable();
            $table->string('genre')->nullable();
            $table->string('action', 30)->default('view')->comment('view|play|like|mylist');
            $table->double('progress_seconds')->default(0);
            $table->double('duration_seconds')->default(0);
            $table->double('max_progress_seconds')->default(0);
            $table->double('percentage')->default(0);
            $table->string('status', 20)->default('Active')->comment('Active|Completed');
            $table->string('device', 100)->nullable();
            $table->string('platform', 50)->default('safemode');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'external_video_id', 'action'], 'sm_user_video_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safemode_views');
    }
};
