<?php

namespace App\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use App\Jobs\PushSyncEvent;
use App\Models\ChatMessage;
use App\Models\CoinTransaction;
use App\Models\MovieDownload;
use App\Models\MovieModel;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Observers\ChatMessageObserver;
use App\Observers\MovieDownloadObserver;
use App\Observers\MovieFileTransferObserver;
use App\Observers\MovieModelObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Explicitly bind the Spatie ResponseCache Serializer interface to its default
        // concrete implementation. This is a safety net for environments where the
        // config cache may be stale (bootstrap/cache/config.php not yet cleared).
        $this->app->bind(
            \Spatie\ResponseCache\Serializers\Serializer::class,
            \Spatie\ResponseCache\Serializers\DefaultSerializer::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS for all generated URLs when running behind Nginx SSL termination.
        // Nginx terminates SSL and forwards to PHP-FPM via FastCGI, so PHP never sees
        // the HTTPS connection directly. Without this, url() and asset() return http://.
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Enforce Nairobi timezone (EAT / GMT+3) globally
        date_default_timezone_set('Africa/Nairobi');

        // Debugbar JS can conflict with Laravel-Admin assets on admin pages.
        // Disable it for admin route prefix to avoid UI/runtime errors.
        if (!$this->app->runningInConsole()) {
            $adminPrefix = trim((string) config('admin.route.prefix', 'admin'), '/');
            if ($adminPrefix !== '' && request()->is($adminPrefix . '*') && $this->app->bound('debugbar')) {
                app('debugbar')->disable();
            }
        }

        // ──────────────────────────────────────────────────────────────
        // HEALTH CHECKS — accessible at GET /health
        // ──────────────────────────────────────────────────────────────
        Health::checks([
            DatabaseCheck::new(),
            EnvironmentCheck::new(),
            UsedDiskSpaceCheck::new()->warnWhenUsedSpaceIsAbovePercentage(75)->failWhenUsedSpaceIsAbovePercentage(90),
        ]);

        // ──────────────────────────────────────────────────────────────
        // MODEL OBSERVERS (P10-11)
        // ──────────────────────────────────────────────────────────────
        ChatMessage::observe(ChatMessageObserver::class);           // async push notifications (P10-10)
        MovieDownload::observe(MovieDownloadObserver::class);        // increment downloads_count (P10-08)
        MovieModel::observe(MovieModelObserver::class);              // increment/decrement total_episodes (P10-09)
        MovieModel::observe(MovieFileTransferObserver::class);       // auto-queue Hetzner transfer on new/changed video_url

        // ──────────────────────────────────────────────────────────────
        // REAL-TIME SYNC EVENT PUSH (Phase 4)
        // SOURCE server only (SYNC_ROLE=source in .env).
        // On every save, POST the row to the replica's receive-event API
        // so Tier-1 data (users, subscriptions, payments) arrives within
        // seconds instead of waiting for the next 5-minute pull cycle.
        // The SSH tunnel pull is the fallback safety net — no data is lost
        // if these pushes fail (they retry 3× before giving up).
        // ──────────────────────────────────────────────────────────────
        if (config('services.sync.role') === 'source'
            && !empty(config('services.sync.replica_url'))
            && config('services.sync.push_enabled')) {
            $pushRow = function (string $table, $model) use (&$pushRow): void {
                try {
                    $row = DB::table($table)->where('id', $model->id)->first();
                    if ($row) {
                        PushSyncEvent::dispatch($table, [(array) $row])
                            ->onQueue('default')
                            ->delay(now()->addSeconds(2));
                    }
                } catch (\Throwable $e) {
                    Log::warning("[SyncPush] Failed to dispatch push for {$table}#{$model->id}: {$e->getMessage()}");
                }
            };

            // admin_users is saved on EVERY manifest request (app_type/platform
            // bookkeeping) — pushing each save generated ~100k events/day and
            // repeatedly overwhelmed the replica (the Aug 2026 outage cascade).
            // Only push when something the replica actually needs has changed;
            // bookkeeping-only saves are covered by the 5-minute pull sync.
            $userBookkeepingOnly = function ($m): bool {
                $ignorable = [
                    'updated_at', 'app_type', 'platform', 'last_online_at',
                    'remember_token', 'token', 'last_seen',
                ];
                $meaningful = array_diff(array_keys($m->getChanges()), $ignorable);
                return empty($meaningful);
            };

            Subscription::saved(fn($m)            => $pushRow('subscriptions', $m));
            User::saved(function ($m) use ($pushRow, $userBookkeepingOnly) {
                if (!$userBookkeepingOnly($m)) {
                    $pushRow('admin_users', $m);
                }
            });
            SubscriptionTransaction::saved(fn($m) => $pushRow('subscription_transactions', $m));
            SubscriptionPlan::saved(fn($m)        => $pushRow('subscription_plans', $m));
            CoinTransaction::saved(fn($m)         => $pushRow('coin_transactions', $m));
        }

        // ──────────────────────────────────────────────────────────────
        // CLEANUP: Ensure all Movie-type records have null category_id
        // category_id is a FK to series_movies.id — only Series episodes
        // should have a value. Movies must NEVER have this set.
        // Runs once per day (cached) to avoid overhead on every request.
        // ──────────────────────────────────────────────────────────────
        $runMovieCleanup = function (): bool {
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
            } catch (\Throwable $e) {
                Log::warning("[AppServiceProvider] Movie cleanup failed: " . $e->getMessage());
            }

            return true;
        };

        try {
            // Avoid boot-time dependence on the database cache driver.
            $cacheStore = config('cache.default') === 'database' ? 'file' : (string) config('cache.default', 'file');
            Cache::store($cacheStore)->remember('movie_category_id_cleanup_v1', 86400, $runMovieCleanup);
        } catch (\Throwable $e) {
            Log::warning('[AppServiceProvider] Cleanup cache gate unavailable, running best-effort cleanup: ' . $e->getMessage());
            $runMovieCleanup();
        }
    }
}
