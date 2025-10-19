<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Google Drive OAuth Diagnostic\n";
echo "================================\n\n";

$clientId = env('GOOGLE_DRIVE_CLIENT_ID');
$clientSecret = env('GOOGLE_DRIVE_CLIENT_SECRET');
$refreshToken = env('GOOGLE_DRIVE_REFRESH_TOKEN');

echo "📋 Current Configuration:\n";
echo "   Client ID: {$clientId}\n";
echo "   Client Secret: {$clientSecret}\n";
echo "   Refresh Token: {$refreshToken}\n\n";

echo "🧪 Testing OAuth Token Exchange...\n\n";

$response = \Illuminate\Support\Facades\Http::asForm()
    ->withOptions(['verify' => false]) // Disable SSL verification for local testing
    ->post('https://oauth2.googleapis.com/token', [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);

echo "Response Status: " . $response->status() . "\n";
echo "Response Body:\n";
echo json_encode($response->json(), JSON_PRETTY_PRINT) . "\n\n";

if ($response->successful()) {
    echo "✅ SUCCESS! Token exchange worked!\n";
    echo "Your credentials are correctly configured.\n\n";
    
    $accessToken = $response->json()['access_token'];
    echo "Access Token (first 30 chars): " . substr($accessToken, 0, 30) . "...\n";
} else {
    echo "❌ FAILED! Token exchange failed.\n\n";
    
    $error = $response->json()['error'] ?? 'Unknown error';
    $errorDesc = $response->json()['error_description'] ?? 'No description';
    
    echo "Error: {$error}\n";
    echo "Description: {$errorDesc}\n\n";
    
    echo "📝 Possible Solutions:\n";
    
    if ($error === 'invalid_client' || $error === 'unauthorized_client') {
        echo "   1. Verify Client Secret is correct (no extra characters)\n";
        echo "   2. Make sure you created OAuth credentials as 'Desktop app' type\n";
        echo "   3. Regenerate refresh token using OAuth Playground:\n";
        echo "      https://developers.google.com/oauthplayground/\n";
    } elseif ($error === 'invalid_grant') {
        echo "   1. Refresh token may have expired\n";
        echo "   2. Regenerate a new refresh token\n";
        echo "   3. Make sure you used the same Client ID/Secret\n";
    }
    
    echo "\n📖 Full guide: GOOGLE_DRIVE_VIDEO_TRANSFER_GUIDE.md\n";
}
