<?php

use DigitalLabs\Shop\Enums\ProductCardStyleEnum;

it('uses the Classic card until the owner picks another style', function (?string $saved, ProductCardStyleEnum $expected) {
    $this->pinStoreConfig(ProductCardStyleEnum::CONFIG_KEY, $saved);

    expect(ProductCardStyleEnum::current())->toBe($expected);
})->with([
    'nothing saved' => [null, ProductCardStyleEnum::CLASSIC],
    'classic' => ['classic', ProductCardStyleEnum::CLASSIC],
    'swipe' => ['swipe', ProductCardStyleEnum::SWIPE],
    'deck' => ['deck', ProductCardStyleEnum::DECK],
    'an unknown value' => ['carousel', ProductCardStyleEnum::CLASSIC],
]);
