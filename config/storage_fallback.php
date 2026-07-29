<?php

/*
|--------------------------------------------------------------------------
| Storage Fallback / Maintenance Bypass
|--------------------------------------------------------------------------
|
| When a storage provider (e.g. Hetzner StorageShare) is under maintenance
| the MovieModel URL accessor transparently substitutes old_video_url
| (the pre-migration CDN URL) so the mobile app keeps working.
|
| Controls:
|   STORAGE_MAINTENANCE_ENABLED=true        — flip on/off without deploy
|   STORAGE_MAINTENANCE_HOST=nx100800…de   — the affected hostname
|   STORAGE_MAINTENANCE_ENDS_AT=2026-07-31 02:00:00   — UTC; auto-turns off
|
| After ends_at passes the bypass deactivates automatically — no action
| needed. To end early, set STORAGE_MAINTENANCE_ENABLED=false and
| run: php artisan config:cache
|
*/

return [

    'maintenance' => [

        // Master switch — set to false to disable unconditionally.
        'enabled'  => env('STORAGE_MAINTENANCE_ENABLED', false),

        // Hostname fragment matched against the movie URL.
        // Any URL containing this string is considered "affected".
        'host'     => env('STORAGE_MAINTENANCE_HOST', 'nx100800.your-storageshare.de'),

        // UTC datetime string. When now() is past this value the bypass
        // deactivates automatically even if 'enabled' is still true.
        // Set to null to disable auto-expiry (keep 'enabled' as the only toggle).
        'ends_at'  => env('STORAGE_MAINTENANCE_ENDS_AT', null),
    ],

];
