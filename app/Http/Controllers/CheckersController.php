<?php

namespace App\Http\Controllers;

use App\Models\CheckersSession;
use App\Models\CheckersChatMessage;
use App\Models\GameInvitation;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Checkers Game Controller
 *
 * Handles all Checkers-specific game actions:
 * - Creating/joining rooms (private code)
 * - Game session retrieval (polling)
 * - Making moves (with server-side validation)
 * - Chat messages
 * - Leaving games
 *
 * Reuses invitation system from GameController for matchmaking.
 */
class CheckersController extends Controller
{
    use ApiResponser;

    // ========================================
    // INVITATION → SESSION
    // ========================================

    /**
     * POST /checkers/invite
     * Send a Checkers-specific invitation.
     */
    public function sendInvitation(Request $request)
    {
        $senderId = $request->input('logged_in_user_id');
        $receiverId = $request->input('receiver_id');

        if (!$senderId || !$receiverId) {
            return $this->error('Both sender and receiver are required.', 422);
        }

        $sender = User::find($senderId);
        $receiver = User::find($receiverId);
        if (!$sender || !$receiver) {
            return $this->error('User not found.', 404);
        }

        // Prevent duplicate pending invitations
        $existing = GameInvitation::where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->where('game_type', 'checkers')
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return $this->success($existing, 'Invitation already pending.');
        }

        $invitation = GameInvitation::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'game_type' => 'checkers',
            'message' => $request->input('message', 'Let\'s play Checkers!'),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);

        return $this->success($invitation, 'Invitation sent.');
    }

    /**
     * POST /checkers/invite/{id}/accept
     * Accept an invitation and create a game session.
     */
    public function acceptInvitation(Request $request, int $id)
    {
        $userId = $request->input('logged_in_user_id');
        $invitation = GameInvitation::find($id);

        if (!$invitation || $invitation->receiver_id != $userId) {
            return $this->error('Invalid invitation.', 403);
        }

        if ($invitation->status !== 'pending') {
            return $this->error('Invitation is no longer pending.', 409);
        }

        $invitation->status = 'accepted';
        $invitation->save();

        $sender = User::find($invitation->sender_id);
        $receiver = User::find($invitation->receiver_id);

        $session = CheckersSession::createFromInvitation(
            $sender->id, $sender->name ?? $sender->email,
            $receiver->id, $receiver->name ?? $receiver->email
        );

        $invitation->game_session_id = $session->id;
        $invitation->save();

        return $this->success([
            'session' => $session->toApiFormat(),
            'invitation' => $invitation,
        ], 'Game started!');
    }

    // ========================================
    // PRIVATE ROOM
    // ========================================

    /**
     * POST /checkers/room/create
     * Create a private room with a code.
     */
    public function createRoom(Request $request)
    {
        $userId = $request->input('logged_in_user_id');
        $user = User::find($userId);
        if (!$user) return $this->error('User not found.', 404);

        $session = CheckersSession::createRoom($userId, $user->name ?? $user->email);

        return $this->success([
            'session' => $session->toApiFormat(),
        ], 'Room created. Share the code: ' . $session->session_code);
    }

    /**
     * POST /checkers/room/join
     * Join a room by code.
     */
    public function joinRoom(Request $request)
    {
        $userId = $request->input('logged_in_user_id');
        $code = strtoupper(trim($request->input('code', '')));

        if (strlen($code) < 4) {
            return $this->error('Invalid room code.', 422);
        }

        $session = CheckersSession::where('session_code', $code)
            ->where('status', 'pending')
            ->first();

        if (!$session) {
            return $this->error('Room not found or already started.', 404);
        }

        if ($session->player1_id == $userId) {
            return $this->error('You cannot join your own room.', 409);
        }

        $user = User::find($userId);
        $session->player2_id = $userId;
        $session->player2_name = $user->name ?? $user->email;
        $session->status = 'active';
        $session->started_at = now();
        $session->expires_at = now()->addHours(2);
        $session->save();

        return $this->success([
            'session' => $session->toApiFormat(),
        ], 'Joined the game!');
    }

    // ========================================
    // GAME SESSION
    // ========================================

    /**
     * GET /checkers/session/{id}
     * Retrieve game state (for polling).
     */
    public function getSession(Request $request, int $id)
    {
        $userId = $request->input('logged_in_user_id');
        $session = CheckersSession::find($id);

        if (!$session) return $this->error('Session not found.', 404);

        // Update poll timestamp for abandonment detection
        if ($session->player1_id == $userId) {
            $session->player1_last_poll = now();
        } elseif ($session->player2_id == $userId) {
            $session->player2_last_poll = now();
        }
        $session->save();

        // Check opponent abandonment (60s no poll while active)
        if ($session->status === 'active') {
            $opponentPoll = $session->player1_id == $userId
                ? $session->player2_last_poll
                : $session->player1_last_poll;
            if ($opponentPoll && $opponentPoll->diffInSeconds(now()) > 60) {
                $session->status = 'cancelled';
                $session->winner_id = $userId;
                $session->winner_name = $session->player1_id == $userId
                    ? $session->player1_name : $session->player2_name;
                $session->ended_at = now();
                $session->save();
            }
        }

        return $this->success([
            'session' => $session->toApiFormat(),
        ]);
    }

    /**
     * POST /checkers/session/{id}/move
     * Make a move.
     */
    public function makeMove(Request $request, int $id)
    {
        $userId = $request->input('logged_in_user_id');
        $from = (int) $request->input('from');
        $to = (int) $request->input('to');

        $session = CheckersSession::find($id);
        if (!$session) return $this->error('Session not found.', 404);

        $validation = $session->validateMove($from, $to, $userId);
        if (!$validation['valid']) {
            return $this->error($validation['error'], 422);
        }

        $crowned = $session->applyMove($validation['move']);

        return $this->success([
            'session' => $session->fresh()->toApiFormat(),
            'crowned' => $crowned,
        ], 'Move applied.');
    }

    /**
     * POST /checkers/session/{id}/leave
     * Leave / forfeit the game.
     */
    public function leaveGame(Request $request, int $id)
    {
        $userId = $request->input('logged_in_user_id');
        $session = CheckersSession::find($id);
        if (!$session) return $this->error('Session not found.', 404);

        $opponentId = $session->player1_id == $userId
            ? $session->player2_id
            : $session->player1_id;
        $opponentName = $session->player1_id == $userId
            ? $session->player2_name
            : $session->player1_name;

        $session->status = 'cancelled';
        $session->winner_id = $opponentId;
        $session->winner_name = $opponentName;
        $session->ended_at = now();
        $session->save();

        return $this->success(null, 'You left the game.');
    }

    // ========================================
    // CHAT
    // ========================================

    /**
     * GET /checkers/session/{id}/chat
     * Get chat messages for a session.
     */
    public function getChat(Request $request, int $id)
    {
        $afterId = (int) $request->input('after_id', 0);

        $session = CheckersSession::find($id);
        if (!$session) return $this->error('Session not found.', 404);

        $query = CheckersChatMessage::where('session_id', $id)
            ->orderBy('id', 'asc');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $messages = $query->limit(100)->get();

        return $this->success([
            'messages' => $messages->toArray(),
        ]);
    }

    /**
     * POST /checkers/session/{id}/chat
     * Send a chat message.
     */
    public function sendChat(Request $request, int $id)
    {
        $userId = $request->input('logged_in_user_id');
        $text = trim($request->input('message', ''));

        if (empty($text) || mb_strlen($text) > 500) {
            return $this->error('Message must be 1-500 characters.', 422);
        }

        $session = CheckersSession::find($id);
        if (!$session) return $this->error('Session not found.', 404);

        // Only players in the session can chat
        if ($session->player1_id != $userId && $session->player2_id != $userId) {
            return $this->error('Not a player in this game.', 403);
        }

        $userName = $session->player1_id == $userId
            ? $session->player1_name
            : $session->player2_name;

        $msg = CheckersChatMessage::create([
            'session_id' => $id,
            'user_id' => $userId,
            'user_name' => $userName,
            'message' => $text,
        ]);

        return $this->success(['message' => $msg->toArray()], 'Message sent.');
    }
}
