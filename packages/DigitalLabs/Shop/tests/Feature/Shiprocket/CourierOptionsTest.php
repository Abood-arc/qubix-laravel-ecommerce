<?php

use DigitalLabs\Shiprocket\Support\Courier;
use DigitalLabs\Shiprocket\Support\CourierOptions;
use DigitalLabs\Shiprocket\Support\RateCode;

/**
 * The couriers Shiprocket listed for one bag, Chennai to Delhi, on 2026-11-19.
 */
function liveCouriers(): array
{
    return [
        new Courier(1, 'Blue Dart Air', 156.84, 2, 'Nov 21, 2026'),
        new Courier(58, 'Shadowfax Surface', 93.72, 6, 'Nov 25, 2026'),
        new Courier(6, 'DTDC Surface', 107.22, 5, 'Nov 24, 2026'),
        new Courier(196, 'DTDC Air 500gm', 125.82, 2, 'Nov 21, 2026'),
    ];
}

it('offers the cheapest courier as standard and the cheaper of the fastest as express', function () {
    $options = new CourierOptions(liveCouriers());

    expect($options->standard()->name)->toBe('Shadowfax Surface')
        ->and($options->express()->name)->toBe('DTDC Air 500gm')
        ->and($options->get(RateCode::STANDARD)->id)->toBe(58)
        ->and($options->get(RateCode::EXPRESS)->id)->toBe(196)
        ->and($options->get(RateCode::BACKUP))->toBeNull();
});

it('offers no express when nothing is faster than standard', function (array $couriers) {
    expect((new CourierOptions($couriers))->express())->toBeNull();
})->with([
    'one courier' => [[new Courier(58, 'Shadowfax Surface', 93.72, 6, '')]],
    'the cheapest is also the fastest' => [[new Courier(1, 'A', 90, 2, ''), new Courier(2, 'B', 150, 2, '')]],
    'no estimates' => [[new Courier(1, 'A', 90, 0, ''), new Courier(2, 'B', 150, 0, '')]],
]);

it('takes an estimate over none for express', function () {
    $options = new CourierOptions([new Courier(1, 'A', 90, 0, ''), new Courier(2, 'B', 150, 3, '')]);

    expect($options->standard()->id)->toBe(1)
        ->and($options->express()->id)->toBe(2);
});

it('breaks price ties by speed, then by id', function () {
    $options = new CourierOptions([new Courier(9, 'Slow', 90, 6, ''), new Courier(8, 'Fast', 90, 3, ''), new Courier(7, 'Fast too', 90, 3, '')]);

    expect($options->standard()->id)->toBe(7)
        ->and($options->express())->toBeNull();
});

it('offers nothing for an empty list', function () {
    $options = new CourierOptions([]);

    expect($options->standard())->toBeNull()
        ->and($options->express())->toBeNull();
});

it('builds and reads back the method codes', function () {
    expect(RateCode::make(RateCode::STANDARD, 58))->toBe('shiprocket_standard_c58')
        ->and(RateCode::parse('shiprocket_standard_c58'))->toBe(['tier' => 'standard', 'courier_id' => 58])
        ->and(RateCode::parse('shiprocket_express_c196'))->toBe(['tier' => 'express', 'courier_id' => 196])
        ->and(RateCode::make(RateCode::BACKUP))->toBe('shiprocket_backup')
        ->and(RateCode::parse('shiprocket_backup'))->toBe(['tier' => 'backup', 'courier_id' => null]);
});

it('does not read other methods as ours', function (?string $code) {
    expect(RateCode::parse($code))->toBeNull();
})->with(['flatrate_flatrate', 'free_free', 'shiprocket_standard', 'shiprocket_cheap_c5', 'shiprocket_standard_c', null]);

it('rounds the price up to the rupee, with gst when the rates exclude it', function (float $rate, bool $gst, int $price) {
    expect(RateCode::price($rate, $gst))->toBe($price);
})->with([
    [93.72, false, 94],
    [93.72, true, 111],
    [100, true, 118],
    [94, false, 94],
]);
