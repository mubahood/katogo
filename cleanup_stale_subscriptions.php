<?php
/**
 * One-time cleanup: Fix stale Pending subscriptions that never got a payment URL
 * These accumulated due to initializePayment() failures without proper cleanup.
 *
 * Run with: php cleanup_stale_subscriptions.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;

echo "=== STALE SUBSCRIPTION CLEANUP ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Find all Pending subscriptions with no tracking ID (dead subscriptions)
$staleNoTrack = Subscription::where('status', 'Pending')
    ->whereIn('payment_status', ['Pending', 'Processing'])
    ->whereNull('pesapal_tracking_id')
    ->get();

echo "Found {$staleNoTrack->count()} stale Pending subscriptions with no tracking ID\n";

$cleaned = 0;
foreach ($staleNoTrack as $sub) {
    $sub->status = 'Failed';
    $sub->payment_status = 'Failed';
    $sub->failed_at = $sub->updated_at ?? now();
    $sub->cancelled_reason = 'Cleanup: payment initialization never completed (no tracking ID)';
    $sub->payment_failure_reason = 'System cleanup: subscription was created but Pesapal payment URL was never generated. The payment gateway was likely unreachable at the time of creation.';
    // Keep start/end dates as-is (NOT NULL constraint)
    $sub->save();
    $cleaned++;
}

echo "Cleaned up {$cleaned} dead subscriptions (marked as Failed)\n";

// 2. Find Processing subscriptions older than 24 hours (stuck)
$staleProcessing = Subscription::where('payment_status', 'Processing')
    ->where('status', 'Pending')
    ->where('updated_at', '<', now()->subHours(24))
    ->get();

echo "\nFound {$staleProcessing->count()} stale Processing subscriptions (>24h old, still Pending)\n";

$cleaned2 = 0;
foreach ($staleProcessing as $sub) {
    // Only cancel if no successful payment confirmation
    if (empty($sub->payment_confirmed_at)) {
        $sub->status = 'Failed';
        $sub->payment_status = 'Failed';
        $sub->failed_at = $sub->updated_at ?? now();
        $sub->cancelled_reason = 'Cleanup: payment processing timed out (>24 hours)';
        $sub->payment_failure_reason = 'System cleanup: payment was in Processing status for over 24 hours without completion.';
        // Keep start/end dates as-is (NOT NULL constraint)
        $sub->save();
        $cleaned2++;
    }
}

echo "Cleaned up {$cleaned2} stale Processing subscriptions\n";

// 3. Summary stats
echo "\n=== POST-CLEANUP STATS ===\n";
$stats = Subscription::selectRaw("status, payment_status, COUNT(*) as cnt")
    ->groupBy('status', 'payment_status')
    ->orderBy('cnt', 'desc')
    ->get();

foreach ($stats as $s) {
    echo "  {$s->status}/{$s->payment_status}: {$s->cnt}\n";
}

echo "\nDone!\n";
