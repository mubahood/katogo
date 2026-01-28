<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameInvitation extends Model
{
    use HasFactory;

    // Expiry time in seconds
    const EXPIRY_SECONDS = 60;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'game_type',
        'status',
        'message',
        'expires_at',
        'game_session_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the sender
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the receiver
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Get the game session if accepted
     */
    public function gameSession()
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    /**
     * Check if invitation has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get remaining seconds until expiry
     */
    public function getRemainingSeconds(): int
    {
        if (!$this->expires_at || $this->expires_at->isPast()) {
            return 0;
        }
        return (int) now()->diffInSeconds($this->expires_at, false);
    }

    /**
     * Mark as expired if time has passed
     */
    public function checkAndExpire(): bool
    {
        if ($this->status === 'pending' && $this->isExpired()) {
            $this->status = 'expired';
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * Get formatted sender info
     */
    public function getSenderInfo(): array
    {
        $sender = $this->sender;
        if (!$sender) {
            return ['id' => $this->sender_id, 'name' => 'Unknown', 'avatar' => null];
        }
        return [
            'id' => $sender->id,
            'name' => $sender->name ?? $sender->first_name ?? 'Player',
            'avatar' => $sender->avatar ?? null,
        ];
    }

    /**
     * Get formatted receiver info
     */
    public function getReceiverInfo(): array
    {
        $receiver = $this->receiver;
        if (!$receiver) {
            return ['id' => $this->receiver_id, 'name' => 'Unknown', 'avatar' => null];
        }
        return [
            'id' => $receiver->id,
            'name' => $receiver->name ?? $receiver->first_name ?? 'Player',
            'avatar' => $receiver->avatar ?? null,
        ];
    }

    /**
     * Scope for pending invitations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    /**
     * Create a new invitation with auto-expiry
     */
    public static function createInvitation($senderId, $receiverId, $gameType = 'matatu', $message = null): self
    {
        return self::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'game_type' => $gameType,
            'status' => 'pending',
            'message' => $message,
            'expires_at' => now()->addSeconds(self::EXPIRY_SECONDS),
        ]);
    }
}
