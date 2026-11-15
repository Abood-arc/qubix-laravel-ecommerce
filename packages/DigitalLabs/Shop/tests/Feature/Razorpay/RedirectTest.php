<?php

use DigitalLabs\Checkout\Models\CartAddress;
use DigitalLabs\Checkout\Models\CartShippingRate;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Webkul\Faker\Helpers\Customer as CustomerFaker;

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

it('sends a fresh receipt to razorpay on every page load', function () {
    Http::swap(new Illuminate\Http\Client\Factory);

    Http::fake(['api.razorpay.com/v1/orders' => fn () => Http::response(['id' => 'order_redirect_'.uniqid()])]);

    $cart = $this->createRazorpayCart();

    get(route('razorpay.redirect'))->assertOk();

    get(route('razorpay.redirect'))->assertOk();

    $receipts = Http::recorded()->map(fn (array $pair) => $pair[0]['receipt'])->all();

    expect($receipts)->toHaveCount(2)
        ->and($receipts[0])->not->toBe($receipts[1])
        ->and(max(array_map('strlen', $receipts)))->toBeLessThanOrEqual(40)
        ->and(RazorpayPayment::where('cart_id', $cart->id)->count())->toBe(2);
});

it('sends a cart that cannot be ordered to the cart page', function (Closure $break) {
    $cart = $this->createRazorpayCart();

    $break($cart);

    cart()->setCart($cart->refresh());

    get(route('razorpay.redirect'))->assertRedirect(route('shop.checkout.cart.index'));

    expect(RazorpayPayment::where('cart_id', $cart->id)->exists())->toBeFalse();

    Http::assertNothingSent();
})->with([
    'cart error' => [fn ($cart) => $cart->items->first()->forceFill(['quantity' => 99999])->save()],
    'no billing address' => [fn ($cart) => CartAddress::where('cart_id', $cart->id)->where('address_type', CartAddress::ADDRESS_TYPE_BILLING)->delete()],
    'no shipping rate' => [fn ($cart) => CartShippingRate::where('cart_id', $cart->id)->delete()],
]);

it('refuses a suspended or inactive customer', function (array $state) {
    $customer = (new CustomerFaker)->factory()->create($state);

    $this->loginAsCustomer($customer);

    $cart = $this->createRazorpayCart($customer);

    get(route('razorpay.redirect'))->assertRedirect(route('shop.checkout.cart.index'));

    expect(RazorpayPayment::where('cart_id', $cart->id)->exists())->toBeFalse();

    Http::assertNothingSent();
})->with([
    'suspended' => [['is_suspended' => 1]],
    'inactive' => [['status' => 0]],
]);
