<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    protected $table = 'system_configs';

    protected $fillable = [
        'ios_review_mode',
        'ios_review_movie_ids',
        'ios_review_message',
        'maintenance_mode',
        'maintenance_message',
        'min_android_version',
        'min_ios_version',
        'storage_maintenance_enabled',
        'storage_maintenance_host',
        'storage_maintenance_ends_at',
    ];

    protected $casts = [
        'ios_review_mode'             => 'boolean',
        'maintenance_mode'            => 'boolean',
        'min_android_version'         => 'integer',
        'min_ios_version'             => 'integer',
        'ios_review_movie_ids'        => 'array',
        'storage_maintenance_enabled' => 'boolean',
        'storage_maintenance_ends_at' => 'datetime',
    ];

    /**
     * Always return the single config row, creating it if absent.
     */
    public static function instance(): self
    {
        $config = self::first();
        if (!$config) {
            $config = self::create([
                'ios_review_mode'      => false,
                'ios_review_movie_ids' => '[]',
                'ios_review_message'   => 'Welcome to LugaFlix',
                'maintenance_mode'     => false,
                'min_android_version'  => 1,
                'min_ios_version'      => 1,
            ]);
        }
        return $config;
    }

    /**
     * Returns decoded array of iOS review movie IDs.
     */
    public function getIosReviewMovieIdsArrayAttribute(): array
    {
        if (empty($this->ios_review_movie_ids)) {
            return [];
        }
        $decoded = json_decode($this->ios_review_movie_ids, true);
        return is_array($decoded) ? $decoded : [];
    }
}
