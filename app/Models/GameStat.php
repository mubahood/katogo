<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameStat extends Model
{
    protected $fillable = [
        'user_id',
        'game_type',
        'games_played',
        'wins',
        'losses',
        'draws',
        'high_score',
        'total_play_seconds',
        'last_played_at',
    ];

    protected $casts = [
        'games_played'       => 'integer',
        'wins'               => 'integer',
        'losses'             => 'integer',
        'draws'              => 'integer',
        'high_score'         => 'integer',
        'total_play_seconds' => 'integer',
        'last_played_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
