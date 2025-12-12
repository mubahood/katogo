#!/usr/bin/env php
<?php

/**
 * Final Verification - Fix Success Check
 * 
 * Quick script to verify the fix is working correctly
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TrendingNotification;
use Carbon\Carbon;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║         TRENDING NOTIFICATION FIX - VERIFICATION                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Check today's notifications
$today = Carbon::now()->startOfDay();
$todayNotifications = TrendingNotification::where('created_at', '>=', $today)
    ->orderBy('day_time')
    ->get();

echo "📅 Today's Date: " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";

echo "📋 TODAY'S NOTIFICATIONS:\n";
echo str_repeat("-", 70) . "\n";

if ($todayNotifications->isEmpty()) {
    echo "⚠️  No notifications found for today.\n";
    echo "   Run: php force_create_trending_notifications.php\n\n";
} else {
    $uniqueMovies = $todayNotifications->pluck('movie_model_id')->unique()->count();
    $totalNotifications = $todayNotifications->count();
    
    foreach ($todayNotifications as $notif) {
        $status = $notif->is_sent == 'Yes' ? '✅ SENT' : '⏳ PENDING';
        echo sprintf("  %-10s | %-45s | %s\n", 
            strtoupper($notif->day_time), 
            substr($notif->title, 0, 45),
            $status
        );
    }
    
    echo str_repeat("-", 70) . "\n";
    echo "Total: {$totalNotifications} notifications | Unique movies: {$uniqueMovies}\n\n";
    
    // Check diversity
    if ($uniqueMovies == $totalNotifications) {
        echo "✅ EXCELLENT: Each time period has a different movie!\n";
    } elseif ($uniqueMovies >= $totalNotifications * 0.75) {
        echo "✅ GOOD: Most time periods have different movies.\n";
    } else {
        echo "⚠️  WARNING: Low diversity - same movie used multiple times.\n";
    }
}

echo "\n";

// Check last 7 days diversity
$last7Days = TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(7))
    ->get();

$uniqueLast7 = $last7Days->pluck('movie_model_id')->unique()->count();
$totalLast7 = $last7Days->count();

echo "📊 LAST 7 DAYS STATISTICS:\n";
echo str_repeat("-", 70) . "\n";
echo "  Total notifications: {$totalLast7}\n";
echo "  Unique movies: {$uniqueLast7}\n";
echo "  Diversity ratio: " . ($totalLast7 > 0 ? round(($uniqueLast7 / $totalLast7) * 100, 1) : 0) . "%\n\n";

if ($uniqueLast7 >= 7) {
    echo "✅ EXCELLENT: Good rotation with {$uniqueLast7} different movies!\n";
} elseif ($uniqueLast7 >= 3) {
    echo "✅ GOOD: Moderate diversity with {$uniqueLast7} different movies.\n";
} else {
    echo "⚠️  WARNING: Low diversity - only {$uniqueLast7} different movies in 7 days.\n";
}

echo "\n";

// Overall status
echo str_repeat("=", 70) . "\n";
echo "OVERALL STATUS\n";
echo str_repeat("=", 70) . "\n\n";

$allGood = true;

if ($todayNotifications->isEmpty()) {
    echo "❌ No notifications for today - needs setup\n";
    $allGood = false;
} elseif ($todayNotifications->pluck('movie_model_id')->unique()->count() < $todayNotifications->count()) {
    echo "⚠️  Today has duplicate movies - acceptable but not ideal\n";
} else {
    echo "✅ Today's notifications are diverse\n";
}

if ($uniqueLast7 >= 5) {
    echo "✅ Last 7 days show good rotation ({$uniqueLast7} unique movies)\n";
} else {
    echo "⚠️  Last 7 days show low rotation ({$uniqueLast7} unique movies)\n";
    $allGood = false;
}

echo "\n";

if ($allGood) {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║              🎉 FIX IS WORKING CORRECTLY! 🎉                       ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
} else {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║          ⚠️  SOME ISSUES DETECTED - CHECK ABOVE                    ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
}

echo "\n";
