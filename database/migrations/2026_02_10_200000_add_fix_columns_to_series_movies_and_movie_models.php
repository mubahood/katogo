<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add debugging fix-tracking columns to series_movies and movie_models.
 *
 * Columns:
 *  - fix_status      VARCHAR(20) DEFAULT 'pending', nullable  — pending | fixed | error
 *  - fix_error_message TEXT nullable                          — error details when fix_status='error'
 *  - fix_date        TIMESTAMP nullable                       — date the record was last fixed
 *  - fix_counter     INT UNSIGNED DEFAULT 0                   — how many fix attempts have been made
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── series_movies ──
        if (!Schema::hasColumn('series_movies', 'fix_status')) {
            Schema::table('series_movies', function (Blueprint $table) {
                $table->string('fix_status', 20)->nullable()->default('pending')
                      ->comment('Fix status: pending, fixed, error')
                      ->after('is_active');
                $table->text('fix_error_message')->nullable()
                      ->comment('Error message when fix_status=error')
                      ->after('fix_status');
                $table->timestamp('fix_date')->nullable()
                      ->comment('Date when the record was last fixed')
                      ->after('fix_error_message');
                $table->unsignedInteger('fix_counter')->default(0)
                      ->comment('Number of fix attempts')
                      ->after('fix_date');
            });
        }

        // ── movie_models ──
        if (!Schema::hasColumn('movie_models', 'fix_status')) {
            Schema::table('movie_models', function (Blueprint $table) {
                $table->string('fix_status', 20)->nullable()->default('pending')
                      ->comment('Fix status: pending, fixed, error')
                      ->after('status');
                $table->text('fix_error_message')->nullable()
                      ->comment('Error message when fix_status=error')
                      ->after('fix_status');
                $table->timestamp('fix_date')->nullable()
                      ->comment('Date when the record was last fixed')
                      ->after('fix_error_message');
                $table->unsignedInteger('fix_counter')->default(0)
                      ->comment('Number of fix attempts')
                      ->after('fix_date');
            });
        }
    }

    public function down(): void
    {
        Schema::table('series_movies', function (Blueprint $table) {
            $table->dropColumn(['fix_status', 'fix_error_message', 'fix_date', 'fix_counter']);
        });
        Schema::table('movie_models', function (Blueprint $table) {
            $table->dropColumn(['fix_status', 'fix_error_message', 'fix_date', 'fix_counter']);
        });
    }
};
