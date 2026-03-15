<?php
/**
 * Network Diagnostic Script — test outbound HTTPS connectivity from production server
 * Access via: https://katogo.schooldynamics.ug/api-network-test.php
 * DELETE THIS FILE AFTER DEBUGGING.
 */
header('Content-Type: application/json');

$results = [];

// 1. DNS resolution
$results['dns'] = [];
$ip = gethostbyname('pay.pesapal.com');
$results['dns']['pay.pesapal.com'] = $ip;
$results['dns']['resolved'] = ($ip !== 'pay.pesapal.com');

// 2. PHP info
$results['php'] = [
    'version' => PHP_VERSION,
    'curl_enabled' => function_exists('curl_init'),
    'allow_url_fopen' => ini_get('allow_url_fopen'),
    'openssl' => extension_loaded('openssl') ? OPENSSL_VERSION_TEXT : 'NOT LOADED',
];

if (function_exists('curl_version')) {
    $cv = curl_version();
    $results['php']['curl_version'] = $cv['version'];
    $results['php']['ssl_version'] = $cv['ssl_version'];
}

// 3. Test cURL connection to Pesapal with verbose info
$tests = [
    ['url' => 'https://pay.pesapal.com/v3', 'label' => 'Pesapal Production', 'ssl' => true],
    ['url' => 'https://pay.pesapal.com/v3', 'label' => 'Pesapal No-SSL-Verify', 'ssl' => false],
    ['url' => 'https://www.google.com', 'label' => 'Google (control test)', 'ssl' => true],
    ['url' => 'https://cybqa.pesapal.com/pesapalv3', 'label' => 'Pesapal Sandbox', 'ssl' => true],
];

$results['connections'] = [];
foreach ($tests as $test) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $test['ssl']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $test['ssl'] ? 2 : 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    // Try with IPv4 only (some servers have IPv6 issues)
    if (defined('CURLOPT_IPRESOLVE')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $results['connections'][] = [
        'label' => $test['label'],
        'url' => $test['url'],
        'ssl_verify' => $test['ssl'],
        'http_code' => $httpCode,
        'curl_errno' => $errno,
        'curl_error' => $error ?: null,
        'resolved_ip' => $ip ?: null,
        'time_seconds' => round($totalTime, 3),
        'success' => ($errno === 0 && $httpCode > 0),
        'body_preview' => $response ? substr($response, 0, 100) : null,
    ];
}

// 4. Test file_get_contents as fallback
$results['file_get_contents'] = [];
if (ini_get('allow_url_fopen')) {
    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'method' => 'GET'],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    
    $start = microtime(true);
    $body = @file_get_contents('https://pay.pesapal.com/v3', false, $ctx);
    $elapsed = round(microtime(true) - $start, 3);
    
    $results['file_get_contents'] = [
        'success' => ($body !== false),
        'time_seconds' => $elapsed,
        'body_preview' => $body ? substr($body, 0, 100) : null,
    ];
} else {
    $results['file_get_contents'] = ['skipped' => 'allow_url_fopen is disabled'];
}

// 5. Socket test
$results['socket_test'] = [];
$start = microtime(true);
$sock = @fsockopen('ssl://pay.pesapal.com', 443, $sockErrno, $sockError, 10);
$elapsed = round(microtime(true) - $start, 3);
if ($sock) {
    fclose($sock);
    $results['socket_test'] = ['success' => true, 'time_seconds' => $elapsed];
} else {
    $results['socket_test'] = ['success' => false, 'errno' => $sockErrno, 'error' => $sockError, 'time_seconds' => $elapsed];
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
