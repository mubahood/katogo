<?php

return [
    'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
    'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
    'environment' => env('PESAPAL_ENVIRONMENT', 'production'),
    'currency' => env('PESAPAL_CURRENCY', 'UGX'),
    'sandbox_url' => env('PESAPAL_SANDBOX_URL', 'https://cybqa.pesapal.com/pesapalv3'),
    'production_url' => env('PESAPAL_PRODUCTION_URL', 'https://pay.pesapal.com/v3'),
    'ipn_url' => env('PESAPAL_IPN_URL'),
    'callback_url' => env('PESAPAL_CALLBACK_URL'),
];
