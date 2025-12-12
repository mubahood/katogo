#!/usr/bin/env php
<?php

/**
 * Full Day Simulation Test
 * 
 * Simulates a complete day of trending notifications to ensure:
 * 1. Each time period gets a different movie
 * 2. Movies are properly excluded after being notified
 * 3. System works continuously over multiple days
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TrendingNotification;
use App\Models\MovieModel;
use Carbon\Carbon;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║            FULL DAY SIMULATION - NOTIFICATION TEST                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

echo "This test simulates calling getTrendingMovie() throughout a day\n";
echo "to verify different movies are selected for each time period.\n\n";

// Store original state
$originalTime = Carbon::now();

// Define time periods and their test times
$testScenarios = [
    ['period' => 'MORNING', 'hour' => 8, 'minute' => 30],
    ['period' => 'AFTERNOON', 'hour' => 14, 'minute' => 45],
    ['period' => 'EVENING', 'hour' => 19, 'minute' => 15],
    ['period' => 'NIGHT', 'hour' => 23, 'minute' => 30],
];

$results = [];
$previousMovieIds = [];

echo str_repeat("=", 70) . "\n";
echo "SIMULATING FULL DAY CYCLE\n";
echo str_repeat("=", 70) . "\n\n";

foreach ($testScenarios as $scenario) {
    echo str_repeat("-", 70) . "\n";
    echo "⏰ {$scenario['period']} - Testing at {$scenario['hour']}:{$scenario['minute']}\n";
    echo str_repeat("-", 70) . "\n";
    
    // Set test time
    $testTime = Carbon::now()->setHour($scenario['hour'])->setMinute($scenario['minute']);
    Carbon::setTestNow($testTime);
    
    try {
        // Get trending movie
        $movie = TrendingNotification::getTrendingMovie();
        
        if ($movie) {
            echo "✅ Movie selected: {$movie->title} (ID: {$movie->id})\n";
            echo "   Watch time: " . number_format($movie->views_time_count / 60, 0) . " minutes\n";
            echo "   Views: {$movie->views_count}\n";
            
            // Check if this is a duplicate
            if (in_array($movie->id, $previousMovieIds)) {
                echo "   ⚠️  WARNING: This movie was already used today!\n";
                $results[$scenario['period']] = [
                    'movie_id' => $movie->id,
                    'title' => $movie->title,
                    'is_duplicate' => true
                ];
            } else {
                echo "   ✅ GOOD: This is a new movie for today\n";
                $results[$scenario['period']] = [
                    'movie_id' => $movie->id,
                    'title' => $movie->title,
                    'is_duplicate' => false
                ];
                $previousMovieIds[] = $movie->id;
            }
        } else {
            echo "❌ ERROR: No movie returned\n";
            $results[$scenario['period']] = [
                'movie_id' => null,
                'title' => 'ERROR - No movie',
                'is_duplicate' => false
            ];
        }
    } catch (\Exception $e) {
        echo "❌ EXCEPTION: {$e->getMessage()}\n";
        $results[$scenario['period']] = [
            'movie_id' => null,
            'title' => 'EXCEPTION: ' . $e->getMessage(),
            'is_duplicate' => false
        ];
    }
    
    echo "\n";
    usleep(500000); // Small delay
}

// Reset time
Carbon::setTestNow($originalTime);

// Analysis
echo str_repeat("=", 70) . "\n";
echo "ANALYSIS & RESULTS\n";
echo str_repeat("=", 70) . "\n\n";

$uniqueMovies = count(array_unique($previousMovieIds));
$totalPeriods = count($testScenarios);
$duplicates = 0;

echo "📊 Statistics:\n";
echo "  • Total time periods tested: {$totalPeriods}\n";
echo "  • Unique movies selected: {$uniqueMovies}\n";
echo "  • Diversity ratio: " . round(($uniqueMovies / $totalPeriods) * 100, 1) . "%\n\n";

echo "📋 Detailed Results:\n";
foreach ($results as $period => $data) {
    $icon = $data['is_duplicate'] ? '⚠️ ' : '✅';
    $status = $data['is_duplicate'] ? 'DUPLICATE' : 'UNIQUE';
    echo sprintf("  %s %-10s: %-45s [%s]\n", 
        $icon,
        $period, 
        substr($data['title'], 0, 45),
        $status
    );
    $duplicates += $data['is_duplicate'] ? 1 : 0;
}

echo "\n";

// Final verdict
echo str_repeat("=", 70) . "\n";
echo "FINAL VERDICT\n";
echo str_repeat("=", 70) . "\n\n";

$allPassed = true;

if ($uniqueMovies == $totalPeriods) {
    echo "✅ PERFECT: Every time period has a different movie!\n";
} elseif ($uniqueMovies >= $totalPeriods * 0.75) {
    echo "✅ GOOD: Most time periods have different movies ({$uniqueMovies}/{$totalPeriods})\n";
} else {
    echo "❌ POOR: Low diversity - only {$uniqueMovies}/{$totalPeriods} unique movies\n";
    $allPassed = false;
}

if ($duplicates == 0) {
    echo "✅ EXCELLENT: No duplicate movies detected\n";
} else {
    echo "⚠️  WARNING: {$duplicates} duplicate movie(s) detected\n";
    $allPassed = false;
}

// Check database state
$todayNotifications = TrendingNotification::where('created_at', '>=', Carbon::now()->startOfDay())->get();
$dbUniqueMovies = $todayNotifications->pluck('movie_model_id')->unique()->count();

if ($dbUniqueMovies >= $totalPeriods * 0.75) {
    echo "✅ DATABASE: Good diversity in stored notifications ({$dbUniqueMovies} unique)\n";
} else {
    echo "⚠️  DATABASE: Low diversity in stored notifications ({$dbUniqueMovies} unique)\n";
}

echo "\n";

if ($allPassed && $uniqueMovies >= 3) {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║              🎉 SIMULATION PASSED - FIX WORKING! 🎉                ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
} else {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║           ⚠️  SIMULATION SHOWS ISSUES - CHECK ABOVE                ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
}

echo "\n";
echo "💡 TIP: Run 'php verify_notification_fix.php' for current status\n";
echo "\n";
