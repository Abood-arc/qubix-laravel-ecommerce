<?php

namespace DigitalLabs\Shiprocket\Contracts;

/**
 * Everything the store asks Shiprocket. Refusals throw ShiprocketException; a missing or
 * failed answer throws ShiprocketUnknownOutcomeException.
 */
interface ShiprocketApi
{
    /**
     * Log in afresh with the API user, ignoring a cached token, so a wrong user shows now.
     */
    public function checkLogin(): void;

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
     * Shiprocket asks for the manifest right after a pickup request.
     *
     * @return string|null the manifest PDF URL, or null when the shipment has one already
     */
    public function generateManifest(int $shipmentId): ?string;

    /**
     * @return string the label PDF URL
     */
    public function generateLabel(int $shipmentId): string;

    public function cancelOrder(int $shiprocketOrderId): void;

    /**
     * The account's pickup locations, by the exact name an order's `pickup_location` uses.
     *
     * @return array<int, array{pickup_location: string, pin_code: string}>
     */
    public function listPickupLocations(): array;

    /**
     * One order as Shiprocket recorded it (its "data" object).
     */
    public function getOrder(int $shiprocketOrderId): array;
}
