<?php

use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Customer\Models\CustomerAddress;
use Illuminate\Support\Arr;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

function setDeliveryCountryConfig(string $code, ?string $value): void
{
    CoreConfig::query()->where('code', $code)->get()->each->delete();

    if ($value === null) {
        return;
    }

    CoreConfig::create([
        'code' => $code,
        'value' => $value,
        'channel_code' => core()->getRequestedChannelCode(),
        // The origin country is set per language in the admin; the switch is not.
        'locale_code' => $code === 'sales.shipping.origin.country' ? core()->getRequestedLocaleCode() : null,
    ]);
}

/**
 * The store delivers only to its own country, India.
 */
function deliverOnlyToIndia(): void
{
    setDeliveryCountryConfig('sales.shipping.origin.country', 'IN');
    setDeliveryCountryConfig('sales.shipping.origin.deliver_only_here', '1');
}

function deliveryCountryAddress(array $overrides = []): array
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

function deliverOnlyWithinIndia(): string
{
    return trans('shop::app.checkout.onepage.address.deliver-only-within', ['country' => 'India']);
}

beforeEach(function () {
    setDeliveryCountryConfig('sales.shipping.origin.country', null);
    setDeliveryCountryConfig('sales.shipping.origin.deliver_only_here', null);
});

it('refuses a checkout address in another country', function (array $addresses, string $field) {
    deliverOnlyToIndia();

    $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), $addresses)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => deliverOnlyWithinIndia()]);
})->with([
    'billing used for shipping' => [fn () => [
        'billing' => deliveryCountryAddress(['use_for_shipping' => 1, 'country' => 'PK', 'state' => 'PB']),
    ], 'billing.country'],
    'separate shipping address' => [fn () => [
        'billing' => deliveryCountryAddress(['use_for_shipping' => 0]),
        'shipping' => deliveryCountryAddress(['country' => 'PK', 'state' => 'PB']),
    ], 'shipping.country'],
]);

it('accepts a checkout address in its country', function () {
    deliverOnlyToIndia();

    $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => deliveryCountryAddress(['use_for_shipping' => 1]),
    ])->assertOk();
});

it('accepts any country while the switch is off or the store has no country', function (string $how) {
    match ($how) {
        'switch off' => setDeliveryCountryConfig('sales.shipping.origin.country', 'IN'),
        'no store country' => setDeliveryCountryConfig('sales.shipping.origin.deliver_only_here', '1'),
    };

    $this->createRazorpayCart();

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => deliveryCountryAddress(['use_for_shipping' => 1, 'country' => 'PK', 'state' => 'PB']),
    ])->assertOk();
})->with(['switch off', 'no store country']);

it('refuses another country in the address book and on the cart estimate', function () {
    deliverOnlyToIndia();

    $customer = $this->loginAsCustomer();

    postJson(route('shop.customers.account.addresses.store'), [
        ...deliveryCountryAddress(['country' => 'PK', 'state' => 'PB']),
        'customer_id' => $customer->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.country', [deliverOnlyWithinIndia()]);

    $this->createRazorpayCart($customer);

    postJson(route('shop.api.checkout.cart.estimate_shipping'), ['country' => 'PK', 'state' => 'PB', 'postcode' => '54000'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.country', [deliverOnlyWithinIndia()]);

    postJson(route('shop.api.checkout.cart.estimate_shipping'), ['country' => 'IN', 'state' => 'MH', 'postcode' => '400001'])
        ->assertOk();
});

it('shows the country fixed in the address forms', function () {
    deliverOnlyToIndia();

    $this->createRazorpayCart($this->loginAsCustomer());

    foreach ([route('shop.checkout.onepage.index'), route('shop.customers.account.addresses.create'), route('shop.checkout.cart.index')] as $page) {
        get($page)
            ->assertOk()
            ->assertSee('value="India"', false)
            ->assertSee(deliverOnlyWithinIndia())
            ->assertDontSee(trans('shop::app.checkout.onepage.address.select-country'));
    }
});

it('lets the customer choose the country while the switch is off', function () {
    setDeliveryCountryConfig('sales.shipping.origin.country', 'IN');

    $this->createRazorpayCart($this->loginAsCustomer());

    get(route('shop.checkout.onepage.index'))
        ->assertOk()
        ->assertSee(trans('shop::app.checkout.onepage.address.select-country'))
        ->assertDontSee(deliverOnlyWithinIndia());
});
