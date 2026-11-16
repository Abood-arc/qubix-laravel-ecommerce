<?php

use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Razorpay\Services\FinalizeResult;
use DigitalLabs\Razorpay\Services\PaymentFinalizer;
use DigitalLabs\Sales\Models\Order;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\call;
use function Pest\Laravel\postJson;

function webhookBody(string $razorpayOrderId, string $event = 'order.paid'): string
{
    return json_encode([
        'event' => $event,
        'payload' => [
            'payment' => ['entity' => ['id' => 'pay_test', 'order_id' => $razorpayOrderId]],
            'order' => ['entity' => ['id' => $razorpayOrderId]],
        ],
    ]);
}

function sendWebhook(string $body, ?string $signature = null)
{
    return call('POST', route('razorpay.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, 'test_webhook_secret'),
    ], $body);
}

beforeEach(function () {
    $this->configureRazorpay();

    Mail::fake();
});

it('creates the order for a guest', function () {
    $cart = $this->createRazorpayCart();

    $payment = $this->createRazorpayPayment($cart);

    $this->fakeRazorpayApi($payment);

    sendWebhook(webhookBody($payment->razorpay_order_id))->assertOk();

    expect(Order::where('cart_id', $cart->id)->count())->toBe(1);
});

it('creates the order for a customer', function () {
    $customer = $this->loginAsCustomer();

    $cart = $this->createRazorpayCart($customer);

    $payment = $this->createRazorpayPayment($cart);

    $this->fakeRazorpayApi($payment);

    sendWebhook(webhookBody($payment->razorpay_order_id))->assertOk();

    expect(Order::where('cart_id', $cart->id)->firstOrFail()->customer_id)->toBe($customer->id);
});

it('produces one order for callback then webhook, and the reverse', function (bool $callbackFirst) {
    $cart = $this->createRazorpayCart();

    $payment = $this->createRazorpayPayment($cart);

    $this->fakeRazorpayApi($payment);

    $callback = fn () => postJson(route('razorpay.callback'), [
        'razorpay_order_id' => $payment->razorpay_order_id,
        'razorpay_payment_id' => 'pay_test',
        'razorpay_signature' => hash_hmac('sha256', $payment->razorpay_order_id.'|pay_test', 'test_key_secret'),
    ])->assertOk();

    $webhook = fn () => sendWebhook(webhookBody($payment->razorpay_order_id))->assertOk();

    $callbackFirst ? [$callback(), $webhook()] : [$webhook(), $callback()];

    expect(Order::where('cart_id', $cart->id)->count())->toBe(1);
})->with(['callback first' => [true], 'webhook first' => [false]]);

it('creates no order and no second refund for a late webhook after a refund', function () {
    $cart = $this->createRazorpayCart();

    $payment = $this->createRazorpayPayment($cart, [
        'status' => RazorpayPayment::STATUS_REFUNDED,
        'refund_reason' => 'out_of_stock',
        'razorpay_payment_id' => 'pay_test',
    ]);

    $this->fakeRazorpayApi($payment);

    sendWebhook(webhookBody($payment->razorpay_order_id))->assertOk();

    expect(Order::where('cart_id', $cart->id)->exists())->toBeFalse();

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/refund'));
});

it('rejects a bad signature and logs it, so a wrong webhook secret is noticed', function () {
    Log::spy();

    sendWebhook(webhookBody('order_any'), 'bad')->assertStatus(400);

    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => str_contains($message, 'RAZORPAY_WEBHOOK_SECRET')
        && ($context['secret_set'] ?? null) === true)->once();
});

it('rejects everything while the secret is blank, even a matching empty-key signature', function () {
    $this->configureRazorpay(['webhook_secret' => '']);

    $body = webhookBody('order_any');

    sendWebhook($body, hash_hmac('sha256', $body, ''))->assertStatus(400);
});

it('acknowledges unknown events and unknown orders', function () {
    sendWebhook(webhookBody('order_any', 'payment.failed'))->assertOk();

    sendWebhook(webhookBody('order_unknown'))->assertOk();
});

it('asks razorpay to retry when the result is pending', function () {
    $cart = $this->createRazorpayCart();

    $payment = $this->createRazorpayPayment($cart);

    $this->mock(PaymentFinalizer::class, fn ($mock) => $mock->shouldReceive('finalize')->once()->andReturn(FinalizeResult::pending()));

    sendWebhook(webhookBody($payment->razorpay_order_id))->assertStatus(503);
});

it('logs an event it does not handle', function () {
    Log::spy();

    sendWebhook(webhookBody('order_any', 'payment.failed'))->assertOk();

    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => ($context['event'] ?? null) === 'payment.failed')->once();
});

it('still completes after razorpay has been switched off', function () {
    $this->configureRazorpay(['active' => false]);

    $cart = $this->createRazorpayCart();

    $payment = $this->createRazorpayPayment($cart);

    $this->fakeRazorpayApi($payment);

    sendWebhook(webhookBody($payment->razorpay_order_id))->assertOk();

    expect(Order::where('cart_id', $cart->id)->count())->toBe(1);
});

it('records a refund that failed later', function () {
    $this->mock(PaymentFinalizer::class, fn ($mock) => $mock->shouldReceive('recordRefundFailure')->once()->with('pay_test'));

    $body = json_encode([
        'event' => 'refund.failed',
        'payload' => ['refund' => ['entity' => ['id' => 'rfnd_test', 'payment_id' => 'pay_test']]],
    ]);

    sendWebhook($body)->assertOk();
});

it('rejects a refund failure with a bad signature', function () {
    $this->mock(PaymentFinalizer::class, fn ($mock) => $mock->shouldNotReceive('recordRefundFailure'));

    $body = json_encode([
        'event' => 'refund.failed',
        'payload' => ['refund' => ['entity' => ['payment_id' => 'pay_test']]],
    ]);

    sendWebhook($body, 'bad')->assertStatus(400);
});
