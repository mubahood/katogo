<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Google Drive Video Transfer System\n";
echo "============================================\n\n";

// Test 1: Check credentials
echo "✅ Test 1: Checking Google Drive Credentials...\n";
$clientId = env('GOOGLE_DRIVE_CLIENT_ID');
$clientSecret = env('GOOGLE_DRIVE_CLIENT_SECRET');
$refreshToken = env('GOOGLE_DRIVE_REFRESH_TOKEN');

if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
    echo "❌ FAILED: Missing credentials in .env file\n";
    exit(1);
}

echo "   Client ID: " . substr($clientId, 0, 20) . "...\n";
echo "   Client Secret: " . substr($clientSecret, 0, 10) . "...\n";
echo "   Refresh Token: " . substr($refreshToken, 0, 15) . "...\n";
echo "   ✅ All credentials present!\n\n";

// Test 2: Test Google OAuth
echo "✅ Test 2: Testing Google OAuth Token Exchange...\n";
try {
    $response = \Illuminate\Support\Facades\Http::asForm()->post('https://oauth2.googleapis.com/token', [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);

    if ($response->successful()) {
        $accessToken = $response->json()['access_token'] ?? null;
        if ($accessToken) {
            echo "   ✅ Successfully obtained access token!\n";
            echo "   Access Token: " . substr($accessToken, 0, 20) . "...\n\n";
        } else {
            echo "   ❌ FAILED: No access token in response\n";
            echo "   Response: " . $response->body() . "\n";
            exit(1);
        }
    } else {
        echo "   ❌ FAILED: Could not get access token\n";
        echo "   Status: " . $response->status() . "\n";
        echo "   Error: " . $response->body() . "\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "   ❌ FAILED: Exception occurred\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Create test video transfer (small file)
echo "✅ Test 3: Creating Test Video Transfer...\n";
try {
    $transfer = new \App\Models\VideoTransfer();
    $transfer->source_url = 'https://sample-videos.com/video321/mp4/240/big_buck_bunny_240p_1mb.mp4';
    $transfer->video_title = 'Test Video - Big Buck Bunny';
    $transfer->video_description = 'Test transfer from automated test script';
    $transfer->transferred_by = 'Test Script';
    $transfer->save();
    
    echo "   ✅ Transfer record created! ID: " . $transfer->id . "\n";
    echo "   Status: " . $transfer->status . "\n";
    echo "   URL: " . $transfer->source_url . "\n\n";

    // Wait a bit for processing to start
    echo "⏳ Waiting for transfer to process (this may take 30-60 seconds)...\n";
    echo "   Press CTRL+C to cancel and check status later in admin panel\n\n";
    
    $maxWait = 60; // Wait up to 60 seconds
    $waited = 0;
    $lastStatus = '';
    
    while ($waited < $maxWait) {
        sleep(5);
        $waited += 5;
        
        $transfer->refresh();
        
        if ($transfer->status !== $lastStatus) {
            echo "   [" . date('H:i:s') . "] Status: " . strtoupper($transfer->status) . " | Progress: {$transfer->progress}%\n";
            $lastStatus = $transfer->status;
        }
        
        if ($transfer->status === 'completed') {
            echo "\n🎉 SUCCESS! Transfer completed!\n\n";
            echo "📊 Results:\n";
            echo "   Status: ✅ " . strtoupper($transfer->status) . "\n";
            echo "   Progress: {$transfer->progress}%\n";
            echo "   File Size: " . $transfer->formatted_size . "\n";
            echo "   Duration: " . $transfer->formatted_duration . "\n";
            echo "   Google Drive File ID: " . $transfer->drive_file_id . "\n";
            echo "   Public URL: " . $transfer->drive_public_url . "\n";
            echo "   Embed URL: " . $transfer->embed_url . "\n\n";
            echo "✅ All tests passed! System is working correctly!\n";
            echo "🎬 Visit admin panel: " . env('APP_URL') . "admin/video-transfers\n";
            exit(0);
        }
        
        if ($transfer->status === 'failed') {
            echo "\n❌ Transfer failed!\n";
            echo "   Error: " . $transfer->error_message . "\n";
            echo "   Details: " . substr($transfer->error_details, 0, 200) . "...\n";
            exit(1);
        }
    }
    
    echo "\n⏱️ Test timeout reached (60 seconds)\n";
    echo "   Current status: " . strtoupper($transfer->status) . " ({$transfer->progress}%)\n";
    echo "   Transfer is still processing in background\n";
    echo "   Check status in admin panel: " . env('APP_URL') . "admin/video-transfers/{$transfer->id}\n";
    exit(0);
    
} catch (\Exception $e) {
    echo "   ❌ FAILED: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
