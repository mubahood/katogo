<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * LiteSpeed Cache middleware.
 * 
 * Applied to routes that should be cached by LiteSpeed.
 * Must run AFTER HandleCors to override Vary: Origin.
 * 
 * Usage in routes: ->middleware('lscache:120,tag_name')
 */
class LiteSpeedCache
{
    public function handle(Request $request, Closure $next, $maxAge = 120, $tag = null)
    {
        $response = $next($request);

        // Set LSCache headers on the response
        $response->headers->set('X-LiteSpeed-Cache-Control', "public, max-age={$maxAge}");
        $response->headers->set('Cache-Control', "public, max-age={$maxAge}");

        if ($tag) {
            $response->headers->set('X-LiteSpeed-Tag', $tag);
        }

        // Remove Vary header that prevents LSCache from storing
        $response->headers->remove('Vary');

        return $response;
    }
}
