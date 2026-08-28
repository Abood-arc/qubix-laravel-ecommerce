<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use DigitalLabs\Shop\Http\Controllers\BookingProductController;
use DigitalLabs\Shop\Http\Controllers\CompareController;
use DigitalLabs\Shop\Http\Controllers\HomeController;
use DigitalLabs\Shop\Http\Controllers\PageController;
use DigitalLabs\Shop\Http\Controllers\ProductController;
use DigitalLabs\Shop\Http\Controllers\ProductsCategoriesProxyController;
use DigitalLabs\Shop\Http\Controllers\SearchController;
use DigitalLabs\Shop\Http\Controllers\SubscriptionController;

/**
 * Sitemap files. The Sitemap package writes generated files to the "public" disk
 * (storage/app/public), which only resolves under /storage/... by default. A sitemap
 * hosted there can't list root-level product/category URLs per the sitemap protocol's
 * location scoping rule, so serve the stored files at the domain root instead.
 */
Route::get('sitemap.xml', function () {
    abort_unless(Storage::disk('public')->exists('sitemap.xml'), 404);

    return Storage::disk('public')->response('sitemap.xml');
})->name('shop.sitemap.index');

Route::get('sitemap-{filename}.xml', function (string $filename) {
    $path = "sitemap-{$filename}.xml";

    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->response($path);
})->where('filename', '[\w\-]+')->name('shop.sitemap.file');

/**
 * CMS pages.
 */
Route::get('page/{slug}', [PageController::class, 'view'])
    ->name('shop.cms.page')
    ->middleware('cache.response');

/**
 * Fallback route.
 */
Route::fallback(ProductsCategoriesProxyController::class.'@index')
    ->name('shop.product_or_category.index')
    ->middleware('cache.response');

/**
 * Store front home.
 */
Route::get('/', [HomeController::class, 'index'])
    ->name('shop.home.index')
    ->middleware('cache.response');

Route::get('contact-us', [HomeController::class, 'contactUs'])
    ->name('shop.home.contact_us')
    ->middleware('cache.response');

Route::post('contact-us/send-mail', [HomeController::class, 'sendContactUsMail'])
    ->name('shop.home.contact_us.send_mail')
    ->middleware('cache.response');

/**
 * Store front search.
 */
Route::get('search', [SearchController::class, 'index'])
    ->name('shop.search.index')
    ->middleware('cache.response');

Route::post('search/upload', [SearchController::class, 'upload'])->name('shop.search.upload');

/**
 * Subscription routes.
 */
Route::controller(SubscriptionController::class)->group(function () {
    Route::post('subscription', 'store')->name('shop.subscription.store');

    Route::get('subscription/{token}', 'destroy')->name('shop.subscription.destroy');
});

/**
 * Compare products
 */
Route::get('compare', [CompareController::class, 'index'])
    ->name('shop.compare.index')
    ->middleware('cache.response');

/**
 * Downloadable products
 */
Route::controller(ProductController::class)->group(function () {
    Route::get('downloadable/download-sample/{type}/{id}', 'downloadSample')->name('shop.downloadable.download_sample');

    Route::get('product/{id}/{attribute_id}', 'download')->name('shop.product.file.download');
});

/**
 * Booking products
 */
Route::get('booking-slots/{id}', [BookingProductController::class, 'index'])
    ->name('shop.booking-product.slots.index');
