<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreamingStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'category',
        'frequency',
        'description',
        'logo_url',
        'country',
        'language',
        'region',
        'website_url',
        'sort_order',
        'votes',
        'listeners_count',
        'status',
        'is_featured',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'votes' => 'integer',
        'listeners_count' => 'integer',
        'is_featured' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function streamingUrls()
    {
        return $this->hasMany(StreamingUrl::class);
    }

    public function activeUrls()
    {
        return $this->hasMany(StreamingUrl::class)->where('status', 'Active');
    }

    public function defaultUrl()
    {
        return $this->hasOne(StreamingUrl::class)->where('is_default', true);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($station) {
            if (empty($station->slug)) {
                $station->slug = \Str::slug($station->name);
                // Ensure unique slug
                $count = static::where('slug', $station->slug)->count();
                if ($count > 0) {
                    $station->slug .= '-' . ($count + 1);
                }
            }
        });

        static::deleting(function ($station) {
            $station->streamingUrls()->delete();
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeTv($query)
    {
        return $query->where('type', 'tv');
    }

    public function scopeRadio($query)
    {
        return $query->where('type', 'radio');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
