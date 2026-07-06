<?php

namespace App\Console\Commands;

use App\Models\TrendingNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendTrendingNotifications extends Command
{
    protected $signature   = 'trending:send-notifications {--force : Re-send even if this period was already sent today}';
    protected $description = 'Broadcast the trending push notification to all subscribed users';

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
        $force = $this->option('force');

        $this->info("Trending notifications — period: {$dayTime}" . ($force ? ' [--force]' : ''));

        // Guard: don't send the same time-period twice in one day unless --force.
        // Prevents duplicate blasts when the command is run manually alongside the cron.
        if (!$force) {
            $alreadySent = TrendingNotification::where('is_sent', 'Yes')
                ->where('day_time', $dayTime)
                ->where('created_at', '>=', Carbon::now()->startOfDay())
                ->exists();

            if ($alreadySent) {
                $this->info("Already sent for '{$dayTime}' today — skipping. Use --force to override.");
                return self::SUCCESS;
            }
        }

        // Pick up the most-recent unsent record created today.
        // If none exists yet, getTrendingMovie() picks a movie and queues one.
        $pending = TrendingNotification::where('is_sent', 'No')
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$pending) {
            $this->info('No pending record for today — calling getTrendingMovie().');
            TrendingNotification::getTrendingMovie();
            $pending = TrendingNotification::where('is_sent', 'No')
                ->where('created_at', '>=', Carbon::now()->startOfDay())
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // When --force is set, also accept an already-sent record from today
        // so we can re-broadcast without needing a fresh pending entry.
        if (!$pending && $force) {
            $pending = TrendingNotification::where('created_at', '>=', Carbon::now()->startOfDay())
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (!$pending) {
            $this->error('No trending record found for today and no active movie available.');
            return self::FAILURE;
        }

        // Validate content before hitting the API
        if (empty($pending->title)) {
            $this->error('Trending record has no title — aborting.');
            Log::error('trending:send-notifications: pending record missing title', ['id' => $pending->id]);
            return self::FAILURE;
        }
        if (empty($pending->description)) {
            $this->error('Trending record has no description — aborting.');
            Log::error('trending:send-notifications: pending record missing description', ['id' => $pending->id]);
            return self::FAILURE;
        }

        $this->info("Sending: \"{$pending->title}\" (record id={$pending->id}, movie_id={$pending->movie_model_id})");

        try {
            $onesignalId = NotificationService::sendToAll([
                'title' => '🎬 ' . $pending->title . ' is Trending',
                'body'  => $pending->description,
                'image' => $pending->image_url ?: null,
                'data'  => [
                    'movie_id'          => (string) $pending->movie_model_id,
                    'type'              => $pending->type ?? 'Movie',
                    'notification_type' => 'trending_notification',
                ],
            ]);

            // Mark as sent only after the API confirms acceptance with a notification ID.
            $pending->is_sent   = 'Yes';
            $pending->sent_time = Carbon::now();
            $pending->save();

            $this->info("Broadcast confirmed — OneSignal ID: {$onesignalId}");

            Log::info('trending:send-notifications: broadcast sent', [
                'day_time'     => $dayTime,
                'movie_id'     => $pending->movie_model_id,
                'movie_title'  => $pending->title,
                'record_id'    => $pending->id,
                'onesignal_id' => $onesignalId,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Broadcast failed: ' . $e->getMessage());
            Log::error('trending:send-notifications: broadcast failed', [
                'error'       => $e->getMessage(),
                'day_time'    => $dayTime,
                'record_id'   => $pending->id,
                'movie_title' => $pending->title,
            ]);
            // Leave is_sent=No so the next scheduled run retries automatically.
            return self::FAILURE;
        }
    }
}
