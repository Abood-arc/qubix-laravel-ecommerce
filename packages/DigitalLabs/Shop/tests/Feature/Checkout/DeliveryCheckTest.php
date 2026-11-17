<?php

use DigitalLabs\Checkout\Contracts\Cart as CartContract;
use DigitalLabs\Checkout\Contracts\CartAddress as CartAddressContract;
use DigitalLabs\Checkout\Models\CartAddress;
use DigitalLabs\Customer\Models\CustomerAddress;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Shipping\Contracts\DeliveryCheck;
use Illuminate\Support\Arr;

use function Pest\Laravel\postJson;

const UNDELIVERABLE_PIN = '999999';

const UNDELIVERABLE_REASON = 'We cannot deliver to 999999.';

beforeEach(function () {
    // Refuses one PIN code, delivers everywhere else.
    app()->instance(DeliveryCheck::class, new class implements DeliveryCheck
    {
        public function check(CartAddressContract $shippingAddress, CartContract $cart): ?string
        {
            return $shippingAddress->postcode === UNDELIVERABLE_PIN ? UNDELIVERABLE_REASON : null;
        }
    });
});

function deliveryCheckAddress(array $overrides = []): array
{
    return [
        ...Arr::except(CustomerAddress::factory()->make()->toArray(), ['address_type']),
        'address' => [fake()->streetAddress()],
        ...$overrides,
    ];
}

it('refuses the shipping address on its postcode field', function () {
    $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => deliveryCheckAddress(['use_for_shipping' => 0]),
        'shipping' => deliveryCheckAddress(['postcode' => UNDELIVERABLE_PIN]),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', UNDELIVERABLE_REASON)
        ->assertJsonPath('errors', ['shipping.postcode' => [UNDELIVERABLE_REASON]]);
});

it('refuses the billing postcode when billing is used for shipping', function () {
    $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => deliveryCheckAddress(['use_for_shipping' => 1, 'postcode' => UNDELIVERABLE_PIN]),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['billing.postcode' => [UNDELIVERABLE_REASON]]);
});

it('checks the shipping address, not the billing one', function () {
    $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => deliveryCheckAddress(['use_for_shipping' => 0, 'postcode' => UNDELIVERABLE_PIN]),
        'shipping' => deliveryCheckAddress(),
    ])
        ->assertOk()
        ->assertJsonPath('redirect', false)
        ->assertJsonPath('data.shippingMethods.free.rates.0.method', 'free_free');
});

it('accepts a deliverable address', function () {
    $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => deliveryCheckAddress(['use_for_shipping' => 1]),
    ])
        ->assertOk()
        ->assertJsonPath('redirect', false)
        ->assertJsonPath('data.shippingMethods.free.rates.0.method', 'free_free');
});

it('refuses to place an order to an undeliverable address', function () {
    $cart = $this->createRazorpayCart();

    $cart->payment->forceFill(['method' => 'cashondelivery'])->save();

    CartAddress::where('cart_id', $cart->id)
        ->where('address_type', CartAddress::ADDRESS_TYPE_SHIPPING)
        ->update(['postcode' => UNDELIVERABLE_PIN]);

    cart()->setCart($cart->refresh());

    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertStatus(500)
        ->assertJsonPath('message', UNDELIVERABLE_REASON);

    expect(Order::where('cart_id', $cart->id)->exists())->toBeFalse();
});

it('places an order to a deliverable address', function () {
    $cart = $this->createRazorpayCart();

    $cart->payment->forceFill(['method' => 'cashondelivery'])->save();

    cart()->setCart($cart->refresh());

    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertOk()
        ->assertJsonPath('data.redirect_url', route('shop.checkout.onepage.success'));

    expect(Order::where('cart_id', $cart->id)->exists())->toBeTrue();
});
