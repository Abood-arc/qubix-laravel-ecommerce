<?php

use DigitalLabs\Sales\Models\Invoice;
use DigitalLabs\Sales\Models\Order;

function openInvoiceTestInvoice(Order $order, string $state): Invoice
{
    return Invoice::factory()->create([
        'order_id' => $order->id,
        'state' => $state,
        'total_qty' => 1,
        'base_currency_code' => $order->base_currency_code,
        'channel_currency_code' => $order->channel_currency_code,
        'order_currency_code' => $order->order_currency_code,
        'email_sent' => 1,
        'discount_amount' => 0,
        'base_discount_amount' => 0,
        'sub_total' => 10,
        'base_sub_total' => 10,
        'grand_total' => 10,
        'base_grand_total' => 10,
    ]);
}

it('ignores the pending invoices of other orders', function (string $state) {
    $order = Order::factory()->create();

    openInvoiceTestInvoice($order, Invoice::STATUS_PAID);

    openInvoiceTestInvoice(Order::factory()->create(), $state);

    expect($order->hasOpenInvoice())->toBeFalse();
})->with([Invoice::STATUS_PENDING, Invoice::STATUS_PENDING_PAYMENT]);

it('sees its own pending invoice', function (string $state) {
    $order = Order::factory()->create();

    openInvoiceTestInvoice($order, Invoice::STATUS_PAID);

    openInvoiceTestInvoice($order, $state);

    expect($order->hasOpenInvoice())->toBeTrue();
})->with([Invoice::STATUS_PENDING, Invoice::STATUS_PENDING_PAYMENT]);

it('has no open invoice when every invoice is paid', function () {
    $order = Order::factory()->create();

    openInvoiceTestInvoice($order, Invoice::STATUS_PAID);

    expect($order->hasOpenInvoice())->toBeFalse();
});
