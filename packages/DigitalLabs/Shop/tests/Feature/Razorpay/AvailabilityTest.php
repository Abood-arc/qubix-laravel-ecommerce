<?php

use DigitalLabs\Payment\Facades\Payment;
use DigitalLabs\Razorpay\Payment\Razorpay;
use Illuminate\Support\Facades\DB;
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

it('keeps the api keys out of the admin settings', function (string $field) {
    expect(system_config()->getConfigField('sales.payment_methods.razorpay.'.$field))->toBeNull();
})->with(['key_id', 'key_secret', 'webhook_secret']);

it('removes keys that were saved through the admin panel', function () {
    foreach (['key_id', 'key_secret', 'webhook_secret', 'test_customer_email'] as $field) {
        DB::table('core_config')->insert([
            'code' => 'sales.payment_methods.razorpay.'.$field,
            'value' => 'old-'.$field,
            'channel_code' => 'default',
        ]);
    }

    (require base_path('packages/DigitalLabs/Razorpay/src/Database/Migrations/2026_11_16_000001_remove_razorpay_keys_from_core_config.php'))->up();

    expect(DB::table('core_config')->where('code', 'like', 'sales.payment_methods.razorpay.%')->pluck('code')->all())
        ->toBe(['sales.payment_methods.razorpay.test_customer_email']);
});
