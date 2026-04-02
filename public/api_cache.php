<?php
/**
 * Pre-Bootstrap API Response Cache
 * 
 * Serves cached API responses without loading Laravel.
 * If cache miss or expired, falls through to normal Laravel bootstrap.
 */

// Debug: temporarily log what this file sees
@file_put_contents(__DIR__ . '/../storage/logs/pbc_debug.log',
    date('H:i:s') . ' ALL=' . json_encode(array_filter($_SERVER, function($k) {
        return in_array($k, ['REQUEST_URI','QUERY_STRING','REDIRECT_URL','SCRIPT_URL',
            'ORIG_PATH_INFO','PATH_INFO','SCRIPT_NAME','SCRIPT_FILENAME','PHP_SELF',
            'REQUEST_METHOD','REDIRECT_QUERY_STRING','HTTP_X_REWRITE_URL']);
    }, ARRAY_FILTER_USE_KEY))
    . "\n", FILE_APPEND);

$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = strtok($uri, '?');

// LiteSpeed sometimes rewrites REQUEST_URI to the handler file — check REDIRECT_URL as fallback
if (strpos($path, 'api_cache.php') !== false || strpos($path, '/api/') === false) {
    $path = $_SERVER['REDIRECT_URL'] ?? $_SERVER['SCRIPT_URL'] ?? $path;
}

// Shared endpoints: same response for all users
$shared = [
    '/api/v2/streaming/home'  => 120,
    '/api/v2/blog/marquee'    => 120,
];
// Per-user endpoints
$peruser = [
    '/api/v2/manifest'        => 45,
    '/api/manifest'           => 45,
];

$file = null;
$ttl = 0;
$cacheDir = __DIR__ . '/../storage/api_cache';

if (isset($shared[$path])) {
    $ttl = $shared[$path];
    $file = "{$cacheDir}/shared_" . md5($path);
} elseif (isset($peruser[$path])) {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    $uid = $qs['logged_in_user_id'] ?? '0';
    $app = $qs['app_type'] ?? '';
    $ttl = $peruser[$path];
    $file = "{$cacheDir}/u_" . md5($path . $uid . $app);
}

if ($file && file_exists($file) && (time() - filemtime($file)) < $ttl) {
    header('Content-Type: application/json');
    header('X-Cache: HIT');
    readfile($file);
    exit;
}

// Cache miss — fall through to normal Laravel
require __DIR__ . '/index.php';
