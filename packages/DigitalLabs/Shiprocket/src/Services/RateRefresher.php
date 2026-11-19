<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Checkout\Contracts\Cart as CartContract;
use DigitalLabs\Checkout\Facades\Cart;
use DigitalLabs\Shipping\Facades\Shipping;
use DigitalLabs\Shiprocket\Support\RateCode;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Prices the cart's Shiprocket delivery again, keeping the tier the customer chose.
 *
 * A Shiprocket price depends on more than the address: Cash on Delivery has its own couriers
 * and fee, and Shiprocket's rates can change between the shipping step and the order. So the
 * price is renewed when the payment method changes and once more before the order is placed.
 * Rate codes carry the quoted price (see RateCode), so a changed quote is a changed code.
 *
 * One re-pricing at a time per cart: a customer clicking two payment methods quickly sends two
 * requests at once, and interleaved, the slower one would leave the other's price behind. In
 * turn, the last one reads the payment method that was saved last.
 */
class RateRefresher
{
    protected const LOCK_SECONDS = 15;

    protected const WAIT_SECONDS = 10;

    /**
     * @return array{changed: bool, price: ?float}|null null when the cart has no Shiprocket
     *                                                  delivery; changed when the quoted price
     *                                                  moved; price null when Shiprocket no
     *                                                  longer offers a delivery for the cart
     */
    public function refresh(CartContract $cart): ?array
    {
        if (! RateCode::parse($cart->shipping_method)) {
            return null;
        }

        // Unchecked is never "unchanged": the check before the order refuses it instead.
        return $this->locked($cart->id, fn () => $this->reprice($cart), ['changed' => true, 'price' => null]);
    }

    /**
     * Collects the cart's rates and returns the code now offered for the tier of $code: $code
     * itself while its quote still stands, else the same tier's new code, else Standard, else
     * the backup price. Null when $code is not a Shiprocket code or nothing is offered.
     */
    public function currentCode(string $code): ?string
    {
        $cart = Cart::getCart();

        if (! $cart || ! RateCode::parse($code)) {
            return null;
        }

        return $this->locked($cart->id, fn () => $this->offeredCode($code), null);
    }

    protected function reprice(CartContract $cart): array
    {
        // Reloaded: the cart in hand can still hold the payment method it had before this request
        // replaced it, and the price depends on it.
        $cart->refresh();

        Cart::setCart($cart);

        if (! $chosen = RateCode::parse($cart->shipping_method)) {
            return ['changed' => true, 'price' => null];
        }

        if (! $code = $this->offeredCode($cart->shipping_method)) {
            return ['changed' => true, 'price' => null];
        }

        $cart = Cart::getCart();

        if ($cart->shipping_method !== $code) {
            // Set directly: Cart::saveShippingMethod() would collect the rates all over again.
            $cart->shipping_method = $code;
            $cart->save();
        }

        Cart::setCart($cart->refresh());

        Cart::collectTotals();

        $price = RateCode::parse($code)['price'];

        // Another courier at the same price is no news to the customer.
        return ['changed' => abs($price - $chosen['price']) >= 0.01, 'price' => $price];
    }

    protected function offeredCode(string $code): ?string
    {
        $tier = RateCode::parse($code)['tier'];

        Shipping::collectRates();

        $offered = Cart::getCart()->shipping_rates()->pluck('method');

        if ($offered->contains($code)) {
            return $code;
        }

        foreach ([$tier, RateCode::STANDARD, RateCode::BACKUP] as $fallback) {
            $match = $offered->first(fn (string $offer) => (RateCode::parse($offer)['tier'] ?? null) === $fallback);

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Runs $work while holding the cart's re-pricing lock, or returns $timedOut when another
     * request holds it longer than we wait.
     */
    protected function locked(int $cartId, \Closure $work, mixed $timedOut): mixed
    {
        try {
            return Cache::lock('shiprocket:reprice:cart:'.$cartId, self::LOCK_SECONDS)
                ->block(self::WAIT_SECONDS, $work);
        } catch (LockTimeoutException) {
            return $timedOut;
        }
    }
}
