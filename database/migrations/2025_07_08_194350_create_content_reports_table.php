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
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('reporter_id');
            $table->string('reported_content_type'); // 'movie', 'chat_message', 'user_profile', etc.
            $table->unsignedBigInteger('reported_content_id');
            $table->unsignedInteger('reported_user_id');
            $table->enum('report_type', [
                'spam', 'harassment', 'inappropriate_content', 'hate_speech', 
                'violence', 'copyright', 'misinformation', 'other'
            ]);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'under_review', 'resolved', 'dismissed', 'escalated'])
                  ->default('pending');
            $table->unsignedInteger('moderator_id')->nullable();
            $table->enum('moderation_action', [
                'no_action', 'warning', 'content_removed', 'user_suspended', 'user_banned', 'escalated'
            ])->nullable();
            $table->text('moderation_notes')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['reporter_id']);
            $table->index(['reported_user_id']);
            $table->index(['status']);
            $table->index(['priority']);
            $table->index(['created_at']);
            $table->index(['reported_content_type', 'reported_content_id']);

            // Note: Foreign key cascading handled manually in models
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
