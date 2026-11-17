<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use LogicException;

/**
 * Can Shiprocket deliver to a PIN code, and with Cash on Delivery? (Task 5)
 */
class ServiceabilityChecker
{
    public function __construct(protected ShiprocketApi $api) {}

    /**
     * @return array{deliverable: bool, cod: bool}
     */
    public function check(string $deliveryPin, float $weightKg): array
    {
        throw new LogicException('Not implemented yet: Task 5');
    }
}
