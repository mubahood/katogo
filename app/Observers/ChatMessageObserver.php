<?php

namespace App\Observers;

use App\Jobs\SendChatNotification;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;

/**
 * ChatMessageObserver — dispatches push notifications asynchronously (P10-10 / P7-03).
 *
 * Replaces the synchronous send_notification() call that was inside ChatMessage::boot().
 * The queued job runs via the scheduler-spawned queue:work worker so the HTTP
 * response is returned to the client before the OneSignal API call is made.
 */
class ChatMessageObserver
{
    /**
     * Runs just before the model is saved for the first time.
     * Uses `creating` (not `created`) so we have the full model data
     * but can still avoid dispatching if the message is invalid.
     */
    public function creating(ChatMessage $message): void
    {
        if (!$message->receiver_id) {
            return;
        }

        try {
            SendChatNotification::dispatch(
                receiverId: (int) $message->receiver_id,
                title:      'You have a new message',
                body:       'You have a new message' .
                            ($message->sender_id ? '' : ''),
            );
        } catch (\Throwable $e) {
            // Never block message creation due to notification failure
            Log::warning("[ChatMessageObserver] Failed to dispatch notification: " . $e->getMessage());
        }
    }
}
