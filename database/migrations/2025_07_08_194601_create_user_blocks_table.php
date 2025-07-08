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
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('blocker_id');
            $table->unsignedInteger('blocked_user_id');
            $table->string('reason', 500)->nullable();
            $table->enum('block_type', ['user_initiated', 'moderator_initiated', 'automatic'])
                  ->default('user_initiated');
            $table->enum('status', ['active', 'expired', 'removed'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['blocker_id']);
            $table->index(['blocked_user_id']);
            $table->index(['status']);
            $table->index(['expires_at']);
            
            // Unique constraint to prevent duplicate blocks
            $table->unique(['blocker_id', 'blocked_user_id'], 'unique_user_block');

            // Note: Foreign key cascading handled manually in models
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
