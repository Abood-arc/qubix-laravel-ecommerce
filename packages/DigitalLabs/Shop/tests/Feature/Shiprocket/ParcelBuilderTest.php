<?php

use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Sales\Models\OrderAddress;
use DigitalLabs\Sales\Models\OrderItem;
use DigitalLabs\Sales\Models\OrderPayment;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Services\ParcelBuilder;
use Webkul\Faker\Helpers\Customer as CustomerFaker;

/**
 * Save one admin setting for the current channel, replacing any saved value.
 */
function setShiprocketParcelConfig(string $code, mixed $value): void
{
    CoreConfig::query()->where('code', $code)->get()->each->delete();

    if ($value !== null) {
        CoreConfig::create([
            'code' => $code,
            'value' => $value,
            'channel_code' => core()->getRequestedChannelCode(),
        ]);
    }
}

/**
 * An order with a billing and a shipping address, a payment and the given top-level items.
 *
 * @param  array<int, array<string, mixed>>  $items
 */
function createShiprocketParcelOrder(array $items, array $totals = [], string $method = 'razorpay'): Order
{
    $customer = (new CustomerFaker)->factory()->create();

    $order = Order::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'sub_total' => 0,
        'tax_amount' => 0,
        'shipping_amount' => 0,
        'shipping_tax_amount' => 0,
        'shipping_discount_amount' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
    ], $totals));

    foreach ($items as $item) {
        $qty = $item['qty_ordered'] ?? 1;

        OrderItem::factory()->create(array_merge([
            'order_id' => $order->id,
            'type' => 'simple',
            'name' => 'Leather Tote',
            'sku' => 'TOTE-1',
            'weight' => 0,
            'price' => 1000,
            'total' => 1000 * $qty,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ], $item));
    }

    foreach ([OrderAddress::ADDRESS_TYPE_BILLING, OrderAddress::ADDRESS_TYPE_SHIPPING] as $type) {
        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'address_type' => $type,
            'first_name' => $type === OrderAddress::ADDRESS_TYPE_BILLING ? 'Bina' : 'Asha',
            'last_name' => 'Rao',
            'address' => implode(PHP_EOL, ['12 MG Road', 'Camp']),
            'city' => 'Pune',
            'state' => 'MH',
            'postcode' => '411001',
            'country' => 'IN',
            'email' => 'asha@example.com',
            'phone' => '+91 98765 43210',
        ]);
    }

    OrderPayment::factory()->create(['order_id' => $order->id, 'method' => $method]);

    return $order->fresh();
}

function shiprocketParcelBuilder(): ParcelBuilder
{
    return app(ParcelBuilder::class);
}

beforeEach(function () {
    setShiprocketParcelConfig('general.general.locale_options.weight_unit', 'g');
    setShiprocketParcelConfig('sales.shipping.shiprocket.pickup_location', 'Pune Warehouse');
    setShiprocketParcelConfig('sales.shipping.shiprocket.length', '30');
    setShiprocketParcelConfig('sales.shipping.shiprocket.breadth', '25');
    setShiprocketParcelConfig('sales.shipping.shiprocket.height', '10');
});

it('converts the store weight unit to kg', function (?string $unit, float $weight, float $kg) {
    setShiprocketParcelConfig('general.general.locale_options.weight_unit', $unit);

    $order = createShiprocketParcelOrder([
        ['weight' => $weight, 'qty_ordered' => 2],
        ['weight' => $weight, 'qty_ordered' => 1],
    ]);

    expect(shiprocketParcelBuilder()->weightKg($order))->toBe($kg);
})->with([
    'grams' => ['g', 250, 0.75],
    'kilograms' => ['kgs', 0.25, 0.75],
    'pounds' => ['lbs', 1, 1.361],
    'unknown unit is kg' => ['stone', 0.25, 0.75],
    'unset unit is kg' => [null, 0.25, 0.75],
]);

it('weighs only what is still to ship and ignores child items', function () {
    $order = createShiprocketParcelOrder([
        ['weight' => 400, 'qty_ordered' => 3, 'qty_shipped' => 1, 'total' => 3000],
        ['weight' => 900, 'qty_ordered' => 1, 'qty_shipped' => 1, 'sku' => 'SHIPPED'],
    ]);

    // A configurable's child row carries the same weight; counting it would double the parcel.
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'parent_id' => $order->items->first()->id,
        'type' => 'simple',
        'sku' => 'CHILD',
        'weight' => 400,
        'qty_ordered' => 3,
    ]);

    $order = $order->fresh();

    $payload = shiprocketParcelBuilder()->build($order);

    expect(shiprocketParcelBuilder()->weightKg($order))->toBe(0.8)
        ->and($payload['weight'])->toBe(0.8)
        ->and($payload['order_items'])->toHaveCount(1)
        ->and($payload['order_items'][0]['sku'])->toBe('TOTE-1')
        ->and($payload['order_items'][0]['units'])->toBe(2)
        // The remainder of a partly shipped order declares only the units in this parcel.
        ->and($payload['sub_total'])->toBe(2000.0);
});

it('builds the shiprocket order payload', function () {
    $order = createShiprocketParcelOrder([
        ['weight' => 500, 'qty_ordered' => 2, 'total' => 2000, 'name' => 'Leather Tote', 'sku' => 'TOTE-1'],
    ], ['sub_total' => 2000, 'grand_total' => 2000]);

    $payload = shiprocketParcelBuilder()->build($order);

    expect($payload)->toMatchArray([
        'order_id' => (string) $order->increment_id,
        'order_date' => $order->created_at->format('Y-m-d H:i'),
        'pickup_location' => 'Pune Warehouse',
        'billing_customer_name' => 'Bina',
        'billing_last_name' => 'Rao',
        'billing_address' => '12 MG Road',
        'billing_address_2' => 'Camp',
        'billing_city' => 'Pune',
        'billing_pincode' => 411001,
        'billing_state' => 'Maharashtra',
        'billing_country' => 'India',
        'billing_email' => 'asha@example.com',
        'billing_phone' => 9876543210,
        'shipping_is_billing' => false,
        'shipping_customer_name' => 'Asha',
        'shipping_state' => 'Maharashtra',
        'shipping_phone' => 9876543210,
        'payment_method' => 'Prepaid',
        'length' => 30.0,
        'breadth' => 25.0,
        'height' => 10.0,
        'weight' => 1.0,
    ])
        ->and($payload)->not->toHaveKey('channel_id')
        ->and($payload['order_items'])->toBe([[
            'name' => 'Leather Tote',
            'sku' => 'TOTE-1',
            'units' => 2,
            'selling_price' => 1000.0,
            'discount' => 0.0,
            'tax' => 0.0,
        ]]);
});

it('marks cash on delivery orders as COD', function () {
    $order = createShiprocketParcelOrder([['weight' => 500]], ['sub_total' => 1000, 'grand_total' => 1000], 'cashondelivery');

    expect(shiprocketParcelBuilder()->build($order)['payment_method'])->toBe('COD');
});

/**
 * What Shiprocket collects on a COD parcel: it does not add anything up itself, so the
 * `sub_total` sent (after discount, with tax) plus shipping must be the order's grand total.
 */
function expectShiprocketAmounts(array $payload, float $grandTotal): void
{
    $itemsSum = collect($payload['order_items'])->sum(fn ($item) => round($item['selling_price'] * $item['units'], 2));

    expect(round($payload['sub_total'] + $payload['shipping_charges'], 2))->toBe(round($grandTotal, 2))
        ->and(round($itemsSum, 2))->toBe($payload['sub_total'])
        ->and($payload['total_discount'])->toBe(0.0)
        ->and(collect($payload['order_items'])->pluck('discount')->unique()->all())->toBe([0.0]);
}

it('sends the amount after discount as the sub total, with shipping on top', function () {
    $order = createShiprocketParcelOrder([
        ['weight' => 500, 'qty_ordered' => 2, 'total' => 2000, 'discount_amount' => 150],
    ], [
        'sub_total' => 2000,
        'shipping_amount' => 100,
        'discount_amount' => 150,
        'grand_total' => 1950,
    ], 'cashondelivery');

    $payload = shiprocketParcelBuilder()->build($order);

    expect($payload['sub_total'])->toBe(1850.0)
        ->and($payload['shipping_charges'])->toBe(100.0)
        ->and($payload['order_items'][0])->toMatchArray(['units' => 2, 'selling_price' => 925.0, 'discount' => 0.0]);

    expectShiprocketAmounts($payload, (float) $order->grand_total);
});

it('carries tax in the net item price and still adds up to the grand total', function () {
    // 18% GST on the goods and on shipping, and a 200 discount.
    $order = createShiprocketParcelOrder([
        ['weight' => 500, 'qty_ordered' => 2, 'total' => 2000, 'tax_percent' => 18, 'tax_amount' => 360, 'discount_amount' => 200],
    ], [
        'sub_total' => 2000,
        'tax_amount' => 378,
        'shipping_amount' => 100,
        'shipping_tax_amount' => 18,
        'discount_amount' => 200,
        'grand_total' => 2278,
    ], 'cashondelivery');

    $payload = shiprocketParcelBuilder()->build($order);

    expect($payload['order_items'][0])->toMatchArray(['selling_price' => 1080.0, 'tax' => 18.0, 'discount' => 0.0])
        ->and($payload['sub_total'])->toBe(2160.0)
        ->and($payload['shipping_charges'])->toBe(118.0);

    expectShiprocketAmounts($payload, (float) $order->grand_total);
});

it('sends shipping after its discount when shipping is free by coupon', function () {
    // Core counts the shipping discount in the order's discount_amount too.
    $order = createShiprocketParcelOrder([
        ['weight' => 500, 'qty_ordered' => 1, 'total' => 1000],
    ], [
        'sub_total' => 1000,
        'shipping_amount' => 100,
        'shipping_discount_amount' => 100,
        'discount_amount' => 100,
        'grand_total' => 1000,
    ], 'cashondelivery');

    $payload = shiprocketParcelBuilder()->build($order);

    expect($payload['shipping_charges'])->toBe(0.0)
        ->and($payload['sub_total'])->toBe(1000.0)
        ->and($payload['order_items'][0]['selling_price'])->toBe(1000.0);

    expectShiprocketAmounts($payload, (float) $order->grand_total);
});

it('puts the paisa left over from rounding unit prices on the last line', function () {
    // 1000 over 3 units is 333.33 a unit, 999.99 in all: the missing paisa goes on the last line.
    $order = createShiprocketParcelOrder([
        ['weight' => 500, 'qty_ordered' => 3, 'total' => 1000, 'sku' => 'THIRDS'],
        ['weight' => 500, 'qty_ordered' => 1, 'total' => 500, 'sku' => 'LAST'],
    ], [
        'sub_total' => 1500,
        'shipping_amount' => 50,
        'grand_total' => 1550,
    ], 'cashondelivery');

    $payload = shiprocketParcelBuilder()->build($order);

    expect($payload['order_items'][0])->toMatchArray(['sku' => 'THIRDS', 'units' => 3, 'selling_price' => 333.33])
        ->and($payload['order_items'][1])->toMatchArray(['sku' => 'LAST', 'units' => 1, 'selling_price' => 500.01])
        ->and($payload['sub_total'])->toBe(1500.0);

    expectShiprocketAmounts($payload, (float) $order->grand_total);
});

it('adds up for orders with discount, shipping and tax', function (array $items, array $totals) {
    $order = createShiprocketParcelOrder($items, $totals, 'cashondelivery');

    expectShiprocketAmounts(shiprocketParcelBuilder()->build($order), (float) $order->grand_total);
})->with([
    'two lines, coupon split unevenly' => [[
        ['weight' => 500, 'qty_ordered' => 3, 'total' => 2997, 'tax_percent' => 12, 'tax_amount' => 359.64, 'discount_amount' => 100, 'sku' => 'A'],
        ['weight' => 500, 'qty_ordered' => 1, 'total' => 1499, 'tax_percent' => 12, 'tax_amount' => 179.88, 'discount_amount' => 50, 'sku' => 'B'],
    ], [
        'sub_total' => 4496, 'tax_amount' => 545.52, 'shipping_amount' => 49, 'shipping_tax_amount' => 6,
        'discount_amount' => 150, 'grand_total' => 4940.52,
    ]],
    'last line with several units' => [[
        ['weight' => 500, 'qty_ordered' => 1, 'total' => 799, 'discount_amount' => 33.33, 'sku' => 'A'],
        ['weight' => 500, 'qty_ordered' => 2, 'total' => 1598, 'discount_amount' => 66.67, 'sku' => 'B'],
    ], [
        'sub_total' => 2397, 'shipping_amount' => 0, 'discount_amount' => 100, 'grand_total' => 2297,
    ]],
]);

it('keeps the sub total exact even when no line has a single unit', function () {
    // 1000 over 3 units and 500 over 3: neither line can take one paisa per unit exactly.
    $order = createShiprocketParcelOrder([
        ['weight' => 500, 'qty_ordered' => 3, 'total' => 1000, 'sku' => 'A'],
        ['weight' => 500, 'qty_ordered' => 3, 'total' => 500, 'sku' => 'B'],
    ], ['sub_total' => 1500, 'grand_total' => 1500], 'cashondelivery');

    $payload = shiprocketParcelBuilder()->build($order);

    $itemsSum = collect($payload['order_items'])->sum(fn ($item) => $item['selling_price'] * $item['units']);

    // What Shiprocket collects is exact; the declared unit prices are off by under a paisa a unit.
    expect($payload['sub_total'])->toBe(1500.0)
        ->and(abs($itemsSum - 1500))->toBeLessThan(0.03);
});

it('declares only the units in a parcel that is not the whole order, after their discount', function () {
    $order = createShiprocketParcelOrder([
        ['weight' => 400, 'qty_ordered' => 3, 'qty_shipped' => 1, 'total' => 3000, 'discount_amount' => 300],
    ], ['sub_total' => 3000, 'shipping_amount' => 100, 'discount_amount' => 300, 'grand_total' => 2800], 'cashondelivery');

    $payload = shiprocketParcelBuilder()->build($order);

    expect($payload['shipping_charges'])->toBe(0.0)
        ->and($payload['sub_total'])->toBe(1800.0)
        ->and($payload['order_items'][0])->toMatchArray(['units' => 2, 'selling_price' => 900.0]);
});

it('sends pin codes and phone numbers as numbers and cuts the city at 30 characters', function () {
    $order = createShiprocketParcelOrder([['weight' => 500]], ['sub_total' => 1000, 'grand_total' => 1000]);

    $order->addresses()->update(['city' => 'Thiruvananthapuram Cantonment North']);

    $payload = shiprocketParcelBuilder()->build($order->fresh());

    expect($payload['billing_pincode'])->toBe(411001)
        ->and($payload['shipping_pincode'])->toBe(411001)
        ->and($payload['billing_phone'])->toBe(9876543210)
        ->and($payload['shipping_phone'])->toBe(9876543210)
        ->and($payload['billing_city'])->toBe('Thiruvananthapuram Cantonment')
        ->and($payload['shipping_city'])->toBe('Thiruvananthapuram Cantonment');
});

it('refuses a parcel size shiprocket would reject', function (string $field, ?string $value) {
    setShiprocketParcelConfig('sales.shipping.shiprocket.'.$field, $value);

    $order = createShiprocketParcelOrder([['weight' => 500]], ['sub_total' => 1000, 'grand_total' => 1000]);

    expect(fn () => shiprocketParcelBuilder()->build($order))
        ->toThrow(ShiprocketException::class, 'The parcel size in the Shiprocket settings must be more than 0.5 cm');
})->with([
    'length of 0.5' => ['length', '0.5'],
    'breadth of 0' => ['breadth', '0'],
    'height not set' => ['height', null],
]);

it('only accepts a parcel size above 0.5 cm in the settings', function (string $value, bool $valid) {
    foreach (['length', 'breadth', 'height'] as $field) {
        $rules = system_config()->getConfigField('sales.shipping.shiprocket.'.$field)['validation'];

        expect($rules)->toContain('numeric')->toContain('gt:0.5')
            ->and(validator(['active' => '1', 'value' => $value], ['value' => $rules])->passes())->toBe($valid);
    }
})->with([
    '0.5' => ['0.5', false],
    '0.51' => ['0.51', true],
    '10' => ['10', true],
    'text' => ['ten', false],
]);

it('falls back to the order email when the address has none', function () {
    $order = createShiprocketParcelOrder([['weight' => 500]], ['sub_total' => 1000, 'grand_total' => 1000]);

    $order->addresses()->update(['email' => null]);

    $payload = shiprocketParcelBuilder()->build($order->fresh());

    expect($payload['billing_email'])->toBe($order->customer_email)
        ->and($payload['shipping_email'])->toBe($order->customer_email);
});

it('offers grams as a store weight unit and keeps kgs the default', function () {
    $field = system_config()->getConfigField('general.general.locale_options.weight_unit');

    expect(array_column($field['options'], 'value'))->toBe(['g', 'lbs', 'kgs'])
        ->and($field['default'])->toBe('kgs');
});
