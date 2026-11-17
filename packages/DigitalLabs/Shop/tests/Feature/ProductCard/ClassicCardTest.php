<?php

/*
 * Classic must stay exactly today's card. fixtures/classic-card-template.html is today's grid
 * and list template, recorded from `abood` before the card styles existed (2026-11-16).
 * Re-record it only for an intended change to the Classic card:
 *   docker exec -u sail -e UPDATE_CLASSIC_CARD_FIXTURE=1 -w /var/www/html qubix-cards-app \
 *     ./vendor/bin/pest --configuration=phpunit.cards.xml packages/DigitalLabs/Shop/tests/Feature/ProductCard/ClassicCardTest.php
 */

beforeEach(function () {
    config(['responsecache.enabled' => false]);

    $this->pinClassicCardConfig();
});

it('renders the Classic card exactly as before when no card style is saved', function () {
    $this->pinStoreConfig('general.design.product_card.style', null);

    $template = $this->productCardTemplate($this->searchPageHtml());

    if (getenv('UPDATE_CLASSIC_CARD_FIXTURE') === '1') {
        file_put_contents($this->classicCardFixturePath(), $template.PHP_EOL);
    }

    expect($template)->toBe($this->classicCardFixture());
});

it('renders the Classic card exactly as before when Classic is chosen or an unknown style is saved', function (string $style) {
    $this->pinStoreConfig(\DigitalLabs\Shop\Enums\ProductCardStyleEnum::CONFIG_KEY, $style);

    $html = $this->searchPageHtml();

    expect($this->productCardTemplate($html))->toBe($this->classicCardFixture())
        ->and($html)->not->toContain('v-card-swipe-gallery')
        ->and($html)->not->toContain('v-card-deck-gallery');
})->with(['classic', 'no-such-style']);

it('keeps the Classic card on the cart and compare pages whatever the setting', function (string $route) {
    $this->pinStoreConfig(\DigitalLabs\Shop\Enums\ProductCardStyleEnum::CONFIG_KEY, 'swipe');
    $this->pinStoreConfig('sales.checkout.shopping_cart.cross_sell', '1');

    $html = $this->get(route($route))->assertOk()->getContent();

    expect($this->productCardTemplate($html))->toBe($this->classicCardFixture())
        ->and($html)->not->toContain('v-card-swipe-gallery');
})->with([
    'cart page (cross-sell row)' => 'shop.checkout.cart.index',
    'compare page' => 'shop.compare.index',
]);
