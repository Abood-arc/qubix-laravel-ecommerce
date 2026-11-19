<?php

use Illuminate\Support\Facades\Route;
use DigitalLabs\Shop\Http\Controllers\API\AddressController;
use DigitalLabs\Shop\Http\Controllers\API\CartController;
use DigitalLabs\Shop\Http\Controllers\API\CategoryController;
use DigitalLabs\Shop\Http\Controllers\API\CompareController;
use DigitalLabs\Shop\Http\Controllers\API\CoreController;
use DigitalLabs\Shop\Http\Controllers\API\CustomerController;
use DigitalLabs\Shop\Http\Controllers\API\OnepageController;
use DigitalLabs\Shop\Http\Controllers\API\ProductController;
use DigitalLabs\Shop\Http\Controllers\API\ReviewController;
use DigitalLabs\Shop\Http\Controllers\API\WishlistController;

Route::group(['prefix' => 'api'], function () {
    Route::controller(CoreController::class)->prefix('core')->group(function () {
        Route::get('countries', 'getCountries')->name('shop.api.core.countries');

        Route::get('states', 'getStates')->name('shop.api.core.states');
    });

    Route::controller(CategoryController::class)->prefix('categories')->group(function () {
        Route::get('', 'index')->name('shop.api.categories.index');

        Route::get('tree', 'tree')->name('shop.api.categories.tree');

        Route::get('attributes', 'getAttributes')->name('shop.api.categories.attributes');

        Route::get('attributes/{attribute_id}/options', 'getAttributeOptions')->name('shop.api.categories.attribute_options');

        Route::get('max-price/{id?}', 'getProductMaxPrice')->name('shop.api.categories.max_price');
    });

    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::get('', 'index')->name('shop.api.products.index');

        Route::get('{id}/related', 'relatedProducts')->name('shop.api.products.related.index');

        Route::get('{id}/up-sell', 'upSellProducts')->name('shop.api.products.up-sell.index');
    });

    Route::controller(ReviewController::class)->prefix('product/{id}')->group(function () {
        Route::get('reviews', 'index')->name('shop.api.products.reviews.index');

        Route::post('review', 'store')->name('shop.api.products.reviews.store');

        Route::get('reviews/{review_id}/translate', 'translate')->name('shop.api.products.reviews.translate');
    });

    Route::controller(CompareController::class)->prefix('compare-items')->group(function () {
        Route::get('', 'index')->name('shop.api.compare.index');

        Route::post('', 'store')->name('shop.api.compare.store');

        Route::delete('', 'destroy')->name('shop.api.compare.destroy');

        Route::delete('all', 'destroyAll')->name('shop.api.compare.destroy_all');
    });

    /*
     * One shopper's cart requests take turns (block(): a lock on their session), so they reach the cart in
     * the order they were sent: a quantity saved as the shopper leaves the cart page lands after the save
     * that was still on its way, adds of different products don't change the cart at once, and a read never
     * answers with the cart as it was before a change that was already under way.
     */
    Route::controller(CartController::class)->prefix('checkout/cart')->group(function () {
        Route::get('', 'index')->name('shop.api.checkout.cart.index')->block();

        Route::post('', 'store')->name('shop.api.checkout.cart.store')->block();

        Route::put('', 'update')->name('shop.api.checkout.cart.update')->block();

        Route::delete('', 'destroy')->name('shop.api.checkout.cart.destroy')->block();

        Route::delete('selected', 'destroySelected')->name('shop.api.checkout.cart.destroy_selected')->block();

        Route::post('move-to-wishlist', 'moveToWishlist')->name('shop.api.checkout.cart.move_to_wishlist')->block();

        Route::post('coupon', 'storeCoupon')->name('shop.api.checkout.cart.coupon.apply')->block();

        Route::post('estimate-shipping-methods', 'estimateShippingMethods')->name('shop.api.checkout.cart.estimate_shipping')->block();

        Route::delete('coupon', 'destroyCoupon')->name('shop.api.checkout.cart.coupon.remove')->block();

        Route::get('cross-sell', 'crossSellProducts')->name('shop.api.checkout.cart.cross-sell.index');
    });

    Route::controller(OnepageController::class)->prefix('checkout/onepage')->group(function () {
        Route::get('summary', 'summary')->name('shop.checkout.onepage.summary');

        Route::post('addresses', 'storeAddress')->name('shop.checkout.onepage.addresses.store');

        Route::post('shipping-methods', 'storeShippingMethod')->name('shop.checkout.onepage.shipping_methods.store');

        Route::post('payment-methods', 'storePaymentMethod')->name('shop.checkout.onepage.payment_methods.store');

        Route::post('orders', 'storeOrder')->name('shop.checkout.onepage.orders.store');
    });

    /**
     * Login routes.
     */
    Route::controller(CustomerController::class)->prefix('customer')->group(function () {
        Route::post('login', 'login')->name('shop.api.customers.session.create');
    });

    Route::group(['middleware' => ['customer'], 'prefix' => 'customer'], function () {
        Route::controller(AddressController::class)->prefix('addresses')->group(function () {
            Route::get('', 'index')->name('shop.api.customers.account.addresses.index');

            Route::post('', 'store')->name('shop.api.customers.account.addresses.store');

            Route::put('edit/{id?}', 'update')->name('shop.api.customers.account.addresses.update');
        });

        Route::controller(WishlistController::class)->prefix('wishlist')->group(function () {
            Route::get('', 'index')->name('shop.api.customers.account.wishlist.index');

            Route::post('', 'store')->name('shop.api.customers.account.wishlist.store');

            Route::post('{id}/move-to-cart', 'moveToCart')->name('shop.api.customers.account.wishlist.move_to_cart');

            Route::delete('all', 'destroyAll')->name('shop.api.customers.account.wishlist.destroy_all');

            Route::delete('{id}', 'destroy')->name('shop.api.customers.account.wishlist.destroy');
        });
    });
});
