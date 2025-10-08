<?php

$token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';

echo "=== JWT TOKEN ANALYSIS ===\n\n";

// Decode JWT to check expiration
$parts = explode('.', $token);
if (count($parts) === 3) {
    $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);
    
    if ($payload) {
        echo "Token payload:\n";
        print_r($payload);
        
        if (isset($payload['exp'])) {
            $expiry = date('Y-m-d H:i:s', $payload['exp']);
            $now = date('Y-m-d H:i:s');
            echo "\nToken expires: {$expiry}\n";
            echo "Current time:  {$now}\n";
            echo "Token expired: " . ($payload['exp'] < time() ? 'YES' : 'NO') . "\n";
        }
    } else {
        echo "Failed to decode payload\n";
    }
} else {
    echo "Invalid JWT format\n";
}

echo "\n🎯 JWT analysis complete!\n";