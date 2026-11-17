<?php

use DigitalLabs\Payment\Facades\Payment;
use DigitalLabs\Payment\Payment\Payment as PaymentMethod;
use DigitalLabs\Sales\Models\Order;

use function Pest\Laravel\postJson;

/**
 * A payment method that is switched on but cannot take this order.
 */
class PaymentListTestMethod extends PaymentMethod
{
    public static ?string $reason = 'Not available for this PIN code.';

    protected $code = 'paymentlisttest';

    public function isAvailable()
    {
        return false;
    }

    public function getUnavailableReason(): ?string
    {
        return static::$reason;
    }

    public function getTitle()
    {
        return 'Test method';
    }

    public function getDescription()
    {
        return 'Test description';
    }

    public function getImage()
    {
        return 'test.png';
    }

    public function getSortOrder()
    {
        return 99;
    }

    public function getRedirectUrl() {}
}

beforeEach(function () {
    PaymentListTestMethod::$reason = 'Not available for this PIN code.';

    config()->set('payment_methods.paymentlisttest', [
        'code' => 'paymentlisttest',
        'title' => 'Test method',
        'class' => PaymentListTestMethod::class,
        'active' => true,
        'sort' => 99,
    ]);
});

function paymentListTestEntry(array $methods): ?array
{
    return collect($methods)->firstWhere('method', 'paymentlisttest');
}

it('lists an unavailable method with its reason only when asked to', function () {
    $this->createRazorpayCart();

    $methods = Payment::getPaymentMethods(includeDisabled: true);

    expect(paymentListTestEntry($methods))->toMatchArray([
        'method_title' => 'Test method',
        'disabled' => true,
        'reason' => 'Not available for this PIN code.',
    ])
        ->and(collect($methods)->firstWhere('method', 'cashondelivery'))->toMatchArray(['disabled' => false]);

    expect(paymentListTestEntry(Payment::getSupportedPaymentMethods(includeDisabled: true)['payment_methods']))->not->toBeNull();
});

it('keeps the default list (admin order create, transactions) to available methods', function () {
    $this->createRazorpayCart();

    $methods = Payment::getSupportedPaymentMethods()['payment_methods'];

    expect(paymentListTestEntry($methods))->toBeNull()
        ->and(collect($methods)->pluck('disabled')->unique()->all())->toBe([false]);
});

it('hides an unavailable method that gives no reason', function () {
    PaymentListTestMethod::$reason = null;

    $this->createRazorpayCart();

    expect(paymentListTestEntry(Payment::getPaymentMethods(includeDisabled: true)))->toBeNull();
});

it('shows disabled methods on the shop checkout', function () {
    $this->createRazorpayCart();

    $methods = postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'free_free'])
        ->assertOk()
        ->json('payment_methods');

    expect(paymentListTestEntry($methods))->toMatchArray([
        'disabled' => true,
        'reason' => 'Not available for this PIN code.',
    ]);
});

it('refuses to place an order with an unavailable method', function (?string $reason, string $message) {
    PaymentListTestMethod::$reason = $reason;

    $cart = $this->createRazorpayCart();

    $cart->payment->forceFill(['method' => 'paymentlisttest'])->save();

    cart()->setCart($cart->refresh());

    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertStatus(500)
        ->assertJsonPath('message', $message);

    expect(Order::where('cart_id', $cart->id)->exists())->toBeFalse();
})->with([
    'with its reason' => ['Not available for this PIN code.', 'Not available for this PIN code.'],
    'with the generic message' => [null, 'This payment method is not available for this order.'],
]);
