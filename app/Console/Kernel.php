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

        // Check for expired subscriptions - runs daily at 1:00 AM
        $schedule->command('subscriptions:check-expired')
            ->dailyAt('01:00')
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
