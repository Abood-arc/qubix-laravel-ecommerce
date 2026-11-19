<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Checkout\Contracts\Cart;
use DigitalLabs\Checkout\Contracts\CartAddress;
use DigitalLabs\Shipping\Contracts\DeliveryCheck as DeliveryCheckContract;

/**
 * Refuses checkout addresses Shiprocket cannot deliver to, and a Shiprocket delivery price
 * that is no longer what Shiprocket charges (checked again right before the order is placed).
 */
class DeliveryCheck implements DeliveryCheckContract
{
    public function __construct(
        protected ServiceabilityChecker $checker,
        protected RateRefresher $refresher,
    ) {}

    public function check(CartAddress $shippingAddress, Cart $cart): ?string
    {
        return $this->undeliverable($shippingAddress, $cart) ?? $this->stalePrice($cart);
    }

    protected function undeliverable(CartAddress $shippingAddress, Cart $cart): ?string
    {
        if (! $this->checker->checksAddress($shippingAddress)) {
            return null;
        }

        $pin = trim((string) $shippingAddress->postcode);

        if (! AddressNormalizer::isValidPin($pin)) {
            return trans('shiprocket::app.errors.invalid-pin', ['pin' => $pin]);
        }

        if (! $this->checker->check($pin, $this->checker->billedWeightKg($cart))['deliverable']) {
            return trans('shiprocket::app.checkout.not-deliverable', ['pin' => $pin]);
        }

        return null;
    }

    /**
     * The customer must never pay a Shiprocket price that changed since they saw it. Saving an
     * address clears the shipping method, so this only acts once a delivery option is chosen.
     */
    protected function stalePrice(Cart $cart): ?string
    {
        $refreshed = $this->refresher->refresh($cart);

        if (! $refreshed || ! $refreshed['changed']) {
            return null;
        }

        // No current Shiprocket option, or the price could not be checked just now.
        if ($refreshed['price'] === null) {
            return trans('shiprocket::app.checkout.recheck-delivery');
        }

        return trans('shiprocket::app.checkout.price-changed', ['price' => core()->currency($refreshed['price'])]);
    }
}
