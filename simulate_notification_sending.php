#!/usr/bin/env php
<?php

/**
 * Simulate Notification Sending Test
 * 
 * This script simulates the notification sending process to verify:
 * 1. Correct movie titles are sent
 * 2. Notifications are marked as sent
 * 3. No duplicate sending occurs
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TrendingNotification;
use App\Models\Utils;
use Carbon\Carbon;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║          SIMULATE NOTIFICATION SENDING TEST                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Get unsent notifications created today
$today = Carbon::now()->startOfDay();
$unsentNotifications = TrendingNotification::where('is_sent', 'No')
    ->where('created_at', '>=', $today)
    ->orderBy('created_at', 'desc')
    ->get();

echo "📋 Found {$unsentNotifications->count()} unsent notifications for today\n\n";

if ($unsentNotifications->isEmpty()) {
    echo "⚠️  No unsent notifications found. Run force_create_trending_notifications.php first.\n";
    exit(1);
}

echo str_repeat("=", 70) . "\n";
echo "NOTIFICATION PREVIEW (WITHOUT ACTUAL SENDING)\n";
echo str_repeat("=", 70) . "\n\n";

foreach ($unsentNotifications as $notification) {
    echo str_repeat("-", 70) . "\n";
    echo "🕐 Period: " . strtoupper($notification->day_time) . "\n";
    echo str_repeat("-", 70) . "\n";
    echo "📌 Title: {$notification->title}\n";
    echo "🎬 Movie ID: {$notification->movie_model_id}\n";
    echo "📝 Description: {$notification->description}\n";
    echo "🖼️  Image: " . substr($notification->image_url, 0, 60) . "...\n";
    echo "📊 Views: {$notification->views_count}\n";
    echo "⏱️  Watch Time: " . number_format($notification->views_time / 60, 0) . " minutes\n";
    echo "📅 Created: {$notification->created_at}\n";
    echo "\n";
}

echo str_repeat("=", 70) . "\n\n";

// Ask for confirmation
echo "❓ Do you want to simulate sending these notifications? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$response = trim(strtolower($line));
fclose($handle);

if ($response !== 'yes' && $response !== 'y') {
    echo "\n❌ Test cancelled. No notifications sent.\n";
    exit(0);
}

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "SIMULATING NOTIFICATION SENDING\n";
echo str_repeat("=", 70) . "\n\n";

$sentCount = 0;
$errors = [];

foreach ($unsentNotifications as $notification) {
    echo "📤 Sending: {$notification->title} ({$notification->day_time})... ";
    
    try {
        // SIMULATION: In production, this would call Utils::sendNotificationToAll()
        // For testing, we just mark it as sent without actually sending
        
        $notificationData = [
            'title' => $notification->title,
            'body' => $notification->description,
            'image' => $notification->image_url,
            'data' => [
                'movie_id' => $notification->movie_model_id,
                'type' => $notification->type,
                'notification_type' => 'trending_notification',
            ],
        ];
        
        // Uncomment below to actually send (PRODUCTION)
        // Utils::sendNotificationToAll($notificationData);
        
        // Mark as sent
        $notification->is_sent = 'Yes';
        $notification->sent_time = Carbon::now();
        $notification->save();
        
        echo "✅ SUCCESS\n";
        $sentCount++;
        
    } catch (\Throwable $th) {
        echo "❌ ERROR: {$th->getMessage()}\n";
        $errors[] = [
            'notification' => $notification->title,
            'error' => $th->getMessage()
        ];
    }
    
    // Small delay between notifications
    usleep(500000); // 0.5 second
}

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 70) . "\n\n";

echo "✅ Successfully sent: {$sentCount}\n";
echo "❌ Errors: " . count($errors) . "\n\n";

if (!empty($errors)) {
    echo "Error details:\n";
    foreach ($errors as $error) {
        echo "  • {$error['notification']}: {$error['error']}\n";
    }
    echo "\n";
}

// Verify they are marked as sent
$stillUnsent = TrendingNotification::where('is_sent', 'No')
    ->where('created_at', '>=', $today)
    ->count();

echo "📊 Unsent notifications remaining: {$stillUnsent}\n\n";

if ($stillUnsent == 0) {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║                  ✅ ALL NOTIFICATIONS SENT                         ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
} else {
    echo "⚠️  WARNING: Some notifications were not marked as sent.\n";
}

echo "\n";
echo "NOTE: This was a SIMULATION. To actually send notifications to users,\n";
echo "uncomment the Utils::sendNotificationToAll() line in the script.\n";
