<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckersChatMessage extends Model
{
    protected $fillable = ['session_id', 'user_id', 'user_name', 'message'];

    public function session()
    {
        return $this->belongsTo(CheckersSession::class, 'session_id');
    }
}
