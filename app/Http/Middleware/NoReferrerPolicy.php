<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Adds Referrer-Policy: no-referrer HTTP header to responses.
 *
 * Required for the debug video player: BunnyCDN hotlink protection returns 403
 * when a Referer header is present from non-whitelisted domains (like localhost).
 * This header tells Chrome to NOT send Referer on any request from this page,
 * including <video> element media requests.
 *
 * Note: element-level referrerpolicy="no-referrer" and dynamic <meta> tags are NOT
 * reliably honored by Chrome for <video> element requests. The HTTP response header
 * is the only guaranteed approach.
 */
class NoReferrerPolicy
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
