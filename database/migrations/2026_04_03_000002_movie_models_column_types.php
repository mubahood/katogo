<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 7B — movie_models column type optimisations:
 *
 * P3-20  title           TEXT  → VARCHAR(500)
 * P3-21  external_url    TEXT  → VARCHAR(1000)
 * P3-22  url             TEXT  → VARCHAR(1000)
 * P3-23  image_url       TEXT  → VARCHAR(500)
 * P3-24  thumbnail_url   TEXT  → VARCHAR(500)
 * P3-25  views_count     TEXT  → INT UNSIGNED DEFAULT 0
 * P3-26  downloads_count TEXT  → INT UNSIGNED DEFAULT 0
 * P3-27  likes_count     TEXT  → INT UNSIGNED DEFAULT 0
 * P3-28  dislikes_count  TEXT  → INT UNSIGNED DEFAULT 0
 * P3-29  comments_count  TEXT  → INT UNSIGNED DEFAULT 0
 * P3-15  Add index on views_count (now INT, so indexable)
 * P5-11  Add FULLTEXT index on title (enables MATCH AGAINST in search)
 *
 * Estimated savings: ~3 GB on a 50k-row table.
 * MySQL best-practice: run OPTIMIZE TABLE movie_models after this migration.
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
        // ── String columns: TEXT → VARCHAR ──────────────────────────────────
        // VARCHAR is stored inline (no off-page pointer), reduces row size,
        // and allows indexing without prefix length prefixes.
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN title VARCHAR(500) DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN external_url VARCHAR(1000) DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN url VARCHAR(1000) DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN image_url VARCHAR(500) DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN thumbnail_url VARCHAR(500) DEFAULT NULL');

        // ── Count columns: TEXT → INT UNSIGNED ───────────────────────────────
        // Counts were stored as TEXT (e.g. "42" or ""). Convert to INT.
        // COALESCE ensures blank strings and NULLs become 0 safely.
        DB::statement("UPDATE movie_models SET views_count     = '0' WHERE views_count     IS NULL OR views_count     = '' OR views_count     NOT REGEXP '^[0-9]+$'");
        DB::statement("UPDATE movie_models SET downloads_count = '0' WHERE downloads_count IS NULL OR downloads_count = '' OR downloads_count NOT REGEXP '^[0-9]+$'");
        DB::statement("UPDATE movie_models SET likes_count     = '0' WHERE likes_count     IS NULL OR likes_count     = '' OR likes_count     NOT REGEXP '^[0-9]+$'");
        DB::statement("UPDATE movie_models SET dislikes_count  = '0' WHERE dislikes_count  IS NULL OR dislikes_count  = '' OR dislikes_count  NOT REGEXP '^[0-9]+$'");
        DB::statement("UPDATE movie_models SET comments_count  = '0' WHERE comments_count  IS NULL OR comments_count  = '' OR comments_count  NOT REGEXP '^[0-9]+$'");

        DB::statement('ALTER TABLE movie_models MODIFY COLUMN views_count     INT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN downloads_count INT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN likes_count     INT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN dislikes_count  INT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN comments_count  INT UNSIGNED NOT NULL DEFAULT 0');

        // ── P3-15: Index views_count (now INT, so indexable) ─────────────────
        Schema::table('movie_models', function (Blueprint $table) {
            if (!$this->indexExists('movie_models', 'idx_mm_views_count')) {
                $table->index('views_count', 'idx_mm_views_count');
            }
        });

        // ── P5-11: FULLTEXT index on title ────────────────────────────────────
        // Enables MATCH(title) AGAINST('term' IN BOOLEAN MODE) for title searches.
        // Much faster than LIKE '%term%' which forces a full sequential scan.
        if (!$this->indexExists('movie_models', 'ft_mm_title')) {
            DB::statement('ALTER TABLE movie_models ADD FULLTEXT INDEX ft_mm_title (title)');
        }

        // Recommend running after deploy:
        //   php artisan tinker --execute="DB::statement('OPTIMIZE TABLE movie_models');"
    }

    public function down(): void
    {
        // Drop FULLTEXT and views_count indexes first
        if ($this->indexExists('movie_models', 'ft_mm_title')) {
            DB::statement('ALTER TABLE movie_models DROP INDEX ft_mm_title');
        }
        Schema::table('movie_models', function (Blueprint $table) {
            if ($this->indexExists('movie_models', 'idx_mm_views_count')) {
                $table->dropIndex('idx_mm_views_count');
            }
        });

        // Revert count columns back to TEXT (data loss in direction of conversion is acceptable;
        // reversing is best-effort — values will still be numeric strings)
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN views_count     TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN downloads_count TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN likes_count     TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN dislikes_count  TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN comments_count  TEXT DEFAULT NULL');

        // Revert string columns back to TEXT
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN title         TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN external_url  TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN url           TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN image_url     TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN thumbnail_url TEXT DEFAULT NULL');
    }
};
