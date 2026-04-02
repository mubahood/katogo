<?php
/**
 * Pre-Bootstrap API Response Cache
 * 
 * Serves cached API responses without loading Laravel.
 * If cache miss or expired, falls through to normal Laravel bootstrap.
 * The original path is passed via _pbc_path query parameter by .htaccess.
 */
$path = $_GET['_pbc_path'] ?? '';
$cacheDir = __DIR__ . '/../storage/api_cache';
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
