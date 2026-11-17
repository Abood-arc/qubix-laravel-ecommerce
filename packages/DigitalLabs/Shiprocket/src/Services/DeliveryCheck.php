<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Checkout\Contracts\Cart;
use DigitalLabs\Checkout\Contracts\CartAddress;
use DigitalLabs\Shipping\Contracts\DeliveryCheck as DeliveryCheckContract;

/**
 * Refuses checkout addresses Shiprocket cannot deliver to.
 */
class DeliveryCheck implements DeliveryCheckContract
{
    public function __construct(protected ServiceabilityChecker $checker) {}

    public function check(CartAddress $shippingAddress, Cart $cart): ?string
    {
        if (! $this->checker->checksAddress($shippingAddress)) {
            return null;
        }

        $pin = trim((string) $shippingAddress->postcode);

        if (! AddressNormalizer::isValidPin($pin)) {
            return trans('shiprocket::app.errors.invalid-pin', ['pin' => $pin]);
        }

        if (! $this->checker->check($pin, $this->checker->cartWeightKg($cart))['deliverable']) {
            return trans('shiprocket::app.checkout.not-deliverable', ['pin' => $pin]);
        }

        return null;
    }
}
