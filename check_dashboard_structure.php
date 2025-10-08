<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Utils;

echo "=== CHECKING DASHBOARD RESPONSE STRUCTURE ===\n\n";

$url = 'https://munowatch.org/api/dashboard/v2/169464';
$headers = ['X-Api-Key' => 'katogo-key'];

try {
    $response = Utils::get_url($url, $headers);
    
    echo "Response received (first 1000 chars):\n";
    echo substr($response, 0, 1000) . "\n\n";
    
    $json = json_decode($response, true);
    if ($json) {
        echo "JSON Structure:\n";
        echo "Keys: " . implode(', ', array_keys($json)) . "\n\n";
        
        // Check what's in each key
        foreach ($json as $key => $value) {
            echo "Key '{$key}': ";
            if (is_array($value)) {
                echo "Array with " . count($value) . " items\n";
                if (!empty($value) && is_array($value[0])) {
                    echo "  First item keys: " . implode(', ', array_keys($value[0])) . "\n";
                }
            } elseif (is_object($value)) {
                echo "Object\n";
            } else {
                echo gettype($value) . " - " . (is_string($value) ? substr($value, 0, 50) : $value) . "\n";
            }
        }
    } else {
        echo "Failed to decode JSON: " . json_last_error_msg() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}