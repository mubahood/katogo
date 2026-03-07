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
