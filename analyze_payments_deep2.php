<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║      DEEP FAILURE INVESTIGATION — " . date('Y-m-d H:i:s') . "       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// 1. Check for duplicate merchant references
echo "=== 1. DUPLICATE MERCHANT REFERENCES ===\n";
$dupes = DB::table('subscriptions')
    ->select('pesapal_merchant_reference', DB::raw('COUNT(*) as cnt'))
    ->whereNotNull('pesapal_merchant_reference')
    ->groupBy('pesapal_merchant_reference')
    ->having('cnt', '>', 1)
    ->orderBy('cnt', 'desc')
    ->limit(20)
    ->get();
echo "Duplicate merchant refs: " . count($dupes) . "\n";
foreach ($dupes as $d) {
    echo "  {$d->pesapal_merchant_reference}: {$d->cnt} copies\n";
}

// 2. Check subs with tracking ID vs without — timing analysis
echo "\n=== 2. TRACKING ID SUCCESS/FAIL TIMING ===\n";
$last200 = Subscription::orderBy('id', 'desc')->limit(200)->get();
$withTrack = $last200->filter(fn($s) => !empty($s->pesapal_tracking_id));
$withoutTrack = $last200->filter(fn($s) => empty($s->pesapal_tracking_id));

echo "Last 200 subs: " . $last200->count() . "\n";
echo "  With tracking ID: " . $withTrack->count() . "\n";
echo "  Without tracking ID: " . $withoutTrack->count() . "\n";

// Are they clustered by time?
echo "\nSuccess (with tracking) by hour:\n";
$byHour = $withTrack->groupBy(fn($s) => $s->created_at->format('Y-m-d H:00'));
foreach ($byHour->sortKeys() as $hour => $items) {
    echo "  {$hour}: " . count($items) . "\n";
}

echo "\nFail (no tracking) by hour:\n";
$byHour2 = $withoutTrack->groupBy(fn($s) => $s->created_at ? $s->created_at->format('Y-m-d H:00') : 'unknown');
foreach ($byHour2->sortKeys() as $hour => $items) {
    echo "  {$hour}: " . count($items) . "\n";
}

// 3. Check if SUCCESS subs have any common attribute
echo "\n=== 3. SUCCESSFUL (tracking ID) SUBS PROFILE ===\n";
foreach ($withTrack->take(10) as $s) {
    $user = $s->user;
    $uname = $user ? ($user->name ?: '?') : '?';
    $uemail = $user ? ($user->email ?: 'NONE') : 'NONE';
    echo "  #{$s->id} | User:{$s->user_id} ({$uname}) | Email:{$uemail} | App:{$s->app_type} | Plan:{$s->plan_id} | Amount:{$s->amount_paid} | MerchRef:{$s->pesapal_merchant_reference} | PayStatus:{$s->payment_status}\n";
}

// 4. Check if FAILED subs have any common attribute 
echo "\n=== 4. FAILED (no tracking ID) SUBS PROFILE ===\n";
foreach ($withoutTrack->where('status', 'Pending')->take(10) as $s) {
    $user = $s->user;
    $uname = $user ? ($user->name ?: '?') : '?';
    $uemail = $user ? ($user->email ?: 'NONE') : 'NONE';
    echo "  #{$s->id} | User:{$s->user_id} ({$uname}) | Email:{$uemail} | App:{$s->app_type} | Plan:{$s->plan_id} | Amount:{$s->amount_paid} | MerchRef:{$s->pesapal_merchant_reference}\n";
}

// 5. Check if SAME USERS succeed sometimes and fail other times
echo "\n=== 5. USERS WHO BOTH SUCCEEDED AND FAILED ===\n";
$successUsers = $withTrack->pluck('user_id')->unique();
$failUsers = $withoutTrack->pluck('user_id')->unique();
$bothUsers = $successUsers->intersect($failUsers);
echo "Users who succeeded: " . $successUsers->count() . "\n";
echo "Users who failed: " . $failUsers->count() . "\n";
echo "Users who had BOTH success and failure: " . $bothUsers->count() . "\n";
foreach ($bothUsers->take(5) as $uid) {
    $user = \App\Models\User::find($uid);
    $uname = $user ? ($user->name ?: '?') : '?';
    $userSubs = $last200->where('user_id', $uid);
    echo "  User #{$uid} ({$uname}): ";
    foreach ($userSubs as $s) {
        $hasTrack = !empty($s->pesapal_tracking_id) ? 'OK' : 'NO-TRACK';
        echo "#{$s->id}({$s->status}/{$hasTrack}) ";
    }
    echo "\n";
}

// 6. Let me try to simulate a payment initialization
echo "\n=== 6. SIMULATING PAYMENT INIT (like create() does) ===\n";
try {
    $service = app(\App\Services\SubscriptionPesapalService::class);
    echo "Service initialized OK\n";
    
    // Try authenticate
    $token = $service->authenticate();
    echo "Authentication OK (token: " . substr($token, 0, 20) . "...)\n";
    
    // Try IPN registration
    $ipnId = $service->registerIpnUrl();
    echo "IPN Registration OK (ID: {$ipnId})\n";
    
    // Try SubmitOrderRequest with a test payload
    // Use a recent failed subscription to see what error we get
    $testSub = $withoutTrack->where('status', 'Pending')->first();
    if ($testSub) {
        echo "Testing with Sub #{$testSub->id} (MerchRef: {$testSub->pesapal_merchant_reference})\n";
        
        // Try to initialize payment for this sub
        try {
            $result = $service->initializePayment($testSub, $ipnId);
            echo "SUCCESS! Redirect URL: " . ($result['redirect_url'] ?? 'NONE') . "\n";
            echo "Tracking ID: " . ($result['order_tracking_id'] ?? 'NONE') . "\n";
        } catch (\Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "Service init FAILED: " . $e->getMessage() . "\n";
}

// 7. Check production server response times (curl test)
echo "\n=== 7. PESAPAL API CONNECTIVITY TEST ===\n";
$urls = [
    'Auth' => 'https://pay.pesapal.com/v3/api/Auth/RequestToken',
    'IPN' => 'https://pay.pesapal.com/v3/api/URLSetup/GetIpnList',
];
foreach ($urls as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $start = microtime(true);
    curl_exec($ch);
    $elapsed = round((microtime(true) - $start) * 1000);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    echo "  {$name}: HTTP {$httpCode}, {$elapsed}ms" . ($error ? " ERROR: {$error}" : "") . "\n";
}

// 8. The overall 30-day success rate
echo "\n=== 8. 30-DAY OVERALL SUCCESS RATE ===\n";
$month = Subscription::where('created_at', '>=', now()->subDays(30))->get();
$monthTotal = $month->count();
$monthWithTrack = $month->filter(fn($s) => !empty($s->pesapal_tracking_id))->count();
$monthCompleted = $month->where('payment_status', 'Completed')->count();
$monthActive = $month->where('status', 'Active')->count();
echo "Total subs (30d): {$monthTotal}\n";
echo "Got payment URL: {$monthWithTrack} (" . ($monthTotal > 0 ? round($monthWithTrack/$monthTotal*100, 1) : 0) . "%)\n";
echo "Payment completed: {$monthCompleted} (" . ($monthTotal > 0 ? round($monthCompleted/$monthTotal*100, 1) : 0) . "%)\n";
echo "Currently active: {$monthActive}\n";
echo "Payment URL → Completed conversion: " . ($monthWithTrack > 0 ? round($monthCompleted/$monthWithTrack*100, 1) : 0) . "%\n";

echo "\n=== INVESTIGATION COMPLETE ===\n";
