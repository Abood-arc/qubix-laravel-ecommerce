<?php

namespace DigitalLabs\Shiprocket\Payment;

use DigitalLabs\Payment\Payment\CashOnDelivery as CoreCashOnDelivery;
use DigitalLabs\Shiprocket\Services\AddressNormalizer;
use DigitalLabs\Shiprocket\Services\ServiceabilityChecker;
use DigitalLabs\Shiprocket\Support\CourierOptions;
use DigitalLabs\Shiprocket\Support\RateCode;

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

        if ($checker->check($pin, $checker->billedWeightKg($cart))['cod']) {
            return null;
        }

        return trans('shiprocket::app.checkout.cod-unavailable', ['pin' => $pin]);
    }

    /**
     * Says what paying cash adds to the chosen Shiprocket delivery, before the customer picks it.
     */
    public function getDescription()
    {
        $description = parent::getDescription();

        if (! $fee = $this->shiprocketFee()) {
            return $description;
        }

        return trim($description.' '.trans('shiprocket::app.checkout.cod-fee', ['fee' => core()->currency($fee)]));
    }

    /**
     * The chosen Shiprocket delivery's cash on delivery price minus its prepaid price, or null
     * when the cart has no priced Shiprocket delivery or Shiprocket has no answer.
     */
    protected function shiprocketFee(): ?float
    {
        $cart = $this->getCart();

        $chosen = RateCode::parse($cart?->shipping_method);

        $address = $cart?->shipping_address;

        if (
            ! $chosen
            || $chosen['tier'] === RateCode::BACKUP
            || ! $address
            || ! AddressNormalizer::isValidPin((string) $address->postcode)
        ) {
            return null;
        }

        $checker = app(ServiceabilityChecker::class);

        $pin = trim((string) $address->postcode);

        $weight = $checker->billedWeightKg($cart);

        $prepaid = $checker->couriers($pin, $weight, cod: false);

        $cash = $checker->couriers($pin, $weight, cod: true);

        if ($prepaid === null || $cash === null) {
            return null;
        }

        $cashOptions = new CourierOptions($cash);

        $prepaidCourier = (new CourierOptions($prepaid))->get($chosen['tier']);

        // The same fallback RateRefresher uses when the tier has no cash courier.
        $cashCourier = $cashOptions->get($chosen['tier']) ?? $cashOptions->standard();

        if (! $prepaidCourier || ! $cashCourier) {
            return null;
        }

        $addGst = (bool) core()->getConfigData('sales.carriers.'.RateCode::CARRIER.'.add_gst');

        $fee = RateCode::price($cashCourier->rate, $addGst) - RateCode::price($prepaidCourier->rate, $addGst);

        return $fee > 0 ? (float) $fee : null;
    }
}
