<?php

namespace DigitalLabs\Shipping\Contracts;

use DigitalLabs\Checkout\Contracts\Cart;
use DigitalLabs\Checkout\Contracts\CartAddress;

/**
 * Decides whether the store can deliver to a checkout address.
 *
 * Shipping integrations rebind this; core always asks it before showing shipping rates
 * and before placing an order.
 */
interface DeliveryCheck
{
    /**
     * Null when the address is deliverable, otherwise the reason shown to the customer.
     */
    public function check(CartAddress $shippingAddress, Cart $cart): ?string;
}
