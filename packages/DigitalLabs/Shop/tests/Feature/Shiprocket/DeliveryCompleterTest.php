<?php

use DigitalLabs\Sales\Models\Invoice;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Shiprocket\Services\DeliveryCompleter;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/fixtures/delivery.php';

beforeEach(function () {
    Mail::fake();
});

it('invoices what is left as paid and completes a shipped cash on delivery order', function () {
    $order = shiprocketDeliveryOrder('cashondelivery');

    app(DeliveryCompleter::class)->complete($order);

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_COMPLETED)
        ->and($order->invoices)->toHaveCount(1)
        ->and($order->invoices->first()->state)->toBe(Invoice::STATUS_PAID)
        ->and((float) $order->invoices->first()->grand_total)->toBe(1000.0)
        ->and($order->items->first()->qty_invoiced)->toEqual(1);
});

it('marks the open invoice of an already invoiced order paid and completes it', function (string $state) {
    $order = shiprocketDeliveryOrder('cashondelivery', invoiceState: $state);

    app(DeliveryCompleter::class)->complete($order);

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_COMPLETED)
        ->and($order->invoices)->toHaveCount(1)
        ->and($order->invoices->first()->state)->toBe(Invoice::STATUS_PAID);
})->with([Invoice::STATUS_PENDING, Invoice::STATUS_PENDING_PAYMENT]);

it('does nothing more the second time', function () {
    $order = shiprocketDeliveryOrder('cashondelivery');

    app(DeliveryCompleter::class)->complete($order);
    app(DeliveryCompleter::class)->complete($order->fresh());

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_COMPLETED)
        ->and($order->invoices)->toHaveCount(1);
});

it('leaves refunded invoices alone', function () {
    $order = shiprocketDeliveryOrder('cashondelivery', invoiceState: Invoice::STATUS_REFUNDED);

    app(DeliveryCompleter::class)->complete($order);

    expect($order->fresh()->invoices->first()->state)->toBe(Invoice::STATUS_REFUNDED);
});
