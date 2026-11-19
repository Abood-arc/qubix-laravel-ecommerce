<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Checkout\Contracts\Cart;
use DigitalLabs\Checkout\Contracts\CartAddress;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Support\Courier;
use DigitalLabs\Shiprocket\Support\Settings;
use DigitalLabs\Shiprocket\Support\WeightUnit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Which couriers deliver to a PIN code, at what price, and can any of them collect cash?
 *
 * Two courier lists (prepaid, and Cash on Delivery with its fee), each answered from a 6-hour
 * cache keyed by pickup PIN, delivery PIN, 0.5 kg weight slab and the COD flag. Asked with
 * COD, Shiprocket lists only couriers that collect cash, so "deliverable" is always asked
 * without it. When Shiprocket fails
 * (no answer within ShiprocketClient's 3 s, or an unclear one), checkout goes ahead
 * (deliverable, COD allowed); that answer is logged and cached for only 2 minutes, so the
 * customer's next checkout steps do not wait for a Shiprocket that is down.
 */
class ServiceabilityChecker
{
    protected const CACHE_SECONDS = 6 * 60 * 60;

    protected const FAIL_OPEN_CACHE_SECONDS = 2 * 60;

    /**
     * Cached in place of an answer while Shiprocket recently failed.
     */
    protected const FAILED = 'failed';

    protected const WEIGHT_SLAB_KG = 0.5;

    /**
     * Cubic centimetres per volumetric kilogram, as Indian couriers bill.
     */
    protected const VOLUMETRIC_DIVISOR = 5000;

    protected const FAIL_OPEN = ['deliverable' => true, 'cod' => true];

    public function __construct(protected ShiprocketApi $api) {}

    /**
     * Does the PIN check apply to this address: Shiprocket and its PIN check are on, and it is
     * an Indian address with a postcode?
     */
    public function checksAddress(?CartAddress $address): bool
    {
        return Settings::enabled()
            && Settings::pinCheck()
            && $address
            && strtoupper(trim((string) $address->country)) === 'IN'
            && trim((string) $address->postcode) !== '';
    }

    /**
     * @return array{deliverable: bool, cod: bool}
     */
    public function check(string $deliveryPin, float $weightKg): array
    {
        $deliverable = $this->couriers($deliveryPin, $weightKg, cod: false);

        if ($deliverable === null) {
            return self::FAIL_OPEN;
        }

        if ($deliverable === []) {
            return ['deliverable' => false, 'cod' => false];
        }

        $cod = $this->couriers($deliveryPin, $weightKg, cod: true);

        return ['deliverable' => true, 'cod' => $cod === null || $cod !== []];
    }

    /**
     * The couriers for this parcel (only those that collect cash, priced with their fee, when
     * $cod). Null when Shiprocket failed, now or in the last 2 minutes.
     *
     * @return list<Courier>|null
     */
    public function couriers(string $deliveryPin, float $weightKg, bool $cod): ?array
    {
        $pickupPin = Settings::pickupPin();

        $deliveryPin = preg_replace('/\s+/', '', $deliveryPin);

        $slab = max(1, (int) ceil(round($weightKg / self::WEIGHT_SLAB_KG, 6)));

        $key = sprintf('shiprocket:svc:%s:%s:%d:%d', $pickupPin, $deliveryPin, $slab, $cod);

        $cached = Cache::get($key);

        if ($cached === self::FAILED) {
            return null;
        }

        // Anything else that is not a list (a yes/no cached before prices existed) is asked again.
        if (is_array($cached)) {
            return array_map(fn (array $courier) => Courier::fromArray($courier), $cached);
        }

        try {
            $couriers = $this->api->couriers($pickupPin, $deliveryPin, $slab * self::WEIGHT_SLAB_KG, $cod);
        } catch (ShiprocketException $e) {
            Log::error('Shiprocket serviceability check failed; checkout continues without it.', [
                'pickup_pin' => $pickupPin,
                'delivery_pin' => $deliveryPin,
                'cod' => $cod,
                'error' => $e->getMessage(),
            ]);

            Cache::put($key, self::FAILED, self::FAIL_OPEN_CACHE_SECONDS);

            return null;
        }

        Cache::put($key, array_map(fn (Courier $courier) => $courier->toArray(), $couriers), self::CACHE_SECONDS);

        return $couriers;
    }

    /**
     * The cart's weight in kg: every top-level item's weight × quantity, in the store weight unit.
     */
    public function cartWeightKg(Cart $cart): float
    {
        $weight = $cart->items->sum(fn ($item) => (float) $item->weight * (float) $item->quantity);

        return round($weight * WeightUnit::kgPerUnit(), 3);
    }

    /**
     * The weight couriers charge for: the cart's weight, or the packed parcel's volumetric
     * weight (L × B × H cm / 5000) when that is more.
     */
    public function billedWeightKg(Cart $cart): float
    {
        $size = Settings::dimensions();

        $volumetric = $size['length'] * $size['breadth'] * $size['height'] / self::VOLUMETRIC_DIVISOR;

        return round(max($this->cartWeightKg($cart), $volumetric), 3);
    }
}
