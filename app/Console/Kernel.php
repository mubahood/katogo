<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Subscription System Commands

        // Repair corrupted subscription dates — runs daily at 00:30 AM (before the expiry check)
        $schedule->command('subscriptions:repair')
            ->dailyAt('00:30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/subscriptions-repair.log'));

        // Check for expired subscriptions — runs every hour (P7-13)
        // Hourly instead of daily so users' statuses flip within minutes of expiry.
        $schedule->command('subscriptions:check-expired')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/subscriptions-check-expired.log'));

        // Send expiry notifications - runs daily at 9:00 AM
        $schedule->command('subscriptions:send-expiry-notifications --days=3')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/subscriptions-expiry-notifications.log'));

        // Check pending payments - runs every 15 minutes
        $schedule->command('subscriptions:check-pending-payments --age=15 --limit=50')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/subscriptions-check-pending.log'));

        // Optional: Send second reminder 1 day before expiry
        $schedule->command('subscriptions:send-expiry-notifications --days=1')
            ->dailyAt('10:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/subscriptions-expiry-notifications-1day.log'));

        // Daily: purge expired password reset tokens (older than 60 minutes)
        $schedule->call(function () {
            \DB::table('password_reset_tokens')
                ->where('created_at', '<', now()->subMinutes(60))
                ->delete();
        })->dailyAt('02:00')->name('purge-expired-password-tokens')->withoutOverlapping();

        // Daily: purge expired game invitations
        $schedule->call(function () {
            \DB::table('game_invitations')
                ->where('status', 'expired')
                ->where('created_at', '<', now()->subDays(30))
                ->delete();
        })->dailyAt('02:15')->name('purge-expired-game-invitations')->withoutOverlapping();

        // Queue worker — runs every minute, processes pending jobs then exits (P7-02)
        $schedule->command('queue:work database --sleep=3 --tries=3 --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/queue-worker.log'));

        // ──────────────────────────────────────────────────────────────────
        // DB CLEANUP JOBS — keep the database lean (P6-03/05/06/07/08/10-14)
        // ──────────────────────────────────────────────────────────────────

        // Every 6 hours: NULL out raw content on successfully-processed crawler pages
        // page_content / notes / muno_message are large TEXT blobs no longer needed
        $schedule->call(function () {
            \DB::table('movie_crawler_pages')
                ->where('status', 'success')
                ->where(function ($q) {
                    $q->whereNotNull('page_content')
                      ->orWhereNotNull('notes')
                      ->orWhereNotNull('muno_message');
                })
                ->update([
                    'page_content'  => null,
                    'notes'         => null,
                    'muno_message'  => null,
                ]);
            // Also clear old error pages that haven't retried in 7+ days
            \DB::table('movie_crawler_pages')
                ->where('status', 'error')
                ->where('updated_at', '<', now()->subDays(7))
                ->whereNotNull('page_content')
                ->update(['page_content' => null]);
        })->everySixHours()->name('clear-crawler-page-raw-content')->withoutOverlapping();

        // Daily at 03:00: NULL out muno_message on movie_models (debug/processing data)
        $schedule->call(function () {
            \DB::table('movie_models')
                ->whereNotNull('muno_message')
                ->where('updated_at', '<', now()->subDay())
                ->update(['muno_message' => null]);
        })->dailyAt('03:00')->name('clear-movie-muno-message')->withoutOverlapping();

        // Weekly: NULL out response_data on old crawler website fetches (P6-05)
        $schedule->call(function () {
            \DB::table('movie_crawler_websites')
                ->whereNotNull('response_data')
                ->where('updated_at', '<', now()->subDays(7))
                ->update(['response_data' => null]);
        })->weeklyOn(0, '03:30')->name('clear-crawler-website-response-data')->withoutOverlapping();

        // Monthly: NULL out old subscription transaction payloads >6 months (P6-06/07)
        $schedule->call(function () {
            \DB::table('subscription_transactions')
                ->where('created_at', '<', now()->subMonths(6))
                ->where(function ($q) {
                    $q->whereNotNull('request_payload')
                      ->orWhereNotNull('response_payload');
                })
                ->update(['request_payload' => null, 'response_payload' => null]);
        })->monthlyOn(1, '04:00')->name('clear-old-transaction-payloads')->withoutOverlapping();

        // Daily at 02:30: Purge completed/expired/cancelled game sessions >30 days (P6-11)
        $schedule->call(function () {
            \DB::table('game_sessions')
                ->whereIn('status', ['completed', 'expired', 'cancelled'])
                ->where('updated_at', '<', now()->subDays(30))
                ->delete();
        })->dailyAt('02:30')->name('purge-old-game-sessions')->withoutOverlapping();

        // Daily at 02:35: Purge completed/expired/cancelled ludo sessions >30 days (P6-12)
        $schedule->call(function () {
            \DB::table('ludo_sessions')
                ->whereIn('status', ['completed', 'expired', 'cancelled'])
                ->where('updated_at', '<', now()->subDays(30))
                ->delete();
        })->dailyAt('02:35')->name('purge-old-ludo-sessions')->withoutOverlapping();

        // Daily at 02:40: Purge completed/expired/cancelled checkers sessions >30 days (P6-13)
        $schedule->call(function () {
            \DB::table('checkers_sessions')
                ->whereIn('status', ['completed', 'expired', 'cancelled'])
                ->where('updated_at', '<', now()->subDays(30))
                ->delete();
        })->dailyAt('02:40')->name('purge-old-checkers-sessions')->withoutOverlapping();

        // Weekly: Purge trending_notifications older than 30 days (P6-14)
        $schedule->call(function () {
            \DB::table('trending_notifications')
                ->where('created_at', '<', now()->subDays(30))
                ->delete();
        })->weeklyOn(0, '04:30')->name('purge-old-trending-notifications')->withoutOverlapping();

        // Weekly: Force-delete soft-deleted content_reports older than 1 year (P6-08)
        $schedule->call(function () {
            \DB::table('content_reports')
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', now()->subYear())
                ->delete();
        })->weeklyOn(0, '04:45')->name('purge-old-content-reports')->withoutOverlapping();

        // ──────────────────────────────────────────────────────────────────
        // VIDEO PLAYBACK FAILURE CLEANUP (P6-01 / P6-02)
        // ──────────────────────────────────────────────────────────────────

        // Weekly: Delete resolved video_playback_failures older than 3 months (P6-01)
        $schedule->call(function () {
            \DB::table('video_playback_failures')
                ->where('status', 'resolved')
                ->where('created_at', '<', now()->subMonths(3))
                ->delete();
        })->weeklyOn(0, '05:00')->name('purge-resolved-vpf')->withoutOverlapping();

        // Weekly: Delete ignored video_playback_failures older than 1 month (P6-02)
        $schedule->call(function () {
            \DB::table('video_playback_failures')
                ->where('status', 'ignored')
                ->where('created_at', '<', now()->subMonth())
                ->delete();
        })->weeklyOn(0, '05:05')->name('purge-ignored-vpf')->withoutOverlapping();

        // ──────────────────────────────────────────────────────────────────
        // SOFT-DELETE PURGE (P6-09 / P7-11)
        // ──────────────────────────────────────────────────────────────────

        // Monthly: Force-delete soft-deleted user_blocks older than 1 year (P6-09 / P7-11)
        $schedule->call(function () {
            \DB::table('user_blocks')
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', now()->subYear())
                ->delete();
        })->monthlyOn(2, '05:00')->name('purge-old-user-blocks')->withoutOverlapping();

        // ──────────────────────────────────────────────────────────────────
        // DENORMALIZED COUNT SYNC (P7-08)
        // Recalculates views_count / downloads_count / likes_count on movie_models
        // from the real source tables — keeps displayed counts accurate even if
        // individual increment/decrement calls get missed.
        // ──────────────────────────────────────────────────────────────────

        $schedule->call(function () {
            \DB::statement("
                UPDATE movie_models mm
                LEFT JOIN (
                    SELECT movie_model_id, COUNT(*) AS cnt
                    FROM movie_views
                    GROUP BY movie_model_id
                ) v ON v.movie_model_id = mm.id
                LEFT JOIN (
                    SELECT movie_model_id, COUNT(*) AS cnt
                    FROM movie_downloads
                    GROUP BY movie_model_id
                ) d ON d.movie_model_id = mm.id
                LEFT JOIN (
                    SELECT movie_model_id, COUNT(*) AS cnt
                    FROM movie_likes
                    GROUP BY movie_model_id
                ) l ON l.movie_model_id = mm.id
                SET
                    mm.views_count     = COALESCE(v.cnt, 0),
                    mm.downloads_count = COALESCE(d.cnt, 0),
                    mm.likes_count     = COALESCE(l.cnt, 0)
            ");
        })->weeklyOn(0, '05:30')->name('sync-movie-counts')->withoutOverlapping();

        // ──────────────────────────────────────────────────────────────────
        // MONTHLY DATA ARCHIVAL (P6-20 / P7-10)
        // Move movie_views, movie_downloads, chat_messages older than 6 months
        // into archive_* tables, then delete the originals.
        // Runs on the 3rd of each month at 02:00 AM.
        // ──────────────────────────────────────────────────────────────────

        $schedule->call(function () {
            $cutoff = now()->subMonths(6)->toDateTimeString();

            // ── archive_movie_views ──────────────────────────────────────
            \DB::statement("
                INSERT INTO archive_movie_views
                    (created_at, updated_at, archived_at,
                     movie_model_id, user_id, ip_address, device, platform,
                     browser, country, city, status, progress, max_progress)
                SELECT created_at, updated_at, NOW(),
                       movie_model_id, user_id, ip_address, device, platform,
                       browser, country, city, status, progress, max_progress
                FROM movie_views
                WHERE created_at < ?
            ", [$cutoff]);

            \DB::table('movie_views')->where('created_at', '<', $cutoff)->delete();

            // ── archive_movie_downloads ──────────────────────────────────
            \DB::statement("
                INSERT INTO archive_movie_downloads
                    (created_at, updated_at, archived_at,
                     local_id, user_id, movie_model_id, status, error_message,
                     local_video_link, download_started_at, download_completed_at,
                     download_duration, title, url, image_url, genre, vj,
                     is_premium, episode_number)
                SELECT created_at, updated_at, NOW(),
                       local_id, user_id, movie_model_id, status, error_message,
                       local_video_link, download_started_at, download_completed_at,
                       download_duration, title, url, image_url, genre, vj,
                       is_premium, episode_number
                FROM movie_downloads
                WHERE created_at < ?
            ", [$cutoff]);

            \DB::table('movie_downloads')->where('created_at', '<', $cutoff)->delete();

            // ── archive_chat_messages ────────────────────────────────────
            \DB::statement("
                INSERT INTO archive_chat_messages
                    (created_at, updated_at, archived_at,
                     chat_head_id, sender_id, receiver_id, sender_name,
                     receiver_name, body, type, audio_url, audio_duration, status)
                SELECT created_at, updated_at, NOW(),
                       chat_head_id, sender_id, receiver_id, sender_name,
                       receiver_name, body, type, audio_url, audio_duration, status
                FROM chat_messages
                WHERE created_at < ?
            ", [$cutoff]);

            \DB::table('chat_messages')->where('created_at', '<', $cutoff)->delete();

        })->monthlyOn(3, '02:00')->name('archive-old-records')->withoutOverlapping();

        // ──────────────────────────────────────────────────────────────────
        // OPTIMIZE + ANALYZE TABLES (P6-24..P6-28)
        // Reclaims fragmented space after deletes/archival; refreshes optimizer stats.
        // Runs Mondays at 06:00 AM.
        // ──────────────────────────────────────────────────────────────────

        $schedule->call(function () {
            $tables = [
                'movie_models', 'movie_views', 'movie_downloads',
                'movie_crawler_pages', 'subscriptions', 'admin_users',
                'series_movies', 'movie_likes',
            ];
            foreach ($tables as $tbl) {
                try {
                    \DB::statement("OPTIMIZE TABLE `{$tbl}`");
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("[optimize-tables] {$tbl}: " . $e->getMessage());
                }
            }
            // ANALYZE refreshes index statistics so the query planner makes better choices
            \DB::statement('ANALYZE TABLE `' . implode('`, `', $tables) . '`');
        })->weeklyOn(1, '06:00')->name('optimize-analyze-tables')->withoutOverlapping();

        // Prune expired database cache entries daily at 03:00 AM.
        // Prevents the cache table from bloating (it grew to 212MB before this was added).
        $schedule->call(function () {
            \DB::table('cache')->where('expiration', '<', time())->delete();
        })->dailyAt('03:00')->name('prune-expired-cache')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
