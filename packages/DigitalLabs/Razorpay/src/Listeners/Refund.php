<?php

namespace DigitalLabs\Razorpay\Listeners;

use DigitalLabs\Razorpay\Exceptions\RazorpayException;
use DigitalLabs\Razorpay\Exceptions\RazorpayRefundFailedException;
use DigitalLabs\Razorpay\Exceptions\RazorpayUnknownOutcomeException;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Razorpay\Services\RazorpayClient;
use Throwable;

class Refund
{
    public function __construct(protected RazorpayClient $client) {}

    /**
     * Send an admin refund to Razorpay. Throwing rolls the refund back.
     *
     * Each refund is rounded to paise on its own, so the amount is capped at
     * what is left of the captured payment.
     *
     * @param  \DigitalLabs\Sales\Contracts\Refund  $refund
     */
    public function afterCreated($refund): void
    {
        $order = $refund->order->fresh();

        if ($order->payment->method !== 'razorpay') {
            return;
        }

        $payment = RazorpayPayment::where('order_id', $order->id)->first();

        if (! $payment?->razorpay_payment_id) {
            throw new RazorpayRefundFailedException('No Razorpay payment found for order '.$order->id);
        }

        $alreadyRefunded = (int) round(($order->grand_total_refunded - $refund->grand_total) * 100);

        $amount = min((int) round($refund->grand_total * 100), (int) $payment->amount - $alreadyRefunded);

        try {
            $this->client->refundPayment(
                $payment->razorpay_payment_id,
                $amount,
                'admin-'.$order->id.'-'.(int) round($order->grand_total_refunded * 100)
            );
        } catch (RazorpayUnknownOutcomeException $exception) {
            // Razorpay may have sent the money even though it did not confirm. A retry with the
            // same amount reuses the same idempotency key, so Razorpay would not pay twice.
            throw RazorpayRefundFailedException::unknownOutcome($exception);
        } catch (RazorpayException $exception) {
            $available = $this->refundableBalance();

            if ($available !== null && $available < $amount) {
                throw RazorpayRefundFailedException::lowBalance($available, $amount, $exception);
            }

            throw new RazorpayRefundFailedException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * What the Razorpay account can pay refunds from, in paise, or null when unknown.
     *
     * Asked only after Razorpay refuses a refund, so it never blocks one Razorpay would take.
     */
    protected function refundableBalance(): ?int
    {
        try {
            $balance = $this->client->fetchBalance();
        } catch (Throwable) {
            return null;
        }

        if (! isset($balance['balance'])) {
            return null;
        }

        return (int) $balance['balance'] + (int) ($balance['refund_credits'] ?? 0);
    }
}
