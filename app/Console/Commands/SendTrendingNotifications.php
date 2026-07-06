<?php

namespace App\Console\Commands;

use App\Models\TrendingNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendTrendingNotifications extends Command
{
    protected $signature   = 'trending:send-notifications {--force : Send even if already sent today}';
    protected $description = 'Broadcast trending push notification to all subscribed users';

    public function handle(): int
    {
        $now     = Carbon::now();
        $hour    = (int) $now->format('H');
        $dayTime = match (true) {
            $hour >= 5  && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 17 => 'afternoon',
            $hour >= 17 && $hour < 21 => 'evening',
            default                   => 'night',
        };

        $this->info("Trending notifications — period: {$dayTime}");

        // Find unsent record for today, or create one via getTrendingMovie()
        $pending = TrendingNotification::where('is_sent', 'No')
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$pending) {
            $this->info('No pending record today — triggering getTrendingMovie().');
            TrendingNotification::getTrendingMovie();
            $pending = TrendingNotification::where('is_sent', 'No')
                ->where('created_at', '>=', Carbon::now()->startOfDay())
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (!$pending) {
            $this->warn('No active movie found. Aborting.');
            return self::FAILURE;
        }

        $this->info("Sending: \"{$pending->title}\" (id={$pending->id})");

        // Mark sent BEFORE the API call so a crash/timeout doesn't leave it as is_sent=No
        $pending->is_sent   = 'Yes';
        $pending->sent_time = Carbon::now();
        $pending->save();

        try {
            // Broadcast to all subscribed OneSignal devices via segment.
            // Per-user external-ID sends no longer work because the OneSignal Flutter SDK v5+
            // dropped setExternalUserId() in favour of a new aliases model — old external IDs
            // are all "unsubscribed" from the legacy player API perspective.
            NotificationService::sendToAll([
                'title' => '🎬 ' . $pending->title . ' is Trending',
                'body'  => $pending->description,
                'image' => $pending->image_url,
                'data'  => [
                    'movie_id'          => $pending->movie_model_id,
                    'type'              => $pending->type,
                    'notification_type' => 'trending_notification',
                ],
            ]);

            $this->info('Broadcast sent successfully.');

            Log::info('trending:send-notifications broadcast sent', [
                'day_time'    => $dayTime,
                'movie_id'    => $pending->movie_model_id,
                'movie_title' => $pending->title,
                'notif_id'    => $pending->id,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Send failed: ' . $e->getMessage());
            Log::error('trending:send-notifications failed', [
                'error'    => $e->getMessage(),
                'day_time' => $dayTime,
                'notif_id' => $pending->id,
            ]);
            return self::FAILURE;
        }
    }
}
