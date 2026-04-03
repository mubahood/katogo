<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * LiteSpeed Cache middleware.
 * 
 * Runs as global middleware (outer position, before HandleCors) so it can
 * override headers AFTER HandleCors processes the response.
 * 
 * Only modifies responses for routes that have the 'lscache' route middleware
 * which sets the _lscache flag on the request.
 */
class LiteSpeedCacheGlobal
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only modify responses flagged for LSCache
        $maxAge = $request->attributes->get('_lscache_max_age');
        if ($maxAge === null) {
            return $response;
        }

        $tag = $request->attributes->get('_lscache_tag');

        // Set cache-control on the response object (for the client)
        $response->headers->set('Cache-Control', "public, max-age={$maxAge}");
        $response->headers->remove('Vary');

        // Use PHP native header() for LiteSpeed-specific headers
        // LiteSpeed intercepts these during header output
        header("X-LiteSpeed-Cache-Control: public, max-age={$maxAge}");
        if ($tag) {
            header("X-LiteSpeed-Tag: {$tag}");
        }

        return $response;
    }
}
