<?php
// Debug authentication issue
header('Content-Type: text/plain');

$baseUrl = 'http://localhost:8888/katogo/api';

// Test different login attempts
$users = [
    ['email' => 'admin@gmail.com', 'password' => 'test123'],
    ['email' => 'mubahood@gmail.com', 'password' => 'test123'],
    ['email' => 'admin@gmail.com', 'password' => 'admin123'],
];

foreach ($users as $user) {
    echo "Testing login for: " . $user['email'] . "\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/auth/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($user),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "  HTTP Code: " . $httpCode . "\n";
    echo "  Response: " . $response . "\n\n";
}
?>
