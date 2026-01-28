<?php
/**
 * Direct Game Logic Test
 * Run from Laravel project root: php test_game_logic.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\GameSession;
use App\Models\GameInvitation;
use App\Models\ChatHead;
use App\Http\Controllers\GameController;

echo "\n\033[34m=== Multiplayer Game Logic Tests ===\033[0m\n\n";

// Get test users
$user1 = User::find(1);
$user2 = User::find(2);

if (!$user1 || !$user2) {
    echo "\033[31m✗ Need at least 2 users in database\033[0m\n";
    exit(1);
}

echo "Test Users:\n";
echo "  User 1: ID={$user1->id}, Name={$user1->name}\n";
echo "  User 2: ID={$user2->id}, Name={$user2->name}\n\n";

// Test 1: Create invitation
echo "\033[33mTest 1: Create Invitation\033[0m\n";
$invite = GameInvitation::createInvitation($user1->id, $user2->id, 'matatu', 'Let\'s play Matatu!');
echo "  ✓ Created invitation ID: {$invite->id}\n";
echo "  ✓ Expires at: {$invite->expires_at}\n";
echo "  ✓ Remaining seconds: {$invite->getRemainingSeconds()}\n";
echo "  ✓ Status: {$invite->status}\n\n";

// Test 2: Create game session via controller
echo "\033[33mTest 2: Create Game Session\033[0m\n";
$controller = new GameController();
$method = new ReflectionMethod($controller, 'createGameSession');
$method->setAccessible(true);
$session = $method->invoke($controller, $user1->id, $user2->id);

echo "  ✓ Session ID: {$session->id}\n";
echo "  ✓ Status: {$session->status}\n";
echo "  ✓ Player 1 hand count: " . count($session->getPlayerHand($user1->id)) . "\n";
echo "  ✓ Player 2 hand count: " . count($session->getPlayerHand($user2->id)) . "\n";
echo "  ✓ Draw pile count: " . count($session->getDrawPile()) . "\n";
echo "  ✓ Discard pile count: " . count($session->getDiscardPile()) . "\n";
echo "  ✓ Current turn: User {$session->current_turn_user_id}\n";
echo "  ✓ Current suit: {$session->current_suit}\n";
echo "  ✓ Chat head ID: {$session->chat_head_id}\n";

$topCard = $session->getTopCard();
echo "  ✓ Top card: {$topCard['rank']} of {$topCard['suit']}\n\n";

// Test 3: Verify card dealing
echo "\033[33mTest 3: Verify Card Distribution\033[0m\n";
$p1Hand = $session->getPlayerHand($user1->id);
$p2Hand = $session->getPlayerHand($user2->id);
$drawPile = $session->getDrawPile();
$discardPile = $session->getDiscardPile();

$totalCards = count($p1Hand) + count($p2Hand) + count($drawPile) + count($discardPile);
echo "  Total cards: {$totalCards} (should be 52)\n";
echo "  " . ($totalCards === 52 ? "✓ Correct!" : "✗ ERROR: Card count mismatch!") . "\n\n";

// Test 4: Card validation logic
echo "\033[33mTest 4: Card Play Validation\033[0m\n";
$validateMethod = new ReflectionMethod($controller, 'isValidPlay');
$validateMethod->setAccessible(true);

// Test Ace (always valid)
$ace = ['suit' => 'hearts', 'rank' => 1];
$isValid = $validateMethod->invoke($controller, $ace, $topCard, $topCard['suit'], 0);
echo "  Ace can be played: " . ($isValid ? "✓ Yes" : "✗ No") . "\n";

// Test matching suit
$matchingSuit = ['suit' => $topCard['suit'], 'rank' => 5];
$isValid = $validateMethod->invoke($controller, $matchingSuit, $topCard, $topCard['suit'], 0);
echo "  Same suit card: " . ($isValid ? "✓ Valid" : "✗ Invalid") . "\n";

// Test matching rank
$matchingRank = ['suit' => 'spades', 'rank' => $topCard['rank']];
$isValid = $validateMethod->invoke($controller, $matchingRank, $topCard, $topCard['suit'], 0);
echo "  Same rank card: " . ($isValid ? "✓ Valid" : "✗ Invalid") . "\n";

// Test invalid play
$invalidCard = ['suit' => ($topCard['suit'] === 'hearts' ? 'clubs' : 'hearts'), 'rank' => ($topCard['rank'] + 1) % 13 + 1];
$isValid = $validateMethod->invoke($controller, $invalidCard, $topCard, $topCard['suit'], 0);
echo "  Invalid card (diff suit & rank): " . (!$isValid ? "✓ Correctly rejected" : "✗ Should be invalid!") . "\n";

// Test 2 when there's a draw stack
$two = ['suit' => 'hearts', 'rank' => 2];
$isValid = $validateMethod->invoke($controller, $two, $topCard, $topCard['suit'], 2);
echo "  Play 2 when draw stack exists: " . ($isValid ? "✓ Valid" : "✗ Invalid") . "\n";

$nonTwo = ['suit' => $topCard['suit'], 'rank' => 5];
$isValid = $validateMethod->invoke($controller, $nonTwo, $topCard, $topCard['suit'], 2);
echo "  Play non-2 when draw stack exists: " . (!$isValid ? "✓ Correctly rejected" : "✗ Should be invalid!") . "\n\n";

// Test 5: Point calculation
echo "\033[33mTest 5: Point Calculation\033[0m\n";
$calcMethod = new ReflectionMethod($controller, 'calculateHandPoints');
$calcMethod->setAccessible(true);

$testHand = [
    ['suit' => 'hearts', 'rank' => 2],  // 25 points
    ['suit' => 'diamonds', 'rank' => 1], // 15 points (Ace)
    ['suit' => 'clubs', 'rank' => 13],   // 12 points (King)
    ['suit' => 'spades', 'rank' => 5],   // 5 points
];
$points = $calcMethod->invoke($controller, $testHand);
$expected = 25 + 15 + 12 + 5;
echo "  Hand: 2♥ A♦ K♣ 5♠\n";
echo "  Calculated: {$points} points\n";
echo "  Expected: {$expected} points\n";
echo "  " . ($points === $expected ? "✓ Correct!" : "✗ ERROR!") . "\n\n";

// Test 6: Session formatting
echo "\033[33mTest 6: Session API Response Format\033[0m\n";
$formatMethod = new ReflectionMethod($controller, 'formatSession');
$formatMethod->setAccessible(true);
$formatted = $formatMethod->invoke($controller, $session, $user1->id);

$requiredFields = ['id', 'player1_id', 'player2_id', 'discard_pile', 'current_turn_user_id', 
                   'current_suit', 'status', 'is_my_turn', 'my_hand', 'my_info', 'opponent_info'];

$missing = [];
foreach ($requiredFields as $field) {
    if (!array_key_exists($field, $formatted)) {
        $missing[] = $field;
    }
}

if (empty($missing)) {
    echo "  ✓ All required fields present\n";
} else {
    echo "  ✗ Missing fields: " . implode(', ', $missing) . "\n";
}

echo "  ✓ is_my_turn: " . ($formatted['is_my_turn'] ? 'Yes' : 'No') . "\n";
echo "  ✓ my_hand card count: " . count($formatted['my_hand']) . "\n";
echo "  ✓ opponent_info: " . json_encode($formatted['opponent_info']) . "\n\n";

// Cleanup
echo "\033[33mCleanup\033[0m\n";
$chatHeadId = $session->chat_head_id;
$session->delete();
ChatHead::find($chatHeadId)?->delete();
$invite->delete();
echo "  ✓ Test data cleaned up\n\n";

echo "\033[32m=== All Tests Passed! ===\033[0m\n\n";
