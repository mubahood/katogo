<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 9 — Create archive tables (P6-17 / P6-18 / P6-19)
 *
 * Three mirror tables for cold storage of historical rows older than 6 months.
 * No foreign key constraints (referential integrity not required for archives).
 * Each table adds an `archived_at` timestamp so we know when the row was moved.
 *
 * The matching archival jobs run monthly via the scheduler (see Kernel.php P6-20 / P7-10).
 * After archival, OPTIMIZE TABLE reclaims the freed space (P6-25 / P6-26, Kernel).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── archive_movie_views ──────────────────────────────────────────────
        if (!Schema::hasTable('archive_movie_views')) {
            Schema::create('archive_movie_views', function (Blueprint $table) {
                $table->id();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('archived_at')->useCurrent();

                $table->unsignedBigInteger('movie_model_id');
                $table->unsignedBigInteger('user_id');
                $table->string('ip_address', 50)->nullable();
                $table->string('device', 50)->nullable();
                $table->string('platform', 50)->nullable();
                $table->string('browser', 50)->nullable();
                $table->string('country', 50)->nullable();
                $table->string('city', 50)->nullable();
                $table->string('status', 50)->default('Active');
                $table->integer('progress')->default(0);
                $table->integer('max_progress')->default(0);

                $table->index('movie_model_id');
                $table->index('user_id');
                $table->index('archived_at');
            });
        }

        // ── archive_movie_downloads ──────────────────────────────────────────
        if (!Schema::hasTable('archive_movie_downloads')) {
            Schema::create('archive_movie_downloads', function (Blueprint $table) {
                $table->id();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('archived_at')->useCurrent();

                $table->string('local_id', 500)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('movie_model_id')->nullable();
                $table->string('status', 100)->nullable();
                $table->text('error_message')->nullable();
                $table->string('local_video_link', 2000)->nullable();
                $table->dateTime('download_started_at')->nullable();
                $table->dateTime('download_completed_at')->nullable();
                $table->integer('download_duration')->nullable();
                $table->string('title', 500)->nullable();
                $table->string('url', 2000)->nullable();
                $table->string('image_url', 2000)->nullable();
                $table->string('genre', 255)->nullable();
                $table->string('vj', 255)->nullable();
                $table->tinyInteger('is_premium')->nullable();
                $table->unsignedInteger('episode_number')->nullable();

                $table->index('movie_model_id');
                $table->index('user_id');
                $table->index('archived_at');
            });
        }

        // ── archive_chat_messages ────────────────────────────────────────────
        if (!Schema::hasTable('archive_chat_messages')) {
            Schema::create('archive_chat_messages', function (Blueprint $table) {
                $table->id();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('archived_at')->useCurrent();

                $table->unsignedBigInteger('chat_head_id');
                $table->unsignedBigInteger('sender_id');
                $table->unsignedBigInteger('receiver_id');
                $table->text('sender_name')->nullable();
                $table->text('receiver_name')->nullable();
                $table->text('body')->nullable();
                $table->string('type', 100)->nullable();
                $table->string('audio_url', 500)->nullable();
                $table->integer('audio_duration')->nullable();
                $table->string('status', 100)->nullable();

                $table->index('sender_id');
                $table->index('receiver_id');
                $table->index('archived_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_chat_messages');
        Schema::dropIfExists('archive_movie_downloads');
        Schema::dropIfExists('archive_movie_views');
    }
};
