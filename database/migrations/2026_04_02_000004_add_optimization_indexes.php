<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance-critical indexes identified in the optimization audit.
 * These indexes target the heaviest queries: manifest API, revenue dashboards,
 * movie listing/filtering, and subscription checks.
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
        // === subscriptions table ===
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!$this->indexExists('subscriptions', 'idx_sub_app_type')) {
                $table->index('app_type', 'idx_sub_app_type');
            }
            if (!$this->indexExists('subscriptions', 'idx_sub_app_type_payment_status')) {
                $table->index(['app_type', 'payment_status'], 'idx_sub_app_type_payment_status');
            }
        });

        // === subscription_transactions table ===
        Schema::table('subscription_transactions', function (Blueprint $table) {
            if (!$this->indexExists('subscription_transactions', 'idx_st_platform')) {
                $table->index('platform', 'idx_st_platform');
            }
            if (!$this->indexExists('subscription_transactions', 'idx_st_status_type')) {
                $table->index(['status', 'transaction_type'], 'idx_st_status_type');
            }
            if (!$this->indexExists('subscription_transactions', 'idx_st_status_type_date')) {
                $table->index(['status', 'transaction_type', 'created_at'], 'idx_st_status_type_date');
            }
        });

        // === movie_likes table ===
        if (!$this->indexExists('movie_likes', 'idx_ml_user_movie')) {
            Schema::table('movie_likes', function (Blueprint $table) {
                $table->index(['user_id', 'movie_model_id'], 'idx_ml_user_movie');
            });
        }

        // === movie_models table ===
        // Convert TEXT columns to VARCHAR so they can be indexed
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN status VARCHAR(50) DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN genre VARCHAR(255) DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN vj VARCHAR(255) DEFAULT NULL');

        Schema::table('movie_models', function (Blueprint $table) {
            if (!$this->indexExists('movie_models', 'idx_mm_type')) {
                $table->index('type', 'idx_mm_type');
            }
            if (!$this->indexExists('movie_models', 'idx_mm_status')) {
                $table->index('status', 'idx_mm_status');
            }
            if (!$this->indexExists('movie_models', 'idx_mm_type_status')) {
                $table->index(['type', 'status'], 'idx_mm_type_status');
            }
            if (!$this->indexExists('movie_models', 'idx_mm_category')) {
                $table->index('category_id', 'idx_mm_category');
            }
            if (!$this->indexExists('movie_models', 'idx_mm_genre')) {
                $table->index('genre', 'idx_mm_genre');
            }
            if (!$this->indexExists('movie_models', 'idx_mm_vj')) {
                $table->index('vj', 'idx_mm_vj');
            }
            if (!$this->indexExists('movie_models', 'idx_mm_series_listing')) {
                $table->index(['type', 'is_first_episode', 'category_id'], 'idx_mm_series_listing');
            }
        });

        // === watchlists table ===
        if (Schema::hasColumns('watchlists', ['user_id', 'movie_model_id'])) {
            if (!$this->indexExists('watchlists', 'idx_wl_user_movie')) {
                Schema::table('watchlists', function (Blueprint $table) {
                    $table->index(['user_id', 'movie_model_id'], 'idx_wl_user_movie');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_sub_app_type');
            $table->dropIndex('idx_sub_app_type_payment_status');
        });

        Schema::table('subscription_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_st_platform');
            $table->dropIndex('idx_st_status_type');
            $table->dropIndex('idx_st_status_type_date');
        });

        Schema::table('movie_likes', function (Blueprint $table) {
            $table->dropIndex('idx_ml_user_movie');
        });

        Schema::table('movie_models', function (Blueprint $table) {
            $table->dropIndex('idx_mm_type');
            $table->dropIndex('idx_mm_status');
            $table->dropIndex('idx_mm_type_status');
            $table->dropIndex('idx_mm_category');
            $table->dropIndex('idx_mm_genre');
            $table->dropIndex('idx_mm_vj');
            $table->dropIndex('idx_mm_series_listing');
        });

        if (Schema::hasTable('watchlists')) {
            Schema::table('watchlists', function (Blueprint $table) {
                $table->dropIndex('idx_wl_user_movie');
            });
        }
    }
};
