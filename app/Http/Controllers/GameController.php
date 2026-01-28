<?php

namespace App\Http\Controllers;

use App\Models\ChatHead;
use App\Models\GameInvitation;
use App\Models\GameSession;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GameController extends Controller
{
    use ApiResponser;

    // Standard 52-card deck suits
    const SUITS = ['hearts', 'diamonds', 'clubs', 'spades'];

    // ========================================
    // ONLINE USERS
    // ========================================

    /**
     * Get list of online users available to play
     * GET /api/game/online-users
     */
    public function onlineUsers(Request $request)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        // Update current user's last online time
        $user = User::find($currentUser->id);
        if ($user) {
            $user->last_online_at = now();
            $user->save();
        }

        // Get search query
        $search = $request->get('search', '');

        // Consider users "online" if active in last 15 minutes
        $fifteenMinutesAgo = now()->subMinutes(15);
        // Consider "recently online" for display within 30 minutes
        $thirtyMinutesAgo = now()->subMinutes(30);

        $query = User::where('id', '!=', $currentUser->id)
            ->where(function($q) use ($thirtyMinutesAgo) {
                // Users active in last 30 minutes OR have any last_online_at set
                $q->where('last_online_at', '>=', $thirtyMinutesAgo)
                  ->orWhereNotNull('last_online_at');
            })
            ->where('status', 'Active'); // Active users only

        // Apply search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('last_online_at', 'desc')
            ->limit(50)
            ->get();

        // Format users for response
        $formattedUsers = $users->map(function ($user) use ($fifteenMinutesAgo) {
            $lastOnline = $user->last_online_at;
            $lastOnlineStr = null;
            $isOnline = false;

            if ($lastOnline) {
                // Handle both string and Carbon datetime
                if ($lastOnline instanceof \Carbon\Carbon) {
                    $lastOnlineStr = $lastOnline->toIso8601String();
                    $isOnline = $lastOnline >= $fifteenMinutesAgo;
                } else {
                    $lastOnlineStr = $lastOnline;
                    $isOnline = strtotime($lastOnline) >= $fifteenMinutesAgo->timestamp;
                }
            }

            return [
                'id' => $user->id,
                'name' => $user->name ?? $user->first_name ?? 'Player',
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'last_online_at' => $lastOnlineStr,
                'is_online' => $isOnline,
            ];
        });

        return $this->success($formattedUsers, 'Online users retrieved');
    }

    // ========================================
    // INVITATIONS
    // ========================================

    /**
     * Send a game invitation
     * POST /api/game/invite
     */
    public function sendInvitation(Request $request)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $receiverId = $request->input('receiver_id');
        $message = $request->input('message', '');

        if (!$receiverId) {
            return $this->error('Receiver ID is required');
        }

        if ($receiverId == $currentUser->id) {
            return $this->error('You cannot invite yourself');
        }

        // Check if receiver exists
        $receiver = User::find($receiverId);
        if (!$receiver) {
            return $this->error('User not found');
        }

        // Check for existing pending invitation
        $existingInvite = GameInvitation::where('sender_id', $currentUser->id)
            ->where('receiver_id', $receiverId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvite) {
            return $this->error('You already have a pending invitation to this user');
        }

        // Check if receiver has a pending invite from sender
        $reverseInvite = GameInvitation::where('sender_id', $receiverId)
            ->where('receiver_id', $currentUser->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($reverseInvite) {
            return $this->error('This user has already invited you. Check your invitations!');
        }

        // Create invitation
        $invitation = GameInvitation::createInvitation(
            $currentUser->id,
            $receiverId,
            'matatu',
            $message
        );

        // Load relationships
        $invitation->load(['sender', 'receiver']);

        // Format response
        $response = $this->formatInvitation($invitation);

        return $this->success($response, 'Invitation sent successfully');
    }

    /**
     * Get pending invitations for current user (received invitations)
     * GET /api/game/invitations
     */
    public function getInvitations(Request $request)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        // Update last online
        $user = User::find($currentUser->id);
        if ($user) {
            $user->last_online_at = now();
            $user->save();
        }

        // Expire old invitations
        GameInvitation::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        // Get pending invitations received by current user
        $invitations = GameInvitation::where('receiver_id', $currentUser->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $invitations->map(function ($inv) {
            return $this->formatInvitation($inv);
        });

        return $this->success($formatted, 'Invitations retrieved');
    }

    /**
     * Get status of sent invitation (for polling while waiting)
     * GET /api/game/invite/{id}/status
     */
    public function getInvitationStatus(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $invitation = GameInvitation::with(['sender', 'receiver', 'gameSession'])->find($id);

        if (!$invitation) {
            return $this->error('Invitation not found', 404);
        }

        // Verify ownership
        if ($invitation->sender_id != $currentUser->id && $invitation->receiver_id != $currentUser->id) {
            return $this->error('Unauthorized', 403);
        }

        // Check and update expiry status
        $invitation->checkAndExpire();
        $invitation->refresh();

        $response = $this->formatInvitation($invitation);

        // Include game session if accepted
        if ($invitation->status === 'accepted' && $invitation->game_session_id) {
            $session = GameSession::find($invitation->game_session_id);
            if ($session) {
                $response['game_session'] = $this->formatSession($session, $currentUser->id);
            }
        }

        return $this->success($response, 'Invitation status retrieved');
    }

    /**
     * Accept an invitation
     * POST /api/game/invite/{id}/accept
     */
    public function acceptInvitation(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $invitation = GameInvitation::find($id);

        if (!$invitation) {
            return $this->error('Invitation not found', 404);
        }

        if ($invitation->receiver_id != $currentUser->id) {
            return $this->error('This invitation is not for you', 403);
        }

        if ($invitation->status !== 'pending') {
            return $this->error('This invitation is no longer pending');
        }

        if ($invitation->isExpired()) {
            $invitation->status = 'expired';
            $invitation->save();
            return $this->error('This invitation has expired');
        }

        // Create game session
        $session = $this->createGameSession($invitation->sender_id, $invitation->receiver_id);

        // Update invitation
        $invitation->status = 'accepted';
        $invitation->game_session_id = $session->id;
        $invitation->save();

        return $this->success(
            $this->formatSession($session, $currentUser->id),
            'Game started!'
        );
    }

    /**
     * Decline an invitation
     * POST /api/game/invite/{id}/decline
     */
    public function declineInvitation(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $invitation = GameInvitation::find($id);

        if (!$invitation) {
            return $this->error('Invitation not found', 404);
        }

        if ($invitation->receiver_id != $currentUser->id) {
            return $this->error('This invitation is not for you', 403);
        }

        $invitation->status = 'declined';
        $invitation->save();

        return $this->success(null, 'Invitation declined');
    }

    /**
     * Cancel a sent invitation
     * POST /api/game/invite/{id}/cancel
     */
    public function cancelInvitation(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $invitation = GameInvitation::find($id);

        if (!$invitation) {
            return $this->error('Invitation not found', 404);
        }

        if ($invitation->sender_id != $currentUser->id) {
            return $this->error('You can only cancel your own invitations', 403);
        }

        if ($invitation->status !== 'pending') {
            return $this->error('This invitation is no longer pending');
        }

        $invitation->status = 'cancelled';
        $invitation->save();

        return $this->success(null, 'Invitation cancelled');
    }

    // ========================================
    // GAME SESSIONS
    // ========================================

    /**
     * Get game session state
     * GET /api/game/session/{id}
     */
    public function getSession(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        // Update last online
        $user = User::find($currentUser->id);
        if ($user) {
            $user->last_online_at = now();
            $user->save();
        }

        $session = GameSession::find($id);

        if (!$session) {
            return $this->error('Game session not found', 404);
        }

        if (!$session->isPlayer($currentUser->id)) {
            return $this->error('You are not a player in this game', 403);
        }

        return $this->success(
            $this->formatSession($session, $currentUser->id),
            'Session retrieved'
        );
    }

    /**
     * Submit a game action (play card, draw, pass)
     * POST /api/game/session/{id}/action
     */
    public function submitAction(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $session = GameSession::find($id);

        if (!$session) {
            return $this->error('Game session not found', 404);
        }

        if (!$session->isPlayer($currentUser->id)) {
            return $this->error('You are not a player in this game', 403);
        }

        if ($session->status !== 'active') {
            return $this->error('Game is not active');
        }

        if (!$session->isMyTurn($currentUser->id)) {
            return $this->error('It is not your turn');
        }

        $action = $request->input('action');
        $data = $request->input('data', []);

        switch ($action) {
            case 'play_card':
                return $this->handlePlayCard($session, $currentUser->id, $data);

            case 'draw_card':
                return $this->handleDrawCard($session, $currentUser->id);

            case 'pass':
                return $this->handlePass($session, $currentUser->id);

            default:
                return $this->error('Invalid action');
        }
    }

    /**
     * Leave/forfeit a game
     * POST /api/game/session/{id}/leave
     */
    public function leaveSession(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $session = GameSession::find($id);

        if (!$session) {
            return $this->error('Game session not found', 404);
        }

        if (!$session->isPlayer($currentUser->id)) {
            return $this->error('You are not a player in this game', 403);
        }

        if ($session->status === 'completed') {
            return $this->error('Game is already completed');
        }

        // Set the other player as winner (forfeit)
        $winnerId = $session->player1_id == $currentUser->id
            ? $session->player2_id
            : $session->player1_id;

        $session->status = 'completed';
        $session->winner_id = $winnerId;
        $session->ended_at = now();
        $session->save();

        return $this->success(null, 'You have left the game');
    }

    // ========================================
    // GAME LOGIC HELPERS
    // ========================================

    /**
     * Create a new game session with shuffled deck
     */
    private function createGameSession($player1Id, $player2Id): GameSession
    {
        // Create shuffled deck
        $deck = $this->createShuffledDeck();

        // Deal 5 cards to each player
        $player1Hand = array_splice($deck, 0, 5);
        $player2Hand = array_splice($deck, 0, 5);

        // Cut card - first card from remaining deck, placed under draw pile
        $cutCard = array_shift($deck);

        // Put one card on discard pile (ensure it's not a special card for start)
        $startCard = null;
        $attempts = 0;
        while ($attempts < 10) {
            $startCard = array_pop($deck);
            // Avoid starting with Ace (1), 2, 8, or Jack (11) - special cards
            if (!in_array($startCard['rank'], [1, 2, 8, 11])) {
                break;
            }
            // Put it back at a random position
            array_splice($deck, rand(0, count($deck)), 0, [$startCard]);
            $attempts++;
        }
        $discardPile = [$startCard];

        // Create chat head for the game
        $chatHead = ChatHead::create([
            'product_owner_id' => $player1Id,
            'customer_id' => $player2Id,
            'type' => 'game',
        ]);

        // Randomly decide who goes first
        $firstPlayer = rand(0, 1) === 0 ? $player1Id : $player2Id;

        // Create session
        $session = GameSession::create([
            'player1_id' => $player1Id,
            'player2_id' => $player2Id,
            'player1_hand' => json_encode($player1Hand),
            'player2_hand' => json_encode($player2Hand),
            'discard_pile' => json_encode($discardPile),
            'draw_pile' => json_encode($deck),
            'cut_card' => json_encode($cutCard),
            'current_turn_user_id' => $firstPlayer,
            'current_suit' => $startCard['suit'],
            'draw_stack' => 0,
            'player1_score' => 0,
            'player2_score' => 0,
            'player1_rounds_won' => 0,
            'player2_rounds_won' => 0,
            'current_round' => 1,
            'target_score' => 100,
            'status' => 'active',
            'chat_head_id' => $chatHead->id,
            'started_at' => now(),
        ]);

        return $session;
    }

    /**
     * Create a shuffled 52-card deck
     */
    private function createShuffledDeck(): array
    {
        $deck = [];

        foreach (self::SUITS as $suit) {
            for ($rank = 1; $rank <= 13; $rank++) {
                $deck[] = [
                    'suit' => $suit,
                    'rank' => $rank,
                ];
            }
        }

        // Shuffle multiple times
        for ($i = 0; $i < 3; $i++) {
            shuffle($deck);
        }

        return $deck;
    }

    /**
     * Handle play card action
     */
    private function handlePlayCard(GameSession $session, $playerId, $data)
    {
        $cardId = $data['card_id'] ?? null;
        $newSuit = $data['new_suit'] ?? null;

        if (!$cardId) {
            return $this->error('Card ID is required');
        }

        // Parse card_id (format: "suit_rank")
        $parts = explode('_', $cardId);
        if (count($parts) !== 2) {
            return $this->error('Invalid card ID format');
        }
        $cardSuit = $parts[0];
        $cardRank = (int) $parts[1];

        // Get player's hand
        $hand = $session->getPlayerHand($playerId);

        // Find the card in hand
        $cardIndex = -1;
        foreach ($hand as $i => $c) {
            if ($c['suit'] === $cardSuit && $c['rank'] === $cardRank) {
                $cardIndex = $i;
                break;
            }
        }

        if ($cardIndex === -1) {
            return $this->error('Card not found in your hand');
        }

        $card = $hand[$cardIndex];
        $topCard = $session->getTopCard();
        $currentSuit = $session->current_suit ?: ($topCard['suit'] ?? null);

        // Validate play
        if (!$this->isValidPlay($card, $topCard, $currentSuit, $session->draw_stack)) {
            return $this->error('Invalid card play');
        }

        // Remove card from hand
        array_splice($hand, $cardIndex, 1);
        $session->setPlayerHand($playerId, $hand);

        // Add to discard pile
        $session->addToDiscardPile($card);

        // Handle special cards
        $skipTurn = false;
        $extraTurn = false;

        // Ace - set new suit
        if ($cardRank === 1) {
            if ($newSuit && in_array($newSuit, self::SUITS)) {
                $session->current_suit = $newSuit;
            } else {
                $session->current_suit = $card['suit'];
            }
        } else {
            $session->current_suit = $card['suit'];
        }

        // 2 - Add to draw stack
        if ($cardRank === 2) {
            $session->draw_stack += 2;
        } else {
            // If playing non-2 and there's a draw stack, must handle it
            if ($session->draw_stack > 0) {
                // Player already drew cards (handled elsewhere) or this shouldn't happen
            }
        }

        // 8 - Skip opponent (give extra turn in 2-player)
        if ($cardRank === 8) {
            $extraTurn = true;
        }

        // Jack (11) - Reverse (extra turn in 2-player)
        if ($cardRank === 11) {
            $extraTurn = true;
        }

        // Check for round win (empty hand)
        if (empty($hand)) {
            return $this->handleRoundWin($session, $playerId);
        }

        // Switch turn unless extra turn
        if (!$extraTurn) {
            $session->switchTurn();
        }

        $session->save();

        return $this->success(
            $this->formatSession($session, $playerId),
            'Card played'
        );
    }

    /**
     * Handle draw card action
     */
    private function handleDrawCard(GameSession $session, $playerId)
    {
        // If there's a draw stack (from 2s), must draw that many
        $cardsToDraw = $session->draw_stack > 0 ? $session->draw_stack : 1;

        $hand = $session->getPlayerHand($playerId);
        $drawnCards = [];

        for ($i = 0; $i < $cardsToDraw; $i++) {
            $card = $session->drawCard();
            if ($card) {
                $hand[] = $card;
                $drawnCards[] = $card;
            }
        }

        $session->setPlayerHand($playerId, $hand);
        $session->draw_stack = 0; // Clear draw stack after drawing

        // Note: Player can still try to play after drawing
        // They would need to "pass" if they can't play

        $session->save();

        return $this->success(
            $this->formatSession($session, $playerId),
            count($drawnCards) . ' card(s) drawn'
        );
    }

    /**
     * Handle pass action (after drawing)
     */
    private function handlePass(GameSession $session, $playerId)
    {
        $session->switchTurn();
        $session->save();

        return $this->success(
            $this->formatSession($session, $playerId),
            'Turn passed'
        );
    }

    /**
     * Handle round win
     */
    private function handleRoundWin(GameSession $session, $winnerId)
    {
        // Calculate points from opponent's hand
        $loserId = $session->player1_id == $winnerId
            ? $session->player2_id
            : $session->player1_id;

        $loserHand = $session->getPlayerHand($loserId);
        $points = $this->calculateHandPoints($loserHand);

        // Update scores
        if ($session->player1_id == $winnerId) {
            $session->player1_score += $points;
            $session->player1_rounds_won++;
        } else {
            $session->player2_score += $points;
            $session->player2_rounds_won++;
        }

        // Check for game win
        $winnerScore = $session->player1_id == $winnerId
            ? $session->player1_score
            : $session->player2_score;

        if ($winnerScore >= $session->target_score) {
            // Game over!
            $session->status = 'completed';
            $session->winner_id = $winnerId;
            $session->ended_at = now();
            $session->save();

            return $this->success(
                $this->formatSession($session, $winnerId),
                'Game over! You won!'
            );
        }

        // Start new round
        $session->current_round++;

        // Re-deal cards
        $deck = $this->createShuffledDeck();
        $player1Hand = array_splice($deck, 0, 5);
        $player2Hand = array_splice($deck, 0, 5);

        // New start card
        $startCard = array_pop($deck);
        while (in_array($startCard['rank'], [1, 2, 8, 11])) {
            array_splice($deck, rand(0, count($deck)), 0, [$startCard]);
            $startCard = array_pop($deck);
        }

        $session->player1_hand = json_encode($player1Hand);
        $session->player2_hand = json_encode($player2Hand);
        $session->discard_pile = json_encode([$startCard]);
        $session->draw_pile = json_encode($deck);
        $session->current_suit = $startCard['suit'];
        $session->draw_stack = 0;

        // Loser starts next round
        $session->current_turn_user_id = $loserId;

        $session->save();

        return $this->success(
            $this->formatSession($session, $winnerId),
            "Round won! +{$points} points. Round {$session->current_round} starting..."
        );
    }

    /**
     * Check if a card play is valid
     */
    private function isValidPlay($card, $topCard, $currentSuit, $drawStack): bool
    {
        // If there's a draw stack, only 2 can be played
        if ($drawStack > 0) {
            return $card['rank'] === 2;
        }

        // Ace can always be played (wild)
        if ($card['rank'] === 1) {
            return true;
        }

        // If no top card (shouldn't happen), anything is valid
        if (!$topCard) {
            return true;
        }

        // Match suit
        if ($card['suit'] === $currentSuit) {
            return true;
        }

        // Match rank
        if ($card['rank'] === $topCard['rank']) {
            return true;
        }

        return false;
    }

    /**
     * Calculate points in a hand
     */
    private function calculateHandPoints(array $hand): int
    {
        $points = 0;
        foreach ($hand as $card) {
            $rank = $card['rank'];
            switch ($rank) {
                case 2: // Two - highest penalty
                    $points += 25;
                    break;
                case 1: // Ace
                    $points += 15;
                    break;
                case 13: // King
                    $points += 12;
                    break;
                case 12: // Queen
                    $points += 11;
                    break;
                case 11: // Jack
                    $points += 10;
                    break;
                default: // 3-10
                    $points += $rank;
                    break;
            }
        }
        return $points;
    }

    // ========================================
    // FORMATTERS
    // ========================================

    /**
     * Format invitation for API response
     */
    private function formatInvitation(GameInvitation $invitation): array
    {
        $sender = $invitation->sender;
        $receiver = $invitation->receiver;

        return [
            'id' => $invitation->id,
            'sender_id' => $invitation->sender_id,
            'sender_name' => $sender ? ($sender->name ?? $sender->first_name ?? 'Player') : 'Unknown',
            'sender_avatar' => $sender->avatar ?? null,
            'receiver_id' => $invitation->receiver_id,
            'receiver_name' => $receiver ? ($receiver->name ?? $receiver->first_name ?? 'Player') : 'Unknown',
            'receiver_avatar' => $receiver->avatar ?? null,
            'game_type' => $invitation->game_type,
            'status' => $invitation->status,
            'message' => $invitation->message ?? '',
            'expires_at' => $invitation->expires_at ? $invitation->expires_at->toIso8601String() : null,
            'remaining_seconds' => $invitation->getRemainingSeconds(),
            'game_session_id' => $invitation->game_session_id,
            'created_at' => $invitation->created_at->toIso8601String(),
        ];
    }

    /**
     * Format session for API response
     */
    private function formatSession(GameSession $session, $currentUserId): array
    {
        $isPlayer1 = $session->player1_id == $currentUserId;
        
        // Load player info
        $player1 = User::find($session->player1_id);
        $player2 = User::find($session->player2_id);

        // Get opponent card count
        $player1CardCount = count(json_decode($session->player1_hand ?? '[]', true) ?? []);
        $player2CardCount = count(json_decode($session->player2_hand ?? '[]', true) ?? []);

        return [
            'id' => $session->id,
            'player1_id' => $session->player1_id,
            'player1_name' => $player1 ? ($player1->name ?? $player1->first_name ?? 'Player 1') : 'Player 1',
            'player1_avatar' => $player1->avatar ?? null,
            'player2_id' => $session->player2_id,
            'player2_name' => $player2 ? ($player2->name ?? $player2->first_name ?? 'Player 2') : 'Player 2',
            'player2_avatar' => $player2->avatar ?? null,
            'player1_hand' => $isPlayer1 ? $session->player1_hand : json_encode([]), // Only show own hand
            'player2_hand' => !$isPlayer1 ? $session->player2_hand : json_encode([]),
            'player1_card_count' => $player1CardCount,
            'player2_card_count' => $player2CardCount,
            'discard_pile' => $session->discard_pile,
            'cut_card' => $session->cut_card,
            'draw_pile_count' => count(json_decode($session->draw_pile ?? '[]', true) ?? []),
            'current_turn_user_id' => $session->current_turn_user_id,
            'current_suit' => $session->current_suit,
            'draw_stack' => $session->draw_stack,
            'player1_score' => $session->player1_score,
            'player2_score' => $session->player2_score,
            'player1_rounds_won' => $session->player1_rounds_won,
            'player2_rounds_won' => $session->player2_rounds_won,
            'current_round' => $session->current_round,
            'target_score' => $session->target_score,
            'status' => $session->status,
            'winner_id' => $session->winner_id,
            'chat_head_id' => $session->chat_head_id,
            'started_at' => $session->started_at ? $session->started_at->toIso8601String() : null,
            'ended_at' => $session->ended_at ? $session->ended_at->toIso8601String() : null,
            // Convenience fields
            'is_my_turn' => $session->current_turn_user_id == $currentUserId,
            'my_hand' => $session->getPlayerHand($currentUserId),
            'my_info' => [
                'id' => $currentUserId,
                'name' => $isPlayer1 
                    ? ($player1 ? ($player1->name ?? $player1->first_name ?? 'Player 1') : 'Player 1')
                    : ($player2 ? ($player2->name ?? $player2->first_name ?? 'Player 2') : 'Player 2'),
                'avatar' => $isPlayer1 ? ($player1->avatar ?? null) : ($player2->avatar ?? null),
                'score' => $isPlayer1 ? $session->player1_score : $session->player2_score,
                'card_count' => $isPlayer1 ? $player1CardCount : $player2CardCount,
            ],
            'opponent_info' => [
                'id' => $isPlayer1 ? $session->player2_id : $session->player1_id,
                'name' => $isPlayer1 
                    ? ($player2 ? ($player2->name ?? $player2->first_name ?? 'Player 2') : 'Player 2')
                    : ($player1 ? ($player1->name ?? $player1->first_name ?? 'Player 1') : 'Player 1'),
                'avatar' => $isPlayer1 ? ($player2->avatar ?? null) : ($player1->avatar ?? null),
                'score' => $isPlayer1 ? $session->player2_score : $session->player1_score,
                'card_count' => $isPlayer1 ? $player2CardCount : $player1CardCount,
            ],
        ];
    }
}
