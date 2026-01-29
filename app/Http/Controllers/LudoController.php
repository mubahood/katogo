<?php

namespace App\Http\Controllers;

use App\Models\LudoSession;
use App\Models\GameInvitation;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;

/**
 * Ludo Game Controller
 * 
 * Handles all Ludo-specific game actions:
 * - Creating game sessions
 * - Rolling dice
 * - Moving pieces
 * - Game state management
 * 
 * Reuses invitation system from GameController
 */
class LudoController extends Controller
{
    use ApiResponser;

    // ========================================
    // GAME SESSION MANAGEMENT
    // ========================================

    /**
     * Get Ludo session state
     * GET /api/ludo/session/{id}
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

        $session = LudoSession::find($id);

        if (!$session) {
            return $this->error('Ludo session not found', 404);
        }

        if (!$session->isPlayer($currentUser->id)) {
            return $this->error('You are not a player in this game', 403);
        }

        // Track last poll time for abandonment detection
        $playerNum = $session->getPlayerNumber($currentUser->id);
        $session->{"player{$playerNum}_last_poll"} = now();
        $session->save();

        // Check if opponent abandoned (no poll in 30 seconds)
        $this->checkAbandonment($session, $currentUser->id);

        return $this->success(
            $session->toApiFormat($currentUser->id),
            'Session retrieved'
        );
    }

    /**
     * Check if any player has abandoned the game
     */
    private function checkAbandonment(LudoSession $session, $currentUserId)
    {
        if ($session->status !== 'playing') {
            return;
        }

        $abandonmentThreshold = now()->subSeconds(30);
        $maxPlayers = ($session->game_type == '4_player') ? 4 : 2;
        
        for ($p = 1; $p <= $maxPlayers; $p++) {
            $playerId = $session->{"player{$p}_id"};
            
            // Skip current user and empty slots
            if (!$playerId || $playerId == $currentUserId) {
                continue;
            }
            
            $lastPoll = $session->{"player{$p}_last_poll"};
            
            if ($lastPoll && $lastPoll < $abandonmentThreshold) {
                // Player seems to have abandoned - but don't auto-forfeit yet
                // Just log it for now (could implement auto-forfeit after longer period)
                \Log::info("Ludo: Player {$p} may have abandoned session {$session->id}");
            }
        }
    }

    /**
     * Create a new Ludo game session from accepted invitation
     * Called internally when invitation is accepted
     */
    public static function createFromInvitation($invitation)
    {
        return LudoSession::createTwoPlayerGame(
            $invitation->sender_id,
            $invitation->receiver_id
        );
    }

    // ========================================
    // GAME ACTIONS
    // ========================================

    /**
     * Roll the dice
     * POST /api/ludo/session/{id}/roll
     */
    public function rollDice(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $session = LudoSession::find($id);

        if (!$session) {
            return $this->error('Ludo session not found', 404);
        }

        if (!$session->isPlayer($currentUser->id)) {
            return $this->error('You are not a player in this game', 403);
        }

        if ($session->status !== 'playing') {
            return $this->error('Game is not active', 400);
        }

        // Roll the dice
        $result = $session->rollDice($currentUser->id);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        // Refresh session for response
        $session->refresh();

        return $this->success([
            'dice' => $result['dice'],
            'valid_moves' => $result['valid_moves'] ?? [],
            'can_roll_again' => $result['can_roll_again'] ?? false,
            'turn_ended' => $result['turn_ended'] ?? false,
            'message' => $result['message'] ?? null,
            'session' => $session->toApiFormat($currentUser->id),
        ], 'Dice rolled: ' . $result['dice']);
    }

    /**
     * Move a piece
     * POST /api/ludo/session/{id}/move
     */
    public function movePiece(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $pieceId = $request->input('piece_id');
        
        if ($pieceId === null) {
            return $this->error('piece_id is required');
        }

        $session = LudoSession::find($id);

        if (!$session) {
            return $this->error('Ludo session not found', 404);
        }

        if (!$session->isPlayer($currentUser->id)) {
            return $this->error('You are not a player in this game', 403);
        }

        if ($session->status !== 'playing') {
            return $this->error('Game is not active', 400);
        }

        // Move the piece
        $result = $session->movePiece($currentUser->id, (int) $pieceId);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        // Refresh session for response
        $session->refresh();

        $response = [
            'session' => $session->toApiFormat($currentUser->id),
            'roll_again' => $result['roll_again'] ?? false,
            'turn_ended' => $result['turn_ended'] ?? false,
            'captured' => $result['captured'] ?? false,
            'reached_home' => $result['reached_home'] ?? false,
            'game_over' => $result['game_over'] ?? false,
        ];

        if (!empty($result['winner'])) {
            $response['winner_user_id'] = $result['winner'];
        }

        return $this->success($response, $result['message']);
    }

    /**
     * Pass turn (when no valid moves after rolling)
     * POST /api/ludo/session/{id}/pass
     */
    public function passTurn(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $session = LudoSession::find($id);

        if (!$session) {
            return $this->error('Ludo session not found', 404);
        }

        if (!$session->isPlayer($currentUser->id)) {
            return $this->error('You are not a player in this game', 403);
        }

        if ($session->status !== 'playing') {
            return $this->error('Game is not active', 400);
        }

        if ($session->current_turn_user_id != $currentUser->id) {
            return $this->error('Not your turn');
        }

        // Can only pass after rolling with no moves (or decline bonus roll)
        if ($session->must_move_piece) {
            // Check if there are actually no valid moves
            $playerNum = $session->getPlayerNumber($currentUser->id);
            $validMoves = $session->getValidMoves($playerNum, $session->last_dice_roll);
            
            if (!empty($validMoves)) {
                return $this->error('You have valid moves available');
            }
        }

        // End turn
        $session->last_action = 'Passed turn';
        $session->last_action_player = $session->getPlayerNumber($currentUser->id);
        $session->must_move_piece = false;
        $session->can_roll_again = false;
        $session->nextTurn();
        $session->save();

        return $this->success(
            $session->toApiFormat($currentUser->id),
            'Turn passed'
        );
    }

    /**
     * Leave/forfeit the game
     * POST /api/ludo/session/{id}/leave
     */
    public function leaveGame(Request $request, $id)
    {
        $currentUser = Utils::get_user($request);
        if (!$currentUser) {
            return $this->error('Authentication required', 401);
        }

        $session = LudoSession::find($id);

        if (!$session) {
            return $this->error('Ludo session not found', 404);
        }

        if (!$session->isPlayer($currentUser->id)) {
            return $this->error('You are not a player in this game', 403);
        }

        $result = $session->playerLeave($currentUser->id);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        // Update user stats (losses)
        $user = User::find($currentUser->id);
        if ($user) {
            $user->total_games_played = ($user->total_games_played ?? 0) + 1;
            $user->save();
        }

        return $this->success(null, 'You left the game');
    }

    // ========================================
    // LUDO-SPECIFIC INVITATION HANDLING
    // ========================================

    /**
     * Accept a Ludo game invitation
     * POST /api/ludo/invite/{id}/accept
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

        // Verify it's a Ludo invitation
        if ($invitation->game_type !== 'ludo') {
            return $this->error('This is not a Ludo invitation');
        }

        // Create Ludo game session
        $session = self::createFromInvitation($invitation);

        // Update invitation
        $invitation->status = 'accepted';
        $invitation->game_session_id = $session->id;
        $invitation->save();

        return $this->success(
            $session->toApiFormat($currentUser->id),
            'Ludo game started!'
        );
    }

    /**
     * Send a Ludo game invitation
     * POST /api/ludo/invite
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

        // Check for existing pending Ludo invitation
        $existingInvite = GameInvitation::where('sender_id', $currentUser->id)
            ->where('receiver_id', $receiverId)
            ->where('game_type', 'ludo')
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvite) {
            return $this->error('You already have a pending Ludo invitation to this user');
        }

        // Create invitation for Ludo
        $invitation = GameInvitation::createInvitation(
            $currentUser->id,
            $receiverId,
            'ludo', // Game type
            $message
        );

        // Load relationships
        $invitation->load(['sender', 'receiver']);

        return $this->success(
            $this->formatInvitation($invitation),
            'Ludo invitation sent successfully'
        );
    }

    /**
     * Format invitation for response
     */
    private function formatInvitation($invitation)
    {
        return [
            'id' => $invitation->id,
            'sender_id' => $invitation->sender_id,
            'sender_name' => $invitation->sender->name ?? 'Player',
            'sender_avatar' => $invitation->sender->avatar ?? '',
            'receiver_id' => $invitation->receiver_id,
            'receiver_name' => $invitation->receiver->name ?? 'Player',
            'receiver_avatar' => $invitation->receiver->avatar ?? '',
            'game_type' => $invitation->game_type,
            'status' => $invitation->status,
            'message' => $invitation->message,
            'game_session_id' => $invitation->game_session_id,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'created_at' => $invitation->created_at?->toIso8601String(),
        ];
    }
}
