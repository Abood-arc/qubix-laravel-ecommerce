<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Sales\Contracts\Order;
use LogicException;

/**
 * Marks a delivered Cash on Delivery order as paid and completed (Task 7).
 */
class DeliveryCompleter
{
    public function complete(Order $order): void
    {
        throw new LogicException('Not implemented yet: Task 7');
    }
}
