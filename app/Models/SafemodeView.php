<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafemodeView extends Model
{
    protected $table = 'safemode_views';

    protected $fillable = [
        'user_id',
        'external_video_id',
        'video_title',
        'category',
        'genre',
        'action',
        'progress_seconds',
        'duration_seconds',
        'max_progress_seconds',
        'percentage',
        'status',
        'device',
        'platform',
        'ip_address',
    ];

    protected $casts = [
        'progress_seconds'     => 'double',
        'duration_seconds'     => 'double',
        'max_progress_seconds' => 'double',
        'percentage'           => 'double',
    ];
}
