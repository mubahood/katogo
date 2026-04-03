<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AddETagHeader — P5-04
 *
 * Computes a weak ETag from the response body's MD5 hash and attaches it.
 * If the client sends an If-None-Match header that matches the current ETag,
 * returns a 304 Not Modified response with no body.
 *
 * Apply to read-heavy GET endpoints via route middleware or route groups.
 *
 * Example (routes/api.php):
 *   Route::middleware(['etag'])->group(function () { ... });
 */
class AddETagHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Only apply ETag to successful GET/HEAD 200 responses
        if (
            !in_array($request->method(), ['GET', 'HEAD'])
            || $response->getStatusCode() !== 200
        ) {
            return $response;
        }

        $content = $response->getContent();

        // Skip empty or streaming responses
        if ($content === false || $content === '') {
            return $response;
        }

        $etag = 'W/"' . md5($content) . '"';

        $response->headers->set('ETag', $etag);

        // Conditional request: return 304 if ETag matches
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch && $ifNoneMatch === $etag) {
            return response('', 304, [
                'ETag'          => $etag,
                'Cache-Control' => $response->headers->get('Cache-Control', 'no-store'),
            ]);
        }

        return $response;
    }
}
