<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'hetzner_storage' => [
        'url'  => env('HETZNER_STORAGE_URL'),
        'user' => env('HETZNER_STORAGE_USER'),
        'pass' => env('HETZNER_STORAGE_PASS'),
    ],

    'url_sync' => [
        // Shared secret — must match on both Hetzner and mruodel.com
        'secret'     => env('URL_SYNC_SECRET', ''),
        // Where to push URL changes (set on Hetzner, leave empty on mruodel.com)
        'origin_url' => env('URL_SYNC_ORIGIN_URL', ''),
    ],

    'namz' => [
        'email'    => env('NAMZ_EMAIL', 'mubahood360@gmail.com'),
        'password' => env('NAMZ_PASSWORD', '0783204665'),
    ],

    'sync' => [
        'enabled'     => env('SYNC_ENABLED', false),
        'source_host' => env('SYNC_SOURCE_HOST', '209.74.87.69'),
        'ssh_user'    => env('SYNC_SSH_USER', 'root'),
        'db_user'     => env('SYNC_DB_USER', 'katogo'),
        'db_pass'     => env('SYNC_DB_PASS', ''),
        'db_name'     => env('SYNC_DB_NAME', 'katogo_3'),
        'tunnel_port' => env('SYNC_TUNNEL_PORT', 13306),
        'batch_size'  => env('SYNC_BATCH_SIZE', 500),
        'max_pages'   => env('SYNC_MAX_PAGES', 30),
    ],

];
