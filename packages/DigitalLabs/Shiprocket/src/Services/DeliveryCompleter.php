<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Sales\Contracts\Order;
use DigitalLabs\Sales\Models\Invoice;
use DigitalLabs\Sales\Repositories\InvoiceRepository;
use DigitalLabs\Sales\Repositories\OrderRepository;

/**
 * Marks a delivered Cash on Delivery order as paid and completed: the courier has the money.
 *
 * Safe to run more than once: a second run finds nothing left to invoice and no open invoice.
 */
class DeliveryCompleter
{
    public function __construct(
        protected InvoiceRepository $invoiceRepository,
        protected OrderRepository $orderRepository,
    ) {}

    public function complete(Order $order): void
    {
        $order = $order->fresh();

        $this->markOpenInvoicesPaid($order);

        $this->invoiceTheRest($order);

        $this->orderRepository->updateOrderStatus($order->fresh());
    }

    /**
     * An invoice the admin created before delivery and left waiting for the payment.
     */
    protected function markOpenInvoicesPaid(Order $order): void
    {
        $order->invoices()
            ->whereIn('state', [Invoice::STATUS_PENDING, Invoice::STATUS_PENDING_PAYMENT])
            ->get()
            ->each(fn ($invoice) => $this->invoiceRepository->updateState($invoice, Invoice::STATUS_PAID));
    }

    protected function invoiceTheRest(Order $order): void
    {
        $items = [];

        foreach ($order->items as $item) {
            if ($item->qty_to_invoice > 0) {
                $items[$item->id] = $item->qty_to_invoice;
            }
        }

        if (! $items) {
            return;
        }

        $this->invoiceRepository->create([
            'order_id' => $order->id,
            'invoice' => ['items' => $items],
        ], Invoice::STATUS_PAID);
    }
}
