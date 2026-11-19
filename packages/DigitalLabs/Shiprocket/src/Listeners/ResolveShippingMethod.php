<?php

namespace DigitalLabs\Shiprocket\Listeners;

use DigitalLabs\Shiprocket\Services\RateRefresher;

/**
 * Checkout keeps showing the delivery options of the address step, but a Shiprocket code
 * changes with its quote (paying cash, or Shiprocket's new rates). A chosen option whose code
 * has gone stale is mapped to the same tier's current code instead of being refused.
 */
class ResolveShippingMethod
{
    public function __construct(protected RateRefresher $refresher) {}

    public function handle(string $code): ?string
    {
        return $this->refresher->currentCode($code);
    }
}
