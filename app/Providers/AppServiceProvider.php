<?php

namespace App\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use App\Models\ChatMessage;
use App\Models\MovieDownload;
use App\Models\MovieModel;
use App\Observers\ChatMessageObserver;
use App\Observers\MovieDownloadObserver;
use App\Observers\MovieModelObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce Nairobi timezone (EAT / GMT+3) globally
        date_default_timezone_set('Africa/Nairobi');

        // ──────────────────────────────────────────────────────────────
        // MODEL OBSERVERS (P10-11)
        // ──────────────────────────────────────────────────────────────
        ChatMessage::observe(ChatMessageObserver::class);    // async push notifications (P10-10)
        MovieDownload::observe(MovieDownloadObserver::class); // increment downloads_count (P10-08)
        MovieModel::observe(MovieModelObserver::class);       // increment/decrement total_episodes (P10-09)

        // ──────────────────────────────────────────────────────────────
        // CLEANUP: Ensure all Movie-type records have null category_id
        // category_id is a FK to series_movies.id — only Series episodes
        // should have a value. Movies must NEVER have this set.
        // Runs once per day (cached) to avoid overhead on every request.
        // ──────────────────────────────────────────────────────────────
        Cache::remember('movie_category_id_cleanup_v1', 86400, function () {
            try {
                $affected = DB::update("
                    UPDATE movie_models 
                    SET category_id = NULL,
                        episode_number = NULL,
                        season_number = NULL,
                        series_title = NULL,
                        episode_title = NULL,
                        is_first_episode = NULL
                    WHERE type = 'Movie' 
                      AND (category_id IS NOT NULL 
                           OR episode_number IS NOT NULL 
                           OR season_number IS NOT NULL)
                ");
                if ($affected > 0) {
                    Log::info("[AppServiceProvider] Cleaned {$affected} Movie records that had stale series fields (category_id, episode_number, etc.)");
                }
            } catch (\Exception $e) {
                Log::warning("[AppServiceProvider] Movie cleanup failed: " . $e->getMessage());
            }
            return true;
        });
    }
}
