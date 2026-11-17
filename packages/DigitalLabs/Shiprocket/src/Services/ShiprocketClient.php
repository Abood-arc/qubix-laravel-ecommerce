<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use LogicException;

/**
 * Talks to Shiprocket's external API (Task 2).
 */
class ShiprocketClient implements ShiprocketApi
{
    public function checkServiceability(string $pickupPin, string $deliveryPin, float $weightKg, bool $cod): array
    {
        throw new LogicException('Not implemented yet: Task 2');
    }

    public function createOrder(array $payload): array
    {
        throw new LogicException('Not implemented yet: Task 2');
    }

    public function findOrderByChannelOrderId(string $channelOrderId): ?array
    {
        throw new LogicException('Not implemented yet: Task 2');
    }

    public function assignAwb(int $shipmentId): array
    {
        throw new LogicException('Not implemented yet: Task 2');
    }

    public function requestPickup(int $shipmentId): void
    {
        throw new LogicException('Not implemented yet: Task 2');
    }

    public function generateLabel(int $shipmentId): string
    {
        throw new LogicException('Not implemented yet: Task 2');
    }

    public function cancelOrder(int $shiprocketOrderId): void
    {
        throw new LogicException('Not implemented yet: Task 2');
    }
}
