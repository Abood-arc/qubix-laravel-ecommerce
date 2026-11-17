<?php

use DigitalLabs\Shop\Enums\ProductCardStyleEnum;

/*
 * The grid card follows Product card > Card style; the list card never changes.
 * A page carries only its own style's photo component.
 */

beforeEach(function () {
    config(['responsecache.enabled' => false]);

    $this->pinClassicCardConfig();
});

it('renders the Swipe card in grids and only ships the Swipe photo component', function () {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, 'swipe');

    $html = $this->searchPageHtml();

    expect($this->productCardTemplate($html))
        ->toContain('data-card-style="swipe"')
        ->toContain('<v-card-swipe-gallery')
        ->and($html)->toContain('id="v-card-swipe-gallery-template"')
        ->and($html)->toContain('id="v-card-buy-template"')
        ->and($html)->toContain('id="v-card-compare-template"')
        ->and($html)->toContain('globalProperties.$cardCompare =')
        ->and($html)->not->toContain('id="v-card-deck-gallery-template"');
});

it('keeps the list card exactly as today with the Swipe card', function () {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, 'swipe');

    expect($this->listCardMarkup($this->productCardTemplate($this->searchPageHtml())))
        ->toBe($this->listCardMarkup($this->classicCardFixture()));
});

it('prints the size unit chosen in admin', function (?string $unit, string $label) {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, 'swipe');
    $this->pinStoreConfig('general.design.product_card.size_unit', $unit);

    expect($this->productCardTemplate($this->searchPageHtml()))->toContain('</bdi> '.$label.' </span>');
})->with([
    'nothing saved' => [null, 'in'],
    'inches' => ['in', 'in'],
    'centimetres' => ['cm', 'cm'],
]);

it('renders the Photo deck card in grids and only ships the deck photo component', function () {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, 'deck');

    $html = $this->searchPageHtml();

    expect($this->productCardTemplate($html))
        ->toContain('data-card-style="deck"')
        ->toContain('<v-card-deck-gallery')
        ->and($html)->toContain('id="v-card-deck-gallery-template"')
        ->and($html)->not->toContain('id="v-card-swipe-gallery-template"');
});

it('keeps the list card exactly as today with the Photo deck card', function () {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, 'deck');

    expect($this->listCardMarkup($this->productCardTemplate($this->searchPageHtml())))
        ->toBe($this->listCardMarkup($this->classicCardFixture()));
});
