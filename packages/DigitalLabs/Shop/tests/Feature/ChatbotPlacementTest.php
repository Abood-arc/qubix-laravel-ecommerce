<?php

use Webkul\Faker\Helpers\Customer as CustomerFaker;

use function Pest\Laravel\get;

/*
 * The chat widget loads on every storefront page except checkout, where nothing should
 * float over the address and payment steps.
 */

beforeEach(function () {
    config(['responsecache.enabled' => false]);
});

it('does not load the chatbot on the checkout page', function () {
    $customer = (new CustomerFaker)->factory()->create();

    $this->loginAsCustomer($customer);

    $this->createRazorpayCart($customer);

    get(route('shop.checkout.onepage.index'))
        ->assertOk()
        ->assertDontSee('cdn.zanderio.ai/widget/loader.js', false)
        ->assertDontSee('qubix.chatLauncher', false);
});

it('still loads the chatbot on the cart and home pages', function (string $route) {
    get(route($route))
        ->assertOk()
        ->assertSee('cdn.zanderio.ai/widget/loader.js', false)
        ->assertSee('qubix.chatLauncher', false);
})->with(['shop.home.index', 'shop.checkout.cart.index']);
