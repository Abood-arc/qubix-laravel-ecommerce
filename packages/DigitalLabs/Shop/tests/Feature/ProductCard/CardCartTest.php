<?php

use Illuminate\Support\Facades\DB;
use Webkul\Faker\Helpers\Product as ProductFaker;

/*
 * The cart calls behind the new cards' − 1 + control: every cart line names its product, a quantity
 * change follows the same stock rule as the cart page (Cart::updateItems), and a line can be removed.
 */

beforeEach(function () {
    config(['responsecache.enabled' => false]);

    $this->loginAsCustomer();
});

function stepperProduct(int $stock)
{
    $product = (new ProductFaker)->getSimpleProductFactory()->create();

    DB::table('product_inventory_indices')->updateOrInsert(
        ['product_id' => $product->id, 'channel_id' => core()->getCurrentChannel()->id],
        ['qty' => $stock, 'updated_at' => now()],
    );

    return $product;
}

function addOneToCart($test, $product): int
{
    return $test->postJson(route('shop.api.checkout.cart.store'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertOk()
        ->json('data.items.0.id');
}

it('names the product of every cart line', function () {
    $product = stepperProduct(10);

    $this->postJson(route('shop.api.checkout.cart.store'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertOk()
        ->assertJsonPath('data.items.0.product_id', fn ($productId) => (int) $productId === $product->id);
});

it('changes a line within the stock and refuses more, as the cart page does', function () {
    $product = stepperProduct(2);

    $itemId = addOneToCart($this, $product);

    $this->putJson(route('shop.api.checkout.cart.update'), ['qty' => [$itemId => 2]])
        ->assertOk()
        ->assertJsonPath('message', trans('shop::app.checkout.cart.index.quantity-update'))
        ->assertJsonPath('data.items.0.quantity', fn ($quantity) => (int) $quantity === 2);

    $this->putJson(route('shop.api.checkout.cart.update'), ['qty' => [$itemId => 3]])
        ->assertOk()
        ->assertJsonPath('data.message', trans('shop::app.checkout.cart.inventory-warning'));

    expect((int) $this->getJson(route('shop.api.checkout.cart.index'))->json('data.items.0.quantity'))->toBe(2);
});

it('removes a line', function () {
    $itemId = addOneToCart($this, stepperProduct(10));

    $this->deleteJson(route('shop.api.checkout.cart.destroy'), ['cart_item_id' => $itemId])
        ->assertOk()
        ->assertJsonPath('message', trans('shop::app.checkout.cart.success-remove'));

    expect($this->getJson(route('shop.api.checkout.cart.index'))->json('data.items') ?? [])->toBeEmpty();
});
