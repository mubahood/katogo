<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChatHead extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_owner_id',
        'customer_id',
        'type',
        'product_id',
    ];

    public function getCustomerUnreadMessagesCountAttribute()
    {
        // Short-circuit: return batch-loaded value if manually set (avoids N+1 on collections)
        if (array_key_exists('customer_unread_messages_count', $this->attributes)) {
            return $this->attributes['customer_unread_messages_count'];
        }
        return ChatMessage::where('chat_head_id', $this->id)
            ->where('receiver_id', $this->customer_id)
            ->where('status', 'sent')
            ->count();
    }
    public function getProductOwnerUnreadMessagesCountAttribute()
    {
        // Short-circuit: return batch-loaded value if manually set (avoids N+1 on collections)
        if (array_key_exists('product_owner_unread_messages_count', $this->attributes)) {
            return $this->attributes['product_owner_unread_messages_count'];
        }
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
