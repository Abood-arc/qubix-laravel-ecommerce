<?php

use DigitalLabs\Checkout\Models\Cart;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Sales\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;

it('forgets a closed guest cart when loading the cart', function () {
    $cart = Cart::factory()->create(['is_active' => 0]);

    cart()->resetCart();

    session()->put('cart', (object) ['id' => $cart->id]);

    cart()->initCart();

    expect(cart()->getCart())->toBeNull()
        ->and(session()->has('cart'))->toBeFalse();
});

it('keeps an open guest cart when loading the cart', function () {
    $cart = Cart::factory()->create(['is_active' => 1]);

    cart()->resetCart();

    session()->put('cart', (object) ['id' => $cart->id]);

    cart()->initCart();

    expect(cart()->getCart()?->id)->toBe($cart->id)
        ->and(session()->has('cart'))->toBeTrue();
});

it('refuses a second order for the same cart', function () {
    $cart = Cart::factory()->create();

    Order::factory()->create(['cart_id' => $cart->id]);

    Order::factory()->create(['cart_id' => $cart->id]);
})->throws(UniqueConstraintViolationException::class);

it('allows many orders without a cart', function () {
    $first = Order::factory()->create();

    $second = Order::factory()->create();

    expect(Order::query()->whereNull('cart_id')->whereIn('id', [$first->id, $second->id])->count())->toBe(2);
});

it('stores the frozen order as an array and knows when a refund has started', function () {
    $cart = $this->createRazorpayCart();

    $payment = $this->createRazorpayPayment($cart);

    expect($payment->fresh()->snapshot['cart_id'])->toBe($cart->id)
        ->and($payment->isRefundStarted())->toBeFalse();

    $payment->update(['status' => RazorpayPayment::STATUS_REFUND_FAILED]);

    expect($payment->fresh()->isRefundStarted())->toBeTrue();
});

it('registers the payment method switched off by default', function () {
    expect(config('payment_methods.razorpay.active'))->toBeFalse()
        ->and(config('payment_methods.razorpay.sort'))->toBe(0);
});
