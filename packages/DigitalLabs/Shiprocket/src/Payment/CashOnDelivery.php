<?php

namespace DigitalLabs\Shiprocket\Payment;

use DigitalLabs\Payment\Payment\CashOnDelivery as CoreCashOnDelivery;
use DigitalLabs\Shiprocket\Services\AddressNormalizer;
use DigitalLabs\Shiprocket\Services\ServiceabilityChecker;

/**
 * Core Cash on Delivery, unavailable (with a reason) where no courier collects cash.
 * Behaves exactly like core COD while Shiprocket or its PIN check is off.
 */
class CashOnDelivery extends CoreCashOnDelivery
{
    public function isAvailable()
    {
        return parent::isAvailable() && $this->getUnavailableReason() === null;
    }

    public function getUnavailableReason(): ?string
    {
        if (! parent::isAvailable()) {
            return null;
        }

        $cart = $this->getCart();

        $address = $cart->shipping_address ?? $cart->billing_address;

        $checker = app(ServiceabilityChecker::class);

        if (! $checker->checksAddress($address) || ! AddressNormalizer::isValidPin((string) $address->postcode)) {
            return null;
        }

        $pin = trim((string) $address->postcode);

        if ($checker->check($pin, $checker->cartWeightKg($cart))['cod']) {
            return null;
        }

        return trans('shiprocket::app.checkout.cod-unavailable', ['pin' => $pin]);
    }
}
