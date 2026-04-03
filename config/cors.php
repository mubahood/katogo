<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'katogo/api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'http://localhost:8888',
        'https://katogo.ugnews24.info',
        'https://about.u-lits.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'Tok',
        'logged_in_user_id',
        'platform_type',
        'Content-Type',
        'X-Requested-With',
        'Accept',
        'Origin',
        'User-Agent',
        'DNT',
        'Cache-Control',
        'X-Mx-ReqToken',
        'Keep-Alive',
        'X-Requested-With',
        'If-Modified-Since'
    ],

    'exposed_headers' => ['Authorization', 'Tok'],

    'max_age' => 86400,

    'supports_credentials' => false,

];
