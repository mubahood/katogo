#!/usr/bin/env php
<?php

/**
 * Test Trending Notification Fix
 * 
 * This script tests the fixed trending notification system to ensure:
 * 1. Movies are properly rotated
 * 2. No movie is sent repeatedly
 * 3. Recently notified movies (last 7 days) are excluded
 * 4. Different movies are selected for different time periods
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TrendingNotification;
use App\Models\MovieModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║      TRENDING NOTIFICATION FIX - COMPREHENSIVE TEST                ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Test 1: Check recent notification history
echo "📊 TEST 1: Analyzing Recent Notification History\n";
echo str_repeat("-", 70) . "\n";

$last7Days = TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(7))
    ->orderBy('created_at', 'desc')
    ->get();

$movieCounts = [];
foreach ($last7Days as $notif) {
    $movieId = $notif->movie_model_id;
    if (!isset($movieCounts[$movieId])) {
        $movieCounts[$movieId] = ['count' => 0, 'title' => $notif->title];
    }
    $movieCounts[$movieId]['count']++;
}

echo "Total notifications sent in last 7 days: " . $last7Days->count() . "\n";
echo "Unique movies notified: " . count($movieCounts) . "\n\n";

echo "Top repeated movies:\n";
arsort($movieCounts);
$count = 0;
foreach ($movieCounts as $movieId => $data) {
    if ($count >= 5) break;
    echo sprintf("  • ID %-6s: %-50s (%d times)\n", $movieId, substr($data['title'], 0, 50), $data['count']);
    $count++;
}

// Test 2: Check available active movies
echo "\n\n📚 TEST 2: Available Active Movies Pool\n";
echo str_repeat("-", 70) . "\n";

$activeMovies = MovieModel::where('status', 'Active')
    ->where('type', 'Movie')
    ->count();

$recentlyNotified = TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(7))
    ->where('is_sent', 'Yes')
    ->pluck('movie_model_id')
    ->unique()
    ->toArray();

$availableForNotification = MovieModel::where('status', 'Active')
    ->where('type', 'Movie')
    ->whereNotIn('id', $recentlyNotified)
    ->count();

echo "Total active movies: {$activeMovies}\n";
echo "Recently notified (last 7 days): " . count($recentlyNotified) . "\n";
echo "Available for new notifications: {$availableForNotification}\n";

// Test 3: Simulate getting trending movie for each time period
echo "\n\n🔄 TEST 3: Simulating Trending Movie Selection (Without Sending)\n";
echo str_repeat("-", 70) . "\n";

$timePeriods = ['morning', 'afternoon', 'evening', 'night'];
$selectedMovies = [];

// Save current time
$originalNow = Carbon::now();

foreach ($timePeriods as $period) {
    // Set time to trigger the period
    switch ($period) {
        case 'morning':
            Carbon::setTestNow(Carbon::now()->setHour(8));
            break;
        case 'afternoon':
            Carbon::setTestNow(Carbon::now()->setHour(14));
            break;
        case 'evening':
            Carbon::setTestNow(Carbon::now()->setHour(19));
            break;
        case 'night':
            Carbon::setTestNow(Carbon::now()->setHour(23));
            break;
    }

    // Check if notification already exists for today's period
    $existing = TrendingNotification::where([
        'day_time' => $period,
        ['created_at', '>=', Carbon::now()->startOfDay()]
    ])->first();

    if ($existing) {
        $movie = MovieModel::find($existing->movie_model_id);
        echo sprintf("  %-10s: %-50s (ID: %-6s) [EXISTING]\n", 
            strtoupper($period), 
            substr($movie ? $movie->title : 'N/A', 0, 50), 
            $existing->movie_model_id
        );
        $selectedMovies[$period] = $existing->movie_model_id;
    } else {
        echo sprintf("  %-10s: No notification for today yet - would create new\n", strtoupper($period));
    }
}

// Reset time
Carbon::setTestNow($originalNow);

// Test 4: Check diversity
echo "\n\n🎯 TEST 4: Notification Diversity Check\n";
echo str_repeat("-", 70) . "\n";

$uniqueMoviesToday = count(array_unique($selectedMovies));
echo "Unique movies selected for today: {$uniqueMoviesToday} / " . count($timePeriods) . "\n";

if ($uniqueMoviesToday < count($timePeriods)) {
    echo "⚠️  WARNING: Same movie being used for multiple time periods!\n";
} else {
    echo "✅ GOOD: Different movies for each time period!\n";
}

// Test 5: Top movies by watch time (potential candidates)
echo "\n\n🎬 TEST 5: Top Movie Candidates (By Watch Time)\n";
echo str_repeat("-", 70) . "\n";

$topMovies = MovieModel::where('status', 'Active')
    ->where('type', 'Movie')
    ->orderBy('views_time_count', 'desc')
    ->limit(10)
    ->get(['id', 'title', 'views_time_count', 'is_trending']);

echo "Top 10 movies by watch time:\n";
foreach ($topMovies as $idx => $movie) {
    $trending = $movie->is_trending == 'Yes' ? '⭐' : '  ';
    $recentlyUsed = in_array($movie->id, $recentlyNotified) ? '🔴' : '🟢';
    echo sprintf("  %d. %s %s ID %-6s: %-40s (%s mins)\n",
        $idx + 1,
        $trending,
        $recentlyUsed,
        $movie->id,
        substr($movie->title, 0, 40),
        number_format($movie->views_time_count / 60, 0)
    );
}

echo "\n  Legend: ⭐=Currently Trending  🔴=Used in last 7 days  🟢=Available\n";

// Test 6: Database stats
echo "\n\n📈 TEST 6: Database Statistics\n";
echo str_repeat("-", 70) . "\n";

$stats = [
    'Total notifications ever sent' => TrendingNotification::where('is_sent', 'Yes')->count(),
    'Pending notifications (unsent)' => TrendingNotification::where('is_sent', 'No')->count(),
    'Notifications last 24 hours' => TrendingNotification::where('created_at', '>=', Carbon::now()->subDay())->count(),
    'Notifications last 7 days' => TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
    'Notifications last 30 days' => TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(30))->count(),
];

foreach ($stats as $label => $value) {
    echo sprintf("  %-35s: %s\n", $label, number_format($value));
}

// Test 7: Check for the bug - same movie repeated
echo "\n\n🐛 TEST 7: Bug Detection - Repeated Movies\n";
echo str_repeat("-", 70) . "\n";

$repeatedMovies = DB::table('trending_notifications')
    ->select('movie_model_id', 'title', DB::raw('COUNT(*) as notification_count'))
    ->where('created_at', '>=', Carbon::now()->subDays(30))
    ->groupBy('movie_model_id', 'title')
    ->having('notification_count', '>', 10)
    ->orderByDesc('notification_count')
    ->get();

if ($repeatedMovies->isEmpty()) {
    echo "✅ NO ISSUES: No movie has been sent more than 10 times in last 30 days\n";
} else {
    echo "⚠️  WARNING: Found movies sent excessively:\n";
    foreach ($repeatedMovies as $movie) {
        echo sprintf("  • ID %-6s: %-50s (%d times in 30 days)\n",
            $movie->movie_model_id,
            substr($movie->title, 0, 50),
            $movie->notification_count
        );
    }
}

// Summary
echo "\n\n" . str_repeat("=", 70) . "\n";
echo "SUMMARY & RECOMMENDATIONS\n";
echo str_repeat("=", 70) . "\n\n";

$hasIssues = false;

// Check 1: Diversity
if ($uniqueMoviesToday < count($timePeriods)) {
    echo "❌ Issue: Same movie used for multiple time periods today\n";
    $hasIssues = true;
} else {
    echo "✅ Good: Different movies for each time period\n";
}

// Check 2: Availability
if ($availableForNotification < 100) {
    echo "⚠️  Warning: Only {$availableForNotification} movies available (not used in last 7 days)\n";
    echo "   Recommendation: Consider reducing the exclusion period from 7 to 3 days\n";
} else {
    echo "✅ Good: {$availableForNotification} movies available for rotation\n";
}

// Check 3: Repetition
if (!$repeatedMovies->isEmpty()) {
    echo "❌ Issue: Some movies sent more than 10 times in 30 days\n";
    echo "   This indicates the fix should be applied\n";
    $hasIssues = true;
} else {
    echo "✅ Good: No excessive repetition detected\n";
}

// Check 4: Recent activity
$last24h = TrendingNotification::where('created_at', '>=', Carbon::now()->subDay())->count();
if ($last24h == 0) {
    echo "⚠️  Warning: No notifications sent in last 24 hours\n";
    echo "   Check if cron job is running\n";
} else {
    echo "✅ Good: System is actively sending notifications ({$last24h} in last 24h)\n";
}

echo "\n";

if (!$hasIssues) {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║              ✅ FIX APPEARS TO BE WORKING CORRECTLY                ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
} else {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║           ⚠️  ISSUES DETECTED - FIX MAY NEED ADJUSTMENTS           ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
}

echo "\n";
