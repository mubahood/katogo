<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 7A quick indexes:
 * P3-06  — compound index subscriptions(status, created_at) for expiry queries
 *
 * NOTE: P3-17 (game_invitations.status) and P3-18 (game_invitations.expires_at)
 * are already indexed in the original create_game_invitations_table migration.
 */
return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }

    public function up(): void
    {
        // P3-06: subscriptions(status, created_at) — used by expiry checker queries
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!$this->indexExists('subscriptions', 'idx_sub_status_date')) {
                $table->index(['status', 'created_at'], 'idx_sub_status_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if ($this->indexExists('subscriptions', 'idx_sub_status_date')) {
                $table->dropIndex('idx_sub_status_date');
            }
        });
    }
};
