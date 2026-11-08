<?php

use DigitalLabs\Razorpay\Http\Controllers\RazorpayController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web', 'shop'], 'prefix' => 'razorpay'], function () {
    Route::get('redirect', [RazorpayController::class, 'redirect'])
        ->middleware('throttle:20,1')
        ->name('razorpay.redirect');

    Route::get('cancel', [RazorpayController::class, 'cancel'])->name('razorpay.cancel');

    Route::post('callback', [RazorpayController::class, 'callback'])
        ->middleware('throttle:30,1')
        ->name('razorpay.callback');
});
