<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Sales\Contracts\Invoice as InvoiceContract;
use DigitalLabs\Sales\Contracts\Order;
use DigitalLabs\Sales\Models\Invoice;
use DigitalLabs\Sales\Repositories\InvoiceRepository;
use DigitalLabs\Sales\Repositories\OrderRepository;
use DigitalLabs\Sales\Repositories\OrderTransactionRepository;
use Illuminate\Support\Collection;

/**
 * Marks a delivered Cash on Delivery order as paid and completed: the courier has the money.
 * The cash is recorded as the order's payment (a transaction per invoice it pays).
 *
 * Safe to run more than once: a second run finds nothing left to invoice, no open invoice,
 * and no invoice amount without its transaction.
 */
class DeliveryCompleter
{
    protected const COD_METHOD = 'cashondelivery';

    public function __construct(
        protected InvoiceRepository $invoiceRepository,
        protected OrderRepository $orderRepository,
        protected OrderTransactionRepository $orderTransactionRepository,
    ) {}

    public function complete(Order $order): void
    {
        $order = $order->fresh();

        $paid = $this->markOpenInvoicesPaid($order);

        if ($invoice = $this->invoiceTheRest($order)) {
            $paid->push($invoice);
        }

        $paid->each(fn ($invoice) => $this->recordPayment($order, $invoice));

        $this->orderRepository->updateOrderStatus($order->fresh());
    }

    /**
     * An invoice the admin created before delivery and left waiting for the payment.
     *
     * @return Collection<int, InvoiceContract> the invoices marked paid
     */
    protected function markOpenInvoicesPaid(Order $order): Collection
    {
        return $order->invoices()
            ->whereIn('state', [Invoice::STATUS_PENDING, Invoice::STATUS_PENDING_PAYMENT])
            ->get()
            ->each(fn ($invoice) => $this->invoiceRepository->updateState($invoice, Invoice::STATUS_PAID));
    }

    protected function invoiceTheRest(Order $order): ?InvoiceContract
    {
        $items = [];

        foreach ($order->items as $item) {
            if ($item->qty_to_invoice > 0) {
                $items[$item->id] = $item->qty_to_invoice;
            }
        }

        if (! $items) {
            return null;
        }

        return $this->invoiceRepository->create([
            'order_id' => $order->id,
            'invoice' => ['items' => $items],
        ], Invoice::STATUS_PAID);
    }

    /**
     * Record the cash collected for an invoice: what its transactions do not cover yet (an
     * admin may have recorded part of it by hand), in the base currency like the admin's own
     * transactions.
     */
    protected function recordPayment(Order $order, InvoiceContract $invoice): void
    {
        $recorded = (float) $this->orderTransactionRepository->where('invoice_id', $invoice->id)->sum('amount');

        $amount = round((float) $invoice->base_grand_total - $recorded, 2);

        if ($amount <= 0) {
            return;
        }

        $this->orderTransactionRepository->create([
            'transaction_id' => 'cod-'.$order->increment_id.'-'.$invoice->id,
            'status' => 'paid',
            'type' => self::COD_METHOD,
            'amount' => $amount,
            'payment_method' => self::COD_METHOD,
            'order_id' => $order->id,
            'invoice_id' => $invoice->id,
            'data' => json_encode(['collected_by' => 'shiprocket']),
        ]);
    }
}
