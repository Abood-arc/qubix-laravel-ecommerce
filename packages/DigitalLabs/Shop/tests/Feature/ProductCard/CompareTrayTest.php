<?php

use DigitalLabs\Shop\Enums\ProductCardStyleEnum;

/*
 * The compare tray hangs off the qubix.shop.layout.content.after hook and renders only where the new
 * cards are: never with the Classic card, on the Classic-only pages, or with compare switched off.
 */

beforeEach(function () {
    config(['responsecache.enabled' => false]);

    $this->pinStoreConfig('catalog.products.settings.compare_option', '1');
});

it('puts the compare tray on pages with the new cards', function (string $style) {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, $style);

    expect($this->searchPageHtml())
        ->toContain('<v-card-compare-tray')
        ->toContain('id="v-card-compare-tray-template"');
})->with(['swipe', 'deck']);

it('leaves the tray out with the Classic card', function () {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, 'classic');

    expect($this->searchPageHtml())->not->toContain('v-card-compare-tray');
});

it('leaves the tray out on the Classic-only pages', function (string $route) {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, 'swipe');

    expect($this->get(route($route))->assertOk()->getContent())->not->toContain('v-card-compare-tray');
})->with(['shop.checkout.cart.index', 'shop.compare.index']);

it('leaves the tray out when compare is switched off', function () {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, 'swipe');
    $this->pinStoreConfig('catalog.products.settings.compare_option', '0');

    expect($this->searchPageHtml())->not->toContain('v-card-compare-tray');
});
