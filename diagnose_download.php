<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Test downloading a video
$testUrl = $argv[1] ?? 'https://sample-videos.com/video321/mp4/240/big_buck_bunny_240p_1mb.mp4';

echo "Testing video download from: {$testUrl}\n";
echo str_repeat("=", 80) . "\n\n";

// Test 1: Simple HEAD request to check if URL is accessible
echo "Test 1: Checking if URL is accessible...\n";
try {
    $headResponse = Http::timeout(30)
        ->withOptions(['verify' => false])
        ->head($testUrl);
    
    echo "✅ HEAD request successful\n";
    echo "Status: " . $headResponse->status() . "\n";
    echo "Headers:\n";
    foreach ($headResponse->headers() as $key => $values) {
        echo "  {$key}: " . implode(', ', $values) . "\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "❌ HEAD request failed: " . $e->getMessage() . "\n\n";
}

// Test 2: Download to memory (small test)
echo "Test 2: Downloading first 1MB to memory...\n";
try {
    $testResponse = Http::timeout(30)
        ->withOptions([
            'verify' => false,
            'allow_redirects' => true,
        ])
        ->withHeaders([
            'Range' => 'bytes=0-1048576', // First 1MB
        ])
        ->get($testUrl);
    
    $bodySize = strlen($testResponse->body());
    echo "✅ Download successful\n";
    echo "Status: " . $testResponse->status() . "\n";
    echo "Downloaded: " . number_format($bodySize) . " bytes\n";
    echo "Content-Type: " . ($testResponse->header('Content-Type') ?? 'N/A') . "\n\n";
} catch (\Exception $e) {
    echo "❌ Download failed: " . $e->getMessage() . "\n\n";
}

// Test 3: Download to file (full download)
echo "Test 3: Downloading full file to disk...\n";
$tempFile = storage_path('app/temp/test_download_' . time() . '.mp4');

try {
    echo "Downloading to: {$tempFile}\n";
    
    $response = Http::timeout(120)
        ->withOptions([
            'sink' => $tempFile,
            'verify' => false,
            'allow_redirects' => true,
            'stream' => true,
            'progress' => function ($downloadTotal, $downloadedBytes) {
                if ($downloadTotal > 0) {
                    $percent = ($downloadedBytes / $downloadTotal) * 100;
                    echo "\rProgress: " . number_format($percent, 1) . "% (" . 
                         number_format($downloadedBytes) . " / " . 
                         number_format($downloadTotal) . " bytes)";
                } else {
                    echo "\rDownloaded: " . number_format($downloadedBytes) . " bytes";
                }
            },
        ])
        ->get($testUrl);
    
    echo "\n";
    
    if (file_exists($tempFile)) {
        $fileSize = filesize($tempFile);
        echo "✅ File created successfully\n";
        echo "File size: " . number_format($fileSize) . " bytes (" . number_format($fileSize / 1048576, 2) . " MB)\n";
        echo "File path: {$tempFile}\n";
        
        // Clean up
        unlink($tempFile);
        echo "✅ Test file cleaned up\n\n";
    } else {
        echo "❌ File was NOT created!\n";
        echo "HTTP Status: " . $response->status() . "\n";
        echo "Response body preview: " . substr($response->body(), 0, 500) . "\n\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ Download failed: " . $e->getMessage() . "\n";
    echo "Error type: " . get_class($e) . "\n\n";
    
    // Clean up if file was partially created
    if (file_exists($tempFile)) {
        unlink($tempFile);
        echo "Partial file cleaned up\n\n";
    }
}

// Test 4: Check server capabilities
echo "Test 4: Server information\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "cURL enabled: " . (function_exists('curl_version') ? 'Yes' : 'No') . "\n";
if (function_exists('curl_version')) {
    $curlVersion = curl_version();
    echo "cURL version: " . $curlVersion['version'] . "\n";
    echo "SSL version: " . $curlVersion['ssl_version'] . "\n";
}
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'Yes' : 'No') . "\n";
echo "open_basedir: " . (ini_get('open_basedir') ?: 'Not set') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . " seconds\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "DIAGNOSIS COMPLETE\n";
echo "\nUsage: php diagnose_download.php [URL]\n";
echo "Example: php diagnose_download.php https://example.com/video.mp4\n";
