<?php

use Illuminate\Support\Facades\DB;
use Webkul\Faker\Helpers\Product as ProductFaker;

/*
 * A compare list holds 4 products, whichever card style the store uses (owner decision, 2026-11-16).
 * The compare endpoint enforces it for signed-in customers. Guests are held to it by the shared client
 * helper $compareList (Playwright: card-styles/compare-limit).
 */

beforeEach(function () {
    $this->customer = $this->loginAsCustomer();
});

function compareCandidates(int $count)
{
    return (new ProductFaker)->getSimpleProductFactory()->count($count)->create();
}

function compareAdd($test, $product)
{
    return $test->postJson(route('shop.api.compare.store'), ['product_id' => $product->id]);
}

it('holds four products in a customer\'s compare list', function () {
    $products = compareCandidates(5);

    $products->take(4)->each(fn ($product) => compareAdd($this, $product)->assertOk());

    compareAdd($this, $products[4])
        ->assertUnprocessable()
        ->assertJsonPath('data.message', 'You can compare up to 4')
        ->assertJsonPath('data.reason', 'compare-limit');

    expect(DB::table('compare_items')->where('customer_id', $this->customer->id)->count())->toBe(4);
});

it('still says "already added" for a product that is in a full list', function () {
    $products = compareCandidates(4);

    $products->each(fn ($product) => compareAdd($this, $product)->assertOk());

    compareAdd($this, $products[0])
        ->assertUnprocessable()
        ->assertJsonPath('data.message', trans('shop::app.compare.already-added'))
        ->assertJsonMissingPath('data.reason');
});

it('takes a product again once one is removed', function () {
    $products = compareCandidates(5);

    $products->take(4)->each(fn ($product) => compareAdd($this, $product)->assertOk());

    $this->deleteJson(route('shop.api.compare.destroy'), ['product_id' => $products[0]->id])->assertOk();

    compareAdd($this, $products[4])->assertOk();
});

it('reads the limit from shop.compare.limit', function () {
    config(['shop.compare.limit' => 2]);

    $products = compareCandidates(3);

    $products->take(2)->each(fn ($product) => compareAdd($this, $product)->assertOk());

    compareAdd($this, $products[2])
        ->assertUnprocessable()
        ->assertJsonPath('data.message', 'You can compare up to 2');
});
