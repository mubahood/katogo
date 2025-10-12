<?php

namespace App\Models;

use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class TrendingNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_time',
        'movie_model_id',
        'title',
        'type',
        'image_url',
        'description',
        'views_count',
        'views_time',
        'url',
        'trending_time',
        'is_sent',
        'sent_time'
    ];

    /**
     * Get trending movie for current time period with improved duplicate prevention
     */
    public static function getTendingMovie()
    {
        $latest_movie = MovieModel::where('type', 'Movie')
            ->where('status', 'Active')
            ->orderBy('created_at', 'desc')
            ->first();
        return $latest_movie;

        $now = Carbon::now();
        $day_time = self::getCurrentDayTime($now);
        $today = Carbon::today();

        // Check if notification has already been sent for this time period today
        $existingTrending = TrendingNotification::whereDate('created_at', $today)
            ->where('day_time', $day_time)
            ->where('is_sent', 'Yes')
            ->first();

        if ($existingTrending) {
            Log::info('Trending notification already sent for this period', [
                'day_time' => $day_time,
                'date' => $today->toDateString(),
                'existing_id' => $existingTrending->id
            ]);
            return $existingTrending->movie;
        }

        // Get or create trending record for this time period
        $trending = TrendingNotification::whereDate('created_at', $today)
            ->where('day_time', $day_time)
            ->first();

        if (!$trending) {
            $trending = new TrendingNotification();
            $trending->day_time = $day_time;
            $trending->created_at = $now;
            $trending->updated_at = $now;
            $trending->is_sent = 'No';
            $trending->save();

            Log::info('Created new trending notification record', [
                'id' => $trending->id,
                'day_time' => $day_time,
                'date' => $today->toDateString()
            ]);
        }

        // If movie is not assigned, find and assign one
        if (!$trending->movie_model_id || !$trending->movie) {
            $movie = self::findTrendingMovie();

            if ($movie) {
                // Mark movie as trending
                $movie->is_trending = 'Yes';
                $movie->trending_time = $now;
                $movie->trending_id = $trending->id;
                $movie->save();

                // Update trending record
                $trending->movie_model_id = $movie->id;
                $trending->title = $movie->title;
                $trending->type = $movie->type;
                $trending->image_url = $movie->thumbnail_url;
                $trending->description = $movie->description;
                $trending->views_count = $movie->views_count;
                $trending->views_time = $movie->views_time_count;
                $trending->url = $movie->url;
                $trending->trending_time = $now;
                $trending->save();

                Log::info('Assigned movie to trending notification', [
                    'trending_id' => $trending->id,
                    'movie_id' => $movie->id,
                    'movie_title' => $movie->title
                ]);
            } else {
                Log::warning('No suitable movie found for trending notification', [
                    'day_time' => $day_time,
                    'date' => $today->toDateString()
                ]);
                return null;
            }
        }

        // Send notification if not already sent
        if ($trending->is_sent !== 'Yes' && $trending->movie) {
            return self::sendTrendingNotification($trending, $day_time);
        }

        return $trending->movie;
    }

    /**
     * Send trending notification using the new NotificationService
     */
    private static function sendTrendingNotification(TrendingNotification $trending, string $dayTime)
    {
        try {
            $notificationData = [
                'title' => 'UGFLIX ' . ucfirst($dayTime) . ' Trending Movie - ' . $trending->title,
                'body' => 'Watch the trending movie this ' . ucfirst($dayTime) . ': "' . $trending->title . '"! Don\'t miss out on the excitement!',
                'image' => $trending->image_url,
                'url' => $trending->url,
                'type' => $trending->type,
                'movie_id' => $trending->movie_model_id,
                'is_trending' => 'Yes',
                'data' => [
                    'movie_id' => $trending->movie_model_id,
                    'is_trending' => 'Yes',
                    'type' => $trending->type,
                    'url' => $trending->url,
                    'image_url' => $trending->image_url,
                ],
            ];

            // Use new NotificationService for rate-limited sending
            $results = NotificationService::sendTrendingNotificationToEligibleUsers($notificationData, $dayTime);

            // Mark as sent only if some notifications were successful
            if ($results['notifications_sent'] > 0) {
                $trending->is_sent = 'Yes';
                $trending->sent_time = Carbon::now();
                $trending->save();

                Log::info('Trending notification sent successfully', [
                    'trending_id' => $trending->id,
                    'movie_title' => $trending->title,
                    'day_time' => $dayTime,
                    'notifications_sent' => $results['notifications_sent'],
                    'eligible_users' => $results['eligible_users'],
                    'total_users' => $results['total_users']
                ]);
            } else {
                Log::warning('No notifications were sent', [
                    'trending_id' => $trending->id,
                    'results' => $results
                ]);
            }

            return $trending->movie;
        } catch (\Throwable $th) {
            Log::error('Error sending trending notification', [
                'trending_id' => $trending->id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            return $trending->movie;
        }
    }

    /**
     * Find a suitable trending movie
     */
    private static function findTrendingMovie()
    {
        $minViewTime = 30 * 60; // 30 minutes minimum watch time

        // Try to find a movie that hasn't been trending recently
        $movie = MovieModel::where('is_trending', '!=', 'Yes')
            ->where('type', 'Movie')
            ->where('status', 'Active')
            ->where('views_time_count', '>=', $minViewTime)
            ->orderBy('views_time_count', 'desc')
            ->first();

        if (!$movie) {
            // Reset all trending flags and try again
            MovieModel::where('is_trending', 'Yes')->update(['is_trending' => 'No']);

            $movie = MovieModel::where('is_trending', '!=', 'Yes')
                ->where('views_time_count', '>=', $minViewTime)
                ->where('type', 'Movie')
                ->where('status', 'Active')
                ->orderBy('views_time_count', 'desc')
                ->first();

            if (!$movie) {
                // Get any latest active movie if no movie meets view time criteria
                $movie = MovieModel::where('is_trending', '!=', 'Yes')
                    ->where('type', 'Movie')
                    ->where('status', 'Active')
                    ->orderBy('created_at', 'desc')
                    ->first();
            }
        }

        return $movie;
    }

    /**
     * Get current day time period
     */
    private static function getCurrentDayTime(Carbon $now): string
    {
        if ($now->hour >= 6 && $now->hour < 12) {
            return 'morning';
        } elseif ($now->hour >= 12 && $now->hour < 18) {
            return 'afternoon';
        } elseif ($now->hour >= 18 && $now->hour < 24) {
            return 'evening';
        } else {
            return 'night';
        }
    }

    /**
     * Belongs to movie relationship
     */
    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_model_id');
    }
}
