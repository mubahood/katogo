<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Route-level middleware that flags the request for LSCache.
 * The actual header modification is done by LiteSpeedCacheGlobal (global middleware).
 * 
 * Usage: ->middleware('lscache:120,tag_name')
 */
class LiteSpeedCacheRoute
{
    public function handle(Request $request, Closure $next, $maxAge = 120, $tag = null)
    {
        $request->attributes->set('_lscache_max_age', (int) $maxAge);
        if ($tag) {
            $request->attributes->set('_lscache_tag', $tag);
        }

        return $next($request);
    }
}
