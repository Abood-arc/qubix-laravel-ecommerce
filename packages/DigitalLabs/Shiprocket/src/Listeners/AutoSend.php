<?php

namespace DigitalLabs\Shiprocket\Listeners;

use DigitalLabs\Sales\Contracts\Order;
use DigitalLabs\Sales\Models\Invoice;
use DigitalLabs\Sales\Models\OrderProxy;
use DigitalLabs\Shiprocket\Jobs\BookShipment;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Support\Settings;
use Throwable;

/**
 * Sends a paid-online order to Shiprocket once it is fully invoiced and every invoice is paid.
 *
 * Runs inside the invoice's DB transaction, so the job is queued only after the commit (a
 * worker must never see an invoice that is then rolled back), and it never throws: a
 * problem here must not undo the customer's payment record.
 */
class AutoSend
{
    /**
     * @param  \DigitalLabs\Sales\Contracts\Invoice  $invoice
     */
    public function handle($invoice): void
    {
        try {
            if (! Settings::enabled() || ! Settings::autoSend()) {
                return;
            }

            $order = OrderProxy::modelClass()::query()->find($invoice->order_id);

            if ($order && $this->shouldSend($order)) {
                BookShipment::dispatch($order->id)->afterCommit();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function shouldSend(Order $order): bool
    {
        return in_array($order->payment?->method, Settings::prepaidMethods(), true)
            && $order->items->every(fn ($item) => $item->qty_invoiced >= $item->qty_ordered)
            && $order->invoices()->exists()
            && $order->invoices()->where('state', '!=', Invoice::STATUS_PAID)->doesntExist()
            && $this->notSentYet($order);
    }

    /**
     * Never a second time: a booking that started, failed or was cancelled is the admin's to handle.
     */
    protected function notSentYet(Order $order): bool
    {
        $status = ShiprocketShipment::where('order_id', $order->id)->value('status');

        return $status === null || $status === ShiprocketShipment::STATUS_PENDING;
    }
}
