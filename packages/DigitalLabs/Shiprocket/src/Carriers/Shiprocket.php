<?php

namespace DigitalLabs\Shiprocket\Carriers;

use DigitalLabs\Checkout\Facades\Cart;
use DigitalLabs\Checkout\Models\CartShippingRate;
use DigitalLabs\Shipping\Carriers\AbstractShipping;
use DigitalLabs\Shiprocket\Services\AddressNormalizer;
use DigitalLabs\Shiprocket\Services\ServiceabilityChecker;
use DigitalLabs\Shiprocket\Support\Courier;
use DigitalLabs\Shiprocket\Support\CourierOptions;
use DigitalLabs\Shiprocket\Support\RateCode;
use DigitalLabs\Shiprocket\Support\Settings;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Charges what Shiprocket bills for the customer's parcel: Standard (cheapest courier) and
 * Express (fastest), priced from the Cash on Delivery list, fee included, when the cart pays
 * cash. When Shiprocket cannot answer, one Standard option at the admin's backup price.
 */
class Shiprocket extends AbstractShipping
{
    protected $code = RateCode::CARRIER;

    /**
     * On in Shipping Methods, with Shiprocket itself set up and a backup price to fall back on.
     */
    public function isAvailable()
    {
        return parent::isAvailable()
            && Settings::enabled()
            && AddressNormalizer::isValidPin(Settings::pickupPin())
            && is_numeric($this->getConfigData('backup_price'));
    }

    /**
     * @return array<int, CartShippingRate>|false
     */
    public function calculate()
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $cart = Cart::getCart();

        $address = $cart?->shipping_address;

        // Shiprocket only ships within India.
        if (
            ! $address
            || strtoupper(trim((string) $address->country)) !== 'IN'
            || ! AddressNormalizer::isValidPin((string) $address->postcode)
        ) {
            return false;
        }

        $checker = app(ServiceabilityChecker::class);

        $cod = $this->paysCash($cart);

        $couriers = $checker->couriers(trim((string) $address->postcode), $checker->billedWeightKg($cart), $cod);

        if ($couriers === null) {
            return [$this->rate(RateCode::BACKUP, null, (float) $this->getConfigData('backup_price'), $cod)];
        }

        $options = new CourierOptions($couriers);

        $addGst = (bool) $this->getConfigData('add_gst');

        $rates = [];

        foreach ([RateCode::STANDARD, RateCode::EXPRESS] as $tier) {
            if ($courier = $options->get($tier)) {
                $rates[] = $this->rate($tier, $courier, RateCode::price($courier->rate, $addGst), $cod);
            }
        }

        return $rates ?: false;
    }

    /**
     * Prices follow the payment method: Cash on Delivery costs the store a fee per parcel.
     */
    protected function paysCash($cart): bool
    {
        return $cart->payment?->method === 'cashondelivery';
    }

    protected function rate(string $tier, ?Courier $courier, float $basePrice, bool $cod): CartShippingRate
    {
        $rate = new CartShippingRate;

        $rate->carrier = $this->getCode();
        $rate->carrier_title = $this->getConfigData('title') ?: 'Shiprocket';
        $rate->method = RateCode::make($tier, $courier?->id, $basePrice);
        $rate->method_title = trans('shiprocket::app.checkout.'.$tier.($cod && $tier !== RateCode::BACKUP ? '-cod' : ''));
        $rate->method_description = $this->arrives($courier);
        $rate->price = core()->convertPrice($basePrice);
        $rate->base_price = $basePrice;

        return $rate;
    }

    /**
     * "Arrives by 25 Nov" from Shiprocket's estimate; checkout shows the title and this side by
     * side, so without an estimate it still says something true.
     */
    protected function arrives(?Courier $courier): string
    {
        $fallback = trans('shiprocket::app.checkout.tracked');

        if (! $courier || $courier->etd === '') {
            return $fallback;
        }

        try {
            $date = Carbon::parse($courier->etd);
        } catch (Throwable) {
            return $fallback;
        }

        return trans('shiprocket::app.checkout.arrives', ['date' => $date->format('j M')]);
    }
}
