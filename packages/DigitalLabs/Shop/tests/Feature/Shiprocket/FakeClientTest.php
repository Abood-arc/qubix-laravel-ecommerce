<?php

use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['shiprocket.fake' => true]);

    Http::preventStrayRequests();
});

it('is the api bound when shiprocket is faked', function () {
    expect(app(ShiprocketApi::class))->toBeInstanceOf(FakeShiprocketClient::class);
});

it('decides serviceability from the delivery pin', function (string $pin, array $expected) {
    expect(app(ShiprocketApi::class)->checkServiceability('400001', $pin, 0.5, true))->toBe($expected);
})->with([
    'starts with 9: not deliverable' => ['900001', ['deliverable' => false, 'cod' => false]],
    'ends with 0: no cash on delivery' => ['560010', ['deliverable' => true, 'cod' => false]],
    'anything else: both' => ['560001', ['deliverable' => true, 'cod' => true]],
]);

it('creates orders with new ids and finds them by store order number', function () {
    $api = app(ShiprocketApi::class);

    $first = $api->createOrder(['order_id' => 'fake-1001']);
    $second = $api->createOrder(['order_id' => 'fake-1002']);

    expect($first['order_id'])->toBeInt()
        ->and($first['shipment_id'])->toBeInt()
        ->and($second['order_id'])->not->toBe($first['order_id'])
        ->and($second['shipment_id'])->not->toBe($first['shipment_id'])
        ->and($api->findOrderByChannelOrderId('fake-1001'))->toBe($first)
        ->and(app(ShiprocketApi::class)->findOrderByChannelOrderId('fake-1002'))->toBe($second)
        ->and($api->findOrderByChannelOrderId('fake-missing'))->toBeNull();
});

it('assigns the same awb, label and pickup for a shipment every time', function () {
    $api = app(ShiprocketApi::class);

    $ids = $api->createOrder(['order_id' => 'fake-2001']);
    $shipmentId = $ids['shipment_id'];

    $awb = ['awb_code' => 'FAKE'.$shipmentId, 'courier_name' => 'Fake Express'];

    expect($api->assignAwb($shipmentId))->toBe($awb)
        ->and($api->assignAwb($shipmentId))->toBe($awb)
        ->and($api->generateLabel($shipmentId))->toBe('https://example.test/labels/'.$shipmentId.'.pdf');

    $api->requestPickup($shipmentId);
    $api->requestPickup($shipmentId);
});

it('marks an order cancelled', function () {
    $api = app(FakeShiprocketClient::class);

    $ids = $api->createOrder(['order_id' => 'fake-3001']);

    expect($api->isCancelled($ids['order_id']))->toBeFalse();

    $api->cancelOrder($ids['order_id']);

    expect($api->isCancelled($ids['order_id']))->toBeTrue()
        ->and($api->findOrderByChannelOrderId('fake-3001'))->toBe($ids);
});
