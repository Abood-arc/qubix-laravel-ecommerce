<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Sales\Contracts\Order;
use LogicException;

/**
 * Builds the Shiprocket order payload and the parcel weight (Task 3).
 */
class ParcelBuilder
{
    public function __construct(protected AddressNormalizer $addresses) {}

    public function weightKg(Order $order): float
    {
        throw new LogicException('Not implemented yet: Task 3');
    }

    public function build(Order $order): array
    {
        throw new LogicException('Not implemented yet: Task 3');
    }
}
