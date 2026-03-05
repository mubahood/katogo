<?php
/**
 * Payment Analysis Script
 * Analyzes recent 100 subscriptions and transactions for failure patterns
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║      PAYMENT INVESTIGATION REPORT — " . date('Y-m-d H:i:s') . "     ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// ============================================================
// 1. SUBSCRIPTION OVERVIEW (Last 100)
// ============================================================
$subs = Subscription::orderBy('id', 'desc')->limit(100)->get();
$totalSubs = Subscription::count();

echo "═══ 1. SUBSCRIPTION OVERVIEW (Last 100 of {$totalSubs} total) ═══\n\n";

// Status breakdown
$byStatus = $subs->groupBy('status')->map->count()->sortDesc();
echo "Status breakdown:\n";
foreach ($byStatus as $status => $count) {
    $pct = round($count / $subs->count() * 100, 1);
    echo "  {$status}: {$count} ({$pct}%)\n";
}

// Payment status breakdown
echo "\nPayment Status breakdown:\n";
$byPayStatus = $subs->groupBy('payment_status')->map->count()->sortDesc();
foreach ($byPayStatus as $status => $count) {
    $pct = round($count / $subs->count() * 100, 1);
    echo "  {$status}: {$count} ({$pct}%)\n";
}

// App type
echo "\nBy App Type:\n";
$byApp = $subs->groupBy('app_type')->map->count()->sortDesc();
foreach ($byApp as $app => $count) {
    echo "  " . ($app ?: 'NULL') . ": {$count}\n";
}

// Platform
echo "\nBy Platform:\n";
$byPlatform = $subs->groupBy('platform')->map->count()->sortDesc();
foreach ($byPlatform as $plat => $count) {
    echo "  " . ($plat ?: 'NULL') . ": {$count}\n";
}

// Date range
$oldest = $subs->min('created_at');
$newest = $subs->max('created_at');
echo "\nDate Range: {$oldest} to {$newest}\n";

// ============================================================
// 2. FAILED & PENDING SUBSCRIPTIONS DETAIL
// ============================================================
echo "\n═══ 2. FAILED & PENDING SUBSCRIPTIONS — DETAILED ═══\n\n";

$failedPending = $subs->whereIn('status', ['Failed', 'Pending']);
echo "Total Failed/Pending in last 100: " . $failedPending->count() . "\n\n";

foreach ($failedPending as $s) {
    echo "--- Sub #{$s->id} ---\n";
    echo "  User ID: {$s->user_id}\n";
    echo "  Status: {$s->status} | Payment: {$s->payment_status}\n";
    echo "  App: " . ($s->app_type ?: 'NULL') . " | Platform: " . ($s->platform ?: 'NULL') . "\n";
    echo "  Amount: {$s->currency} {$s->amount_paid}\n";
    echo "  Plan ID: {$s->plan_id} | Days: {$s->days}\n";
    echo "  Tracking ID: " . ($s->pesapal_tracking_id ?: 'NONE') . "\n";
    echo "  Merchant Ref: " . ($s->pesapal_merchant_reference ?: 'NONE') . "\n";
    echo "  Payment URL: " . ($s->payment_url ? 'YES (' . strlen($s->payment_url) . ' chars)' : 'NONE') . "\n";
    echo "  Failed At: " . ($s->failed_at ?: 'NULL') . "\n";
    echo "  Failure Reason: " . ($s->payment_failure_reason ?: 'NULL') . "\n";
    echo "  Cancel Reason: " . ($s->cancelled_reason ?: 'NULL') . "\n";
    
    // Check pesapal_response
    $resp = $s->pesapal_response;
    if ($resp) {
        echo "  Pesapal Response: " . json_encode($resp, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "  Pesapal Response: NULL\n";
    }
    
    echo "  Created: {$s->created_at} | Updated: {$s->updated_at}\n";
    echo "  Start: " . ($s->start_date_time ?: 'NULL') . " | End: " . ($s->end_date_time ?: 'NULL') . "\n";
    echo "\n";
}

// ============================================================
// 3. COMPLETED SUBSCRIPTIONS (for comparison)
// ============================================================
echo "\n═══ 3. COMPLETED SUBSCRIPTIONS (Last 20) ═══\n\n";

$completed = $subs->where('payment_status', 'Completed')->take(20);
echo "Total Completed in last 100: " . $subs->where('payment_status', 'Completed')->count() . "\n\n";

foreach ($completed as $s) {
    echo "  #{$s->id} | User:{$s->user_id} | App:{$s->app_type} | {$s->currency} {$s->amount_paid} | Confirmed:{$s->payment_confirmed_at} | Start:{$s->start_date_time} | End:{$s->end_date_time} | Created:{$s->created_at}\n";
}

// ============================================================
// 4. TRANSACTION RECORDS (Last 100)
// ============================================================
echo "\n═══ 4. TRANSACTION RECORDS (Last 100) ═══\n\n";

$transactions = SubscriptionTransaction::orderBy('id', 'desc')->limit(100)->get();
$totalTx = SubscriptionTransaction::count();

echo "Total transactions: {$totalTx}\n";
echo "Last 100 breakdown:\n";

$txByStatus = $transactions->groupBy('status')->map->count()->sortDesc();
foreach ($txByStatus as $status => $count) {
    echo "  {$status}: {$count}\n";
}

echo "\nFailed/Error Transactions:\n";
$failedTx = $transactions->whereIn('status', ['Failed', 'Error', 'Cancelled']);
foreach ($failedTx as $tx) {
    echo "  TX#{$tx->id} | Sub:{$tx->subscription_id} | User:{$tx->user_id} | Status:{$tx->status} | Amount:{$tx->currency} {$tx->amount} | Method:{$tx->payment_method} | Error:{$tx->error_message} | TrackID:{$tx->pesapal_tracking_id} | Created:{$tx->created_at}\n";
    if ($tx->response_payload) {
        echo "    Response: " . json_encode($tx->response_payload) . "\n";
    }
}

// ============================================================
// 5. PATTERN ANALYSIS
// ============================================================
echo "\n═══ 5. PATTERN ANALYSIS ═══\n\n";

// Check for subscriptions with no tracking ID
$noTrackingId = $failedPending->where('pesapal_tracking_id', null)->count();
$noTrackingIdAll = $failedPending->filter(function($s) { return empty($s->pesapal_tracking_id); })->count();
echo "Failed/Pending with NO Pesapal Tracking ID: {$noTrackingIdAll}\n";

// Check for subscriptions with no payment URL
$noPayUrl = $failedPending->filter(function($s) { return empty($s->payment_url); })->count();
echo "Failed/Pending with NO Payment URL: {$noPayUrl}\n";

// Check for subscriptions with no merchant reference  
$noMerchRef = $failedPending->filter(function($s) { return empty($s->pesapal_merchant_reference); })->count();
echo "Failed/Pending with NO Merchant Reference: {$noMerchRef}\n";

// Check user duplication — same user with multiple failed attempts
$userFailCounts = $failedPending->groupBy('user_id')->map->count()->filter(function($c) { return $c > 1; })->sortDesc();
if ($userFailCounts->count() > 0) {
    echo "\nUsers with MULTIPLE failed/pending subscriptions:\n";
    foreach ($userFailCounts as $userId => $count) {
        $user = \App\Models\User::find($userId);
        echo "  User #{$userId} (" . ($user->name ?? 'Unknown') . " / " . ($user->email ?? 'N/A') . "): {$count} attempts\n";
    }
}

// Time-based analysis
echo "\nFailed/Pending by date:\n";
$byDate = $failedPending->groupBy(function($s) {
    return $s->created_at ? $s->created_at->format('Y-m-d') : 'unknown';
})->map->count()->sortKeys();
foreach ($byDate as $date => $count) {
    echo "  {$date}: {$count}\n";
}

// Check for common cancel/failure reasons
echo "\nCancel/Failure reasons breakdown:\n";
$reasons = $failedPending->groupBy('cancelled_reason')->map->count()->sortDesc();
foreach ($reasons as $reason => $count) {
    echo "  " . ($reason ?: 'NULL/Empty') . ": {$count}\n";
}

// ============================================================
// 6. CHECK FOR MISMATCHED RECORDS
// ============================================================
echo "\n═══ 6. DATA INTEGRITY CHECKS ═══\n\n";

// Active subs with no end_date_time
$activeNoEnd = Subscription::where('status', 'Active')->whereNull('end_date_time')->count();
echo "Active subscriptions with NO end_date_time: {$activeNoEnd}\n";

// Active subs with no start_date_time
$activeNoStart = Subscription::where('status', 'Active')->whereNull('start_date_time')->count();
echo "Active subscriptions with NO start_date_time: {$activeNoStart}\n";

// Completed payment but not Active
$completedNotActive = Subscription::where('payment_status', 'Completed')->where('status', '!=', 'Active')->count();
echo "Completed payment but NOT Active status: {$completedNotActive}\n";

// Active but not Completed payment
$activeNotPaid = Subscription::where('status', 'Active')->where('payment_status', '!=', 'Completed')->count();
echo "Active status but NOT Completed payment: {$activeNotPaid}\n";

// Processing for too long (>1 hour)
$staleProcessing = Subscription::where('payment_status', 'Processing')
    ->where('updated_at', '<', now()->subHour())
    ->count();
echo "Processing for >1 hour (stale): {$staleProcessing}\n";

// Pending for too long (>24 hours)
$stalePending = Subscription::where('payment_status', 'Pending')
    ->where('created_at', '<', now()->subDay())
    ->count();
echo "Pending for >24 hours (stale): {$stalePending}\n";

// ============================================================
// 7. ENV / CONFIG CHECK
// ============================================================
echo "\n═══ 7. ENVIRONMENT CONFIG CHECK ═══\n\n";

echo "APP_ENV: " . config('app.env') . "\n";
echo "APP_DEBUG: " . (config('app.debug') ? 'TRUE' : 'FALSE') . "\n";
echo "APP_URL: " . config('app.url') . "\n";
echo "APP_PRODUCTION_URL: " . config('app.production_url', 'NOT SET') . "\n";

// Pesapal config
echo "\nPESAPAL_CONSUMER_KEY: " . (config('services.pesapal.consumer_key') ? 'SET (' . strlen(config('services.pesapal.consumer_key')) . ' chars)' : 'NOT SET') . "\n";
echo "PESAPAL_CONSUMER_SECRET: " . (config('services.pesapal.consumer_secret') ? 'SET (' . strlen(config('services.pesapal.consumer_secret')) . ' chars)' : 'NOT SET') . "\n";
echo "PESAPAL_ENVIRONMENT: " . (config('services.pesapal.environment') ?? env('PESAPAL_ENVIRONMENT', 'NOT SET')) . "\n";
echo "PESAPAL_IPN_URL: " . env('PESAPAL_IPN_URL', 'NOT SET') . "\n";
echo "PESAPAL_CALLBACK_URL: " . env('PESAPAL_CALLBACK_URL', 'NOT SET') . "\n";

// ============================================================
// 8. PESAPAL RESPONSE DATA ANALYSIS
// ============================================================
echo "\n═══ 8. PESAPAL RESPONSE DATA (from saved responses) ═══\n\n";

$withResponses = $subs->filter(function($s) { return !empty($s->pesapal_response); });
echo "Subscriptions with Pesapal response data: " . $withResponses->count() . "\n\n";

foreach ($withResponses->take(15) as $s) {
    echo "  Sub #{$s->id} ({$s->status}/{$s->payment_status}):\n";
    $resp = $s->pesapal_response;
    if (isset($resp['status_check'])) {
        $sc = $resp['status_check'];
        echo "    Status Code: " . ($sc['status_code'] ?? $sc['payment_status_code'] ?? 'N/A') . "\n";
        echo "    Status Desc: " . ($sc['payment_status_description'] ?? $sc['status'] ?? 'N/A') . "\n";
        echo "    Payment Method: " . ($sc['payment_method'] ?? 'N/A') . "\n";
        echo "    Description: " . ($sc['description'] ?? 'N/A') . "\n";
        echo "    Message: " . ($sc['message'] ?? 'N/A') . "\n";
        echo "    Error: " . (isset($sc['error']) ? json_encode($sc['error']) : 'N/A') . "\n";
    } else {
        echo "    Raw: " . json_encode($resp) . "\n";
    }
    echo "\n";
}

echo "\n═══ INVESTIGATION COMPLETE ═══\n";
