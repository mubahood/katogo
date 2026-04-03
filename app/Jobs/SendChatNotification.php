<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendChatNotification — dispatches a push notification to a chat receiver.
 *
 * Previously this was done synchronously inside ChatMessage::boot() creating event,
 * blocking the HTTP response while waiting for the OneSignal API call.
 * Now it runs asynchronously via the queue (P7-03 / P10-12).
 */
class SendChatNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * How many times to retry if the job fails.
     */
    public int $tries = 2;

    /**
     * Seconds to wait before retrying.
     */
    public int $backoff = 30;

    public function __construct(
        public readonly int    $receiverId,
        public readonly string $title,
        public readonly string $body,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->receiverId);

        if (!$user) {
            Log::debug("[SendChatNotification] Receiver #{$this->receiverId} not found — skipping.");
            return;
        }

        NotificationService::sendToUser($user, [
            'title' => $this->title,
            'body'  => $this->body,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning("[SendChatNotification] Failed for receiver #{$this->receiverId}: " . $e->getMessage());
    }
}
