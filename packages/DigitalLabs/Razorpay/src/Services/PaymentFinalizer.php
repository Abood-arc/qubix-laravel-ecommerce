<?php

namespace DigitalLabs\Razorpay\Services;

use DigitalLabs\Checkout\Models\Cart;
use DigitalLabs\Product\Repositories\ProductRepository;
use DigitalLabs\Razorpay\Exceptions\RazorpayException;
use DigitalLabs\Razorpay\Mail\PaymentAlertNotification;
use DigitalLabs\Razorpay\Mail\PaymentRefundedNotification;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Sales\Contracts\Order;
use DigitalLabs\Sales\Repositories\InvoiceRepository;
use DigitalLabs\Sales\Repositories\OrderRepository;
use DigitalLabs\Sales\Repositories\OrderTransactionRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PaymentFinalizer
{
    protected const LOCK_SECONDS = 60;

    protected const LOCK_WAIT_SECONDS = 10;

    /**
     * A refund started longer ago than this is taken to have died and is retried.
     */
    protected const STALE_REFUND_SECONDS = 300;

    public function __construct(
        protected RazorpayClient $client,
        protected OrderRepository $orderRepository,
        protected InvoiceRepository $invoiceRepository,
        protected OrderTransactionRepository $orderTransactionRepository,
        protected ProductRepository $productRepository,
    ) {}

    /**
     * Turn a Razorpay payment into exactly one order or one refund.
     *
     * Do not wrap this work in a DB transaction: OrderRepository commits in a
     * finally block, which would commit an outer transaction during a retry.
     */
    public function finalize(RazorpayPayment $payment, string $razorpayPaymentId): FinalizeResult
    {
        try {
            $result = Cache::lock('razorpay:cart:'.$payment->cart_id, self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn () => $this->settle($payment, $razorpayPaymentId));
        } catch (LockTimeoutException) {
            return FinalizeResult::pending();
        }

        if ($result->refundStarted) {
            $this->refund($payment);
        }

        return $result;
    }

    /**
     * The message that explains a refund to the customer.
     */
    public function customerMessage(RazorpayPayment $payment): string
    {
        $reason = $payment->refund_reason ?? 'order_failed';

        if ($reason === 'already_ordered' && $order = $this->findCartOrder($payment)) {
            return trans('razorpay::app.refund.already_ordered', [
                'order' => $order->increment_id,
                'method' => $order->payment?->method_title,
            ]);
        }

        return trans('razorpay::app.refund.'.$reason);
    }

    /**
     * Record a refund that Razorpay reported as failed after it had accepted it.
     */
    public function recordRefundFailure(string $razorpayPaymentId): void
    {
        $payment = RazorpayPayment::where('razorpay_payment_id', $razorpayPaymentId)->first();

        if (! $payment || $payment->status === RazorpayPayment::STATUS_REFUND_FAILED) {
            return;
        }

        if ($payment->status === RazorpayPayment::STATUS_REFUNDED) {
            $payment->update(['status' => RazorpayPayment::STATUS_REFUND_FAILED]);
        }

        $this->alertAdmin($payment, 'refund_failed');
    }

    /**
     * Do everything that must happen while holding the cart lock.
     */
    protected function settle(RazorpayPayment $payment, string $razorpayPaymentId): FinalizeResult
    {
        $payment->refresh();

        if ($payment->isRefundStarted()) {
            return $this->isRefundStale($payment)
                ? $this->resumeRefund($payment)
                : FinalizeResult::refunded();
        }

        if ($payment->order_id) {
            return $this->completeOrder($payment, $this->orderRepository->findOrFail($payment->order_id));
        }

        try {
            if (! $this->confirmPayment($payment, $razorpayPaymentId)) {
                return FinalizeResult::rejected();
            }
        } catch (RazorpayException $exception) {
            report($exception);

            return FinalizeResult::pending();
        }

        $payment->update([
            'razorpay_payment_id' => $razorpayPaymentId,
            'status' => RazorpayPayment::STATUS_PAID,
        ]);

        if ($existing = $this->findCartOrder($payment)) {
            return $this->settleAgainstExisting($payment, $existing);
        }

        try {
            if (! $this->hasStock($payment->snapshot)) {
                return $this->startRefund($payment, 'out_of_stock');
            }

            $order = $this->orderRepository->create($payment->snapshot);
        } catch (Throwable $exception) {
            if (! $exception instanceof UniqueConstraintViolationException) {
                report($exception);
            }

            $existing = $this->findCartOrder($payment);

            return $existing
                ? $this->settleAgainstExisting($payment, $existing)
                : $this->startRefund($payment, 'order_failed');
        }

        return $this->completeOrder($payment, $order);
    }

    /**
     * Ask Razorpay whether this payment is real, and capture it if needed.
     */
    protected function confirmPayment(RazorpayPayment $payment, string $razorpayPaymentId): bool
    {
        $remote = $this->client->fetchPayment($razorpayPaymentId);

        if (
            ($remote['order_id'] ?? null) !== $payment->razorpay_order_id
            || (int) ($remote['amount'] ?? 0) !== (int) $payment->amount
            || ($remote['currency'] ?? null) !== $payment->currency
        ) {
            return false;
        }

        if (($remote['status'] ?? null) === 'authorized') {
            $this->client->capturePayment($razorpayPaymentId, (int) $payment->amount, $payment->currency);

            return true;
        }

        return ($remote['status'] ?? null) === 'captured';
    }

    /**
     * Adopt this payment's own order, or refund because another order won.
     */
    protected function settleAgainstExisting(RazorpayPayment $payment, Order $existing): FinalizeResult
    {
        if (($existing->payment?->additional['razorpay_order_id'] ?? null) === $payment->razorpay_order_id) {
            return $this->completeOrder($payment, $existing);
        }

        return $this->startRefund($payment, 'already_ordered');
    }

    /**
     * Link the order, close the cart, then invoice it.
     *
     * A failure after the link never refunds: the order exists and is paid.
     * Only an order still pending is processed, so a repeat call repairs a
     * crash but never moves a shipped, closed or refunded order backwards.
     */
    protected function completeOrder(RazorpayPayment $payment, Order $order): FinalizeResult
    {
        $payment->update(['order_id' => $order->id]);

        Cart::query()->whereKey($payment->cart_id)->update(['is_active' => false]);

        try {
            if ($order->status === 'pending') {
                $this->processOrder($payment, $order);
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->alertAdmin($payment, 'invoice_failed');
        }

        return FinalizeResult::ordered($order->fresh());
    }

    /**
     * Mark a paid order as processing, invoice it and record the transaction.
     */
    protected function processOrder(RazorpayPayment $payment, Order $order): void
    {
        $this->orderRepository->update(['status' => 'processing'], $order->id);

        if ($order->canInvoice()) {
            $invoice = $this->invoiceRepository->create($this->invoiceData($order));

            $this->orderTransactionRepository->create([
                'transaction_id' => $payment->razorpay_payment_id,
                'status' => 'captured',
                'type' => 'payment',
                'amount' => $payment->amount / 100,
                'payment_method' => 'razorpay',
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'data' => json_encode(['razorpay_order_id' => $payment->razorpay_order_id]),
            ]);
        }
    }

    /**
     * Whether a refund was recorded long enough ago that its API call must have died.
     */
    protected function isRefundStale(RazorpayPayment $payment): bool
    {
        return $payment->status === RazorpayPayment::STATUS_REFUNDING
            && $payment->updated_at->lt(now()->subSeconds(self::STALE_REFUND_SECONDS));
    }

    /**
     * Hand a dead refund back to the caller, and look recent to any parallel retry.
     */
    protected function resumeRefund(RazorpayPayment $payment): FinalizeResult
    {
        $payment->touch();

        return FinalizeResult::refunded(refundStarted: true);
    }

    /**
     * Record that a refund is needed, while the lock is held.
     */
    protected function startRefund(RazorpayPayment $payment, string $reason): FinalizeResult
    {
        $payment->update([
            'status' => RazorpayPayment::STATUS_REFUNDING,
            'refund_reason' => $reason,
        ]);

        return FinalizeResult::refunded(refundStarted: true);
    }

    /**
     * Call the refund API after the lock has been released.
     */
    protected function refund(RazorpayPayment $payment): void
    {
        try {
            $refund = $this->client->refundPayment(
                $payment->razorpay_payment_id,
                (int) $payment->amount,
                'auto-'.$payment->razorpay_payment_id
            );
        } catch (Throwable $exception) {
            report($exception);

            $payment->update(['status' => RazorpayPayment::STATUS_REFUND_FAILED]);

            $this->alertAdmin($payment, 'refund_failed');

            return;
        }

        $payment->update([
            'status' => RazorpayPayment::STATUS_REFUNDED,
            'razorpay_refund_id' => $refund['id'] ?? null,
        ]);

        $snapshot = $payment->snapshot;

        $this->sendMail(new PaymentRefundedNotification(
            (string) ($snapshot['customer_email'] ?? $snapshot['billing_address']['email']),
            trim(($snapshot['customer_first_name'] ?? '').' '.($snapshot['customer_last_name'] ?? '')),
            $this->customerMessage($payment),
            core()->formatPrice($payment->amount / 100, $payment->currency),
        ));
    }

    /**
     * Whether every frozen line can still be fulfilled.
     */
    protected function hasStock(array $snapshot): bool
    {
        foreach ($snapshot['items'] as $item) {
            foreach ($item['children'] ?: [$item] as $line) {
                $product = $this->productRepository->find($line['product_id']);

                if (! $product || ! $product->getTypeInstance()->haveSufficientQuantity((int) $line['qty_ordered'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Find the order already placed from this payment's cart.
     */
    protected function findCartOrder(RazorpayPayment $payment): ?Order
    {
        return $this->orderRepository->findOneByField('cart_id', $payment->cart_id);
    }

    /**
     * Build invoice data for every item left to invoice.
     */
    protected function invoiceData(Order $order): array
    {
        $data = ['order_id' => $order->id];

        foreach ($order->items as $item) {
            $data['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $data;
    }

    /**
     * Tell the store admin that a payment needs attention.
     */
    protected function alertAdmin(RazorpayPayment $payment, string $problem): void
    {
        $this->sendMail(new PaymentAlertNotification(
            (string) ($payment->razorpay_payment_id ?? $payment->razorpay_order_id),
            core()->formatPrice($payment->amount / 100, $payment->currency),
            $problem,
        ));
    }

    /**
     * Send mail without letting a mail failure affect payment handling.
     */
    protected function sendMail(Mailable $mailable): void
    {
        try {
            Mail::queue($mailable);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
