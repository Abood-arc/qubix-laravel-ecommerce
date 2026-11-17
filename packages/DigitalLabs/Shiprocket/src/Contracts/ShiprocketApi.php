<?php

namespace DigitalLabs\Shiprocket\Contracts;

/**
 * Everything the store asks Shiprocket. Refusals throw ShiprocketException; a missing or
 * failed answer throws ShiprocketUnknownOutcomeException.
 */
interface ShiprocketApi
{
    /**
     * @return array{deliverable: bool, cod: bool}
     */
    public function checkServiceability(string $pickupPin, string $deliveryPin, float $weightKg, bool $cod): array;

    /**
     * @return array{order_id: int, shipment_id: int}
     */
    public function createOrder(array $payload): array;

    /**
     * @return array{order_id: int, shipment_id: int}|null
     */
    public function findOrderByChannelOrderId(string $channelOrderId): ?array;

    /**
     * Book Shiprocket's recommended courier. An AWB that is already assigned is returned as success.
     *
     * @return array{awb_code: string, courier_name: string}
     */
    public function assignAwb(int $shipmentId): array;

    public function requestPickup(int $shipmentId): void;

    /**
     * @return string the label PDF URL
     */
    public function generateLabel(int $shipmentId): string;

    public function cancelOrder(int $shiprocketOrderId): void;
}
