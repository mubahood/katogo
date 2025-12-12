#!/usr/bin/env php
<?php

/**
 * Force Create New Trending Notifications
 * 
 * This script forces the creation of new trending notifications for today
 * It will delete existing notifications for today and create fresh ones
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TrendingNotification;
use App\Models\MovieModel;
use App\Models\MovieView;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║         FORCE CREATE NEW TRENDING NOTIFICATIONS                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Delete today's notifications
$today = Carbon::now()->startOfDay();
$deleted = TrendingNotification::where('created_at', '>=', $today)->delete();

echo "🗑️  Deleted {$deleted} notifications created today\n\n";

// Step 2: Get recently notified movies (last 7 days)
$recently_notified_movie_ids = TrendingNotification::where('created_at', '>=', Carbon::now()->subDays(7))
    ->where('is_sent', 'Yes')
    ->pluck('movie_model_id')
    ->unique()
    ->toArray();

echo "📊 Recently notified movies (last 7 days): " . count($recently_notified_movie_ids) . "\n";

// Step 3: Get top movies by watch time (last 90 days) that haven't been notified recently
$ninety_days_ago = Carbon::now()->subDays(90);

$recent_movie_views = MovieView::where('created_at', '>=', $ninety_days_ago)
    ->selectRaw('movie_model_id, SUM(progress) as total_watch_time')
    ->groupBy('movie_model_id')
    ->orderByDesc('total_watch_time')
    ->pluck('movie_model_id')
    ->toArray();

echo "📈 Movies with views in last 90 days: " . count($recent_movie_views) . "\n\n";

// Step 4: Create trending notifications for each time period
$timePeriods = [
    'morning' => ['hour' => 8, 'desc' => 'Morning'],
    'afternoon' => ['hour' => 14, 'desc' => 'Afternoon'],
    'evening' => ['hour' => 19, 'desc' => 'Evening'],
    'night' => ['hour' => 23, 'desc' => 'Night']
];

$usedMovieIds = []; // Track movies used to ensure diversity

foreach ($timePeriods as $period => $config) {
    echo str_repeat("-", 70) . "\n";
    echo "🕐 Creating {$config['desc']} Notification\n";
    echo str_repeat("-", 70) . "\n";

    $movieFound = false;

    // Try to find a suitable movie
    foreach ($recent_movie_views as $movie_id) {
        // Skip if recently notified
        if (in_array($movie_id, $recently_notified_movie_ids)) {
            continue;
        }

        // Skip if already used for another period today
        if (in_array($movie_id, $usedMovieIds)) {
            continue;
        }

        $movie = MovieModel::find($movie_id);
        
        if (!$movie) {
            continue;
        }

        if ($movie->type != 'Movie') {
            continue;
        }

        if ($movie->status != 'Active') {
            continue;
        }

        // Found a suitable movie!
        echo "✅ Selected: {$movie->title} (ID: {$movie->id})\n";
        echo "   Watch time: " . number_format($movie->views_time_count / 60, 0) . " minutes\n";
        echo "   Views: {$movie->views_count}\n";

        // Create trending notification
        $trending = new TrendingNotification();
        $trending->day_time = $period;
        $trending->movie_model_id = $movie->id;
        $trending->is_sent = 'No';
        $trending->title = $movie->title;
        $trending->type = $movie->type;
        $trending->image_url = $movie->thumbnail_url;
        $trending->description = $config['desc'] . ' trending movie is ' . $movie->title . "! Watch now!";
        $trending->views_count = $movie->views_count;
        $trending->views_time = $movie->views_time_count;
        $trending->trending_time = Carbon::now();
        $trending->save();

        // Update movie
        $movie->is_trending = 'Yes';
        $movie->trending_time = Carbon::now();
        $movie->trending_id = $trending->id;
        $movie->save();

        $usedMovieIds[] = $movie->id;
        $movieFound = true;
        break;
    }

    if (!$movieFound) {
        echo "⚠️  WARNING: Could not find suitable movie for {$period}\n";
    }

    echo "\n";
}

// Summary
echo str_repeat("=", 70) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 70) . "\n\n";

$created = TrendingNotification::where('created_at', '>=', $today)->get();
echo "✅ Created " . $created->count() . " new trending notifications for today\n";
echo "📊 Unique movies used: " . count(array_unique($usedMovieIds)) . "\n\n";

if ($created->isNotEmpty()) {
    echo "Details:\n";
    foreach ($created as $notif) {
        echo sprintf("  • %-10s: %s\n", strtoupper($notif->day_time), $notif->title);
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                    ✅ PROCESS COMPLETED                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
