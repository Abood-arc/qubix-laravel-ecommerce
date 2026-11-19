<?php

namespace DigitalLabs\Shiprocket\Listeners;

use DigitalLabs\Checkout\Contracts\Cart;
use DigitalLabs\Shiprocket\Services\RateRefresher;

/**
 * Cash on Delivery has its own couriers and fee, so a new payment method re-prices delivery
 * before the order summary is shown again.
 */
class RepriceShipping
{
    public function __construct(protected RateRefresher $refresher) {}

    public function handle(Cart $cart): void
    {
        $this->refresher->refresh($cart);
    }
}
