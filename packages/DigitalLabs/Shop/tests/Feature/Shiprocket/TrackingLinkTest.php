<?php

use DigitalLabs\Shiprocket\Support\Settings;
use DigitalLabs\Shop\Mail\Order\ShippedNotification;

use function Pest\Laravel\get;

require_once __DIR__.'/fixtures/delivery.php';

it('shows a tracking link in the shipment email for a shiprocket parcel', function () {
    $order = shiprocketDeliveryOrder('razorpay', shipped: false);

    $shipment = shiprocketDeliveryStoreShipment($order, 'Shiprocket – Delhivery', 'AWB 77/1');

    $html = (new ShippedNotification($shipment))->render();

    expect($html)->toContain(e(Settings::trackingUrl('AWB 77/1')))
        ->and($html)->toContain(trans('shiprocket::app.emails.track'));
});

it('shows no tracking link in the shipment email for another carrier', function () {
    $order = shiprocketDeliveryOrder('razorpay', shipped: false);

    $shipment = shiprocketDeliveryStoreShipment($order, 'Blue Dart', '12345');

    $html = (new ShippedNotification($shipment))->render();

    expect($html)->not->toContain(Settings::trackingUrl('12345'))
        ->and($html)->not->toContain(trans('shiprocket::app.emails.track'));
});

it('shows a tracking link on the customer order page only for a shiprocket parcel', function () {
    $order = shiprocketDeliveryOrder('razorpay', shipped: false);

    shiprocketDeliveryStoreShipment($order, 'Shiprocket – Xpressbees', 'SR111');
    shiprocketDeliveryStoreShipment($order, 'India Post', 'IP222');

    $this->loginAsCustomer($order->customer);

    get(route('shop.customers.account.orders.view', $order->id))
        ->assertOk()
        ->assertSee(Settings::trackingUrl('SR111'), false)
        ->assertSeeText(trans('shiprocket::app.emails.track'))
        ->assertDontSee(Settings::trackingUrl('IP222'), false);
});
