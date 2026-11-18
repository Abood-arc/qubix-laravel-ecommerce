<?php

use DigitalLabs\Shop\Enums\ProductCardStyleEnum;
use DigitalLabs\Shop\Enums\StoreLookEnum;
use DigitalLabs\User\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Webkul\Faker\Helpers\Product as ProductFaker;

use function Pest\Laravel\get;

/*
 * The admin's live previews of the product card styles and store looks (shop.card-preview): signed-in
 * admins only, never cached or indexed, framed by the admin only, and rendering only: the store's saved
 * settings stay as they are.
 */

beforeEach(function () {
    $this->pinClassicCardConfig();

    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, 'classic');

    $this->pinStoreConfig(StoreLookEnum::CONFIG_KEY, 'forest-green');
});

/**
 * Signs an admin in on the admin guard only, as in the browser: the storefront's own guard stays the
 * customer one (actingAs() would make the admin guard the default for the whole request).
 */
function signInCardPreviewAdmin(): void
{
    auth()->guard('admin')->setUser(Admin::factory()->create());
}

$previewUrl = fn (array $query = []) => route('shop.card-preview', $query + ['style' => 'swipe', 'look' => 'marigold-indigo']);

it('refuses guests', function () use ($previewUrl) {
    get($previewUrl())->assertForbidden();
});

it('refuses signed-in customers', function () use ($previewUrl) {
    $this->loginAsCustomer();

    get($previewUrl())->assertForbidden();
});

it('shows a signed-in admin the real card in the asked style and look, with the demo', function () use ($previewUrl) {
    (new ProductFaker)->getSimpleProductFactory()->count(2)->create();

    signInCardPreviewAdmin();

    $response = get($previewUrl())->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $html = $response
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->getContent();

    expect($html)
        ->toContain('data-look="marigold-indigo"')
        ->toMatch('/<meta\s+name="robots"\s+content="noindex, nofollow"/')
        ->toContain('<v-card-preview')
        ->toContain('id="v-card-swipe-gallery-template"')
        ->toContain('globals.$cardCart = {')
        ->and($this->productCardTemplate($html))->toContain('data-card-style="swipe"');
});

it('adds the store header only when asked', function () use ($previewUrl) {
    signInCardPreviewAdmin();

    expect(get($previewUrl())->getContent())->not->toContain('<v-mini-cart')
        ->and(get($previewUrl(['header' => 1]))->getContent())->toContain('<v-mini-cart');
});

it('answers an unknown style or look with Not Found', function (array $query) use ($previewUrl) {
    signInCardPreviewAdmin();

    get($previewUrl($query))->assertNotFound();
})->with([
    'style' => [['style' => 'fancy']],
    'look' => [['look' => 'neon']],
]);

it('leaves the saved style and look alone, so shoppers keep seeing them', function () use ($previewUrl) {
    signInCardPreviewAdmin();

    $saved = fn () => DB::table('core_config')
        ->whereIn('code', [ProductCardStyleEnum::CONFIG_KEY, StoreLookEnum::CONFIG_KEY])
        ->orderBy('id')
        ->get(['code', 'value', 'channel_code', 'updated_at'])
        ->toArray();

    $before = $saved();

    foreach (['swipe', 'deck', 'classic'] as $style) {
        get($previewUrl(['style' => $style, 'look' => 'minimal-white', 'header' => 1]))->assertOk();
    }

    expect($saved())->toEqual($before);

    auth()->guard('admin')->logout();

    $html = $this->searchPageHtml();

    expect($html)->toContain('data-look="forest-green"')
        ->and($this->productCardTemplate($html))->not->toContain('data-card-style=');
});

it('is never kept in the page cache', function () {
    expect(Route::getRoutes()->getByName('shop.card-preview')->gatherMiddleware())->not->toContain('cache.response');
});

it('keeps every other page out of frames', function () {
    get(route('shop.home.index'))->assertHeader('X-Frame-Options', 'DENY');
});

it('lets a response relax framing only to the same origin', function (string $asked, string $sent) {
    Route::middleware('web')->get('_frame-test', fn () => response('ok')->header('X-Frame-Options', $asked));

    get('_frame-test')->assertHeader('X-Frame-Options', $sent);
})->with([
    'same origin' => ['SAMEORIGIN', 'SAMEORIGIN'],
    'anything else' => ['ALLOW-FROM https://example.com', 'DENY'],
    'lower case' => ['sameorigin', 'DENY'],
]);
