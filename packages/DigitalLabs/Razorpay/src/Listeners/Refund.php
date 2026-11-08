<?php

namespace DigitalLabs\Razorpay\Listeners;

use DigitalLabs\Razorpay\Exceptions\RazorpayException;
use DigitalLabs\Razorpay\Exceptions\RazorpayRefundFailedException;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Razorpay\Services\RazorpayClient;

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

        try {
            $this->client->refundPayment(
                $payment->razorpay_payment_id,
                min((int) round($refund->grand_total * 100), (int) $payment->amount - $alreadyRefunded),
                'admin-'.$order->id.'-'.(int) round($order->grand_total_refunded * 100)
            );
        } catch (RazorpayException $exception) {
            throw new RazorpayRefundFailedException($exception->getMessage(), 0, $exception);
        }
    }
}
