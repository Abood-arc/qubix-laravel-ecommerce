<?php

use DigitalLabs\Razorpay\Models\RazorpayPayment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->configureRazorpay();

    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_redirect_'.uniqid()])]);
});

it('freezes the order and opens the popup with the right amount', function () {
    $cart = $this->createRazorpayCart();

    $response = get(route('razorpay.redirect'))->assertOk();

    $payment = RazorpayPayment::where('cart_id', $cart->id)->firstOrFail();

    $expected = (int) round($cart->fresh()->grand_total * 100);

    expect($payment->amount)->toBe($expected)
        ->and($payment->status)->toBe(RazorpayPayment::STATUS_CREATED)
        ->and($payment->snapshot['cart_id'])->toBe($cart->id)
        ->and($payment->snapshot['payment']['additional']['razorpay_order_id'])->toBe($payment->razorpay_order_id);

    $response->assertSee($payment->razorpay_order_id)
        ->assertSee('rzp_test_key')
        ->assertDontSee('test_key_secret')
        ->assertDontSee('test_webhook_secret')
        ->assertSee('checkout.razorpay.com/v1/checkout.js', false);

    Http::assertSent(fn (Request $request) => $request['amount'] === $expected);
});

it('sends an unavailable method back to checkout', function () {
    $this->configureRazorpay(['active' => false]);

    $cart = $this->createRazorpayCart();

    get(route('razorpay.redirect'))
        ->assertRedirect(route('shop.checkout.onepage.index'))
        ->assertSessionHas('error', trans('razorpay::app.errors.unavailable'));

    expect(RazorpayPayment::where('cart_id', $cart->id)->exists())->toBeFalse();

    Http::assertNothingSent();
});

it('sends a missing cart to the cart page', function () {
    cart()->resetCart();

    get(route('razorpay.redirect'))->assertRedirect(route('shop.checkout.cart.index'));
});

it('sends a cart using another method to the cart page', function () {
    $cart = $this->createRazorpayCart();

    $cart->payment->forceFill(['method' => 'cashondelivery'])->save();

    cart()->setCart($cart->refresh());

    get(route('razorpay.redirect'))->assertRedirect(route('shop.checkout.cart.index'));
});

it('returns to checkout when razorpay is down', function () {
    Http::swap(new Illuminate\Http\Client\Factory);

    Http::fake(['api.razorpay.com/v1/orders' => Http::response([], 500)]);

    $cart = $this->createRazorpayCart();

    get(route('razorpay.redirect'))
        ->assertRedirect(route('shop.checkout.onepage.index'))
        ->assertSessionHas('error', trans('razorpay::app.errors.gateway'));

    expect(RazorpayPayment::where('cart_id', $cart->id)->exists())->toBeFalse();
});

it('returns to the cart when the popup is dismissed', function () {
    get(route('razorpay.cancel'))
        ->assertRedirect(route('shop.checkout.cart.index'))
        ->assertSessionHas('error', trans('razorpay::app.errors.cancelled'));
});
