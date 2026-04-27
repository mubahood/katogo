<?php

$spec   = json_decode(file_get_contents('/Applications/MAMP/htdocs/katogo/storage/api-docs/api-docs.json'), true);
$routes = json_decode(file_get_contents('/Applications/MAMP/htdocs/katogo/storage/api-docs/route-inventory.json'), true);

// Coverage
$specOps = [];
foreach (($spec['paths'] ?? []) as $p => $ops) {
    foreach ($ops as $m => $d) { $specOps[strtoupper($m).' '.$p] = $d; }
}
$skip = ['api/documentation' => 1, 'api/oauth2-callback' => 1];
$missing = [];
foreach ($routes as $r) {
    $u = $r['uri'] ?? '';
    if ($u === '' || !str_starts_with($u, 'api/') || isset($skip[$u])) continue;
    foreach (array_filter(explode('|', $r['method'] ?? ''), fn($m) => $m !== '' && $m !== 'HEAD') as $m) {
        if (!isset($specOps[strtoupper($m).' /'.$u])) $missing[] = strtoupper($m).' /'.$u;
    }
}

// Quality
$schemas       = array_keys($spec['components']['schemas'] ?? []);
$realSummaries = 0;
$realRequests  = 0;
$realResponses = 0;

foreach ($specOps as $key => $op) {
    $sum = $op['summary'] ?? '';
    if (!preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+\//', $sum)) $realSummaries++;

    $rb = $op['requestBody']['content']['application/json']['schema'] ?? null;
    if ($rb) {
        $ref = $rb['$ref'] ?? '';
        if ($ref && strpos($ref, 'ApiResponse') === false) $realRequests++;
    }

    $r200ref = $op['responses']['200']['content']['application/json']['schema']['$ref'] ?? '';
    if ($r200ref && strpos($r200ref, 'ApiResponse') === false) $realResponses++;
}

echo "=== QUALITY VALIDATION REPORT ===\n";
echo "Total operations : " . count($specOps) . "\n";
echo "Missing from spec: " . count($missing) . "\n";
echo "Schemas defined  : " . count($schemas) . " -> " . implode(', ', $schemas) . "\n\n";
echo "Human-readable summaries : $realSummaries / " . count($specOps) . "\n";
echo "Typed request schemas    : $realRequests\n";
echo "Typed response schemas   : $realResponses\n\n";

// Spot checks
echo "Spot checks:\n";
$checks = [
    '/api/account/likes'                          => 'get',
    '/api/subscriptions/{id}/initiate-payment'    => 'post',
    '/api/account/likes/toggle'                   => 'post',
    '/api/moderation/block-user'                  => 'post',
    '/api/v2/game-stats/sync'                     => 'post',
    '/api/v2/downloads/record'                    => 'post',
    '/api/v2/safemode/track'                      => 'post',
    '/api/moderation/update-legal-consent'        => 'post',
    '/api/video-progress'                         => 'post',
];
foreach ($checks as $path => $m) {
    $op = $spec['paths'][$path][$m] ?? null;
    if (!$op) { echo "  MISSING: $m $path\n"; continue; }
    $reqRef = $op['requestBody']['content']['application/json']['schema']['$ref'] ?? '-';
    $resRef = $op['responses']['200']['content']['application/json']['schema']['$ref'] ?? '-';
    echo "  " . strtoupper($m) . " $path\n";
    echo "    summary    : " . ($op['summary'] ?? '') . "\n";
    echo "    requestBody: $reqRef\n";
    echo "    200 schema : $resRef\n";
}

echo "\nStatus: " . (count($missing) === 0 ? 'ALL CLEAR - Production ready' : 'GAPS FOUND') . "\n";
