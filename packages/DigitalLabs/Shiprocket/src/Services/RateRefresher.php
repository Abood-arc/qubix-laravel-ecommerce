<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Checkout\Contracts\Cart as CartContract;
use DigitalLabs\Checkout\Facades\Cart;
use DigitalLabs\Shipping\Facades\Shipping;
use DigitalLabs\Shiprocket\Support\RateCode;

/**
 * Prices the cart's Shiprocket delivery again, keeping the tier the customer chose.
 *
 * A Shiprocket price depends on more than the address: Cash on Delivery has its own couriers
 * and fee, and Shiprocket's rates can change between the shipping step and the order. So the
 * price is renewed when the payment method changes and once more before the order is placed.
 */
class RateRefresher
{
    /**
     * @return array{changed: bool, price: ?float}|null null when the cart has no Shiprocket
     *                                                  delivery; price null when Shiprocket no
     *                                                  longer offers one for the cart
     */
    public function refresh(CartContract $cart): ?array
    {
        $chosen = RateCode::parse($cart->shipping_method);

        if (! $chosen) {
            return null;
        }

        // Reloaded: the cart in hand can still hold the payment method it had before this request
        // replaced it, and the price depends on it.
        $cart->refresh();

        $oldPrice = $cart->selected_shipping_rate?->base_price;

        Cart::setCart($cart);

        Shipping::collectRates();

        $cart = Cart::getCart();

        $rates = $cart->shipping_rates()->get()->keyBy('method');

        $tierOf = fn (string $code) => RateCode::parse($code)['tier'] ?? null;

        // The same tier; when it is gone (Express without a faster cash courier), the closest one.
        $code = collect([$chosen['tier'], RateCode::STANDARD, RateCode::BACKUP])
            ->map(fn (string $tier) => $rates->keys()->first(fn (string $code) => $tierOf($code) === $tier))
            ->filter()
            ->first();

        if (! $code) {
            return ['changed' => true, 'price' => null];
        }

        if ($cart->shipping_method !== $code) {
            // Set directly: Cart::saveShippingMethod() would collect the rates all over again.
            $cart->shipping_method = $code;
            $cart->save();
        }

        Cart::setCart($cart->refresh());

        Cart::collectTotals();

        $newPrice = (float) $rates[$code]->base_price;

        return [
            'changed' => $oldPrice === null || abs((float) $oldPrice - $newPrice) >= 0.01,
            'price' => $newPrice,
        ];
    }
}
