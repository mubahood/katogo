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
     * Get the truly trending movie for the current time period.
     *
     * Selection priority:
     *  1. Real recent plays (last 30 days) — ranked by unique viewers × 5 + play count × 2
     *  2. All-time views_time_count for movies not recently notified
     *  3. Any active movie (last resort if all have been notified recently)
     *
     * A notification is queued for unsent trending entries before returning.
     */
    public static function getTrendingMovie()
    {
        // Notification sending is expensive (iterates 87k+ users) — do NOT call from
        // a manifest HTTP request. The scheduled cron handles this instead.
        // self::sendTrendingNotification();

        $now      = Carbon::now();
        $hour     = (int) $now->format('H');
        $day_time = match (true) {
            $hour >= 5  && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 17 => 'afternoon',
            $hour >= 17 && $hour < 21 => 'evening',
            default                   => 'night',
        };

        // Return today's already-picked movie for this time slot (avoid churn)
        $movie   = null;
        $midnight = Carbon::now()->startOfDay();
        $existing = TrendingNotification::where('day_time', $day_time)
            ->where('created_at', '>=', $midnight)
            ->orderBy('id', 'desc')
            ->first();
        if ($existing) {
            $movie = MovieModel::where('id', $existing->movie_model_id)->where('status', 'Active')->first();
        }

        if ($movie) {
            return $movie;
        }

        // IDs notified in the last 5 days — avoid repeating the same movie too soon
        $recentlyNotified = TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(5))
            ->where('is_sent', 'Yes')
            ->pluck('movie_model_id')
            ->unique()
            ->toArray();

        // ── Primary: movies with real recent plays ───────────────────────────
        $recentWindow = Carbon::now()->subDays(30);
        $candidates = DB::table('movie_views as mv')
            ->join('movie_models as m', 'm.id', '=', 'mv.movie_model_id')
            ->where('mv.created_at', '>=', $recentWindow)
            ->where('m.status', 'Active')
            ->where('m.type', 'Movie')
            ->when(!empty($recentlyNotified), fn ($q) => $q->whereNotIn('mv.movie_model_id', $recentlyNotified))
            ->selectRaw('mv.movie_model_id, COUNT(DISTINCT mv.user_id) as unique_viewers, COUNT(*) as play_count, SUM(mv.progress) as total_secs')
            ->groupBy('mv.movie_model_id')
            ->having('play_count', '>=', 2)
            ->orderByRaw('(COUNT(DISTINCT mv.user_id) * 5 + COUNT(*) * 2 + SUM(mv.progress) / 3600) DESC')
            ->limit(20)
            ->get();

        foreach ($candidates as $row) {
            $candidate = MovieModel::where('id', $row->movie_model_id)
                ->where('status', 'Active')->where('type', 'Movie')->first();
            if ($candidate) {
                $movie = $candidate;
                Log::info("Trending: picked from recent plays — {$movie->title} (plays={$row->play_count}, viewers={$row->unique_viewers})");
                break;
            }
        }

        // ── Fallback: all-time views_time_count, not recently notified ────────
        if (!$movie) {
            $query = MovieModel::where('type', 'Movie')
                ->where('status', 'Active')
                ->where('views_time_count', '>=', 1800); // min 30 min total watch time
            if (!empty($recentlyNotified)) {
                $query->whereNotIn('id', $recentlyNotified);
            }
            $movie = $query->orderBy('views_time_count', 'desc')->first();
            if ($movie) {
                Log::info("Trending: fallback to all-time views — {$movie->title}");
            }
        }

        // ── Last resort: any active movie at all ─────────────────────────────
        if (!$movie) {
            $movie = MovieModel::where('type', 'Movie')->where('status', 'Active')
                ->orderBy('views_time_count', 'desc')->first();
            if ($movie) {
                Log::warning("Trending: last-resort pick (all recently notified) — {$movie->title}");
            }
        }

        if (!$movie) {
            Log::error('Trending: no active movie found at all.');
            return null;
        }

        // Queue a trending notification entry for this time slot
        $trending                   = new TrendingNotification();
        $trending->day_time         = $day_time;
        $trending->movie_model_id   = $movie->id;
        $trending->is_sent          = 'No';
        $trending->title            = $movie->title;
        $trending->type             = $movie->type;
        $trending->image_url        = $movie->thumbnail_url;
        $trending->description      = self::buildNotificationBody($movie->title, $day_time);
        $trending->views_count      = $movie->views_count ?? 0;
        $trending->views_time       = $movie->views_time_count ?? 0;
        $trending->trending_time    = Carbon::now();
        $trending->save();

        $movie->is_trending    = 'Yes';
        $movie->trending_time  = Carbon::now();
        $movie->trending_id    = $trending->id;
        $movie->save();

        return $movie;
    }

    /**
     * Build a concise, professional push notification body for a trending movie.
     */
    private static function buildNotificationBody(string $title, string $dayTime): string
    {
        $greetings = [
            'morning'   => ['Start your morning with', 'Good morning! People are watching'],
            'afternoon' => ['Trending this afternoon:', 'Top pick right now:'],
            'evening'   => ['Perfect for tonight:', 'Good evening! Viewers are loving'],
            'night'     => ['Late-night favourite:', 'People are watching right now:'],
        ];
        $openers = $greetings[$dayTime] ?? ['Now trending:'];
        $opener  = $openers[array_rand($openers)];
        return "{$opener} \"{$title}\" — watch it now on LugaFlix.";
    }

    /**
     * Send the pending trending notification to eligible users (respects per-user limits).
     * Does NOT blast to all — uses NotificationService rate limiting.
     */
    private static function sendTrendingNotification(): void
    {
        $today             = Carbon::now()->startOfDay();
        $pending           = TrendingNotification::where('is_sent', 'No')
            ->where('created_at', '>=', $today)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$pending) {
            return;
        }

        $hour     = (int) Carbon::now()->format('H');
        $day_time = match (true) {
            $hour >= 5  && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 17 => 'afternoon',
            $hour >= 17 && $hour < 21 => 'evening',
            default                   => 'night',
        };

        try {
            NotificationService::sendTrendingNotificationToEligibleUsers([
                'title' => '🎬 ' . $pending->title . ' is Trending',
                'body'  => $pending->description,
                'image' => $pending->image_url,
                'data'  => [
                    'movie_id'          => $pending->movie_model_id,
                    'type'              => $pending->type,
                    'notification_type' => 'trending_notification',
                ],
            ], $day_time);

            $pending->is_sent    = 'Yes';
            $pending->sent_time  = Carbon::now();
            $pending->save();
        } catch (\Throwable $e) {
            Log::error('TrendingNotification: send failed — ' . $e->getMessage());
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
