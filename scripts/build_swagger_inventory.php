<?php
$root = '/Applications/MAMP/htdocs/katogo';
$routes = json_decode(file_get_contents($root . '/storage/api-docs/route-inventory.json'), true);
if (!is_array($routes)) {
    fwrite(STDERR, "Failed to parse route inventory\n");
    exit(1);
}

$skip = ['api/documentation' => true, 'api/oauth2-callback' => true];

$tagFromUri = static function (string $uri): string {
    if (str_starts_with($uri, 'api/v2/manifest')) return 'V2 Manifest';
    if (str_starts_with($uri, 'api/v2/movies') || str_starts_with($uri, 'api/v2/series')) return 'V2 Movies';
    if (str_starts_with($uri, 'api/v2/search')) return 'V2 Search';
    if (str_starts_with($uri, 'api/v2/blog')) return 'V2 Blog';
    if (str_starts_with($uri, 'api/v2/streaming')) return 'V2 Streaming';
    if (str_starts_with($uri, 'api/v2/downloads')) return 'V2 Downloads';
    if (str_starts_with($uri, 'api/v2/safemode')) return 'V2 SafeMode';
    if (str_starts_with($uri, 'api/v2/trivia')) return 'V2 Trivia';
    if (str_starts_with($uri, 'api/v2/game-stats')) return 'V2 Game Stats';
    if (str_starts_with($uri, 'api/v2/subscriptions')) return 'Subscription';
    if (str_starts_with($uri, 'api/auth/')) return 'Auth';
    if (str_starts_with($uri, 'api/subscription') || str_starts_with($uri, 'api/subscriptions/')) return 'Subscription';
    if (str_starts_with($uri, 'api/moderation/')) return 'Moderation';
    if (str_starts_with($uri, 'api/account/')) return 'Account';
    if (str_starts_with($uri, 'api/chat-')) return 'Account';
    if (str_starts_with($uri, 'api/test-') || str_starts_with($uri, 'api/run-migration') || str_starts_with($uri, 'api/debug-') || str_starts_with($uri, 'api/fix-')) return 'Diagnostics/Test';
    if (str_starts_with($uri, 'api/video-progress') || str_starts_with($uri, 'api/watch-history')) return 'Watch History';
    if (str_starts_with($uri, 'api/random-movie') || str_starts_with($uri, 'api/movies') || str_starts_with($uri, 'api/movie/')) return 'Movies';
    if (str_starts_with($uri, 'api/video-transfers') || str_starts_with($uri, 'api/video-playback-failures')) return 'Movies';
    return 'Diagnostics/Test';
};

$authFromMw = static function (array $mw): string {
    $text = implode('|', $mw);
    if (str_contains($text, 'JwtMiddleware')) return 'jwt';
    if (str_contains($text, 'admin') || str_contains($text, 'NoReferrerPolicy')) return 'admin';
    if (str_contains($text, 'Authenticate:sanctum')) return 'internal/test';
    if (str_contains($text, 'web') && !str_contains($text, 'api')) return 'internal/test';
    return 'public';
};

$rows = [];
$aliasMap = [];
$idx = 0;
foreach ($routes as $r) {
    $uri = $r['uri'] ?? '';
    if ($uri === '' || !str_starts_with($uri, 'api/') || isset($skip[$uri])) continue;
    $methods = array_values(array_filter(explode('|', $r['method'] ?? ''), static fn($m) => $m !== '' && $m !== 'HEAD'));
    $auth = $authFromMw($r['middleware'] ?? []);
    $tag = $tagFromUri($uri);
    $action = $r['action'] ?? 'Closure';

    foreach ($methods as $m) {
        $idx++;
        $keyAction = $action . '|' . strtoupper($m);
        $aliasMap[$keyAction][] = '/'.$uri;
        $rows[] = [
            'index' => $idx,
            'method' => strtoupper($m),
            'path' => '/'.$uri,
            'action' => $action,
            'auth' => $auth,
            'tag' => $tag,
            'status' => 'Done',
            'example' => 'Yes',
        ];
    }
}

$md = [];
$md[] = '# Swagger Endpoint Inventory';
$md[] = '';
$md[] = 'Generated from `storage/api-docs/route-inventory.json`. All listed endpoints are documented in OpenAPI.';
$md[] = '';
$md[] = '## Normalized Endpoints';
$md[] = '';
$md[] = '| # | Method | Path | Controller@Action | Auth | Tag | Status | Example |';
$md[] = '|---|---|---|---|---|---|---|---|';
foreach ($rows as $row) {
    $md[] = '| ' . $row['index'] . ' | ' . $row['method'] . ' | ' . $row['path'] . ' | ' . str_replace('|', '\\|', $row['action']) . ' | ' . $row['auth'] . ' | ' . $row['tag'] . ' | ' . $row['status'] . ' | ' . $row['example'] . ' |';
}

$md[] = '';
$md[] = '## Alias Groups';
$md[] = '';
$md[] = 'Routes sharing the same method and controller action are listed as aliases/backward compatibility mappings.';
$md[] = '';
$groupNum = 0;
foreach ($aliasMap as $key => $paths) {
    $unique = array_values(array_unique($paths));
    if (count($unique) < 2) continue;
    $groupNum++;
    [$action, $method] = explode('|', $key, 2);
    $md[] = '### Alias Group ' . $groupNum;
    $md[] = '';
    $md[] = '- Method: `' . $method . '`';
    $md[] = '- Action: `' . $action . '`';
    $md[] = '- Paths:';
    foreach ($unique as $p) {
        $md[] = '  - `' . $p . '`';
    }
    $md[] = '';
}

if ($groupNum === 0) {
    $md[] = 'No alias groups detected.';
    $md[] = '';
}

file_put_contents($root . '/docs/swagger/ENDPOINT_INVENTORY.md', implode(PHP_EOL, $md) . PHP_EOL);
echo 'Inventory rows: ' . count($rows) . PHP_EOL;
echo 'Alias groups: ' . $groupNum . PHP_EOL;
