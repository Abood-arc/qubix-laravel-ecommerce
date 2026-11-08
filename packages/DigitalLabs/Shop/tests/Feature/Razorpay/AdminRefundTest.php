<?php

use DigitalLabs\Razorpay\Exceptions\RazorpayRefundFailedException;
use DigitalLabs\Razorpay\Services\PaymentFinalizer;
use DigitalLabs\Sales\Repositories\RefundRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->configureRazorpay();

    Mail::fake();
});

function paidRazorpayOrder($test, int $refundStatus = 200)
{
    $payment = $test->createRazorpayPayment($test->createRazorpayCart());

    $test->fakeRazorpayApi($payment, refundStatus: $refundStatus);

    return [app(PaymentFinalizer::class)->finalize($payment, 'pay_test')->order, $payment];
}

function razorpayRefundData($order): array
{
    return [
        'order_id' => $order->id,
        'refund' => [
            'items' => [$order->items->first()->id => 1],
            'shipping' => 0,
            'adjustment_refund' => 0,
            'adjustment_fee' => 0,
        ],
    ];
}

it('refunds the amount in paise with a repeat-safe key', function () {
    [$order] = paidRazorpayOrder($this);

    $refund = app(RefundRepository::class)->create(razorpayRefundData($order));

    $paise = (int) round($refund->grand_total * 100);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payments/pay_test/refund')
        && $request['amount'] === $paise
        && $request->hasHeader('X-Refund-Idempotency', 'admin-'.$order->id.'-'.$paise));
});

it('rolls the refund back when razorpay fails', function () {
    [$order] = paidRazorpayOrder($this, 400);

    expect(fn () => app(RefundRepository::class)->create(razorpayRefundData($order)))
        ->toThrow(RazorpayRefundFailedException::class);

    expect($order->refunds()->count())->toBe(0);
});

it('does not call razorpay for other payment methods', function () {
    [$order] = paidRazorpayOrder($this);

    $order->payment->update(['method' => 'cashondelivery']);

    app(RefundRepository::class)->create(razorpayRefundData($order->fresh()));

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/refund'));
});

it('shows the admin a message instead of a 500 page', function () {
    $response = (new RazorpayRefundFailedException('failed'))->render();

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and(session('error'))->toBe(trans('razorpay::app.errors.admin-refund-failed'));
});
