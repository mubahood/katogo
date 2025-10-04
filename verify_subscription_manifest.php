<?php

/**
 * Subscription Manifest Verification Script
 * 
 * Run this script to verify subscription manifest data is correct
 * 
 * Usage: php artisan tinker < verify_subscription_manifest.php
 */

echo "====================================\n";
echo "Subscription Manifest Verification\n";
echo "====================================\n\n";

// Get a user with active subscription (replace with actual user ID)
$userId = 1; // Change this to test user ID

$user = \App\Models\User::find($userId);

if (!$user) {
    echo "❌ Error: User not found (ID: $userId)\n";
    exit(1);
}

echo "Testing User: {$user->name} (ID: {$user->id})\n";
echo "Email: {$user->email}\n\n";

// Get subscription status
echo "------------------------------------\n";
echo "1. Testing getSubscriptionStatus()\n";
echo "------------------------------------\n";

$status = $user->getSubscriptionStatus();

echo "Raw Response:\n";
print_r($status);
echo "\n";

// Check for critical fields
$checks = [
    'has_subscription' => isset($status['has_subscription']),
    'has_active_subscription' => isset($status['has_active_subscription']),
    'days_remaining' => isset($status['days_remaining']),
    'hours_remaining' => isset($status['hours_remaining']),
    'status' => isset($status['status']),
    'is_in_grace_period' => isset($status['is_in_grace_period']),
];

echo "Field Checks:\n";
foreach ($checks as $field => $exists) {
    $icon = $exists ? '✅' : '❌';
    echo "$icon $field: " . ($exists ? 'Present' : 'MISSING') . "\n";
}
echo "\n";

// Validate consistency
echo "------------------------------------\n";
echo "2. Validating Data Consistency\n";
echo "------------------------------------\n";

$has_active = $status['has_active_subscription'] ?? false;
$days_remaining = $status['days_remaining'] ?? 0;
$subscription_status = $status['status'] ?? 'Unknown';

echo "has_active_subscription: " . ($has_active ? 'true' : 'false') . "\n";
echo "days_remaining: $days_remaining\n";
echo "subscription_status: $subscription_status\n\n";

// Check for inconsistencies
$inconsistent = false;

if (($days_remaining > 0 || $subscription_status === 'Active') && !$has_active) {
    echo "🚨 CRITICAL INCONSISTENCY DETECTED!\n";
    echo "   - days_remaining is $days_remaining (> 0)\n";
    echo "   - subscription_status is '$subscription_status'\n";
    echo "   - BUT has_active_subscription is FALSE!\n";
    echo "   This is WRONG and will cause issues.\n\n";
    $inconsistent = true;
} else {
    echo "✅ Data is CONSISTENT\n\n";
}

// Get active subscription directly
echo "------------------------------------\n";
echo "3. Checking Active Subscription\n";
echo "------------------------------------\n";

$subscription = $user->activeSubscription();

if ($subscription) {
    echo "✅ Active subscription found:\n";
    echo "   ID: {$subscription->id}\n";
    echo "   Status: {$subscription->status}\n";
    echo "   Payment Status: {$subscription->payment_status}\n";
    echo "   Start: {$subscription->start_date_time}\n";
    echo "   End: {$subscription->end_date_time}\n";
    echo "   Grace Period End: {$subscription->grace_period_end}\n";
    echo "   Days Remaining: {$subscription->daysRemaining(true)}\n";
    echo "   Hours Remaining: {$subscription->hoursRemaining()}\n";
    echo "   Is Active: " . ($subscription->isActive(true) ? 'true' : 'false') . "\n";
    echo "   In Grace Period: " . ($subscription->isInGracePeriod() ? 'true' : 'false') . "\n\n";
} else {
    echo "ℹ️  No active subscription found\n\n";
}

// Final verdict
echo "====================================\n";
echo "Final Verdict\n";
echo "====================================\n";

if ($inconsistent) {
    echo "❌ FAILED: Data inconsistency detected\n";
    echo "   Action Required: Fix the getSubscriptionStatus() method\n";
    exit(1);
} else {
    echo "✅ PASSED: All checks successful\n";
    echo "   Subscription manifest data is correct\n";
    exit(0);
}
