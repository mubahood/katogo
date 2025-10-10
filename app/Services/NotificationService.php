<?php

namespace App\Services;

use App\Models\User;
use App\Models\TrendingNotification;
use App\Models\Utils;
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
        
        // Check if user has reached daily limit (default is 4)
        $maxDaily = $user->max_trending_notifications_per_day ?? 4;
        if ($user->trending_notifications_today >= $maxDaily) {
            return false;
        }
        
        // Check if user already received notification for this time period today
        if ($user->last_trending_notification_period === $dayTime && 
            $user->last_trending_notification_date && 
            Carbon::parse($user->last_trending_notification_date)->isSameDay($today)) {
            return false;
        }
        
        // Check minimum time gap (at least 3 hours between notifications)
        if ($user->last_trending_notification_sent) {
            $lastNotificationTime = Carbon::parse($user->last_trending_notification_sent);
            $minimumGapHours = 3;
            
            if ($lastNotificationTime->diffInHours(Carbon::now()) < $minimumGapHours) {
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
            // Get all active users
            $users = User::where('status', 'Active')
                ->orWhereNull('status')
                ->get();
            
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
                    Utils::sendNotificationToUser($user, $notificationData);
                    
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
            if ($lastNotificationTime->diffInHours(Carbon::now()) < 3) {
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
}