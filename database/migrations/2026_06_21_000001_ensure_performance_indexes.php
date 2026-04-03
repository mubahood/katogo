<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3-19: Idempotent verification + re-application of all performance indexes
 * from 2026_03_14_000001_add_performance_indexes.php.
 *
 * Safe to run even if the original migration already ran — each index is only
 * created when it doesn't yet exist (checked via INFORMATION_SCHEMA.STATISTICS).
 */
return new class extends Migration
{
    /**
     * Indexes required on production. format:
     *   [table, columns[], index_name]
     */
    private array $requiredIndexes = [
        // movie_views
        ['movie_views',    ['user_id', 'movie_model_id'], 'idx_mv_user_movie'],
        ['movie_views',    ['user_id', 'updated_at'],     'idx_mv_user_updated'],
        ['movie_views',    ['created_at'],                 'idx_mv_created'],
        // movie_downloads
        ['movie_downloads', ['user_id'],                  'idx_md_user'],
        ['movie_downloads', ['movie_model_id'],            'idx_md_movie'],
        ['movie_downloads', ['created_at'],                'idx_md_created'],
        ['movie_downloads', ['download_type'],             'idx_md_type'],
        // chat_messages
        ['chat_messages',  ['sender_id'],                  'idx_cm_sender'],
        ['chat_messages',  ['receiver_id'],                'idx_cm_receiver'],
        ['chat_messages',  ['receiver_id', 'status'],      'idx_cm_receiver_status'],
        // admin_users
        ['admin_users',    ['email'],                      'idx_au_email'],
        ['admin_users',    ['status'],                     'idx_au_status'],
        ['admin_users',    ['app_type'],                   'idx_au_app_type'],
    ];

    public function up(): void
    {
        $database = DB::connection()->getDatabaseName();

        foreach ($this->requiredIndexes as [$table, $columns, $indexName]) {
            if (! $this->indexExists($database, $table, $indexName)) {
                Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                    $t->index($columns, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        $database = DB::connection()->getDatabaseName();

        foreach ($this->requiredIndexes as [$table, $columns, $indexName]) {
            if ($this->indexExists($database, $table, $indexName)) {
                Schema::table($table, function (Blueprint $t) use ($indexName) {
                    $t->dropIndex($indexName);
                });
            }
        }
    }

    private function indexExists(string $database, string $table, string $indexName): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
