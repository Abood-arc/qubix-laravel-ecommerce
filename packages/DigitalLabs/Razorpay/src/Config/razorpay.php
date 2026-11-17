<?php

/*
 * Razorpay API keys live in the server's .env, never in the database: they stay out of
 * admin page source and database backups. Quote a value that contains "#".
 */
return [
    'key_id' => env('RAZORPAY_KEY_ID'),
    'key_secret' => env('RAZORPAY_KEY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
];
