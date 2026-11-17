<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Shiprocket\Models\ShiprocketShipment;

/**
 * Maps a Shiprocket tracking update to a parcel status.
 */
class StatusMapper
{
    /**
     * Shiprocket's `current_status` text (upper-cased) => parcel status.
     */
    protected const STATUSES = [
        'PICKED UP' => ShiprocketShipment::STATUS_IN_TRANSIT,
        'SHIPPED' => ShiprocketShipment::STATUS_IN_TRANSIT,
        'IN TRANSIT' => ShiprocketShipment::STATUS_IN_TRANSIT,
        'REACHED AT DESTINATION HUB' => ShiprocketShipment::STATUS_IN_TRANSIT,
        'OUT FOR DELIVERY' => ShiprocketShipment::STATUS_OUT_FOR_DELIVERY,
        'DELIVERED' => ShiprocketShipment::STATUS_DELIVERED,
        'RTO INITIATED' => ShiprocketShipment::STATUS_RTO,
        'RTO IN TRANSIT' => ShiprocketShipment::STATUS_RTO,
        'RTO DELIVERED' => ShiprocketShipment::STATUS_RTO,
        'RTO_OFD' => ShiprocketShipment::STATUS_RTO,
        'CANCELED' => ShiprocketShipment::STATUS_CANCELLED,
        'CANCELLED' => ShiprocketShipment::STATUS_CANCELLED,
    ];

    /**
     * Null for a status that does not move the parcel on (it is only recorded).
     */
    public function map(array $payload): ?string
    {
        $status = $payload['current_status'] ?? null;

        if (! is_string($status)) {
            return null;
        }

        return self::STATUSES[strtoupper(trim($status))] ?? null;
    }
}
