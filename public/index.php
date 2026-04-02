<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Pre-Bootstrap API Response Cache
|--------------------------------------------------------------------------
| For heavy API endpoints, serve cached JSON responses without booting
| Laravel. This eliminates 30s+ bootstrap time under high CPU load.
| Controllers write cache files; this layer reads them.
*/
$_pbc_uri = $_SERVER['REQUEST_URI'] ?? '';
$_pbc_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($_pbc_method === 'GET' && strpos($_pbc_uri, '/api/') !== false) {
    $_pbc_path = strtok($_pbc_uri, '?');
    // Shared endpoints: cache by path only (same for all users)
    $_pbc_shared = [
        '/api/v2/streaming/home'  => 120,
        '/api/v2/blog/marquee'    => 120,
    ];
    // Per-user endpoints: cache by path + user_id
    $_pbc_peruser = [
        '/api/v2/manifest'        => 45,
        '/api/manifest'           => 45,
    ];
    $_pbc_file = null;
    if (isset($_pbc_shared[$_pbc_path])) {
        $_pbc_ttl = $_pbc_shared[$_pbc_path];
        $_pbc_file = __DIR__ . '/../storage/api_cache/shared_' . md5($_pbc_path);
    } elseif (isset($_pbc_peruser[$_pbc_path])) {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $_pbc_qs);
        $_pbc_uid = $_pbc_qs['logged_in_user_id'] ?? '0';
        $_pbc_app = $_pbc_qs['app_type'] ?? '';
        $_pbc_ttl = $_pbc_peruser[$_pbc_path];
        $_pbc_file = __DIR__ . '/../storage/api_cache/u_' . md5($_pbc_path . $_pbc_uid . $_pbc_app);
    }
    if ($_pbc_file && file_exists($_pbc_file) && (time() - filemtime($_pbc_file)) < $_pbc_ttl) {
        header('Content-Type: application/json');
        header('X-Cache: HIT');
        readfile($_pbc_file);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
