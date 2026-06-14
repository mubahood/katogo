<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_activity_logs')) return;

        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('action', 50)->comment('movie_play|movie_complete|search|download|like|rate|subscribe');
            $table->string('entity_type', 30)->nullable()->comment('movie|series|game');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedInteger('duration_s')->nullable()->comment('Seconds watched (for play events)');
            $table->string('app_type', 30)->nullable();
            $table->json('meta')->nullable()->comment('Quality, position, device type, etc.');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
