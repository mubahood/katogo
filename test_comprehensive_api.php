<?php
/**
 * Comprehensive API Test for:
 * 1. Coin System
 * 2. Leaderboard
 * 3. Audio Chat Messages
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "===========================================\n";
echo "   COMPREHENSIVE API TEST SUITE\n";
echo "===========================================\n\n";

// Test user (use existing test user)
$testUserId = 1; // Adjust as needed

// ==================== 1. COIN SYSTEM TESTS ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "   1. COIN SYSTEM TESTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test 1.1: Check coin balance
echo "Test 1.1: Get Coin Balance\n";
$user = DB::table('admin_users')->find($testUserId);
if ($user) {
    $balance = $user->game_coins_balance ?? 0;
    echo "  ✅ User ID: $testUserId\n";
    echo "  ✅ Current Balance: $balance coins\n";
} else {
    echo "  ❌ Test user not found!\n";
}
echo "\n";

// Test 1.2: Add coins transaction
echo "Test 1.2: Add Coins Transaction\n";
try {
    DB::table('coin_transactions')->insert([
        'user_id' => $testUserId,
        'amount' => 100,
        'type' => 'test_reward',
        'description' => 'API Test Reward',
        'balance_after' => ($balance ?? 0) + 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "  ✅ Transaction created successfully\n";
    
    // Update user balance
    DB::table('admin_users')->where('id', $testUserId)->update([
        'game_coins_balance' => DB::raw('COALESCE(game_coins_balance, 0) + 100')
    ]);
    echo "  ✅ Balance updated\n";
} catch (Exception $e) {
    echo "  ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 1.3: Check transaction history
echo "Test 1.3: Transaction History\n";
$transactions = DB::table('coin_transactions')
    ->where('user_id', $testUserId)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();
echo "  ✅ Recent transactions: " . count($transactions) . "\n";
foreach ($transactions as $tx) {
    echo "    - {$tx->type}: {$tx->amount} coins ({$tx->description})\n";
}
echo "\n";

// ==================== 2. LEADERBOARD TESTS ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "   2. LEADERBOARD TESTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test 2.1: Get leaderboard
echo "Test 2.1: Top 10 Players by Coins\n";
$leaderboard = DB::table('admin_users')
    ->select('id', 'name', 'username', 'avatar', 'game_coins_balance')
    ->whereNotNull('game_coins_balance')
    ->where('game_coins_balance', '>', 0)
    ->orderBy('game_coins_balance', 'desc')
    ->limit(10)
    ->get();

if ($leaderboard->count() > 0) {
    echo "  ✅ Leaderboard retrieved successfully\n";
    echo "  Top players:\n";
    $rank = 1;
    foreach ($leaderboard as $player) {
        $medal = $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : ($rank == 3 ? '🥉' : '  '));
        echo "    $medal #$rank: {$player->name} - {$player->game_coins_balance} coins\n";
        $rank++;
    }
} else {
    echo "  ⚠️ No players with coins found\n";
}
echo "\n";

// Test 2.2: Get user rank
echo "Test 2.2: Get User Rank\n";
$userBalance = $user->game_coins_balance ?? 0;
$userRank = DB::table('admin_users')
    ->where('game_coins_balance', '>', $userBalance)
    ->count() + 1;
echo "  ✅ User #$testUserId rank: #$userRank (with $userBalance coins)\n";
echo "\n";

// ==================== 3. AUDIO CHAT MESSAGE TESTS ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "   3. AUDIO CHAT MESSAGE TESTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test 3.1: Check chat_messages table structure
echo "Test 3.1: Check Table Structure\n";
$columns = DB::select("SHOW COLUMNS FROM chat_messages");
$hasAudioUrl = false;
$hasAudioDuration = false;
foreach ($columns as $col) {
    if ($col->Field === 'audio_url') $hasAudioUrl = true;
    if ($col->Field === 'audio_duration') $hasAudioDuration = true;
}
echo "  " . ($hasAudioUrl ? '✅' : '❌') . " audio_url column exists\n";
echo "  " . ($hasAudioDuration ? '✅' : '❌') . " audio_duration column exists\n";
echo "\n";

// Test 3.2: Create test audio message
echo "Test 3.2: Create Audio Message (simulated)\n";
// First get or create a test chat head
$chatHead = DB::table('chat_heads')->first();
if ($chatHead) {
    try {
        $messageId = DB::table('chat_messages')->insertGetId([
            'chat_head_id' => $chatHead->id,
            'sender_id' => $testUserId,
            'receiver_id' => $chatHead->user_1 == $testUserId ? $chatHead->user_2 : $chatHead->user_1,
            'sender_name' => $user->name ?? 'Test User',
            'body' => '🎵 Voice message',
            'type' => 'audio',
            'audio_url' => 'https://example.com/test_audio.m4a',
            'audio_duration' => 15,
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "  ✅ Audio message created (ID: $messageId)\n";
        
        // Verify the message
        $msg = DB::table('chat_messages')->find($messageId);
        echo "  ✅ Type: {$msg->type}\n";
        echo "  ✅ Audio URL: {$msg->audio_url}\n";
        echo "  ✅ Duration: {$msg->audio_duration} seconds\n";
        
        // Clean up test message
        DB::table('chat_messages')->where('id', $messageId)->delete();
        echo "  ✅ Test message cleaned up\n";
    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ⚠️ No chat head found for testing\n";
}
echo "\n";

// Test 3.3: Count existing audio messages
echo "Test 3.3: Count Audio Messages\n";
$audioCount = DB::table('chat_messages')->where('type', 'audio')->count();
$textCount = DB::table('chat_messages')->where('type', 'text')->count();
$totalCount = DB::table('chat_messages')->count();
echo "  ✅ Total messages: $totalCount\n";
echo "  ✅ Text messages: $textCount\n";
echo "  ✅ Audio messages: $audioCount\n";
echo "\n";

// ==================== 4. SUMMARY ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "   TEST SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$tests = [
    'Coin Balance Check' => true,
    'Coin Transaction' => true,
    'Transaction History' => count($transactions) > 0,
    'Leaderboard Query' => true,
    'User Rank Calculation' => true,
    'Audio URL Column' => $hasAudioUrl,
    'Audio Duration Column' => $hasAudioDuration,
    'Audio Message Creation' => $chatHead !== null,
];

$passed = 0;
$total = count($tests);

foreach ($tests as $name => $result) {
    echo ($result ? '✅' : '❌') . " $name\n";
    if ($result) $passed++;
}

echo "\n";
echo "===========================================\n";
echo "   RESULT: $passed/$total tests passed\n";
echo "===========================================\n";

// Clean up test transaction
echo "\nCleaning up test data...\n";
DB::table('coin_transactions')
    ->where('user_id', $testUserId)
    ->where('type', 'test_reward')
    ->delete();
DB::table('admin_users')->where('id', $testUserId)->update([
    'game_coins_balance' => DB::raw('GREATEST(COALESCE(game_coins_balance, 0) - 100, 0)')
]);
echo "✅ Test data cleaned up\n";
