<?php

return [
    'secret_key' => env('FLW_SECRET_KEY'),
    'public_key' => env('FLW_PUBLIC_KEY'),
    'encryption_key' => env('FLW_ENCRYPTION_KEY'),
    'secret_hash' => env('FLW_SECRET_HASH'),
    'environment' => env('FLW_ENVIRONMENT', 'production'),
    'currency' => env('FLW_CURRENCY', 'UGX'),
    // Flutterwave options order influences what is shown first on checkout.
    // For Uganda, mobilemoneyuganda should be first for mobile money default UX.
    'payment_options' => env('FLW_PAYMENT_OPTIONS', 'mobilemoneyuganda,card,banktransfer,ussd'),
    'base_url' => env('FLW_BASE_URL', 'https://api.flutterwave.com'),
    'timeout' => (int) env('FLW_TIMEOUT', 20),
];
