<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Runs on the SOURCE server (Namecheap).
 * Provides authenticated, cursor-based, read-only data export for each table.
 */
class SyncExportService
{
    /** Tables the export API is willing to serve — anything not listed is rejected. */
    private const ALLOWED_TABLES = [
        'admin_users', 'admin_roles', 'admin_permissions', 'admin_menu',
        'admin_role_users', 'admin_role_permissions', 'admin_user_permissions',
        'admin_operation_log',
        'subscriptions', 'subscription_plans', 'subscription_transactions',
        'movie_models', 'series_movies', 'movie_views', 'movie_likes',
        'movie_downloads', 'movie_requests', 'movie_searches', 'movie_wishlists',
        'movie_pics', 'movie_crawler_websites', 'movie_crawler_pages',
        'munowatch_categories', 'munowatch_movie_categories',
        'user_activity_logs', 'movie_ratings',
        'content_reports', 'content_moderation_logs', 'video_playback_failures',
        'chat_heads', 'chat_messages',
        'game_stats', 'trending_notifications', 'coin_transactions',
        'user_blocks', 'merged_accounts',
        'streaming_stations', 'streaming_urls',
        'pages', 'links', 'schools',
        'blog_posts', 'blog_comments', 'blog_likes',
        'system_configs', 'scraper_models',
        'learning_material_categories', 'learning_material_posts',
        'support_audit_logs', 'safemode_views', 'page_visits',
        'trivia_questions',
        'companies', 'financial_periods',
        'stock_categories', 'stock_sub_categories', 'stock_items', 'stock_records',
    ];

    /** Tables that have no `id` column — use offset-based pagination instead. */
    private const PIVOT_TABLES = [
        'admin_role_users', 'admin_role_permissions', 'admin_user_permissions',
        'munowatch_movie_categories', 'blog_likes',
    ];

    public function isAllowed(string $table): bool
    {
        return in_array($table, self::ALLOWED_TABLES, true);
    }

    /**
     * Export a page of rows from a table.
     *
     * @param string   $table      Table name (validated before calling)
     * @param int      $cursorId   Minimum id (exclusive) for new rows
     * @param string|null $updatedTs  ISO timestamp — also pull rows updated after this
     * @param int      $limit      Rows per page (max 1000)
     * @param int      $offset     For pivot tables without id
     * @return array{ rows: array, next_cursor_id: int, next_updated_ts: string|null, has_more: bool, total_rows: int }
     */
    public function export(
        string $table,
        int $cursorId = 0,
        ?string $updatedTs = null,
        int $limit = 500,
        int $offset = 0,
    ): array {
        $limit = min($limit, 1000);
        $isPivot = in_array($table, self::PIVOT_TABLES, true);

        if ($isPivot) {
            return $this->exportPivot($table, $limit, $offset);
        }

        return $this->exportWithId($table, $cursorId, $updatedTs, $limit);
    }

    private function exportWithId(string $table, int $cursorId, ?string $updatedTs, int $limit): array
    {
        $hasUpdatedAt = $this->columnExists($table, 'updated_at');

        // New rows (id > cursor)
        $newRows = DB::table($table)
            ->where('id', '>', $cursorId)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->toArray();

        // Updated rows (updated_at > cursor_ts AND id <= cursor, so we don't double-count)
        $updatedRows = [];
        if ($hasUpdatedAt && $updatedTs && $cursorId > 0) {
            $updatedRows = DB::table($table)
                ->where('updated_at', '>', $updatedTs)
                ->where('id', '<=', $cursorId)
                ->orderBy('updated_at')
                ->limit($limit)
                ->get()
                ->toArray();
        }

        // Merge by id (dedup)
        $byId = [];
        foreach (array_merge($updatedRows, $newRows) as $row) {
            $row = (array) $row;
            $byId[$row['id']] = $row;
        }
        $rows = array_values($byId);

        // Calculate next cursors
        $nextId = $cursorId;
        $nextTs = $updatedTs;

        foreach ($rows as $row) {
            if (($row['id'] ?? 0) > $nextId) {
                $nextId = $row['id'];
            }
            if ($hasUpdatedAt && !empty($row['updated_at'])) {
                if ($nextTs === null || $row['updated_at'] > $nextTs) {
                    $nextTs = $row['updated_at'];
                }
            }
        }

        $hasMore = count($newRows) === $limit; // If we got a full page, there may be more
        $total   = DB::table($table)->count();

        return [
            'rows'            => $rows,
            'next_cursor_id'  => $nextId,
            'next_updated_ts' => $nextTs,
            'has_more'        => $hasMore,
            'total_rows'      => $total,
        ];
    }

    private function exportPivot(string $table, int $limit, int $offset): array
    {
        $rows  = DB::table($table)->offset($offset)->limit($limit)->get()->toArray();
        $rows  = array_map(fn($r) => (array) $r, $rows);
        $total = DB::table($table)->count();

        return [
            'rows'            => $rows,
            'next_cursor_id'  => 0,
            'next_updated_ts' => null,
            'has_more'        => ($offset + count($rows)) < $total,
            'next_offset'     => $offset + count($rows),
            'total_rows'      => $total,
        ];
    }

    /** Returns row counts for all allowed tables — used by handshake/health check. */
    public function tableSummary(): array
    {
        $summary = [];
        foreach (self::ALLOWED_TABLES as $table) {
            try {
                $summary[$table] = DB::table($table)->count();
            } catch (\Throwable) {
                $summary[$table] = null; // table may not exist on this server
            }
        }
        return $summary;
    }

    private function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = "{$table}.{$column}";
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = in_array($column, array_column(
                DB::select("SHOW COLUMNS FROM `{$table}` LIKE ?", [$column]),
                'Field'
            ));
        }
        return $cache[$key];
    }
}
