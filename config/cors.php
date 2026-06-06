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
        'https://lugaflix.mruodel.com',
        'https://movies.mruodel.com',
        'https://about.u-lits.com',
        // New store domains
        'https://lugaflix.store',
        'https://www.lugaflix.store',
        'https://munoapp.store',
        'https://www.munoapp.store',
    ],

    'allowed_origins_patterns' => [
        '/^https:\/\/(.+\.)?mruodel\.com$/',
        '/^https:\/\/(.+\.)?ugnews24\.info$/',
        '/^https:\/\/(www\.)?lugaflix\.store$/',
        '/^https:\/\/(www\.)?munoapp\.store$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization', 'Tok'],

    'max_age' => 86400,

    'supports_credentials' => false,

];
