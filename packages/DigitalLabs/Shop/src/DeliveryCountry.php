<?php

namespace DigitalLabs\Shop;

use Closure;

/**
 * The one country a store delivers to, when the admin says so (Sales > Shipping > Origin:
 * "Deliver only to this country"). Address forms then show that country fixed, and the
 * server refuses any other.
 */
final class DeliveryCountry
{
    /**
     * The country code addresses must have, or null when the store delivers anywhere.
     */
    public static function only(): ?string
    {
        if (! core()->getConfigData('sales.shipping.origin.deliver_only_here')) {
            return null;
        }

        return core()->getConfigData('sales.shipping.origin.country') ?: null;
    }

    /**
     * Validation rules for an address's country field.
     *
     * @return array<int, Closure>
     */
    public static function rules(): array
    {
        if (! $only = self::only()) {
            return [];
        }

        return [function (string $attribute, mixed $value, Closure $fail) use ($only) {
            if ($value !== $only) {
                $fail(self::message($only));
            }
        }];
    }

    /**
     * "We deliver only within India."
     */
    public static function message(string $code): string
    {
        return trans('shop::app.checkout.onepage.address.deliver-only-within', [
            'country' => core()->country_name($code),
        ]);
    }
}
