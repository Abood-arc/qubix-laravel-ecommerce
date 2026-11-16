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

it('tells the admin when the razorpay balance is too low for a refund', function () {
    [$order] = paidRazorpayOrder($this, 400);

    Http::fake(['api.razorpay.com/v1/balance' => Http::response(['balance' => 7200, 'refund_credits' => 0])]);

    try {
        app(RefundRepository::class)->create(razorpayRefundData($order));

        $this->fail('The refund should have been refused.');
    } catch (RazorpayRefundFailedException $exception) {
        $exception->render();
    }

    expect(session('error'))->toBe(trans('razorpay::app.errors.admin-refund-low-balance', [
        'balance' => core()->formatPrice(72, 'INR'),
        'amount' => core()->formatPrice($order->items->first()->total, 'INR'),
    ]))
        ->and($order->refunds()->count())->toBe(0);
});

it('tells the admin to check the dashboard when razorpay does not confirm a refund', function () {
    [$order] = paidRazorpayOrder($this, 500);

    try {
        app(RefundRepository::class)->create(razorpayRefundData($order));

        $this->fail('The refund should not have been recorded.');
    } catch (RazorpayRefundFailedException $exception) {
        $exception->render();
    }

    expect(session('error'))->toBe(trans('razorpay::app.errors.admin-refund-unknown'))
        ->and($order->refunds()->count())->toBe(0);

    // An unknown outcome is not a refusal, so the balance is not asked about.
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/balance'));
});

it('keeps the general message when the balance covers the refund', function () {
    [$order] = paidRazorpayOrder($this, 400);

    Http::fake(['api.razorpay.com/v1/balance' => Http::response(['balance' => 100000000])]);

    try {
        app(RefundRepository::class)->create(razorpayRefundData($order));
    } catch (RazorpayRefundFailedException $exception) {
        $exception->render();
    }

    expect(session('error'))->toBe(trans('razorpay::app.errors.admin-refund-failed'));
});

it('keeps the general message when the balance cannot be read', function () {
    [$order] = paidRazorpayOrder($this, 400);

    Http::fake(['api.razorpay.com/v1/balance' => Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR']], 400)]);

    try {
        app(RefundRepository::class)->create(razorpayRefundData($order));
    } catch (RazorpayRefundFailedException $exception) {
        $exception->render();
    }

    expect(session('error'))->toBe(trans('razorpay::app.errors.admin-refund-failed'));
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

function sentRazorpayRefunds()
{
    return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/refund'))
        ->map(fn (array $pair) => $pair[0])
        ->values();
}

it('sends two equal partial refunds with different keys', function () {
    [$order] = paidRazorpayOrder($this);

    $data = ['order_id' => $order->id, 'refund' => ['items' => [], 'shipping' => 0, 'adjustment_refund' => 1, 'adjustment_fee' => 0]];

    app(RefundRepository::class)->create($data);

    app(RefundRepository::class)->create($data);

    $refunds = sentRazorpayRefunds();

    expect($refunds)->toHaveCount(2)
        ->and($refunds->map(fn (Request $request) => $request->header('X-Refund-Idempotency')[0])->all())
        ->toBe(['admin-'.$order->id.'-100', 'admin-'.$order->id.'-200'])
        ->and($refunds->map(fn (Request $request) => $request['amount'])->all())->toBe([100, 100]);
});

it('never asks for more than was captured', function () {
    [$order, $payment] = paidRazorpayOrder($this);

    $payment->update(['amount' => (int) round($order->grand_total * 100) - 1]);

    $refund = app(RefundRepository::class)->create(razorpayRefundData($order));

    $paise = (int) round($refund->grand_total * 100);

    expect($paise)->toBeGreaterThan($payment->amount)
        ->and(sentRazorpayRefunds()->first()['amount'])->toBe($payment->amount);
});

it('never asks for more than what is left after earlier refunds', function () {
    [$order, $payment] = paidRazorpayOrder($this);

    app(RefundRepository::class)->create([
        'order_id' => $order->id,
        'refund' => ['items' => [], 'shipping' => 0, 'adjustment_refund' => 1, 'adjustment_fee' => 0],
    ]);

    $refund = app(RefundRepository::class)->create(razorpayRefundData($order->fresh()));

    $paise = (int) round($refund->grand_total * 100);

    $left = $payment->amount - 100;

    expect($paise)->toBeGreaterThan($left)
        ->and(sentRazorpayRefunds()->last()['amount'])->toBe($left);
});
