<?php

namespace App\Console\Commands;

use App\Models\TrendingNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendTrendingNotifications extends Command
{
    protected $signature   = 'trending:send-notifications {--force : Skip the already-sent guard and send pending ones again}';
    protected $description = 'Send pending trending push notifications to eligible users';

    public function handle(): int
    {
        $now      = Carbon::now();
        $hour     = (int) $now->format('H');
        $dayTime  = match (true) {
            $hour >= 5  && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 17 => 'afternoon',
            $hour >= 17 && $hour < 21 => 'evening',
            default                   => 'night',
        };

        $this->info("Trending notifications — period: {$dayTime}");

        // Pick up the most-recent unsent notification created today
        $pending = TrendingNotification::where('is_sent', 'No')
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$pending) {
            $this->info('No pending trending notifications for today. Triggering getTrendingMovie() to create one.');
            TrendingNotification::getTrendingMovie();

            // Re-query after creation
            $pending = TrendingNotification::where('is_sent', 'No')
                ->where('created_at', '>=', Carbon::now()->startOfDay())
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (!$pending) {
            $this->warn('No active movie found to notify about. Aborting.');
            return self::FAILURE;
        }

        $this->info("Sending: \"{$pending->title}\" (id={$pending->id})");

        // Mark sent optimistically before the loop so a timeout/kill doesn't leave it
        // as is_sent=No, causing every subsequent cron run to re-process all users.
        $pending->is_sent   = 'Yes';
        $pending->sent_time = Carbon::now();
        $pending->save();

        try {
            $results = NotificationService::sendTrendingNotificationToEligibleUsers([
                'title' => '🎬 ' . $pending->title . ' is Trending',
                'body'  => $pending->description,
                'image' => $pending->image_url,
                'data'  => [
                    'movie_id'          => $pending->movie_model_id,
                    'type'              => $pending->type,
                    'notification_type' => 'trending_notification',
                ],
            ], $dayTime);

            $sent    = $results['notifications_sent'] ?? 0;
            $skipped = $results['skipped_users']      ?? 0;
            $errors  = $results['errors']             ?? 0;

            $this->info("Done — sent: {$sent}, skipped: {$skipped}, errors: {$errors}");

            Log::info('trending:send-notifications completed', [
                'day_time'           => $dayTime,
                'movie_id'           => $pending->movie_model_id,
                'movie_title'        => $pending->title,
                'notifications_sent' => $sent,
                'skipped_users'      => $skipped,
                'errors'             => $errors,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Send failed: ' . $e->getMessage());
            Log::error('trending:send-notifications failed', [
                'error'    => $e->getMessage(),
                'day_time' => $dayTime,
            ]);
            return self::FAILURE;
        }
    }
}
