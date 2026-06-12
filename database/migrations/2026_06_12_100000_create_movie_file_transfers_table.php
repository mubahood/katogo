<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_file_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Relationship ──────────────────────────────────────────────
            // Nullable so standalone transfers (not tied to a movie) are possible
            $table->unsignedBigInteger('movie_id')->nullable()->index();
            $table->foreign('movie_id')
                  ->references('id')
                  ->on('movie_models')
                  ->onDelete('set null');

            // ── Movie snapshot (captured at queue time, immutable) ─────────
            $table->string('movie_title', 500)->nullable();
            $table->smallInteger('movie_year')->nullable();
            $table->string('movie_quality', 20)->nullable();
            $table->integer('movie_duration')->nullable();       // seconds
            $table->string('movie_poster_url', 2000)->nullable();
            $table->string('movie_munowatch_id', 100)->nullable();
            $table->boolean('movie_is_series')->default(false);
            $table->json('movie_episode_info')->nullable();      // {season, episode, series_title}

            // ── Source ────────────────────────────────────────────────────
            $table->text('source_url');
            $table->string('source_type', 30)->default('unknown');
            // 'munowatch' | 'gdrive' | 'firebase' | 'hetzner' | 'direct' | 'other'
            $table->bigInteger('source_size_bytes')->nullable()->unsigned();
            $table->timestamp('source_verified_at')->nullable();

            // ── Destination ───────────────────────────────────────────────
            $table->string('dest_type', 20)->default('hetzner');
            $table->string('dest_path', 1000)->nullable();
            $table->string('dest_url', 2000)->nullable();
            $table->string('dest_share_token', 100)->nullable();
            $table->string('dest_file_id', 200)->nullable();    // Google Drive file ID if applicable
            $table->bigInteger('dest_size_bytes')->nullable()->unsigned();

            // ── Status & Progress ─────────────────────────────────────────
            // queued | verifying | transferring | completing | done | failed | cancelled | skipped
            $table->string('status', 20)->default('queued')->index();
            $table->tinyInteger('progress_pct')->default(0)->unsigned();
            $table->bigInteger('bytes_transferred')->default(0)->unsigned();
            $table->decimal('transfer_speed_mbps', 8, 2)->nullable();
            $table->integer('eta_seconds')->nullable();

            // ── Timing ────────────────────────────────────────────────────
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();

            // ── Retry & Error ─────────────────────────────────────────────
            $table->tinyInteger('attempt_count')->default(0)->unsigned();
            $table->tinyInteger('max_attempts')->default(3)->unsigned();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->string('error_message', 500)->nullable();
            $table->text('error_trace')->nullable();
            $table->json('error_context')->nullable();

            // ── Outcome flags ─────────────────────────────────────────────
            $table->boolean('movie_url_updated')->default(false);
            $table->boolean('old_movie_url_backed_up')->default(false);

            // ── Metadata ──────────────────────────────────────────────────
            $table->text('notes')->nullable();
            $table->string('initiated_by', 50)->default('observer');
            // 'observer' | 'scheduler' | 'admin:{user_id}' | 'backfill' | 'api'
            $table->string('worker_hostname', 100)->nullable();

            $table->timestamps();

            // ── Composite indexes for common query patterns ───────────────
            $table->index(['status', 'queued_at'],    'idx_status_queued');
            $table->index(['movie_id', 'status'],     'idx_movie_status');
            $table->index(['status', 'next_retry_at'], 'idx_status_retry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_file_transfers');
    }
};
