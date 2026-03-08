<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreamingUrl extends Model
{
    use HasFactory;

    protected $fillable = [
        'streaming_station_id',
        'url',
        'label',
        'format',
        'quality',
        'bitrate',
        'cdn_provider',
        'referrer_url',
        'is_default',
        'needs_token_refresh',
        'status',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'streaming_station_id' => 'integer',
        'bitrate' => 'integer',
        'is_default' => 'boolean',
        'needs_token_refresh' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function station()
    {
        return $this->belongsTo(StreamingStation::class, 'streaming_station_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
