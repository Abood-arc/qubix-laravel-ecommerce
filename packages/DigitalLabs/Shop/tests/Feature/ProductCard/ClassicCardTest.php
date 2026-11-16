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
