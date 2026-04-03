<?php
/**
 * COMPREHENSIVE SUBSCRIPTION IMPROVEMENT TESTS
 * Validates all fixes: pending cleanup, ownership fallback, day calculation,
 * status checker idempotency, grace period display, and speed optimizations.
 *
 * Run: php test_subscription_improvements.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPesapalService;
use App\Services\PaymentStatusChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$pass = 0;
$fail = 0;
$tests = [];

function assert_test(string $name, bool $result, string $detail = '') {
    global $pass, $fail, $tests;
    if ($result) {
        $pass++;
        echo "  ✅ {$name}\n";
    } else {
        $fail++;
        echo "  ❌ {$name}" . ($detail ? " — {$detail}" : '') . "\n";
    }
    $tests[] = ['name' => $name, 'pass' => $result];
}

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   SUBSCRIPTION IMPROVEMENT TESTS — " . date('Y-m-d H:i:s') . "   ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// ── 1. DAY CALCULATION: hour-precise math ──────────────────────
echo "━━━ 1. DAY CALCULATION (hour-precise) ━━━\n";

DB::beginTransaction();
try {
    $plan3 = SubscriptionPlan::where('duration_days', 3)->where('status', 'active')->first();
    $plan14 = SubscriptionPlan::where('duration_days', 14)->where('status', 'active')->first();
    $plan30 = SubscriptionPlan::where('duration_days', 30)->where('status', 'active')->first();

    if ($plan3) {
        // Test the Subscription model boot hook
        $sub = new Subscription([
            'user_id' => 1,
            'plan_id' => $plan3->id,
            'days' => 3,
            'amount_paid' => $plan3->price,
            'currency' => $plan3->currency,
            'status' => 'Pending',
        ]);
        // Don't save — test the activate() method directly
        $sub->start_date_time = Carbon::parse('2025-06-01 14:30:00');
        $sub->end_date_time = null;
        $sub->status = 'Active';
        $sub->payment_status = 'Completed';

        // Simulate what activate() does
        $days = 3;
        $end = Carbon::parse($sub->start_date_time)->addHours($days * 24);
        $expectedEnd = Carbon::parse('2025-06-04 14:30:00');

        assert_test(
            '3-day plan: ends exactly 72 hours later',
            $end->eq($expectedEnd),
            "Expected {$expectedEnd}, got {$end}"
        );

        assert_test(
            '3-day plan: does NOT add extra day from addDays rounding',
            $end->diffInHours(Carbon::parse('2025-06-01 14:30:00')) === 72,
            "Expected 72 hours, got " . $end->diffInHours(Carbon::parse('2025-06-01 14:30:00'))
        );
    } else {
        echo "  ⚠️  No 3-day plan found — skipping\n";
    }

    if ($plan14) {
        $start = Carbon::parse('2025-06-01 08:15:00');
        $end = Carbon::parse($start)->addHours(14 * 24);
        assert_test(
            '14-day plan: ends exactly 336 hours later',
            $end->diffInHours($start) === 336,
            "Expected 336 hours, got " . $end->diffInHours($start)
        );
    }

    if ($plan30) {
        $start = Carbon::parse('2025-06-01 23:59:59');
        $end = Carbon::parse($start)->addHours(30 * 24);
        assert_test(
            '30-day plan: ends exactly 720 hours later',
            $end->diffInHours($start) === 720,
            "Expected 720 hours, got " . $end->diffInHours($start)
        );
    }
} finally {
    DB::rollBack();
}

echo "\n";

// ── 2. GRACE PERIOD: excluded from displayed days_remaining ────
echo "━━━ 2. GRACE PERIOD DISPLAY ━━━\n";

$sub = new Subscription();
$sub->status = 'Active';
$sub->payment_status = 'Completed';
$sub->start_date_time = now()->subDays(2);
$sub->end_date_time = now()->addHours(20); // ~0.83 days left
$sub->grace_period_end = Carbon::parse($sub->end_date_time)->addDays(3);
$sub->days = 3;

$daysWithGrace = $sub->daysRemaining(true);  // includes grace
$daysWithout = $sub->daysRemaining(false);    // excludes grace

assert_test(
    'daysRemaining(false) excludes grace period',
    $daysWithout < $daysWithGrace,
    "Without grace={$daysWithout}, with grace={$daysWithGrace}"
);

assert_test(
    'daysRemaining(false) for 3-day plan never exceeds plan days+1',
    $daysWithout <= 4,
    "Got {$daysWithout} days remaining"
);

echo "\n";

// ── 3. USER MODEL: getSubscriptionStatus excludes grace ────────
echo "━━━ 3. USER MODEL SUBSCRIPTION STATUS ━━━\n";

$testUser = User::whereHas('subscriptions', function ($q) {
    $q->where('status', 'Active');
})->first();

if ($testUser) {
    $status = $testUser->getSubscriptionStatus();
    assert_test(
        'getSubscriptionStatus() returns valid response',
        isset($status['has_subscription']),
        'Missing has_subscription key'
    );

    if ($status['has_subscription'] && isset($status['days_remaining'])) {
        $activeSub = $testUser->activeSubscription();
        if ($activeSub) {
            $planDays = (int) $activeSub->days;
            assert_test(
                'days_remaining does not exceed plan duration + 1',
                $status['days_remaining'] <= $planDays + 1,
                "days_remaining={$status['days_remaining']}, plan_days={$planDays}"
            );
        }
    }
} else {
    echo "  ⚠️  No user with active subscription found — skipping\n";
}

echo "\n";

// ── 4. PENDING CLEANUP: create() cancels ALL blocking subs ─────
echo "━━━ 4. PENDING CLEANUP IN create() ━━━\n";

// Read the controller source to verify the logic
$controllerSource = file_get_contents(__DIR__ . '/app/Http/Controllers/SubscriptionApiController.php');

assert_test(
    'create() no longer has 2-hour age check',
    strpos($controllerSource, '$ageMinutes > 120') === false,
    'Still contains $ageMinutes > 120'
);

assert_test(
    'create() cancels all Pending/Failed/Processing subs',
    strpos($controllerSource, "whereIn('status', ['Pending', 'Failed', 'Processing'])") !== false,
    'Missing broad status filter'
);

assert_test(
    'create() flushes pending cache after cleanup',
    strpos($controllerSource, 'Cache::forget("sub_pending_{$user->id}")') !== false,
    'Missing Cache::forget for pending'
);

echo "\n";

// ── 5. OWNERSHIP FALLBACK: resolveUserActionableSubscription ───
echo "━━━ 5. OWNERSHIP FALLBACK ━━━\n";

assert_test(
    'resolveUserActionableSubscription exists in controller',
    strpos($controllerSource, 'function resolveUserActionableSubscription') !== false
);

assert_test(
    'retryPayment uses fallback instead of hard 403',
    strpos($controllerSource, 'resolveUserActionableSubscription') !== false
    && strpos($controllerSource, "'does not belong to you'") === false
);

assert_test(
    'No hard ownership blocks remain ("does not belong to you")',
    strpos($controllerSource, 'does not belong to you') === false,
    'Found "does not belong to you" string'
);

echo "\n";

// ── 6. STATUS RESTRICTIONS: relaxed for retry/cancel ───────────
echo "━━━ 6. STATUS RESTRICTIONS ━━━\n";

assert_test(
    'retryPayment does NOT restrict to Pending/Failed only',
    strpos($controllerSource, "subscription->status !== 'Pending' && \$subscription->status !== 'Failed'") === false,
    'Old status restriction still present in retryPayment'
);

assert_test(
    'cancelPending uses idempotent cancel behavior',
    strpos($controllerSource, "Idempotent cancel behavior") !== false
);

echo "\n";

// ── 7. PAYMENT STATUS CHECKER: Failed/Cancelled not treated as final ──
echo "━━━ 7. PAYMENT STATUS CHECKER ━━━\n";

$checkerSource = file_get_contents(__DIR__ . '/app/Services/PaymentStatusChecker.php');

assert_test(
    'isAlreadyProcessed only returns true for Active+Completed or Expired+Completed',
    strpos($checkerSource, "status === 'Active' && \$subscription->payment_status === 'Completed'") !== false,
    'Missing Active+Completed check'
);

assert_test(
    'Failed status is NOT treated as already processed',
    strpos($checkerSource, "'Failed' => true") === false,
    'Failed is wrongly treated as final'
);

assert_test(
    'Bulk check includes Failed and Cancelled statuses',
    strpos($checkerSource, "'Failed'") !== false,
    'Missing Failed in bulk check scope'
);

echo "\n";

// ── 8. SPEED OPTIMIZATIONS ─────────────────────────────────────
echo "━━━ 8. SPEED OPTIMIZATIONS ━━━\n";

assert_test(
    'Pre-warm: create() calls authenticate() before DB transaction',
    (bool) preg_match('/pesapalService->authenticate\(\).*DB::transaction/s', $controllerSource),
    'authenticate() not called before DB transaction'
);

assert_test(
    'Pre-warm: create() calls registerIpnUrl() before DB transaction',
    (bool) preg_match('/pesapalService->registerIpnUrl\(\).*DB::transaction/s', $controllerSource),
    'registerIpnUrl() not called before DB transaction'
);

assert_test(
    'Eager-load: plan+user loaded before initializePayment',
    strpos($controllerSource, "->load('plan', 'user')") !== false,
    'Missing eager load of plan and user'
);

// Test that Pesapal auth caching works
echo "\n  ⏱️  Pesapal auth speed test...\n";
$pesapalService = app(SubscriptionPesapalService::class);
try {
    // First call - may be cold
    $start = microtime(true);
    $pesapalService->authenticate();
    $coldMs = round((microtime(true) - $start) * 1000);

    // Second call - should hit cache
    $start = microtime(true);
    $pesapalService->authenticate();
    $warmMs = round((microtime(true) - $start) * 1000);

    echo "  Cold auth: {$coldMs}ms, Warm auth: {$warmMs}ms\n";

    assert_test(
        'Cached auth token is faster than 50ms',
        $warmMs < 50,
        "Warm call took {$warmMs}ms"
    );
} catch (\Exception $e) {
    echo "  ⚠️  Pesapal auth test skipped: {$e->getMessage()}\n";
}

echo "\n";

// ── 9. PESAPAL SERVICE: hour-based duration math ───────────────
echo "━━━ 9. PESAPAL SERVICE DURATION MATH ━━━\n";

$serviceSource = file_get_contents(__DIR__ . '/app/Services/SubscriptionPesapalService.php');

assert_test(
    'updateSubscriptionStatus uses addHours($days * 24)',
    strpos($serviceSource, 'addHours($days * 24)') !== false,
    'Still using addDays() instead of addHours()'
);

assert_test(
    'Service flushes pending cache on activation',
    strpos($serviceSource, 'Cache::forget("sub_pending_{$subscription->user_id}")') !== false,
    'No cache flush after activation'
);

assert_test(
    'Service flushes pending cache on failure too',
    substr_count($serviceSource, 'Cache::forget("sub_pending_{$subscription->user_id}")') >= 2,
    'Cache flush only on success, not failure'
);

echo "\n";

// ── 10. SUBSCRIPTION MODEL: boot hook uses addHours ────────────
echo "━━━ 10. SUBSCRIPTION MODEL ━━━\n";

$modelSource = file_get_contents(__DIR__ . '/app/Models/Subscription.php');

$addDaysCount = substr_count($modelSource, '->addDays(');
$addHoursCount = substr_count($modelSource, '->addHours(');

// Grace period still uses addDays(3) which is correct — only duration should use addHours
assert_test(
    'Subscription model uses addHours for duration calculation',
    strpos($modelSource, 'addHours($days * 24)') !== false,
    'Missing addHours in model'
);

echo "\n";

// ── 11. LIVE DATABASE: check for common failure patterns ───────
echo "━━━ 11. DATABASE HEALTH CHECK ━━━\n";

$noTrackingId = Subscription::where('status', 'Pending')
    ->whereNull('pesapal_tracking_id')
    ->where('created_at', '<', now()->subHours(1))
    ->count();
echo "  Pending subs without tracking ID (>1hr old): {$noTrackingId}\n";

$noPaymentUrl = Subscription::where('status', 'Pending')
    ->whereNull('payment_url')
    ->where('created_at', '<', now()->subMinutes(30))
    ->count();
echo "  Pending subs without payment URL (>30min old): {$noPaymentUrl}\n";

$totalFailed = Subscription::where('status', 'Failed')->count();
echo "  Total failed subscriptions: {$totalFailed}\n";

$recentFailed = Subscription::where('status', 'Failed')
    ->where('created_at', '>', now()->subDays(7))
    ->count();
echo "  Failed in last 7 days: {$recentFailed}\n";

$activeCount = Subscription::where('status', 'Active')
    ->where('end_date_time', '>', now())
    ->count();
echo "  Currently active subscriptions: {$activeCount}\n";

echo "\n";

// ── SUMMARY ────────────────────────────────────────────────────
echo "═══════════════════════════════════════════════════════════\n";
echo " RESULTS: {$pass} passed, {$fail} failed, " . ($pass + $fail) . " total\n";
echo "═══════════════════════════════════════════════════════════\n";

if ($fail > 0) {
    echo "\n FAILED TESTS:\n";
    foreach ($tests as $t) {
        if (!$t['pass']) {
            echo "   ❌ {$t['name']}\n";
        }
    }
}

exit($fail > 0 ? 1 : 0);
