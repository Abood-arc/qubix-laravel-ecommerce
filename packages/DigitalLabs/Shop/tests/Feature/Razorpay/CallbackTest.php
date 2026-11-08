<?php

use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Razorpay\Services\FinalizeResult;
use DigitalLabs\Razorpay\Services\PaymentFinalizer;
use DigitalLabs\Sales\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->configureRazorpay();

    Mail::fake();

    $this->cart = $this->createRazorpayCart();

    $this->payment = $this->createRazorpayPayment($this->cart);

    $this->fakeRazorpayApi($this->payment);
});

function callbackPayload(RazorpayPayment $payment, ?string $signature = null): array
{
    return [
        'razorpay_order_id' => $payment->razorpay_order_id,
        'razorpay_payment_id' => 'pay_test',
        'razorpay_signature' => $signature ?? hash_hmac('sha256', $payment->razorpay_order_id.'|pay_test', 'test_key_secret'),
    ];
}

it('creates one order and goes to the success page', function () {
    postJson(route('razorpay.callback'), callbackPayload($this->payment))
        ->assertOk()
        ->assertJsonPath('redirect_url', route('shop.checkout.onepage.success'))
        ->assertSessionHas('order_id');

    expect(Order::where('cart_id', $this->cart->id)->count())->toBe(1);
});

it('creates no order for a tampered signature', function () {
    postJson(route('razorpay.callback'), callbackPayload($this->payment, 'tampered'))
        ->assertOk()
        ->assertJsonPath('redirect_url', route('shop.checkout.cart.index'))
        ->assertSessionHas('error', trans('razorpay::app.errors.payment-invalid'));

    expect(Order::where('cart_id', $this->cart->id)->exists())->toBeFalse();

    Http::assertNothingSent();
});

it('creates no order for an unknown razorpay order', function () {
    postJson(route('razorpay.callback'), [
        'razorpay_order_id' => 'order_unknown',
        'razorpay_payment_id' => 'pay_test',
        'razorpay_signature' => hash_hmac('sha256', 'order_unknown|pay_test', 'test_key_secret'),
    ])->assertOk()->assertJsonPath('redirect_url', route('shop.checkout.cart.index'));
});

it('tells the customer why a payment was refunded', function () {
    $existing = Order::factory()->create(['cart_id' => $this->cart->id]);

    postJson(route('razorpay.callback'), callbackPayload($this->payment))
        ->assertOk()
        ->assertJsonPath('redirect_url', route('shop.checkout.cart.index'));

    expect(session('error'))->toContain((string) $existing->increment_id);
});

it('shows the confirming state when the result is pending', function () {
    $this->mock(PaymentFinalizer::class, fn ($mock) => $mock->shouldReceive('finalize')->once()->andReturn(FinalizeResult::pending()));

    postJson(route('razorpay.callback'), callbackPayload($this->payment))
        ->assertOk()
        ->assertJsonPath('pending', true)
        ->assertJsonMissingPath('redirect_url');
});

it('still completes after razorpay has been switched off', function () {
    $this->configureRazorpay(['active' => false]);

    postJson(route('razorpay.callback'), callbackPayload($this->payment))->assertOk();

    expect(Order::where('cart_id', $this->cart->id)->count())->toBe(1);
});
