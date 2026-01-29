<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'player1_id',
        'player2_id',
        'player1_hand',
        'player2_hand',
        'discard_pile',
        'draw_pile',
        'cut_card',
        'current_turn_user_id',
        'current_suit',
        'draw_stack',
        'player1_score',
        'player2_score',
        'player1_rounds_won',
        'player2_rounds_won',
        'player1_last_poll',
        'player2_last_poll',
        'current_round',
        'target_score',
        'status',
        'winner_id',
        'forfeit_user_id',
        'chat_head_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'player1_last_poll' => 'datetime',
        'player2_last_poll' => 'datetime',
    ];

    /**
     * Get player 1
     */
    public function player1()
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    /**
     * Get player 2
     */
    public function player2()
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    /**
     * Get the current turn player
     */
    public function currentTurnPlayer()
    {
        return $this->belongsTo(User::class, 'current_turn_user_id');
    }

    /**
     * Get the winner
     */
    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    /**
     * Get the chat head
     */
    public function chatHead()
    {
        return $this->belongsTo(ChatHead::class, 'chat_head_id');
    }

    /**
     * Check if user is a player in this session
     */
    public function isPlayer($userId): bool
    {
        return $this->player1_id == $userId || $this->player2_id == $userId;
    }

    /**
     * Get player's hand
     */
    public function getPlayerHand($userId): array
    {
        if ($this->player1_id == $userId) {
            return json_decode($this->player1_hand ?? '[]', true) ?? [];
        } elseif ($this->player2_id == $userId) {
            return json_decode($this->player2_hand ?? '[]', true) ?? [];
        }
        return [];
    }

    /**
     * Set player's hand
     */
    public function setPlayerHand($userId, array $hand): void
    {
        if ($this->player1_id == $userId) {
            $this->player1_hand = json_encode($hand);
        } elseif ($this->player2_id == $userId) {
            $this->player2_hand = json_encode($hand);
        }
    }

    /**
     * Get opponent's info for a player
     */
    public function getOpponentInfo($userId): array
    {
        $opponentId = $this->player1_id == $userId ? $this->player2_id : $this->player1_id;
        $opponent = User::find($opponentId);
        
        if (!$opponent) {
            return ['id' => $opponentId, 'name' => 'Unknown', 'avatar' => null, 'score' => 0];
        }

        $score = $this->player1_id == $opponentId ? $this->player1_score : $this->player2_score;

        return [
            'id' => $opponent->id,
            'name' => $opponent->name ?? $opponent->first_name ?? 'Player',
            'avatar' => $opponent->avatar ?? null,
            'score' => $score,
        ];
    }

    /**
     * Get my info for a player
     */
    public function getMyInfo($userId): array
    {
        $player = User::find($userId);
        $score = $this->player1_id == $userId ? $this->player1_score : $this->player2_score;
        $hand = $this->getPlayerHand($userId);

        return [
            'id' => $userId,
            'name' => $player->name ?? $player->first_name ?? 'Player',
            'avatar' => $player->avatar ?? null,
            'score' => $score,
            'hand' => json_encode($hand),
        ];
    }

    /**
     * Check if it's this player's turn
     */
    public function isMyTurn($userId): bool
    {
        return $this->current_turn_user_id == $userId;
    }

    /**
     * Switch turn to the other player
     */
    public function switchTurn(): void
    {
        $this->current_turn_user_id = $this->current_turn_user_id == $this->player1_id
            ? $this->player2_id
            : $this->player1_id;
    }

    /**
     * Get discard pile as array
     */
    public function getDiscardPile(): array
    {
        return json_decode($this->discard_pile ?? '[]', true) ?? [];
    }

    /**
     * Set discard pile
     */
    public function setDiscardPile(array $pile): void
    {
        $this->discard_pile = json_encode($pile);
    }

    /**
     * Get draw pile as array
     */
    public function getDrawPile(): array
    {
        return json_decode($this->draw_pile ?? '[]', true) ?? [];
    }

    /**
     * Set draw pile
     */
    public function setDrawPile(array $pile): void
    {
        $this->draw_pile = json_encode($pile);
    }

    /**
     * Get the top card of discard pile
     */
    public function getTopCard(): ?array
    {
        $pile = $this->getDiscardPile();
        return !empty($pile) ? end($pile) : null;
    }

    /**
     * Draw a card from the draw pile
     */
    public function drawCard(): ?array
    {
        $drawPile = $this->getDrawPile();
        if (empty($drawPile)) {
            // Reshuffle discard pile if draw pile is empty
            $discardPile = $this->getDiscardPile();
            if (count($discardPile) <= 1) {
                return null; // No cards to draw
            }
            $topCard = array_pop($discardPile);
            shuffle($discardPile);
            $drawPile = $discardPile;
            $this->setDiscardPile([$topCard]);
        }
        
        $card = array_pop($drawPile);
        $this->setDrawPile($drawPile);
        return $card;
    }

    /**
     * Add a card to discard pile
     */
    public function addToDiscardPile(array $card): void
    {
        $pile = $this->getDiscardPile();
        $pile[] = $card;
        $this->setDiscardPile($pile);
    }
}
