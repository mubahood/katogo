<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing indexes to heavily-queried tables for performance optimization.
 * These tables were found to be doing full table scans on shared hosting.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── movie_views: composite for progress lookups + time ordering ──
        Schema::table('movie_views', function (Blueprint $table) {
            $table->index(['user_id', 'movie_model_id'], 'idx_mv_user_movie');
            $table->index(['user_id', 'updated_at'], 'idx_mv_user_updated');
            $table->index('created_at', 'idx_mv_created');
        });

        // ── movie_downloads: NO indexes existed beyond PRIMARY ──
        Schema::table('movie_downloads', function (Blueprint $table) {
            $table->index('user_id', 'idx_md_user');
            $table->index('movie_model_id', 'idx_md_movie');
            $table->index('created_at', 'idx_md_created');
            $table->index('download_type', 'idx_md_type');
        });

        // ── chat_messages: NO indexes existed beyond PRIMARY ──
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->index('sender_id', 'idx_cm_sender');
            $table->index('receiver_id', 'idx_cm_receiver');
            $table->index(['receiver_id', 'status'], 'idx_cm_receiver_status');
        });

        // ── admin_users: missing lookups for auth & analytics ──
        Schema::table('admin_users', function (Blueprint $table) {
            $table->index('email', 'idx_au_email');
            $table->index('status', 'idx_au_status');
            $table->index('app_type', 'idx_au_app_type');
        });
    }

    public function down(): void
    {
        Schema::table('movie_views', function (Blueprint $table) {
            $table->dropIndex('idx_mv_user_movie');
            $table->dropIndex('idx_mv_user_updated');
            $table->dropIndex('idx_mv_created');
        });
        Schema::table('movie_downloads', function (Blueprint $table) {
            $table->dropIndex('idx_md_user');
            $table->dropIndex('idx_md_movie');
            $table->dropIndex('idx_md_created');
            $table->dropIndex('idx_md_type');
        });
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('idx_cm_sender');
            $table->dropIndex('idx_cm_receiver');
            $table->dropIndex('idx_cm_receiver_status');
        });
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropIndex('idx_au_email');
            $table->dropIndex('idx_au_status');
            $table->dropIndex('idx_au_app_type');
        });
    }
};
