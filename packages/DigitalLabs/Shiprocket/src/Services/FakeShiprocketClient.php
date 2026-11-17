<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Support\Settings;
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
            throw new ShiprocketException('Shiprocket error (HTTP 422)');
        }

        // A cleared cache must not restart the numbering: continue after the highest
        // shipment already stored, or the next fake AWB would repeat an existing one.
        Cache::add(
            self::CACHE_PREFIX.'counter',
            max(0, (int) ShiprocketShipment::max('shipment_id') - self::SHIPMENT_ID_BASE)
        );

        $number = (int) Cache::increment(self::CACHE_PREFIX.'counter');

        $ids = [
            'order_id' => self::ORDER_ID_BASE + $number,
            'shipment_id' => self::SHIPMENT_ID_BASE + $number,
        ];

        Cache::forever(self::CACHE_PREFIX.'channel:'.$channelOrderId, $ids);
        Cache::forever(self::CACHE_PREFIX.'order:'.$ids['order_id'], [
            'cancelled' => false,
            'channel_order_id' => $channelOrderId,
            'payment_method' => strtolower((string) ($payload['payment_method'] ?? '')),
            'sub_total' => $payload['sub_total'] ?? null,
            'shipping_charges' => $payload['shipping_charges'] ?? null,
        ]);

        return $ids;
    }

    /**
     * Like Shiprocket's lookup, a cancelled order is never returned.
     */
    public function findOrderByChannelOrderId(string $channelOrderId): ?array
    {
        $ids = Cache::get(self::CACHE_PREFIX.'channel:'.$channelOrderId);

        return $ids && ! $this->isCancelled($ids['order_id']) ? $ids : null;
    }

    public function assignAwb(int $shipmentId): array
    {
        return ['awb_code' => 'FAKE'.$shipmentId, 'courier_name' => 'Fake Express'];
    }

    public function requestPickup(int $shipmentId): void {}

    public function generateManifest(int $shipmentId): ?string
    {
        return 'https://example.test/manifests/'.$shipmentId.'.pdf';
    }

    public function generateLabel(int $shipmentId): string
    {
        return 'https://example.test/labels/'.$shipmentId.'.pdf';
    }

    public function cancelOrder(int $shiprocketOrderId): void
    {
        $key = self::CACHE_PREFIX.'order:'.$shiprocketOrderId;

        Cache::forever($key, ['cancelled' => true] + (array) Cache::get($key, []));
    }

    /**
     * The configured pickup location, as if the account had exactly that one.
     */
    public function listPickupLocations(): array
    {
        return [['pickup_location' => Settings::pickupLocation(), 'pin_code' => Settings::pickupPin()]];
    }

    public function getOrder(int $shiprocketOrderId): array
    {
        $order = Cache::get(self::CACHE_PREFIX.'order:'.$shiprocketOrderId);

        if (! $order) {
            throw new ShiprocketException('Order ID not found');
        }

        $total = isset($order['sub_total']) ? round($order['sub_total'] + ($order['shipping_charges'] ?? 0), 2) : null;

        return [
            'id' => $shiprocketOrderId,
            'channel_order_id' => $order['channel_order_id'] ?? null,
            'status' => $order['cancelled'] ? 'CANCELED' : 'NEW',
            'payment_method' => $order['payment_method'] ?? null,
            'total' => $total,
        ];
    }

    /**
     * Whether an order was cancelled (for tests and local checks).
     */
    public function isCancelled(int $shiprocketOrderId): bool
    {
        return (bool) (Cache::get(self::CACHE_PREFIX.'order:'.$shiprocketOrderId)['cancelled'] ?? false);
    }
}
