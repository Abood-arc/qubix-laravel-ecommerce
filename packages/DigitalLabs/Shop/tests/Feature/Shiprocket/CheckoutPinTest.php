<?php

use DigitalLabs\Checkout\Models\CartAddress;
use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Customer\Models\CustomerAddress;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use Illuminate\Support\Arr;
use Webkul\Faker\Helpers\Customer as CustomerFaker;

use function Pest\Laravel\postJson;

/**
 * Counts serviceability calls and answers like the fake client: a PIN starting with 9 has
 * no courier, a PIN ending in 0 has no courier that collects cash.
 */
class CheckoutPinTestApi extends FakeShiprocketClient
{
    public int $calls = 0;

    public function couriers(string $pickupPin, string $deliveryPin, float $weightKg, bool $cod): array
    {
        $this->calls++;

        return parent::couriers($pickupPin, $deliveryPin, $weightKg, $cod);
    }
}

function setCheckoutPinConfig(string $code, mixed $value): void
{
    CoreConfig::query()->where('code', $code)->get()->each->delete();

    CoreConfig::create(['code' => $code, 'value' => $value, 'channel_code' => core()->getRequestedChannelCode()]);
}

/**
 * Checkout address form fields for an Indian address.
 */
function checkoutPinAddress(array $overrides = []): array
{
    return [
        ...Arr::except(CustomerAddress::factory()->make()->toArray(), ['address_type']),
        'address' => [fake()->streetAddress()],
        'country' => 'IN',
        'state' => 'MH',
        'postcode' => '560001',
        ...$overrides,
    ];
}

/**
 * Ship the cart to a PIN code and pay with the given method.
 */
function checkoutPinCartTo($cart, string $postcode, string $method): void
{
    CartAddress::where('cart_id', $cart->id)->update(['country' => 'IN', 'state' => 'MH', 'postcode' => $postcode]);

    $cart->payment->forceFill(['method' => $method])->save();

    cart()->setCart($cart->refresh());
}

beforeEach(function () {
    config(['shiprocket.email' => 'api@example.test', 'shiprocket.password' => 'secret', 'shiprocket.fake' => false]);

    setCheckoutPinConfig('sales.shipping.shiprocket.active', '1');
    setCheckoutPinConfig('sales.shipping.shiprocket.pin_check', '1');
    setCheckoutPinConfig('sales.shipping.shiprocket.pickup_pin', '411001');

    app()->instance(ShiprocketApi::class, $this->api = new CheckoutPinTestApi);
});

it('refuses an undeliverable PIN on the postcode field', function (bool $loggedIn, array $addresses, string $field) {
    $customer = $loggedIn ? (new CustomerFaker)->factory()->create() : null;

    if ($customer) {
        $this->loginAsCustomer($customer);
    }

    $this->createRazorpayCart($customer);

    $message = trans('shiprocket::app.checkout.not-deliverable', ['pin' => '999999']);

    postJson(route('shop.checkout.onepage.addresses.store'), $addresses)
        ->assertUnprocessable()
        ->assertJsonPath('message', $message)
        ->assertJsonPath('errors', [$field => [$message]]);
})->with([
    'guest, separate shipping address' => [false, fn () => [
        'billing' => checkoutPinAddress(['use_for_shipping' => 0]),
        'shipping' => checkoutPinAddress(['postcode' => '999999']),
    ], 'shipping.postcode'],
    'guest, billing used for shipping' => [false, fn () => [
        'billing' => checkoutPinAddress(['use_for_shipping' => 1, 'postcode' => '999999']),
    ], 'billing.postcode'],
    'customer, separate shipping address' => [true, fn () => [
        'billing' => checkoutPinAddress(['use_for_shipping' => 0]),
        'shipping' => checkoutPinAddress(['postcode' => '999999']),
    ], 'shipping.postcode'],
    'customer, billing used for shipping' => [true, fn () => [
        'billing' => checkoutPinAddress(['use_for_shipping' => 1, 'postcode' => '999999']),
    ], 'billing.postcode'],
]);

it('refuses a malformed PIN on the postcode field', function () {
    $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => checkoutPinAddress(['use_for_shipping' => 1, 'postcode' => '012345']),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['billing.postcode' => [trans('shiprocket::app.errors.invalid-pin', ['pin' => '012345'])]]);

    expect($this->api->calls)->toBe(0);
});

it('accepts a deliverable PIN', function () {
    $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => checkoutPinAddress(['use_for_shipping' => 1, 'postcode' => '560010']),
    ])
        ->assertOk()
        ->assertJsonPath('redirect', false)
        ->assertJsonPath('data.shippingMethods.free.rates.0.method', 'free_free');
});

it('shows cash on delivery greyed out with the reason where no courier collects cash', function () {
    $cart = $this->createRazorpayCart();

    checkoutPinCartTo($cart, '560010', 'moneytransfer');

    $methods = collect(
        postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'free_free'])
            ->assertOk()
            ->json('payment_methods')
    );

    expect($methods->firstWhere('method', 'cashondelivery'))->toMatchArray([
        'disabled' => true,
        'reason' => trans('shiprocket::app.checkout.cod-unavailable', ['pin' => '560010']),
    ])
        ->and($methods->firstWhere('method', 'moneytransfer'))->toMatchArray(['disabled' => false]);
});

it('offers cash on delivery where a courier collects cash', function () {
    $cart = $this->createRazorpayCart();

    checkoutPinCartTo($cart, '560001', 'moneytransfer');

    $methods = collect(
        postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'free_free'])
            ->assertOk()
            ->json('payment_methods')
    );

    expect($methods->firstWhere('method', 'cashondelivery'))->toMatchArray(['disabled' => false])
        ->and($methods->firstWhere('method', 'cashondelivery'))->not->toHaveKey('reason');
});

it('refuses a cash on delivery order where no courier collects cash', function () {
    $cart = $this->createRazorpayCart();

    checkoutPinCartTo($cart, '560010', 'cashondelivery');

    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertStatus(500)
        ->assertJsonPath('message', trans('shiprocket::app.checkout.cod-unavailable', ['pin' => '560010']));

    expect(Order::where('cart_id', $cart->id)->exists())->toBeFalse();
});

it('refuses an order to an undeliverable PIN', function () {
    $cart = $this->createRazorpayCart();

    checkoutPinCartTo($cart, '999999', 'moneytransfer');

    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertStatus(500)
        ->assertJsonPath('message', trans('shiprocket::app.checkout.not-deliverable', ['pin' => '999999']));
});

it('still lets a no-cash PIN pay another way', function () {
    $cart = $this->createRazorpayCart();

    checkoutPinCartTo($cart, '560010', 'moneytransfer');

    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertOk()
        ->assertJsonPath('data.redirect_url', route('shop.checkout.onepage.success'));

    expect(Order::where('cart_id', $cart->id)->exists())->toBeTrue();
});

it('places a cash on delivery order where a courier collects cash', function () {
    $cart = $this->createRazorpayCart();

    checkoutPinCartTo($cart, '560001', 'cashondelivery');

    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertOk()
        ->assertJsonPath('data.redirect_url', route('shop.checkout.onepage.success'));

    expect(Order::where('cart_id', $cart->id)->exists())->toBeTrue();
});

it('checks nothing while Shiprocket is off: undeliverable PINs and cash on delivery behave like core', function () {
    setCheckoutPinConfig('sales.shipping.shiprocket.active', '0');

    $cart = $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => checkoutPinAddress(['use_for_shipping' => 1, 'postcode' => '999990']),
    ])->assertOk();

    $methods = collect(
        postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'free_free'])
            ->assertOk()
            ->json('payment_methods')
    );

    expect($methods->firstWhere('method', 'cashondelivery'))->toMatchArray(['disabled' => false]);

    postJson(route('shop.checkout.onepage.payment_methods.store'), ['payment' => ['method' => 'cashondelivery']])->assertOk();

    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertOk()
        ->assertJsonPath('data.redirect_url', route('shop.checkout.onepage.success'));

    expect($this->api->calls)->toBe(0)
        ->and(Order::where('cart_id', $cart->id)->exists())->toBeTrue();
});
