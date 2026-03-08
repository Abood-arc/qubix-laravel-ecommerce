<?php

use Illuminate\Support\Facades\Route;
use DigitalLabs\Admin\Http\Controllers\Controller;
use DigitalLabs\Admin\Http\Controllers\User\ForgetPasswordController;
use DigitalLabs\Admin\Http\Controllers\User\ResetPasswordController;
use DigitalLabs\Admin\Http\Controllers\User\SessionController;

/**
 * Auth routes.
 */
Route::group(['prefix' => config('app.admin_url')], function () {
    /**
     * Redirect route.
     */
    Route::get('/', [Controller::class, 'redirectToLogin']);

    Route::controller(SessionController::class)->prefix('login')->group(function () {
        /**
         * Login routes.
         */
        Route::get('', 'create')->name('admin.session.create');

        /**
         * Login post route to admin auth controller.
         */
        Route::post('', 'store')->name('admin.session.store');
    });

    /**
     * Forget password routes.
     */
    Route::controller(ForgetPasswordController::class)->prefix('forget-password')->group(function () {
        Route::get('', 'create')->name('admin.forget_password.create');

        Route::post('', 'store')->name('admin.forget_password.store');
    });

    /**
     * Reset password routes.
     */
    Route::controller(ResetPasswordController::class)->prefix('reset-password')->group(function () {
        Route::get('{token}', 'create')->name('admin.reset_password.create');

        Route::post('', 'store')->name('admin.reset_password.store');
    });
});
