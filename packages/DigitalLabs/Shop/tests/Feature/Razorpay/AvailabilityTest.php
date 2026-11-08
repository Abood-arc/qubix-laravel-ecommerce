<?php

use DigitalLabs\Payment\Facades\Payment;
use DigitalLabs\Razorpay\Payment\Razorpay;
use Webkul\Faker\Helpers\Customer as CustomerFaker;

function razorpayIsAvailable(): bool
{
    return (bool) (new Razorpay)->isAvailable();
}

beforeEach(fn () => $this->configureRazorpay());

it('is available when everything is set', function () {
    $this->createRazorpayCart();

    expect(razorpayIsAvailable())->toBeTrue();
});

it('is unavailable when a required setting is missing', function (array $setting) {
    $this->configureRazorpay($setting);

    $this->createRazorpayCart();

    expect(razorpayIsAvailable())->toBeFalse();
})->with([
    'inactive' => [['active' => false]],
    'no key id' => [['key_id' => '']],
    'no key secret' => [['key_secret' => '']],
    'no webhook secret' => [['webhook_secret' => '']],
]);

it('is unavailable for a cart not in INR', function () {
    $cart = $this->createRazorpayCart();

    $cart->update(['cart_currency_code' => 'USD']);

    cart()->setCart($cart->refresh());

    expect(razorpayIsAvailable())->toBeFalse();
});

it('is unavailable below 1 INR', function () {
    $cart = $this->createRazorpayCart();

    $cart->update(['grand_total' => 0.99]);

    cart()->setCart($cart->refresh());

    expect(razorpayIsAvailable())->toBeFalse();
});

it('is limited to the test customer while the test email is set', function () {
    $customer = (new CustomerFaker)->factory()->create();

    $this->configureRazorpay(['test_customer_email' => $customer->email]);

    $this->createRazorpayCart();

    expect(razorpayIsAvailable())->toBeFalse();

    $this->loginAsCustomer($customer);

    expect(razorpayIsAvailable())->toBeTrue();
});

it('is listed ahead of cash on delivery', function () {
    config()->set('payment_methods.cashondelivery.active', true);

    $this->createRazorpayCart();

    $codes = collect(Payment::getPaymentMethods())->pluck('method')->all();

    expect(array_search('razorpay', $codes))->toBeLessThan(array_search('cashondelivery', $codes));
});
