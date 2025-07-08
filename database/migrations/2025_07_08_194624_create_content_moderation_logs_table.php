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
        Schema::create('content_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('content_type'); // 'movie', 'chat_message', 'user', etc.
            $table->unsignedBigInteger('content_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('moderator_id')->nullable();
            $table->enum('action_type', [
                'content_filtered', 'content_approved', 'content_blocked', 'content_quarantined',
                'user_warning', 'user_suspended', 'user_banned', 'content_reported',
                'user_blocked', 'user_unblocked', 'legal_consent_updated'
            ]);
            $table->string('reason', 500)->nullable();
            $table->json('filter_result')->nullable();
            $table->boolean('automated')->default(false);
            $table->enum('severity_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['content_type', 'content_id']);
            $table->index(['user_id']);
            $table->index(['moderator_id']);
            $table->index(['action_type']);
            $table->index(['automated']);
            $table->index(['severity_level']);
            $table->index(['created_at']);

            // Note: Foreign key cascading handled manually in models
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_moderation_logs');
    }
};
