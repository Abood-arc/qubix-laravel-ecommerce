<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Sales\Contracts\Order;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use LogicException;

/**
 * Books one order with Shiprocket, safely and resumably (Task 6).
 */
class ShipmentBooker
{
    public function book(Order $order, bool $manual = false): ShiprocketShipment
    {
        throw new LogicException('Not implemented yet: Task 6');
    }

    public function cancel(ShiprocketShipment $shipment): void
    {
        throw new LogicException('Not implemented yet: Task 6');
    }
}
