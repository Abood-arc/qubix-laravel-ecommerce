<?php

use DigitalLabs\Checkout\Facades\Cart;

if (! function_exists('cart')) {
    /**
     * Cart helper.
     *
     * @return \DigitalLabs\Checkout\Cart
     */
    function cart()
    {
        return Cart::getFacadeRoot();
    }
}
