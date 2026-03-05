<?php
/**
 * Initialize a REAL test payment and output the payment link.
 * Run with: php init_test_payment.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPesapalService;

echo "\n=== INITIALIZING REAL TEST PAYMENT ===\n\n";

// Use cheapest plan (1000 UGX = ~$0.27)
$plan = SubscriptionPlan::where('status', 'active')->where('price', '>', 0)->orderBy('price')->first();
echo "Plan: {$plan->name} - {$plan->price} {$plan->currency} / {$plan->duration_days} days\n";

// Use a recent user
$user = User::orderBy('id', 'desc')->first();
echo "User: #{$user->id} {$user->name} ({$user->email})\n\n";

// Cancel any existing pending subs for this user first
$pending = Subscription::where('user_id', $user->id)
    ->where('status', 'Pending')
    ->get();
foreach ($pending as $p) {
    $p->status = 'Cancelled';
    $p->payment_status = 'Failed';
    $p->cancelled_reason = 'Cancelled for new test payment';
    $p->save();
    echo "Cancelled old pending sub #{$p->id}\n";
}

// Create subscription
$subscription = $user->createSubscription($plan);
echo "\nSubscription created: #{$subscription->id}\n";
echo "Merchant Ref: {$subscription->pesapal_merchant_reference}\n";

// Initialize payment
$pesapalService = app(SubscriptionPesapalService::class);
$initMethod = new ReflectionMethod($pesapalService, 'initializePayment');
$initMethod->setAccessible(true);

echo "Initializing payment with Pesapal...\n\n";
$result = $initMethod->invoke($pesapalService, $subscription);

$subscription->refresh();

if ($result && isset($result['redirect_url'])) {
    echo "========================================\n";
    echo "  PAYMENT LINK (click to pay):\n";
    echo "========================================\n\n";
    echo "  " . $result['redirect_url'] . "\n\n";
    echo "========================================\n";
    echo "  Tracking ID: " . $result['order_tracking_id'] . "\n";
    echo "  Amount: {$plan->price} {$plan->currency}\n";
    echo "  Sub ID: #{$subscription->id}\n";
    echo "========================================\n";
} else {
    echo "FAILED: " . json_encode($result) . "\n";
}
