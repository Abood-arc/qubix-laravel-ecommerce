<?php

use DigitalLabs\Shipping\Contracts\DeliveryCheck;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Payment\CashOnDelivery;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use DigitalLabs\Shiprocket\Services\ShiprocketClient;
use DigitalLabs\Shiprocket\Support\Settings;
use Illuminate\Support\Facades\Schema;
use Webkul\Faker\Helpers\Customer as CustomerFaker;

it('registers the package config, contracts and the cash on delivery subclass', function () {
    expect(config('shiprocket'))->toBeArray()
        ->and(app(ShiprocketApi::class))->toBeInstanceOf(ShiprocketClient::class)
        ->and(app(DeliveryCheck::class))->toBeInstanceOf(DeliveryCheck::class)
        ->and(config('payment_methods.cashondelivery.class'))->toBe(CashOnDelivery::class)
        ->and(Schema::hasTable('shiprocket_shipments'))->toBeTrue();

    config(['shiprocket.fake' => true]);

    expect(app(ShiprocketApi::class))->toBeInstanceOf(FakeShiprocketClient::class);
});

it('is off until it is switched on in admin and the api user is set in env', function () {
    expect(Settings::enabled())->toBeFalse();

    config(['shiprocket.email' => 'api@example.com', 'shiprocket.password' => 'secret']);

    // Credentials alone are not enough: it is still switched off in admin.
    expect(Settings::enabled())->toBeFalse();
});

it('never moves a parcel status backwards and leaves terminal states alone', function () {
    $order = createShiprocketTestOrder();

    $shipment = ShiprocketShipment::create(['order_id' => $order->id]);

    expect($shipment->advanceTo(ShiprocketShipment::STATUS_IN_TRANSIT))->toBeTrue()
        ->and($shipment->advanceTo(ShiprocketShipment::STATUS_PICKUP_SCHEDULED))->toBeFalse()
        ->and($shipment->fresh()->status)->toBe(ShiprocketShipment::STATUS_IN_TRANSIT)
        ->and($shipment->isPickedUp())->toBeTrue();

    $shipment->update(['status' => ShiprocketShipment::STATUS_CANCELLED]);

    expect($shipment->advanceTo(ShiprocketShipment::STATUS_DELIVERED))->toBeFalse()
        ->and($shipment->isPickedUp())->toBeFalse();
});

it('has every text in english', function (string $key) {
    expect(trans('shiprocket::app.'.$key))->not->toBe('shiprocket::app.'.$key);
})->with([
    'system.title', 'system.info', 'system.pickup-pin', 'system.max-weight',
    'checkout.not-deliverable', 'checkout.cod-unavailable',
    'admin.status.pickup_scheduled', 'admin.ship', 'admin.cannot-ship',
    'cancel.blocked', 'errors.invalid-pin', 'emails.track',
    'emails.alert.subject', 'emails.alert.booking_failed', 'emails.alert.rto',
    'emails.alert.too_heavy', 'emails.alert.too_light', 'emails.alert.cancel_failed',
]);

function createShiprocketTestOrder()
{
    $customer = (new CustomerFaker)->factory()->create();

    return DigitalLabs\Sales\Models\Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
    ]);
}
