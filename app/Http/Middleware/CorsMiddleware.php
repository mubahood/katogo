<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Debug log to see if middleware is being called
        error_log("🔧 CORS Middleware called for: " . $request->getMethod() . " " . $request->getPathInfo());
        
        // Handle preflight OPTIONS requests
        if ($request->getMethod() === "OPTIONS") {
            error_log("🔧 Handling OPTIONS preflight request");
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        // Add CORS headers to all responses
        $origin = $request->header('Origin') ?: '*';
        error_log("🔧 Setting CORS headers for origin: " . $origin);
        
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Tok, logged_in_user_id, platform_type, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'false'); // Match the CORS config
        $response->headers->set('Access-Control-Expose-Headers', 'Authorization, Tok');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}