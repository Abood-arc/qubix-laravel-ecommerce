<?php

use DigitalLabs\Checkout\Models\CartAddress;
use DigitalLabs\Sales\Models\OrderAddress;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Services\AddressNormalizer;

function shiprocketAddress(array $overrides = []): OrderAddress
{
    return new OrderAddress(array_merge([
        'first_name' => 'Asha',
        'last_name' => 'Rao',
        'address' => implode(PHP_EOL, ['12 MG Road', 'Near City Mall']),
        'city' => 'Pune',
        'state' => 'MH',
        'postcode' => '411001',
        'country' => 'IN',
        'email' => 'asha@example.com',
        'phone' => '9876543210',
    ], $overrides));
}

function normalizeShiprocketAddress(array $overrides = []): array
{
    return app(AddressNormalizer::class)->normalize(shiprocketAddress($overrides));
}

it('turns a store address into shiprocket fields', function () {
    expect(normalizeShiprocketAddress())->toBe([
        'first_name' => 'Asha',
        'last_name' => 'Rao',
        'address' => '12 MG Road',
        'address_2' => 'Near City Mall',
        'city' => 'Pune',
        'state' => 'Maharashtra',
        'pincode' => '411001',
        'country' => 'India',
        'email' => 'asha@example.com',
        'phone' => '9876543210',
    ]);
});

it('accepts a cart address too', function () {
    $address = new CartAddress(shiprocketAddress()->getAttributes());

    expect(app(AddressNormalizer::class)->normalize($address)['state'])->toBe('Maharashtra');
});

it('maps indian state codes to their names', function (string $state, string $name) {
    expect(normalizeShiprocketAddress(['state' => $state])['state'])->toBe($name);
})->with([
    'code' => ['KA', 'Karnataka'],
    'code with spaces' => [' DL ', 'Delhi'],
    'already a name' => ['Rajasthan', 'Rajasthan'],
]);

it('refuses a state it does not know', function () {
    normalizeShiprocketAddress(['state' => 'Mumbai']);
})->throws(ShiprocketException::class, 'Unknown state Mumbai for an Indian address.');

it('accepts a 6-digit pin code and strips spaces', function () {
    expect(normalizeShiprocketAddress(['postcode' => ' 411 001 '])['pincode'])->toBe('411001')
        ->and(AddressNormalizer::isValidPin('411001'))->toBeTrue()
        ->and(AddressNormalizer::isValidPin(' 110 001'))->toBeTrue();
});

it('refuses a pin code that is not a 6-digit indian one', function (string $pin) {
    expect(AddressNormalizer::isValidPin($pin))->toBeFalse();

    expect(fn () => normalizeShiprocketAddress(['postcode' => $pin]))
        ->toThrow(ShiprocketException::class, 'The delivery PIN code '.$pin.' is not a valid 6-digit Indian PIN code.');
})->with([
    'starts with 0' => ['011001'],
    'five digits' => ['41100'],
    'seven digits' => ['4110011'],
    'letters' => ['SW1A1AA'],
    'empty' => [''],
]);

it('normalises phone numbers to 10 digits', function (string $phone) {
    expect(normalizeShiprocketAddress(['phone' => $phone])['phone'])->toBe('9876543210');
})->with([
    'plain' => ['9876543210'],
    'country code with spaces' => ['+91 98765 43210'],
    'country code without plus' => ['919876543210'],
    'trunk zero' => ['09876543210'],
    'dashes' => ['98765-43210'],
]);

it('refuses a phone number that is not 10 digits', function (string $phone) {
    normalizeShiprocketAddress(['phone' => $phone]);
})->throws(ShiprocketException::class, 'The phone number must have 10 digits.')->with([
    'short' => ['98765'],
    'too long' => ['+1 415 555 0100 22'],
    'empty' => [''],
]);

it('keeps the first address line and puts the rest in the second', function () {
    $lines = normalizeShiprocketAddress([
        'address' => implode(PHP_EOL, ['Flat 4, Sunrise Apartments', 'Baner Road', 'Opp. Park']),
    ]);

    expect($lines['address'])->toBe('Flat 4, Sunrise Apartments')
        ->and($lines['address_2'])->toBe('Baner Road, Opp. Park');
});

it('moves an overlong first line into the second at a word break', function () {
    $first = str_repeat('word ', 20).'end'; // 103 characters

    $lines = normalizeShiprocketAddress(['address' => implode(PHP_EOL, [$first, 'Second'])]);

    expect(mb_strlen($lines['address']))->toBeLessThanOrEqual(80)
        ->and($lines['address'])->toBe(trim(str_repeat('word ', 16)))
        ->and($lines['address_2'])->toBe('word word word word end, Second');
});

it('caps the second line at 80 characters', function () {
    $lines = normalizeShiprocketAddress([
        'address' => implode(PHP_EOL, ['Line one', str_repeat('x', 100)]),
    ]);

    expect($lines['address'])->toBe('Line one')
        ->and(mb_strlen($lines['address_2']))->toBe(80);
});

it('cuts a city to shiprocket\'s 30 characters', function (string $city, string $expected) {
    expect(normalizeShiprocketAddress(['city' => $city])['city'])->toBe($expected);
})->with([
    'short city kept' => ['  Pune ', 'Pune'],
    'exactly 30 kept' => [str_repeat('a', 30), str_repeat('a', 30)],
    'cut at a word break' => ['Thiruvananthapuram Cantonment North', 'Thiruvananthapuram Cantonment'],
    'no break: cut at 30' => [str_repeat('x', 35), str_repeat('x', 30)],
    'no dangling punctuation' => ['Sri Ganganagar Cantonment, Rajasthan', 'Sri Ganganagar Cantonment'],
    'multibyte' => [str_repeat('पुणे', 10), mb_substr(str_repeat('पुणे', 10), 0, 30)],
]);
