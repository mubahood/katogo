<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // API rate limit — 120 req/min per user (or per IP for guests)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Strict rate limit for auth endpoints — 10/min per IP (prevents brute-force)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Search endpoints — 30/min per user
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Higher rate limit for game endpoints (polling every 2 seconds)
        RateLimiter::for('game', function (Request $request) {
            $userKey = $request->user()?->id ?: $request->ip();
            return Limit::perMinute(300)->by($userKey);
        });

        // Specific throttle for video progress saves
        RateLimiter::for('video-progress', function (Request $request) {
            $userKey = $request->user()?->id ?: $request->ip();
            // Group by movie as well to allow separate limits per video
            $movieId = (string) ($request->input('movie_model_id') ?? 'unknown');
            return Limit::perMinute(30)->by($userKey . ':movie:' . $movieId);
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
