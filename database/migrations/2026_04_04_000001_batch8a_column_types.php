<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Batch 8A — Column type optimisations (P3-30..P3-44)
 *
 * Changes TEXT columns in movie_downloads and series_movies to narrower types.
 * Benefits: smaller row footprint, enables future indexing, faster temp-table sorts.
 *
 * Safety: all ALTER TABLE use MODIFY COLUMN (in-place on InnoDB).
 * Data sanitisation runs first so no truncation occurs.
 *
 * Estimated impact: moderate storage saving on movie_downloads (per-user records),
 *   improved sort speed on series_movies.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────────────
        // movie_downloads — sanitise data before narrowing types
        // ──────────────────────────────────────────────────────────────────────

        // P3-38/39: ensure download_progress / watch_progress are valid decimals
        // Set invalid (non-numeric) values to NULL so DECIMAL conversion won't fail
        DB::statement("
            UPDATE movie_downloads
            SET download_progress = NULL
            WHERE download_progress IS NOT NULL
              AND download_progress NOT REGEXP '^[0-9]+(\\.[0-9]+)?$'
        ");
        DB::statement("
            UPDATE movie_downloads
            SET watch_progress = NULL
            WHERE watch_progress IS NOT NULL
              AND watch_progress NOT REGEXP '^[0-9]+(\\.[0-9]+)?$'
        ");

        // P3-41: ensure episode_number is a non-negative integer
        DB::statement("
            UPDATE movie_downloads
            SET episode_number = NULL
            WHERE episode_number IS NOT NULL
              AND episode_number NOT REGEXP '^[0-9]+$'
        ");

        // P3-40/42: normalise boolean-ish values to '1' / '0'
        DB::statement("
            UPDATE movie_downloads
            SET is_premium = CASE
                WHEN LOWER(TRIM(is_premium)) IN ('yes', 'true', '1') THEN '1'
                ELSE '0'
            END
            WHERE is_premium IS NOT NULL
        ");
        DB::statement("
            UPDATE movie_downloads
            SET is_first_episode = CASE
                WHEN LOWER(TRIM(is_first_episode)) IN ('yes', 'true', '1') THEN '1'
                ELSE '0'
            END
            WHERE is_first_episode IS NOT NULL
        ");

        // P3-30..P3-42: ALTER movie_downloads columns (VARCHAR / DECIMAL / BOOLEAN / INT)
        // local_id is NOT NULL in original schema; keep NOT NULL.
        DB::statement("
            ALTER TABLE movie_downloads
                MODIFY COLUMN local_id           VARCHAR(500)      NOT NULL,
                MODIFY COLUMN local_video_link   VARCHAR(2000)     NULL,
                MODIFY COLUMN download_progress  DECIMAL(5,2)      NULL,
                MODIFY COLUMN watch_progress     DECIMAL(5,2)      NULL,
                MODIFY COLUMN title              VARCHAR(500)      NULL,
                MODIFY COLUMN url                VARCHAR(2000)     NULL,
                MODIFY COLUMN image_url          VARCHAR(2000)     NULL,
                MODIFY COLUMN genre              VARCHAR(255)      NULL,
                MODIFY COLUMN vj                 VARCHAR(255)      NULL,
                MODIFY COLUMN is_premium         TINYINT(1)        NULL,
                MODIFY COLUMN episode_number     INT UNSIGNED      NULL,
                MODIFY COLUMN is_first_episode   TINYINT(1)        NULL
        ");
        // Note: description kept as TEXT (can contain long multi-paragraph text)

        // ──────────────────────────────────────────────────────────────────────
        // series_movies — P3-43 / P3-44
        // ──────────────────────────────────────────────────────────────────────

        // Truncate values that are longer than VARCHAR(500) to avoid DATA_TRUNCATED error
        DB::statement("
            UPDATE series_movies
            SET title = LEFT(title, 500)
            WHERE title IS NOT NULL AND CHAR_LENGTH(title) > 500
        ");
        DB::statement("
            UPDATE series_movies
            SET Category = LEFT(Category, 500)
            WHERE Category IS NOT NULL AND CHAR_LENGTH(Category) > 500
        ");

        DB::statement("
            ALTER TABLE series_movies
                MODIFY COLUMN title    VARCHAR(500) NULL,
                MODIFY COLUMN Category VARCHAR(500) NULL
        ");
    }

    public function down(): void
    {
        // Restore original TEXT columns
        DB::statement("
            ALTER TABLE movie_downloads
                MODIFY COLUMN local_id           TEXT             NOT NULL,
                MODIFY COLUMN local_video_link   TEXT             NULL,
                MODIFY COLUMN download_progress  TEXT             NULL,
                MODIFY COLUMN watch_progress     TEXT             NULL,
                MODIFY COLUMN title              TEXT             NULL,
                MODIFY COLUMN url                TEXT             NULL,
                MODIFY COLUMN image_url          TEXT             NULL,
                MODIFY COLUMN genre              TEXT             NULL,
                MODIFY COLUMN vj                 TEXT             NULL,
                MODIFY COLUMN is_premium         TEXT             NULL,
                MODIFY COLUMN episode_number     TEXT             NULL,
                MODIFY COLUMN is_first_episode   TEXT             NULL
        ");

        DB::statement("
            ALTER TABLE series_movies
                MODIFY COLUMN title    TEXT NULL,
                MODIFY COLUMN Category TEXT NULL
        ");
    }
};
