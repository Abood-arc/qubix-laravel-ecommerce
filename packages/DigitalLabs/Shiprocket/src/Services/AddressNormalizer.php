<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use Illuminate\Support\Facades\DB;

/**
 * Turns a store address into the fields Shiprocket accepts.
 *
 * Shiprocket ships within India only, so the state must be an Indian one and the
 * country is always India.
 */
class AddressNormalizer
{
    /**
     * Longest address line sent to Shiprocket.
     */
    protected const LINE_LIMIT = 80;

    /**
     * Longest city Shiprocket accepts (Create Custom Order: billing_city "Max char: 30").
     */
    protected const CITY_LIMIT = 30;

    protected const COUNTRY = 'India';

    /**
     * @param  \DigitalLabs\Sales\Contracts\OrderAddress|\DigitalLabs\Checkout\Contracts\CartAddress  $address
     * @return array{first_name: string, last_name: string, address: string, address_2: string, city: string, state: string, pincode: string, country: string, email: string, phone: string}
     *
     * @throws ShiprocketException when the state, PIN code or phone number cannot be used
     */
    public function normalize(object $address): array
    {
        [$line1, $line2] = $this->lines((string) $address->address);

        return [
            'first_name' => trim((string) $address->first_name),
            'last_name' => trim((string) $address->last_name),
            'address' => $line1,
            'address_2' => $line2,
            'city' => $this->city((string) $address->city),
            'state' => $this->stateName((string) $address->state),
            'pincode' => $this->pin((string) $address->postcode),
            'country' => self::COUNTRY,
            'email' => trim((string) $address->email),
            'phone' => $this->phone((string) $address->phone),
        ];
    }

    /**
     * A 6-digit Indian PIN code (spaces ignored, so "411 001" passes).
     */
    public static function isValidPin(string $pin): bool
    {
        return (bool) preg_match('/^[1-9]\d{5}$/', static::stripSpaces($pin));
    }

    protected static function stripSpaces(string $value): string
    {
        return preg_replace('/\s+/', '', $value);
    }

    protected function pin(string $postcode): string
    {
        if (! static::isValidPin($postcode)) {
            throw new ShiprocketException(trans('shiprocket::app.errors.invalid-pin', ['pin' => trim($postcode)]));
        }

        return static::stripSpaces($postcode);
    }

    /**
     * Addresses store Indian states as codes ("MH"); Shiprocket wants the name. A name
     * already in the address (older data, the address factory) is accepted as is.
     */
    protected function stateName(string $state): string
    {
        $state = trim($state);

        $name = $state === '' ? null : DB::table('country_states')
            ->where('country_code', 'IN')
            ->where(fn ($query) => $query->where('code', $state)->orWhere('default_name', $state))
            ->value('default_name');

        if (! $name) {
            throw new ShiprocketException(trans('shiprocket::app.errors.unknown-state', ['state' => $state]));
        }

        return $name;
    }

    /**
     * The city within Shiprocket's limit: cut at the last word break that fits (or at the
     * limit when there is none), without a dangling comma or dash.
     */
    protected function city(string $city): string
    {
        $city = trim($city);

        if (mb_strlen($city) <= self::CITY_LIMIT) {
            return $city;
        }

        $break = mb_strrpos(mb_substr($city, 0, self::CITY_LIMIT + 1), ' ') ?: self::CITY_LIMIT;

        return rtrim(mb_substr($city, 0, $break), " \t,.;:-/");
    }

    /**
     * Digits only, without the +91 country code or a trunk 0.
     */
    protected function phone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            throw new ShiprocketException(trans('shiprocket::app.errors.invalid-phone'));
        }

        return $digits;
    }

    /**
     * The first stored line becomes `address`, the other lines `address_2`. A first line
     * over the limit moves its tail (from the last word break) to the start of `address_2`.
     *
     * @return array{0: string, 1: string}
     */
    protected function lines(string $address): array
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $address)),
            fn (string $line) => $line !== ''
        ));

        $first = $lines[0] ?? '';

        $rest = array_slice($lines, 1);

        if (mb_strlen($first) > self::LINE_LIMIT) {
            $break = mb_strrpos(mb_substr($first, 0, self::LINE_LIMIT + 1), ' ') ?: self::LINE_LIMIT;

            array_unshift($rest, trim(mb_substr($first, $break)));

            $first = trim(mb_substr($first, 0, $break));
        }

        return [$first, trim(mb_substr(implode(', ', $rest), 0, self::LINE_LIMIT))];
    }
}
