<?php

return [

    /*
     * Determine if the response cache middleware should be enabled.
     * Set RESPONSE_CACHE_ENABLED=false in .env to disable without code changes.
     */
    'enabled' => env('RESPONSE_CACHE_ENABLED', true),

    /*
     * The cache profile determines which requests are cached and for how long.
     * The default profile caches all successful GET requests for authenticated
     * and guest users separately (user ID is part of the cache key).
     *
     * The Katogo API is mostly public read-only (no per-user data in movie/series
     * lists), so we override the hasher to use the URI + query string only.
     */
    'cache_profile' => Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests::class,

    /*
     * When using the default cache profile the response will be cached for
     * the amount of seconds specified here.
     *
     * 300 seconds (5 minutes) — good balance between freshness and cache hits.
     * Override per-route with: ->middleware('cacheResponse:3600')
     */
    'cache_lifetime_in_seconds' => env('RESPONSE_CACHE_LIFETIME', 300),

    /*
     * This class is responsible for generating a cache key for the response.
     * The UriHasher ignores the authenticated user — suitable for public movie/series APIs.
     * Switch to RequestHasher if any cached endpoint returns user-specific content.
     */
    'hasher' => Spatie\ResponseCache\Hasher\DefaultHasher::class,

    /*
     * The cache store used by the ResponseCache.
     * Defaults to the default Laravel cache store (file cache in this project).
     */
    'cache_store' => env('RESPONSE_CACHE_DRIVER', config('cache.default')),

    /*
     * Add a X-Spatie-Cache-Time header to responses.
     * Useful for debugging. Disable on production to reduce response size.
     */
    'add_cache_time_header' => env('APP_DEBUG', false),
    'cache_time_header_name' => env('RESPONSE_CACHE_HEADER_NAME', 'X-Spatie-Cache-Time'),

    /*
     * The serializer used to serialize/deserialize cached responses.
     */
    'serializer' => \Spatie\ResponseCache\Serializers\DefaultSerializer::class,

    /*
     * Replacers can replace parts of the response before it is cached.
     * Leave empty unless you need to strip dynamic tokens/CSRF from cached responses.
     */
    'replacers' => [],

    /*
     * A tag to use when storing cached responses.
     * Only applies to cache stores that support tagging (Redis, Memcached).
     * Leave empty for the file driver.
     */
    'cache_tag' => '',

];
