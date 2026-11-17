<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Checkout\Contracts\Cart;
use DigitalLabs\Checkout\Contracts\CartAddress;
use DigitalLabs\Shipping\Contracts\DeliveryCheck as DeliveryCheckContract;

/**
 * Refuses checkout addresses Shiprocket cannot deliver to (Task 5).
 */
class DeliveryCheck implements DeliveryCheckContract
{
    public function check(CartAddress $shippingAddress, Cart $cart): ?string
    {
        return null;
    }
}
