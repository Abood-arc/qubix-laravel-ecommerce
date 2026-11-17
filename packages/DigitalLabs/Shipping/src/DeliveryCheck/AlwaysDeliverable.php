<?php

namespace DigitalLabs\Shipping\DeliveryCheck;

use DigitalLabs\Checkout\Contracts\Cart;
use DigitalLabs\Checkout\Contracts\CartAddress;
use DigitalLabs\Shipping\Contracts\DeliveryCheck;

/**
 * The default: every address is deliverable.
 */
class AlwaysDeliverable implements DeliveryCheck
{
    public function check(CartAddress $shippingAddress, Cart $cart): ?string
    {
        return null;
    }
}
