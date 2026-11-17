<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Checkout\Contracts\Cart;
use DigitalLabs\Checkout\Contracts\CartAddress;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Support\Settings;
use DigitalLabs\Shiprocket\Support\WeightUnit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Can Shiprocket deliver to a PIN code, and with Cash on Delivery?
 *
 * Two questions, each answered from a 6-hour cache keyed by pickup PIN, delivery PIN,
 * 0.5 kg weight slab and the COD flag. Asked with COD, Shiprocket may list only couriers
 * that collect cash, so "deliverable" is always asked without it. When Shiprocket fails,
 * checkout goes ahead (deliverable, COD allowed); that answer is logged and not cached.
 */
class ServiceabilityChecker
{
    protected const CACHE_SECONDS = 6 * 60 * 60;

    protected const WEIGHT_SLAB_KG = 0.5;

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
        $pickupPin = Settings::pickupPin();

        $deliveryPin = preg_replace('/\s+/', '', $deliveryPin);

        $slab = max(1, (int) ceil(round($weightKg / self::WEIGHT_SLAB_KG, 6)));

        $deliverable = $this->ask($pickupPin, $deliveryPin, $slab, cod: false);

        if ($deliverable === null) {
            return self::FAIL_OPEN;
        }

        if (! $deliverable) {
            return ['deliverable' => false, 'cod' => false];
        }

        return [
            'deliverable' => true,
            'cod' => $this->ask($pickupPin, $deliveryPin, $slab, cod: true) ?? true,
        ];
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
     * One cached question: is there a courier (that collects cash, when $cod)? Null when Shiprocket failed.
     */
    protected function ask(string $pickupPin, string $deliveryPin, int $slab, bool $cod): ?bool
    {
        $key = sprintf('shiprocket:svc:%s:%s:%d:%d', $pickupPin, $deliveryPin, $slab, $cod);

        $cached = Cache::get($key);

        if (is_bool($cached)) {
            return $cached;
        }

        try {
            $answer = $this->api->checkServiceability($pickupPin, $deliveryPin, $slab * self::WEIGHT_SLAB_KG, $cod);
        } catch (ShiprocketException $e) {
            Log::error('Shiprocket serviceability check failed; checkout continues without it.', [
                'pickup_pin' => $pickupPin,
                'delivery_pin' => $deliveryPin,
                'cod' => $cod,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $result = $cod ? (bool) $answer['cod'] : (bool) $answer['deliverable'];

        Cache::put($key, $result, self::CACHE_SECONDS);

        return $result;
    }
}
