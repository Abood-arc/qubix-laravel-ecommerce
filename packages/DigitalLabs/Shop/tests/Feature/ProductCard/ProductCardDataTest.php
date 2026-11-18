<?php

use DigitalLabs\Shop\Http\Resources\ProductResource;
use Illuminate\Support\Facades\DB;
use Webkul\Faker\Helpers\Product as ProductFaker;

use function Pest\Laravel\getJson;

/*
 * The facts the Swipe and Photo deck cards show, as ProductResource sends them:
 * the size from the width/height/length attributes and "Only N left" from the channel's
 * inventory index.
 */

beforeEach(function () {
    config(['responsecache.enabled' => false]);
});

function sizedSimpleProduct(?string $width, ?string $height, ?string $length)
{
    return (new ProductFaker([
        'attribute_value' => [
            'width' => ['text_value' => $width],
            'height' => ['text_value' => $height],
            'length' => ['text_value' => $length],
        ],
    ]))->getSimpleProductFactory()->create();
}

function productCardData($product): array
{
    return (new ProductResource($product->fresh()))->resolve();
}

function setChannelStock($product, int $quantity): void
{
    DB::table('product_inventory_indices')->updateOrInsert(
        ['product_id' => $product->id, 'channel_id' => core()->getCurrentChannel()->id],
        ['qty' => $quantity, 'updated_at' => now()],
    );
}

it('gives the card the size from the width, height and length attributes', function () {
    expect(productCardData(sizedSimpleProduct('16', '14.50', '5.0000'))['dimensions'])
        ->toBe(['width' => '16', 'height' => '14.5', 'depth' => '5']);
});

it('leaves the depth out when length is empty', function () {
    expect(productCardData(sizedSimpleProduct('15', '16', null))['dimensions'])
        ->toBe(['width' => '15', 'height' => '16', 'depth' => null]);
});

it('gives no size when width or height is missing', function (?string $width, ?string $height) {
    expect(productCardData(sizedSimpleProduct($width, $height, '4'))['dimensions'])->toBeNull();
})->with([
    'no width' => [null, '14'],
    'no height' => ['16', null],
    'zero width' => ['0', '14'],
    'not a number' => ['big', '14'],
]);

it('tells the card how many are left when stock is low', function (int $quantity, ?int $expected) {
    $product = (new ProductFaker)->getSimpleProductFactory()->create();

    setChannelStock($product, $quantity);

    expect(productCardData($product)['stock_left'])->toBe($expected);
})->with([
    'one left' => [1, 1],
    'five left' => [5, 5],
    'six left' => [6, null],
    'sold out' => [0, null],
]);

it('says nothing about stock when the product does not manage stock', function () {
    $product = (new ProductFaker([
        'attribute_value' => [
            'manage_stock' => ['boolean_value' => false, 'channel' => core()->getCurrentChannel()->code],
        ],
    ]))->getSimpleProductFactory()->create();

    setChannelStock($product, 2);

    expect(productCardData($product)['stock_left'])->toBeNull();
});

it('says nothing about stock for a configurable product', function () {
    $product = (new ProductFaker)->getConfigurableProductFactory()->create();

    expect(productCardData($product)['stock_left'])->toBeNull();
});

it('sends the card data with the products listing', function () {
    sizedSimpleProduct('16', '14', '5');

    getJson(route('shop.api.products.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => [['images', 'dimensions', 'stock_left']]])
        ->assertJsonFragment(['dimensions' => ['width' => '16', 'height' => '14', 'depth' => '5']]);
});
