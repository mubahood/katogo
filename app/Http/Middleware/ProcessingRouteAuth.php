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
    public function handle(Request $request, Closure $next): Response
    {
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
}
