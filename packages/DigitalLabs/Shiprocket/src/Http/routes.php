<?php

use DigitalLabs\Core\Http\Middleware\NoCacheMiddleware;
use DigitalLabs\Shiprocket\Http\Controllers\Admin\ShipmentController;
use DigitalLabs\Shiprocket\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Shiprocket rejects webhook URLs containing "shiprocket", "sr" or "kr".
Route::post('hooks/parcel-status', WebhookController::class)
    ->middleware('throttle:120,1')
    ->name('shiprocket.webhook');

Route::group([
    'middleware' => ['web', 'admin', NoCacheMiddleware::class],
    'prefix' => config('app.admin_url').'/sales/orders/{id}/shiprocket',
], function () {
    Route::post('ship', [ShipmentController::class, 'ship'])->name('admin.shiprocket.ship');

    Route::post('retry', [ShipmentController::class, 'retry'])->name('admin.shiprocket.retry');

    Route::post('cancel', [ShipmentController::class, 'cancel'])->name('admin.shiprocket.cancel');

    Route::get('label', [ShipmentController::class, 'label'])->name('admin.shiprocket.label');
});
