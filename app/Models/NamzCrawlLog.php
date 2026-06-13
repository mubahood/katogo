<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NamzCrawlLog extends Model
{
    protected $fillable = [
        'session_id', 'namz_id', 'url', 'status', 'result',
        'title', 'movie_id', 'series_id', 'video_url', 'video_status',
        'error_message', 'attempts',
    ];

    const STATUS_SUCCESS  = 'success';
    const STATUS_SKIPPED  = 'skipped';
    const STATUS_FAILED   = 'failed';
    const STATUS_PENDING  = 'pending';

    const RESULT_NEW_MOVIE  = 'new_movie';
    const RESULT_NEW_SERIES = 'new_series';
    const RESULT_EXISTING   = 'existing';
    const RESULT_NO_TITLE   = 'no_title';
    const RESULT_ERROR      = 'error';

    const VIDEO_PENDING   = 'pending';
    const VIDEO_FOUND     = 'found';
    const VIDEO_NOT_FOUND = 'not_found';

    public function session()
    {
        return $this->belongsTo(NamzCrawlSession::class, 'session_id');
    }

    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_id');
    }

    public function getStatusBadgeAttribute(): string
    {
        $colors = [
            'success' => '#27ae60',
            'skipped' => '#95a5a6',
            'failed'  => '#e74c3c',
            'pending' => '#e67e22',
        ];
        $color = $colors[$this->status] ?? '#95a5a6';
        return "<span style='background:{$color};color:#fff;padding:2px 6px;border-radius:3px;font-size:11px'>"
             . ucfirst($this->status) . "</span>";
    }

    public function getVideoStatusBadgeAttribute(): string
    {
        $colors = [
            'found'     => '#27ae60',
            'not_found' => '#e74c3c',
            'pending'   => '#e67e22',
        ];
        $color = $colors[$this->video_status] ?? '#95a5a6';
        $label = str_replace('_', ' ', ucfirst($this->video_status));
        return "<span style='background:{$color};color:#fff;padding:2px 6px;border-radius:3px;font-size:11px'>{$label}</span>";
    }
}
