<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    //created
    //boot
    protected static function boot()
    {
        parent::boot();
        // Push notification dispatch moved to ChatMessageObserver (P10-10 / P7-03)
        // — dispatches SendChatNotification queued job instead of blocking the HTTP response.
    }


    //function send_notification
    public function send_notification()
    {
        $user = User::find($this->receiver_id);
        if ($user != null) {
            $user->send_notification($this);
            //IF SENDER IS NOT NULL
            if ($this->sender == null) {
                return;
            }
            Utils::sendNotificationToUser($user,[
                'title' => 'You have a new message',
                'body' => 'You have a new message from ' . $this->sender->name,
            ]);
        }
    }


    //Sender
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    //Receiver
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
