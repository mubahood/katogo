<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChatHead extends Model
{
    use HasFactory;

    public function getCustomerUnreadMessagesCountAttribute()
    {
        return ChatMessage::where('chat_head_id', $this->id)
            ->where('receiver_id', $this->customer_id)
            ->where('status', 'sent')
            ->count();
    }
    public function getProductOwnerUnreadMessagesCountAttribute()
    {
        return ChatMessage::where('chat_head_id', $this->id)
            ->where('receiver_id', $this->product_owner_id)
            ->where('status', 'sent')
            ->count();
    }

    //boot on delete
    public static function boot()
    {
        parent::boot();
        self::deleting(function ($m) {
            try {
                $sql = "DELETE FROM chat_messages WHERE chat_head_id = " . $m->id;
                DB::delete($sql); 
            } catch (\Throwable $th) {
                //throw $th;
            }
        });
    }

    protected $appends = ['customer_unread_messages_count', 'product_owner_unread_messages_count'];
}
