<?php

/*
 * Shiprocket API user and webhook token live in the server's .env, never in the database.
 */
return [
    'email' => env('SHIPROCKET_EMAIL'),
    'password' => env('SHIPROCKET_PASSWORD'),
    'webhook_token' => env('SHIPROCKET_WEBHOOK_TOKEN'),

    // Local development and browser tests only: answer with FakeShiprocketClient.
    'fake' => (bool) env('SHIPROCKET_FAKE', false),

    'tracking_url' => 'https://shiprocket.co/tracking/',

    // Paid-online methods whose orders are booked automatically once paid.
    'prepaid_methods' => ['razorpay'],
];
