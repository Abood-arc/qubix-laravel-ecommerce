<?php

namespace DigitalLabs\Shiprocket\Services;

use LogicException;

/**
 * Turns a store address into the fields Shiprocket accepts (Task 3).
 */
class AddressNormalizer
{
    /**
     * @param  \DigitalLabs\Sales\Contracts\OrderAddress|\DigitalLabs\Checkout\Contracts\CartAddress  $address
     * @return array{first_name: string, last_name: string, address: string, address_2: string, city: string, state: string, pincode: string, country: string, email: string, phone: string}
     */
    public function normalize(object $address): array
    {
        throw new LogicException('Not implemented yet: Task 3');
    }

    public static function isValidPin(string $pin): bool
    {
        throw new LogicException('Not implemented yet: Task 3');
    }
}
