<?php

namespace DigitalLabs\CartRule\Listeners;

use DigitalLabs\CartRule\Helpers\CartRule;

class Cart
{
    /**
     * Create a new listener instance.
     *
     * @param  \DigitalLabs\CartRule\Repositories\CartRule  $cartRuleHelper
     * @return void
     */
    public function __construct(protected CartRule $cartRuleHelper) {}

    /**
     * Apply valid cart rules to cart
     *
     * @param  \DigitalLabs\Checkout\Contracts\Cart  $cart
     * @return void
     */
    public function applyCartRules($cart)
    {
        $this->cartRuleHelper->collect($cart);
    }
}
