<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase services integration.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID'),

    'credentials' => [
        'file' => storage_path('app/firebase-credentials.json'),
    ],

    'storage' => [
        'bucket' => env('FIREBASE_STORAGE_BUCKET', 'ugflix-71aa8'),
        'default_folder' => env('FIREBASE_STORAGE_FOLDER', 'movies'),
    ],

    'database' => [
        'url' => env('FIREBASE_DATABASE_URL'),
    ],
];