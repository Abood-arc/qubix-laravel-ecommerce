<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use Illuminate\Support\Facades\Cache;

/**
 * A deterministic stand-in for local development and browser tests (SHIPROCKET_FAKE=true).
 *
 * Delivery PINs starting with 9 are not deliverable, PINs ending in 0 have no Cash on Delivery,
 * every other PIN has both. Orders live in the cache so a later request can find them.
 */
class FakeShiprocketClient implements ShiprocketApi
{
    protected const CACHE_PREFIX = 'shiprocket:fake:';

    protected const ORDER_ID_BASE = 100000;

    protected const SHIPMENT_ID_BASE = 200000;

    public function checkServiceability(string $pickupPin, string $deliveryPin, float $weightKg, bool $cod): array
    {
        $pin = trim($deliveryPin);

        if (str_starts_with($pin, '9')) {
            return ['deliverable' => false, 'cod' => false];
        }

        return ['deliverable' => true, 'cod' => ! str_ends_with($pin, '0')];
    }

    public function createOrder(array $payload): array
    {
        $channelOrderId = (string) ($payload['order_id'] ?? '');

        if ($channelOrderId === '') {
            throw new ShiprocketException('The order id field is required.');
        }

        Cache::add(self::CACHE_PREFIX.'counter', 0);

        $number = (int) Cache::increment(self::CACHE_PREFIX.'counter');

        $ids = [
            'order_id' => self::ORDER_ID_BASE + $number,
            'shipment_id' => self::SHIPMENT_ID_BASE + $number,
        ];

        Cache::forever(self::CACHE_PREFIX.'channel:'.$channelOrderId, $ids);
        Cache::forever(self::CACHE_PREFIX.'order:'.$ids['order_id'], ['cancelled' => false]);

        return $ids;
    }

    public function findOrderByChannelOrderId(string $channelOrderId): ?array
    {
        return Cache::get(self::CACHE_PREFIX.'channel:'.$channelOrderId);
    }

    public function assignAwb(int $shipmentId): array
    {
        return ['awb_code' => 'FAKE'.$shipmentId, 'courier_name' => 'Fake Express'];
    }

    public function requestPickup(int $shipmentId): void {}

    public function generateLabel(int $shipmentId): string
    {
        return 'https://example.test/labels/'.$shipmentId.'.pdf';
    }

    public function cancelOrder(int $shiprocketOrderId): void
    {
        Cache::forever(self::CACHE_PREFIX.'order:'.$shiprocketOrderId, ['cancelled' => true]);
    }

    /**
     * Whether an order was cancelled (for tests and local checks).
     */
    public function isCancelled(int $shiprocketOrderId): bool
    {
        return (bool) (Cache::get(self::CACHE_PREFIX.'order:'.$shiprocketOrderId)['cancelled'] ?? false);
    }
}
