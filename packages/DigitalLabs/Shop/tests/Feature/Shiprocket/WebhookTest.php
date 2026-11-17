<?php

use DigitalLabs\Sales\Models\Invoice;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Shiprocket\Mail\AdminAlert;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Services\DeliveryCompleter;
use DigitalLabs\Shiprocket\Services\StatusMapper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\postJson;

require_once __DIR__.'/fixtures/delivery.php';

function sendParcelStatus(array $payload, ?string $token = 'hook-token')
{
    return postJson(route('shiprocket.webhook'), $payload, $token === null ? [] : ['x-api-key' => $token]);
}

function parcelStatusPayload(ShiprocketShipment $row, string $status): array
{
    return [
        'awb' => $row->awb_code,
        'courier_name' => 'Delhivery Surface',
        'current_status' => $status,
        'current_status_id' => 20,
        'shipment_status' => $status,
        'order_id' => (string) $row->order->increment_id,
        'sr_order_id' => $row->shiprocket_order_id,
    ];
}

beforeEach(function () {
    Mail::fake();

    enableShiprocketForDelivery();
});

it('maps shiprocket statuses to parcel statuses', function (string $status, ?string $expected) {
    expect(app(StatusMapper::class)->map(['current_status' => $status]))->toBe($expected);
})->with([
    ['PICKED UP', ShiprocketShipment::STATUS_IN_TRANSIT],
    ['Shipped', ShiprocketShipment::STATUS_IN_TRANSIT],
    ['IN TRANSIT', ShiprocketShipment::STATUS_IN_TRANSIT],
    ['REACHED AT DESTINATION HUB', ShiprocketShipment::STATUS_IN_TRANSIT],
    ['OUT FOR DELIVERY', ShiprocketShipment::STATUS_OUT_FOR_DELIVERY],
    ['Delivered', ShiprocketShipment::STATUS_DELIVERED],
    ['RTO INITIATED', ShiprocketShipment::STATUS_RTO],
    ['RTO IN TRANSIT', ShiprocketShipment::STATUS_RTO],
    ['RTO DELIVERED', ShiprocketShipment::STATUS_RTO],
    ['RTO_OFD', ShiprocketShipment::STATUS_RTO],
    ['CANCELED', ShiprocketShipment::STATUS_CANCELLED],
    ['CANCELLED', ShiprocketShipment::STATUS_CANCELLED],
    [' in transit ', ShiprocketShipment::STATUS_IN_TRANSIT],
    ['UNDELIVERED', null],
    ['', null],
]);

it('maps nothing when the payload has no status', function () {
    expect(app(StatusMapper::class)->map([]))->toBeNull()
        ->and(app(StatusMapper::class)->map(['current_status' => ['IN TRANSIT']]))->toBeNull();
});

it('refuses a call without the right token and logs it as an error', function (?string $configured, ?string $sent) {
    config(['shiprocket.webhook_token' => $configured]);

    $row = shiprocketDeliveryRow(shiprocketDeliveryOrder());

    Log::spy();

    sendParcelStatus(parcelStatusPayload($row, 'IN TRANSIT'), $sent)->assertUnauthorized();

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message, $context) => $message === 'Shiprocket webhook refused: token mismatch.' && array_key_exists('ip', $context))
        ->once();

    expect($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->and($row->fresh()->last_event)->toBeNull();
})->with([
    'missing header' => ['hook-token', null],
    'wrong token' => ['hook-token', 'not-the-token'],
    'blank configured token, blank sent' => ['', ''],
    'unset configured token' => [null, 'anything'],
]);

it('moves the parcel on and records the last event', function (string $status, string $expected) {
    $row = shiprocketDeliveryRow(shiprocketDeliveryOrder('razorpay', invoiceState: Invoice::STATUS_PAID));

    sendParcelStatus(parcelStatusPayload($row, $status))->assertOk()->assertJson(['ok' => true]);

    expect($row->fresh()->status)->toBe($expected)
        ->and($row->fresh()->last_event)->toBe($status);
})->with([
    ['PICKED UP', ShiprocketShipment::STATUS_IN_TRANSIT],
    ['IN TRANSIT', ShiprocketShipment::STATUS_IN_TRANSIT],
    ['OUT FOR DELIVERY', ShiprocketShipment::STATUS_OUT_FOR_DELIVERY],
    ['DELIVERED', ShiprocketShipment::STATUS_DELIVERED],
    ['RTO INITIATED', ShiprocketShipment::STATUS_RTO],
    ['CANCELED', ShiprocketShipment::STATUS_CANCELLED],
]);

it('only records an unknown status', function () {
    $row = shiprocketDeliveryRow(shiprocketDeliveryOrder());

    sendParcelStatus(parcelStatusPayload($row, 'UNDELIVERED'))->assertOk();

    expect($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->and($row->fresh()->last_event)->toBe('UNDELIVERED');
});

it('never moves a parcel backwards when updates arrive out of order', function () {
    $row = shiprocketDeliveryRow(shiprocketDeliveryOrder('razorpay', invoiceState: Invoice::STATUS_PAID));

    sendParcelStatus(parcelStatusPayload($row, 'DELIVERED'))->assertOk();

    sendParcelStatus(parcelStatusPayload($row, 'IN TRANSIT'))->assertOk();

    expect($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_DELIVERED)
        ->and($row->fresh()->last_event)->toBe('IN TRANSIT');
});

it('answers 200 for a parcel it does not know', function () {
    sendParcelStatus(['awb' => 'NOPE123', 'current_status' => 'DELIVERED'])->assertOk();

    sendParcelStatus([])->assertOk();
});

it('completes a delivered cash on delivery order: invoiced, paid and completed', function () {
    $order = shiprocketDeliveryOrder('cashondelivery');

    $row = shiprocketDeliveryRow($order);

    sendParcelStatus(parcelStatusPayload($row, 'DELIVERED'))->assertOk();

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_COMPLETED)
        ->and($order->invoices)->toHaveCount(1)
        ->and($order->invoices->first()->state)->toBe(Invoice::STATUS_PAID);
});

it('completes a delivered cash on delivery order only once', function () {
    $order = shiprocketDeliveryOrder('cashondelivery');

    $row = shiprocketDeliveryRow($order);

    $completer = Mockery::mock(DeliveryCompleter::class);
    $completer->shouldReceive('complete')->once();
    app()->instance(DeliveryCompleter::class, $completer);

    sendParcelStatus(parcelStatusPayload($row, 'DELIVERED'))->assertOk();
    sendParcelStatus(parcelStatusPayload($row, 'DELIVERED'))->assertOk();
});

it('leaves a delivered prepaid order alone', function () {
    $order = shiprocketDeliveryOrder('razorpay', invoiceState: Invoice::STATUS_PAID);

    $row = shiprocketDeliveryRow($order);

    $completer = Mockery::mock(DeliveryCompleter::class);
    $completer->shouldNotReceive('complete');
    app()->instance(DeliveryCompleter::class, $completer);

    sendParcelStatus(parcelStatusPayload($row, 'DELIVERED'))->assertOk();

    expect($order->fresh()->invoices)->toHaveCount(1);
});

it('emails the admin once when the parcel is returned', function () {
    $order = shiprocketDeliveryOrder();

    $row = shiprocketDeliveryRow($order);

    sendParcelStatus(parcelStatusPayload($row, 'RTO INITIATED'))->assertOk();
    sendParcelStatus(parcelStatusPayload($row, 'RTO IN TRANSIT'))->assertOk();
    sendParcelStatus(parcelStatusPayload($row, 'RTO DELIVERED'))->assertOk();

    Mail::assertQueued(AdminAlert::class, 1);

    Mail::assertQueued(AdminAlert::class, fn (AdminAlert $mail) => $mail->problem === 'rto'
        && $mail->order->id === $order->id
        && $mail->details === ['awb' => $row->awb_code]);

    expect($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_RTO)
        ->and($order->fresh()->invoices)->toHaveCount(0);
});

it('leaves a cancelled booking alone', function () {
    $row = shiprocketDeliveryRow(shiprocketDeliveryOrder(), ShiprocketShipment::STATUS_CANCELLED);

    sendParcelStatus(parcelStatusPayload($row, 'DELIVERED'))->assertOk();

    expect($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_CANCELLED)
        ->and($row->order->fresh()->invoices)->toHaveCount(0);
});

it('follows a parcel whose booking failed after the courier was booked', function () {
    $order = shiprocketDeliveryOrder('cashondelivery');

    $row = shiprocketDeliveryRow($order, ShiprocketShipment::STATUS_FAILED);

    sendParcelStatus(parcelStatusPayload($row, 'IN TRANSIT'))->assertOk();

    expect($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_IN_TRANSIT);

    sendParcelStatus(parcelStatusPayload($row, 'DELIVERED'))->assertOk();

    expect($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_DELIVERED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_COMPLETED);
});

it('records a failed booking without a courier, but never moves it', function () {
    $row = shiprocketDeliveryRow(shiprocketDeliveryOrder(), ShiprocketShipment::STATUS_FAILED);

    // A row without an AWB is never found by a webhook; the model refuses it all the same.
    $row->update(['awb_code' => null]);

    expect($row->fresh()->advanceTo(ShiprocketShipment::STATUS_IN_TRANSIT))->toBeFalse()
        ->and($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_FAILED);
});

it('starts a new booking generation when shiprocket reports a cancel', function () {
    $row = shiprocketDeliveryRow(shiprocketDeliveryOrder());

    sendParcelStatus(parcelStatusPayload($row, 'CANCELED'))->assertOk();

    expect($row->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_CANCELLED)
        ->generation->toBe(1);
});
