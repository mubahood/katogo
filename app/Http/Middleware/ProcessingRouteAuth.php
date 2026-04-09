<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects internal processing/admin web routes with a secret key.
 * Usage: Add ?key=YOUR_PROCESSING_KEY to the URL, or set X-Processing-Key header.
 * The key is read from .env PROCESSING_ROUTE_KEY.
 */
class ProcessingRouteAuth
{
    /**
     * Routes that must remain publicly accessible without processing key auth.
     */
    private array $publicRoutePatterns = [
        'munowatch-series-crawler',
        'munowatch-movies-crawler',
        'crawler',
        'process-muno-movies-pages',
        'process-series-new',
        'process-episodes-new',
        'process-muno-series',
        'process-muno-movies',
        'crawl-dating-pages',
        'extract-dating-users',
        'process-dating-profile/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isPublicRoute($request)) {
            return $next($request);
        }

        $validKey = config('app.processing_route_key');

        if (empty($validKey)) {
            abort(503, 'Processing routes are not configured.');
        }

        $providedKey = $request->query('key') ?? $request->header('X-Processing-Key');

        if (!$providedKey || !hash_equals($validKey, $providedKey)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }

    private function isPublicRoute(Request $request): bool
    {
        foreach ($this->publicRoutePatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
