<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;

// 1. Cancelled subscriptions
$allRecent = Subscription::orderBy('id','desc')->limit(100)->get();
$cancelled = $allRecent->where('status', 'Cancelled');
echo "=== CANCELLED SUBSCRIPTIONS (" . count($cancelled) . ") ===\n";
foreach ($cancelled->take(10) as $s) {
    echo "#{$s->id} | U:{$s->user_id} | PayStat:{$s->payment_status} | Track:" . ($s->pesapal_tracking_id ?: 'NONE') . " | PayURL:" . ($s->payment_url ? 'YES' : 'NO') . " | CancelReason:" . ($s->cancelled_reason ?: 'NULL') . " | Created:{$s->created_at}\n";
}

// 2. Why 54 have NO tracking ID
echo "\n=== SUBSCRIPTIONS WITH NO TRACKING ID ===\n";
$noTrack = $allRecent->filter(fn($s) => empty($s->pesapal_tracking_id));
echo "Count: " . count($noTrack) . "\n";
echo "Status: " . json_encode($noTrack->groupBy('status')->map->count()) . "\n";
echo "PaymentStatus: " . json_encode($noTrack->groupBy('payment_status')->map->count()) . "\n";

// 3. Check the create() flow - what happens when subscriptions are created
echo "\n=== CHECK: Subscriptions created in last 24h ===\n";
$recent24h = Subscription::where('created_at', '>=', now()->subDay())->orderBy('id','desc')->get();
echo "Created in last 24h: " . $recent24h->count() . "\n";
echo "With tracking ID: " . $recent24h->filter(fn($s) => !empty($s->pesapal_tracking_id))->count() . "\n";
echo "Without tracking ID: " . $recent24h->filter(fn($s) => empty($s->pesapal_tracking_id))->count() . "\n";
echo "With payment URL: " . $recent24h->filter(fn($s) => !empty($s->payment_url))->count() . "\n";

// 4. Check .env pesapal config via direct env read
echo "\n=== .ENV PESAPAL CONFIG (direct) ===\n";
$envContent = file_get_contents(__DIR__ . '/.env');
$lines = explode("\n", $envContent);
foreach ($lines as $line) {
    if (stripos($line, 'PESAPAL') !== false || stripos($line, 'APP_ENV') !== false || stripos($line, 'APP_URL') !== false || stripos($line, 'APP_DEBUG') !== false || stripos($line, 'APP_PRODUCTION') !== false) {
        echo "  " . trim($line) . "\n";
    }
}

// 5. Check services config for pesapal
echo "\n=== config/services.php pesapal section ===\n";
$svcConfig = config('services.pesapal');
echo json_encode($svcConfig, JSON_PRETTY_PRINT) . "\n";

// 6. Check logs for subscription create errors in last 24h
echo "\n=== RECENT LOGS: Pesapal errors (last 3000 lines) ===\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $total = count($lines);
    echo "Total log lines: {$total}\n";
    $search = array_slice($lines, max(0, $total - 3000));
    $errors = [];
    foreach ($search as $line) {
        $lower = strtolower($line);
        if ((strpos($lower, 'pesapal') !== false || strpos($lower, 'subscription') !== false) 
            && (strpos($lower, 'error') !== false || strpos($lower, 'fail') !== false || strpos($lower, 'exception') !== false || strpos($lower, 'curl') !== false)) {
            $errors[] = trim($line);
        }
    }
    echo "Relevant error lines: " . count($errors) . "\n";
    foreach (array_slice($errors, -30) as $e) {
        echo substr($e, 0, 400) . "\n";
    }
} else {
    echo "Log file not found\n";
}

// 7. Check the callback URLs in pesapal_response for pattern issues
echo "\n=== CALLBACK URL PATTERNS IN SAVED RESPONSES ===\n";
$withResp = Subscription::whereNotNull('pesapal_response')
    ->orderBy('id','desc')
    ->limit(20)
    ->get(['id','status','payment_status','pesapal_response']);
foreach ($withResp as $s) {
    $resp = $s->pesapal_response;
    $callbackUrl = null;
    if (isset($resp['status_check']['call_back_url'])) {
        $callbackUrl = $resp['status_check']['call_back_url'];
    }
    echo "  #{$s->id} ({$s->status}/{$s->payment_status}): callback=" . ($callbackUrl ?: 'N/A') . "\n";
}

// 8. Very important: Check if the IPN URL is reachable
echo "\n=== IPN URL ANALYSIS ===\n";
$ipnUrl = env('PESAPAL_IPN_URL');
echo "Configured IPN URL: " . ($ipnUrl ?: 'NOT SET') . "\n";
$callbackUrl = env('PESAPAL_CALLBACK_URL');
echo "Configured Callback URL: " . ($callbackUrl ?: 'NOT SET') . "\n";

// 9. Check for the most telling pattern: created but no tracking ID means create() silently failed
echo "\n=== CRITICAL: What happened to subscriptions without tracking IDs? ===\n";
echo "These subscriptions were CREATED but Pesapal order submission FAILED\n";
echo "Possible causes:\n";
echo "  1. Pesapal API authentication failed (wrong keys)\n";
echo "  2. IPN registration failed\n";
echo "  3. Order submission failed\n";
echo "  4. Network/SSL error\n";
echo "  5. Config error (wrong consumer key/secret)\n\n";

// Check if consumer key is in config vs env
echo "FROM config(): " . (config('services.pesapal.consumer_key') ? 'SET' : 'NOT SET') . "\n";
echo "FROM env(): " . (env('PESAPAL_CONSUMER_KEY') ? 'SET' : 'NOT SET') . "\n";

// 10. Test actual Pesapal connectivity right now
echo "\n=== LIVE PESAPAL API TEST ===\n";
try {
    $service = app(\App\Services\SubscriptionPesapalService::class);
    $testResult = $service->testConnection(false);
    echo json_encode($testResult, JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "TEST FAILED: " . $e->getMessage() . "\n";
}
