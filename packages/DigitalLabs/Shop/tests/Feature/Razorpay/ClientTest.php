<?php

use DigitalLabs\Razorpay\Exceptions\RazorpayException;
use DigitalLabs\Razorpay\Services\RazorpayClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->configureRazorpay());

it('creates an order with the 15 minute capture window', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_1'])]);

    $order = app(RazorpayClient::class)->createOrder(150050, 'INR', 'cart-9', ['cart_id' => '9']);

    expect($order['id'])->toBe('order_1');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.razorpay.com/v1/orders'
        && $request['amount'] === 150050
        && $request['payment']['capture'] === 'automatic'
        && $request['payment']['capture_options']['automatic_expiry_period'] === 15
        && $request['payment']['capture_options']['manual_expiry_period'] === 15
        && $request['payment']['capture_options']['refund_speed'] === 'normal'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_key:test_key_secret')));
});

it('sends the idempotency key with a refund', function () {
    Http::fake(['api.razorpay.com/v1/payments/pay_1/refund' => Http::response(['id' => 'rfnd_1'])]);

    app(RazorpayClient::class)->refundPayment('pay_1', 500, 'auto-pay_1');

    Http::assertSent(fn (Request $request) => $request['amount'] === 500
        && $request->hasHeader('X-Refund-Idempotency', 'auto-pay_1'));
});

it('throws when the api rejects a request', function () {
    Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR']], 400)]);

    app(RazorpayClient::class)->fetchPayment('pay_1');
})->throws(RazorpayException::class);

it('checks a payment signature', function () {
    $client = app(RazorpayClient::class);

    $valid = hash_hmac('sha256', 'order_1|pay_1', 'test_key_secret');

    expect($client->isPaymentSignatureValid('order_1', 'pay_1', $valid))->toBeTrue()
        ->and($client->isPaymentSignatureValid('order_1', 'pay_2', $valid))->toBeFalse();
});

it('checks a webhook signature', function () {
    $client = app(RazorpayClient::class);

    $valid = hash_hmac('sha256', '{"a":1}', 'test_webhook_secret');

    expect($client->isWebhookSignatureValid('{"a":1}', $valid))->toBeTrue()
        ->and($client->isWebhookSignatureValid('{"a":2}', $valid))->toBeFalse();
});

it('rejects every signature when a secret is blank', function () {
    $this->configureRazorpay(['key_secret' => '', 'webhook_secret' => '']);

    $client = app(RazorpayClient::class);

    expect($client->isWebhookSignatureValid('{"a":1}', hash_hmac('sha256', '{"a":1}', '')))->toBeFalse()
        ->and($client->isPaymentSignatureValid('order_1', 'pay_1', hash_hmac('sha256', 'order_1|pay_1', '')))->toBeFalse();
});
