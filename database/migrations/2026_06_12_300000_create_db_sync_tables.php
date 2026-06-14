<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-table cursor state — tracks where the last successful sync stopped
        Schema::create('db_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 100)->unique();
            $table->unsignedBigInteger('last_synced_id')->default(0);
            $table->timestamp('last_updated_ts')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedBigInteger('rows_on_source')->default(0);
            $table->unsignedInteger('rows_this_run')->default(0);
            $table->unsignedInteger('consecutive_errors')->default(0);
            $table->enum('status', ['idle', 'syncing', 'ok', 'error'])->default('idle');
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('priority')->default(4);
            $table->unsignedSmallInteger('frequency_minutes')->default(5);
            $table->boolean('enabled')->default(true);
            $table->boolean('has_timestamps')->default(true);
            $table->boolean('is_pivot')->default(false);
            $table->timestamps();
        });

        // Audit log — one row per table per sync run
        Schema::create('db_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->char('run_id', 36)->index();
            $table->string('table_name', 100)->index();
            $table->unsignedInteger('rows_fetched')->default(0);
            $table->unsignedInteger('rows_upserted')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('pages_fetched')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->enum('status', ['ok', 'error', 'partial', 'skipped'])->default('ok');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('cursor_id_before')->default(0);
            $table->unsignedBigInteger('cursor_id_after')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['table_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_sync_logs');
        Schema::dropIfExists('db_sync_cursors');
    }
};
