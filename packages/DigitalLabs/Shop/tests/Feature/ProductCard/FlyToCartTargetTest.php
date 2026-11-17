<?php

/*
 * The new cards' flying photo lands on the header cart icon, which is marked data-cart-target in
 * both forms of the mini cart: the drawer toggle and the plain link to checkout.
 */

it('marks the header cart icon as where the photo flies to', function (string $drawer) {
    config(['responsecache.enabled' => false]);

    $this->pinStoreConfig('sales.checkout.mini_cart.display_mini_cart', $drawer);

    $this->get(route('shop.home.index'))
        ->assertOk()
        ->assertSee('data-cart-target', false);
})->with([
    'with the cart drawer' => '1',
    'with a link to checkout' => '0',
]);
