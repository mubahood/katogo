<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageVisit extends Model
{
    use HasFactory;

    protected $table = 'page_visits';

    protected $casts = [
        'landed_at' => 'datetime',
        'left_at'   => 'datetime',
    ];

    protected $guarded = ['id'];

    // ── Relationships ──

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ──

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeDevice($query, $type)
    {
        return $query->where('device_type', $type);
    }

    public function scopeWithClicks($query)
    {
        return $query->whereNotNull('button_clicked');
    }
}
