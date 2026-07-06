<?php

namespace App\Services;

use App\Models\User;
use App\Models\TrendingNotification;
use App\Models\Utils;
use Berkayk\OneSignal\OneSignalFacade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Check if a user can receive a trending notification for the current time period
     *
     * @param User $user
     * @param string $dayTime (morning, afternoon, evening, night)
     * @return bool
     */
    public static function canReceiveTrendingNotification(User $user, string $dayTime): bool
    {
        $today = Carbon::today();
        
        // Check if user has notification preferences explicitly disabled
        // NULL or 'Yes' = enabled, only 'No' = disabled
        if ($user->push_notifications === 'No' || $user->notification_preferences === 'No') {
            return false;
        }
        
        // Reset daily counters if it's a new day
        if (!$user->last_trending_notification_date || 
            !Carbon::parse($user->last_trending_notification_date)->isSameDay($today)) {
            
            // Use direct DB update to avoid model validation issues
            DB::table('admin_users')
                ->where('id', $user->id)
                ->update([
                    'trending_notifications_today' => 0,
                    'last_trending_notification_date' => $today->format('Y-m-d'),
                    'updated_at' => Carbon::now()
                ]);
                
            // Refresh the model with updated values
            $user->trending_notifications_today = 0;
            $user->last_trending_notification_date = $today->format('Y-m-d');
        }
        
        // Max 2 trending notifications per user per day — stay meaningful, avoid spam
        $maxDaily = min((int) ($user->max_trending_notifications_per_day ?? 2), 2);
        if ((int) $user->trending_notifications_today >= $maxDaily) {
            return false;
        }

        // One notification per time period per day (morning / afternoon / evening / night)
        if ($user->last_trending_notification_period === $dayTime &&
            $user->last_trending_notification_date &&
            Carbon::parse($user->last_trending_notification_date)->isSameDay($today)) {
            return false;
        }

        // Minimum 4-hour gap between any two notifications to the same user
        if ($user->last_trending_notification_sent) {
            $lastSent        = Carbon::parse($user->last_trending_notification_sent);
            $minimumGapHours = 4;
            if ($lastSent->diffInHours(Carbon::now()) < $minimumGapHours) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Send trending notification to eligible users only
     *
     * @param array $notificationData
     * @param string $dayTime
     * @return array
     */
    public static function sendTrendingNotificationToEligibleUsers(array $notificationData, string $dayTime): array
    {
        $results = [
            'total_users' => 0,
            'eligible_users' => 0,
            'notifications_sent' => 0,
            'skipped_users' => 0,
            'errors' => 0,
            'start_time' => Carbon::now(),
        ];
        
        try {
            // Fetch only columns needed for notification eligibility checks — avoids loading
            // all 87k+ user rows with avatar blobs and other heavy fields into memory
            $notifColumns = [
                'id', 'email', 'push_notifications', 'notification_preferences',
                'last_trending_notification_date', 'trending_notifications_today',
                'max_trending_notifications_per_day', 'last_trending_notification_period',
                'last_trending_notification_sent',
            ];
            $users = User::where('status', 'Active')
                ->orWhereNull('status')
                ->get($notifColumns);
            
            $results['total_users'] = $users->count();
            
            $eligibleUsers = [];
            $skippedReasons = [];
            
            foreach ($users as $user) {
                if (self::canReceiveTrendingNotification($user, $dayTime)) {
                    $eligibleUsers[] = $user;
                    $results['eligible_users']++;
                } else {
                    $results['skipped_users']++;
                    
                    // Track reasons for skipping (for debugging)
                    $reason = self::getSkipReason($user, $dayTime);
                    $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;
                }
            }
            
            // If no eligible users, return early
            if (empty($eligibleUsers)) {
                $results['message'] = 'No eligible users found for trending notification';
                $results['skipped_reasons'] = $skippedReasons;
                return $results;
            }
            
            // Send notifications to eligible users only
            foreach ($eligibleUsers as $user) {
                try {
                    // Send individual notification
                    static::sendToUser($user, $notificationData);
                    
                    // Update user's notification tracking using direct DB update
                    DB::table('admin_users')
                        ->where('id', $user->id)
                        ->update([
                            'last_trending_notification_sent' => Carbon::now(),
                            'last_trending_notification_period' => $dayTime,
                            'last_trending_notification_date' => Carbon::today()->format('Y-m-d'),
                            'trending_notifications_today' => DB::raw('COALESCE(trending_notifications_today, 0) + 1'),
                            'updated_at' => Carbon::now()
                        ]);
                    
                    $results['notifications_sent']++;
                    
                    Log::info('Trending notification sent to user', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'day_time' => $dayTime,
                        'movie_title' => $notificationData['title'] ?? 'Unknown'
                    ]);
                    
                } catch (\Exception $e) {
                    $results['errors']++;
                    Log::error('Failed to send trending notification to user', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'day_time' => $dayTime
                    ]);
                }
            }
            
            $results['end_time'] = Carbon::now();
            $results['duration_seconds'] = $results['start_time']->diffInSeconds($results['end_time']);
            $results['skipped_reasons'] = $skippedReasons;
            $results['success'] = true;
            
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            Log::error('Error in sendTrendingNotificationToEligibleUsers', [
                'error' => $e->getMessage(),
                'day_time' => $dayTime
            ]);
        }
        
        return $results;
    }
    
    /**
     * Get the reason why a user was skipped for notification
     *
     * @param User $user
     * @param string $dayTime
     * @return string
     */
    private static function getSkipReason(User $user, string $dayTime): string
    {
        if ($user->push_notifications === 'No') {
            return 'push_notifications_disabled';
        }
        
        if ($user->notification_preferences === 'No') {
            return 'notification_preferences_disabled';
        }
        
        $today = Carbon::today();
        
        if ($user->trending_notifications_today >= $user->max_trending_notifications_per_day) {
            return 'daily_limit_reached';
        }
        
        if ($user->last_trending_notification_period === $dayTime && 
            $user->last_trending_notification_date && 
            Carbon::parse($user->last_trending_notification_date)->isSameDay($today)) {
            return 'already_notified_this_period';
        }
        
        if ($user->last_trending_notification_sent) {
            $lastNotificationTime = Carbon::parse($user->last_trending_notification_sent);
            if ($lastNotificationTime->diffInHours(Carbon::now()) < 4) {
                return 'minimum_time_gap_not_met';
            }
        }
        
        return 'unknown_reason';
    }
    
    /**
     * Reset daily notification counters for all users (to be run at midnight)
     */
    public static function resetDailyNotificationCounters(): void
    {
        try {
            DB::table('admin_users')
                ->whereDate('last_trending_notification_date', '<', Carbon::today())
                ->update([
                    'trending_notifications_today' => 0,
                    'last_trending_notification_date' => Carbon::today()
                ]);
                
            Log::info('Daily notification counters reset successfully');
        } catch (\Exception $e) {
            Log::error('Failed to reset daily notification counters: ' . $e->getMessage());
        }
    }
    
    /**
     * Get notification statistics for monitoring
     *
     * @return array
     */
    public static function getNotificationStats(): array
    {
        $today = Carbon::today();
        
        return [
            'total_users' => User::count(),
            'users_with_notifications_enabled' => User::where(function($query) {
                // NULL or not 'No' = enabled, only 'No' = disabled
                $query->where(function($subQuery) {
                    $subQuery->where('push_notifications', '!=', 'No')
                             ->orWhereNull('push_notifications');
                })->where(function($subQuery) {
                    $subQuery->where('notification_preferences', '!=', 'No')
                             ->orWhereNull('notification_preferences');
                });
            })->count(),
            'users_notified_today' => User::whereDate('last_trending_notification_date', $today)
                ->where('trending_notifications_today', '>', 0)
                ->count(),
            'total_notifications_sent_today' => User::whereDate('last_trending_notification_date', $today)
                ->sum('trending_notifications_today'),
            'users_at_daily_limit' => User::whereDate('last_trending_notification_date', $today)
                ->whereRaw('trending_notifications_today >= COALESCE(max_trending_notifications_per_day, 4)')
                ->count(),
            'users_with_push_disabled' => User::where('push_notifications', 'No')->count(),
            'users_with_preferences_disabled' => User::where('notification_preferences', 'No')->count(),
        ];
    }

    /**
     * Send a OneSignal push notification to a single user.
     *
     * @param  \App\Models\User  $user
     * @param  array{title?:string, body:string, image?:string, data?:array}  $params
     */
    public static function sendToUser($user, array $params): void
    {
        $body  = $params['body']  ?? null;
        $title = $params['title'] ?? null;
        $img   = $params['image'] ?? null;
        $data  = $params['data']  ?? [];

        if ($body === null) {
            throw new \Exception('Notification body is required.');
        }

        OneSignalFacade::addParams([
            'android_channel_id' => '63c01a80-0acd-467b-bdc7-7a1107141a8b',
            'large_icon'         => $img,
            'big_picture'        => $img,
            'chrome_big_picture' => $img,
            'chrome_web_image'   => $img,
            'small_icon'         => 'logo',
        ])->sendNotificationToExternalUser(
            $body,
            'my-id-' . $user->id,
            null,
            $data,
            null,
            null,
            $title,
        );
    }

    /**
     * Broadcast a OneSignal push notification to all subscribed users.
     *
     * @param  array{title?:string, body:string, image?:string, data?:array, buttons?:array, schedule?:string}  $params
     */
    public static function sendToAll(array $params): void
    {
        $body     = $params['body']     ?? null;
        $title    = $params['title']    ?? null;
        $img      = $params['image']    ?? null;
        $data     = $params['data']     ?? [];
        $buttons  = $params['buttons']  ?? [];
        $schedule = $params['schedule'] ?? null;

        if ($body === null) {
            throw new \Exception('Notification body is required.');
        }

        OneSignalFacade::addParams([
            'android_channel_id' => '63c01a80-0acd-467b-bdc7-7a1107141a8b',
            'large_icon'         => $img,
            'big_picture'        => $img,
            'chrome_big_picture' => $img,
            'chrome_web_image'   => $img,
            'small_icon'         => 'logo',
        ])->sendNotificationToAll(
            $body,
            null,
            $data,
            $buttons,
            $schedule,
            $title,
        );
    }
}