<?php

/*
|--------------------------------------------------------------------------
| Bunny.net Storage & CDN — LugaFlix video hosting
|--------------------------------------------------------------------------
|
| Storage zone: lugaflix-vault (Standard HDD, Frankfurt main, no replicas)
| Pull zone:    lugaflix-cdn   (Volume network tier)
|
| Files are uploaded via the Storage API:
|   PUT https://{storage_host}/{storage_zone}/{path}
|   Header: AccessKey: {storage_password}
| and served publicly via:
|   https://{pull_zone_host}/{path}
|
| url_priority controls which URL variant the API serves to mobile apps:
|   "main"    → movie_models.url (whatever it currently is)
|   "bunny"   → the Bunny CDN copy (movie_file_transfers.bunny_url)
|   "hetzner" → the Hetzner StorageShare copy (transfer dest_url)
| First available wins. Default main,bunny,hetzner = zero behavior change
| until we deliberately flip to bunny,main,hetzner after speed validation.
|
*/

return [

    'storage_zone'     => env('BUNNY_STORAGE_ZONE', 'lugaflix-vault'),

    // Frankfurt (DE) main region uses the base endpoint.
    'storage_host'     => env('BUNNY_STORAGE_HOST', 'storage.bunnycdn.com'),

    // The storage zone password (doubles as the AccessKey for the Storage API).
    // Found at: dash.bunny.net → Storage → lugaflix-vault → FTP & API Access.
    'storage_password' => env('BUNNY_STORAGE_PASSWORD', ''),

    // Public hostname of the connected pull zone (Volume tier).
    'pull_zone_host'   => env('BUNNY_PULL_ZONE_HOST', 'lugaflix-cdn.b-cdn.net'),

    // Account-level API key (optional; only needed for zone management).
    'account_api_key'  => env('BUNNY_ACCOUNT_API_KEY', ''),

    // Comma-separated priority for the playable URL served to apps.
    'url_priority'     => array_map('trim', explode(',', env('MOVIE_URL_PRIORITY', 'main,bunny,hetzner'))),

    // Direct APK distribution (Play Store suspension fallback) — served via
    // the Bunny pull zone; uploaded by ops when a new build ships.
    'apk_url'          => env('LUGAFLIX_APK_URL', 'https://lugaflix-cdn.b-cdn.net/app/lugaflix-latest.apk'),
    'apk_version'      => env('LUGAFLIX_APK_VERSION', '6.0.58'),

];
