<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoinTransaction extends Model
{
    use HasFactory;

    // Transaction types
    const TYPE_GAME_WIN_ONLINE = 'game_win_online';
    const TYPE_GAME_WIN_OFFLINE = 'game_win_offline';
    const TYPE_GAME_FORFEIT = 'game_forfeit';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_REWARD = 'reward';
    const TYPE_ADMIN_ADJUSTMENT = 'admin_adjustment';
    const TYPE_SIGNUP_BONUS = 'signup_bonus';

    // Coin amounts
    const COINS_WIN_ONLINE = 10;
    const COINS_WIN_OFFLINE = 2;
    const COINS_FORFEIT_PENALTY = -5;
    const COINS_SIGNUP_BONUS = 50;

    protected $fillable = [
        'user_id',
        'amount',
        'balance_after',
        'type',
        'description',
        'game_session_id',
        'related_user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user who owns this transaction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the game session if applicable
     */
    public function gameSession()
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * Get the related user (opponent)
     */
    public function relatedUser()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    /**
     * Award coins to a user
     * 
     * @param int $userId
     * @param int $amount (positive for credit, negative for debit)
     * @param string $type
     * @param string|null $description
     * @param int|null $gameSessionId
     * @param int|null $relatedUserId
     * @param array|null $metadata
     * @return CoinTransaction|null
     */
    public static function award(
        int $userId, 
        int $amount, 
        string $type, 
        ?string $description = null,
        ?int $gameSessionId = null,
        ?int $relatedUserId = null,
        ?array $metadata = null
    ): ?CoinTransaction {
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        // Calculate new balance (can go negative for penalties)
        $newBalance = $user->game_coins_balance + $amount;
        
        // Update user balance
        $user->game_coins_balance = $newBalance;
        $user->save();

        // Create transaction record
        $transaction = self::create([
            'user_id' => $userId,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'type' => $type,
            'description' => $description,
            'game_session_id' => $gameSessionId,
            'related_user_id' => $relatedUserId,
            'metadata' => $metadata,
        ]);

        return $transaction;
    }

    /**
     * Award coins for winning an online game
     */
    public static function awardOnlineWin(int $userId, int $gameSessionId, int $opponentId): ?CoinTransaction
    {
        return self::award(
            $userId,
            self::COINS_WIN_ONLINE,
            self::TYPE_GAME_WIN_ONLINE,
            'Won online Matatu game',
            $gameSessionId,
            $opponentId,
            ['game_type' => 'multiplayer', 'opponent_id' => $opponentId]
        );
    }

    /**
     * Award coins for winning an offline game (vs bot)
     */
    public static function awardOfflineWin(int $userId): ?CoinTransaction
    {
        return self::award(
            $userId,
            self::COINS_WIN_OFFLINE,
            self::TYPE_GAME_WIN_OFFLINE,
            'Won offline Matatu game vs Bot',
            null,
            null,
            ['game_type' => 'offline']
        );
    }

    /**
     * Deduct coins for forfeiting a game
     */
    public static function deductForfeit(int $userId, int $gameSessionId, int $opponentId): ?CoinTransaction
    {
        return self::award(
            $userId,
            self::COINS_FORFEIT_PENALTY,
            self::TYPE_GAME_FORFEIT,
            'Forfeited Matatu game',
            $gameSessionId,
            $opponentId,
            ['game_type' => 'multiplayer', 'opponent_id' => $opponentId]
        );
    }

    /**
     * Give signup bonus
     */
    public static function giveSignupBonus(int $userId): ?CoinTransaction
    {
        return self::award(
            $userId,
            self::COINS_SIGNUP_BONUS,
            self::TYPE_SIGNUP_BONUS,
            'Welcome bonus for joining Matatu!',
            null,
            null,
            ['bonus_type' => 'signup']
        );
    }

    /**
     * Get user's transaction history
     */
    public static function getUserHistory(int $userId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
