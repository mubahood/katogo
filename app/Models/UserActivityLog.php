<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'duration_s', 'app_type', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public static function record(int $userId, string $action, array $attrs = []): void
    {
        static::create(array_merge(['user_id' => $userId, 'action' => $action], $attrs));
    }
}
