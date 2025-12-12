<?php

namespace App\Models;

use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
    public static function getTrendingMovie()
    {


        self::sendTrendingNotification();

        $now = Carbon::now();
        //time of day
        $hour = (int) $now->format('H');
        $day_time = '';
        if ($hour >= 5 && $hour < 12) {
            $day_time = 'morning';
        } elseif ($hour >= 12 && $hour < 17) {
            $day_time = 'afternoon';
        } elseif ($hour >= 17 && $hour < 21) {
            $day_time = 'evening';
        } else {
            $day_time = 'night';
        }

        $movie = null;
        $midnight = Carbon::now()->startOfDay();
        $trending = TrendingNotification::where([
            'day_time' => $day_time,
            ['created_at', '>=', $midnight]
        ])
            ->first();
        if ($trending != null) {
            $movie = MovieModel::find($trending->movie_model_id);
        }

        $ninty_days_ago = Carbon::now()->subDays(90);

        if ($movie == null) {

            // FIXED: Get IDs of movies that have been sent as trending notifications in the last 7 days
            // This prevents the same movie from being sent repeatedly
            $recently_notified_movie_ids = TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(7))
                ->where('is_sent', 'Yes')
                ->pluck('movie_model_id')
                ->unique()
                ->toArray();

            Log::info('Recently notified movies in last 7 days: ' . count($recently_notified_movie_ids));

            // Get movies that are currently trending (from last 90 days)
            $ids_of_trendings = MovieModel::where('created_at', '>=', $ninty_days_ago)
                ->where('status', 'Active')
                ->where('type', 'Movie')
                ->where('is_trending', 'Yes')
                ->pluck('id')
                ->toArray();

            //update movies that have not trended in last 90 days to not trending
            $sql = DB::table('movie_models')
                ->where('created_at', '>=', $ninty_days_ago)
                ->where('status', 'Active')
                ->where('type', 'Movie')
                ->where('is_trending', 'Yes');

            if (count($ids_of_trendings) > 0) {
                $sql->whereNotIn('id', $ids_of_trendings);
            }
            $sql->update(['is_trending' => 'No']);


            // FIXED: Exclude recently notified movies from trending selection
            $recent_movie_views = MovieView::where('created_at', '>=', $ninty_days_ago)
                ->selectRaw('movie_model_id, SUM(progress) as total_watch_time')
                ->groupBy('movie_model_id')
                ->orderByDesc('total_watch_time')
                ->pluck('movie_model_id')
                ->toArray();
            
            foreach ($recent_movie_views as $recent_movie_view) {
                // FIXED: Skip movies that have been notified recently (last 7 days)
                if (in_array($recent_movie_view, $recently_notified_movie_ids)) {
                    Log::info("Skipping recently notified movie ID: {$recent_movie_view}");
                    continue;
                }

                $movie = MovieModel::find($recent_movie_view);
                if ($movie == null) {
                    continue;
                }
                if ($movie->type != 'Movie') {
                    continue;
                }
                if ($movie->status != 'Active') {
                    continue;
                }

                Log::info("Creating trending notification for movie: {$movie->title} (ID: {$movie->id})");

                $trending = new TrendingNotification();
                $trending->day_time = $day_time;
                $trending->movie_model_id = $movie->id;
                $trending->is_sent = 'No';
                $trending->title = $movie->title;
                $trending->type = $movie->type;
                $trending->image_url = $movie->thumbnail_url;
                $trending->description = $day_time . ' trending movie is ' . $movie->title . "! Watch now!";
                $trending->views_count = $movie->views_count;
                $trending->views_time = $movie->views_time_count;
                $trending->trending_time = Carbon::now();
                $trending->save();
                $movie->is_trending = 'Yes';
                $movie->trending_time = Carbon::now();
                $movie->trending_id = $trending->id;
                $movie->save();
                break;
            }
        }

        // FIXED: If still no movie found, get one that hasn't been notified in last 7 days
        if ($movie == null) {
            $recently_notified_movie_ids = TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(7))
                ->where('is_sent', 'Yes')
                ->pluck('movie_model_id')
                ->unique()
                ->toArray();

            $minViewTime = 30 * 60; // 30 minutes minimum watch time
            
            $query = MovieModel::where('type', 'Movie')
                ->where('status', 'Active')
                ->where('views_time_count', '>=', $minViewTime);
            
            if (count($recently_notified_movie_ids) > 0) {
                $query->whereNotIn('id', $recently_notified_movie_ids);
            }
            
            $movie = $query->orderBy('views_time_count', 'desc')->first();
            
            if ($movie) {
                Log::info("Found fallback movie (not recently notified): {$movie->title} (ID: {$movie->id})");
            }
        }
        
        // FIXED: Second fallback - any active movie not notified recently
        if ($movie == null) {
            $recently_notified_movie_ids = TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(7))
                ->where('is_sent', 'Yes')
                ->pluck('movie_model_id')
                ->unique()
                ->toArray();

            $minViewTime = 30 * 60; // 30 minutes minimum watch time
            
            $query = MovieModel::where('type', 'Movie')
                ->where('status', 'Active')
                ->where('views_time_count', '>=', $minViewTime);
            
            if (count($recently_notified_movie_ids) > 0) {
                $query->whereNotIn('id', $recently_notified_movie_ids);
            }
            
            $movie = $query->orderBy('views_time_count', 'desc')->first();
        }
        
        // FIXED: Final fallback - any active movie at all (if all have been notified)
        if ($movie == null) {
            Log::warning("All movies have been notified recently, selecting based on view time only");
            $movie = MovieModel::where('type', 'Movie')
                ->where('status', 'Active')
                ->orderBy('views_time_count', 'desc')
                ->first();
        }

        if ($movie) {
            Log::info("Returning trending movie: {$movie->title} (ID: {$movie->id})");
        } else {
            Log::error("No trending movie found!");
        }

        return $movie;
    }

    /**
     * Send trending notification using the new NotificationService
     */
    private static function sendTrendingNotification()
    {
        //created today
        $today = Carbon::now()->startOfDay();
        $getLatestNoteSent = TrendingNotification::where('is_sent', 'No')
            ->where('created_at', '>=', $today)
            ->orderBy('created_at', 'desc')
            ->first();
        if ($getLatestNoteSent == null) {
            return null;
        }

        try {
            Utils::sendNotificationToAll([
                'title' => $getLatestNoteSent->title,
                'body' => $getLatestNoteSent->description,
                'image' => $getLatestNoteSent->image_url,
                'data' => [
                    'movie_id' => $getLatestNoteSent->movie_model_id,
                    'type' => $getLatestNoteSent->type,
                    'notification_type' => 'trending_notification',
                ],
            ]);
            $getLatestNoteSent->is_sent = 'Yes';
            $getLatestNoteSent->sent_time = Carbon::now();
            $getLatestNoteSent->save();
            return $getLatestNoteSent;
        } catch (\Throwable $th) {
            Log::error('Error sending trending notification: ' . $th->getMessage());
            throw $th;
        } catch (\Exception $e) {
            Log::error('Exception while sending trending notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find a suitable trending movie
     */
    private static function findTrendingMovie()
    {
        //return getTrendingMovie
        return self::getTrendingMovie();
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
