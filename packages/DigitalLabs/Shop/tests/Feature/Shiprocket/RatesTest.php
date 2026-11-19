<?php

use DigitalLabs\Checkout\Models\CartAddress;
use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Shipping\Facades\Shipping;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketUnknownOutcomeException;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use DigitalLabs\Shiprocket\Support\Courier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

/**
 * The fake client's couriers (Fake Surface ₹90 / 5 days, Fake Air ₹150 / 2 days, ₹50 more with
 * cash on delivery), with switches to fail, raise every price, or keep only some cash couriers.
 */
class RatesTestApi extends FakeShiprocketClient
{
    public bool $fail = false;

    public float $raise = 0;

    /** @var list<int>|null courier ids that collect cash; null = all */
    public ?array $cashCouriers = null;

    public function couriers(string $pickupPin, string $deliveryPin, float $weightKg, bool $cod): array
    {
        if ($this->fail) {
            throw new ShiprocketUnknownOutcomeException('Shiprocket did not answer');
        }

        return collect(parent::couriers($pickupPin, $deliveryPin, $weightKg, $cod))
            ->filter(fn (Courier $courier) => ! $cod || $this->cashCouriers === null || in_array($courier->id, $this->cashCouriers, true))
            ->map(fn (Courier $courier) => new Courier($courier->id, $courier->name, $courier->rate + $this->raise, $courier->days, $courier->etd))
            ->values()
            ->all();
    }
}

function setRatesConfig(string $code, mixed $value): void
{
    CoreConfig::query()->where('code', $code)->get()->each->delete();

    if ($value !== null) {
        CoreConfig::create(['code' => $code, 'value' => $value, 'channel_code' => core()->getRequestedChannelCode()]);
    }
}

/**
 * A checkout-ready cart shipping to an Indian PIN code, paying with $method.
 */
function ratesCart($test, string $method = 'razorpay', string $postcode = '560001')
{
    $cart = $test->createRazorpayCart();

    CartAddress::where('cart_id', $cart->id)->update(['country' => 'IN', 'state' => 'MH', 'postcode' => $postcode]);

    $cart->payment->forceFill(['method' => $method])->save();

    cart()->setCart($cart->refresh());

    return $cart->refresh();
}

/**
 * The Shiprocket options for the current cart: method code => [base price, title, description].
 */
function shiprocketRates(): array
{
    $rates = Shipping::collectRates()['shippingMethods']['shiprocket']['rates'] ?? [];

    return collect($rates)
        ->mapWithKeys(fn ($rate) => [$rate['method'] => [(float) $rate['base_price'], $rate['method_title'], $rate['method_description']]])
        ->all();
}

function chooseDelivery(string $method): void
{
    postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => $method])->assertOk();
}

function choosePayment(string $method): void
{
    postJson(route('shop.checkout.onepage.payment_methods.store'), ['payment' => ['method' => $method]])->assertOk();
}

beforeEach(function () {
    config(['shiprocket.email' => 'api@example.test', 'shiprocket.password' => 'secret', 'shiprocket.fake' => false]);

    setRatesConfig('sales.shipping.shiprocket.active', '1');
    setRatesConfig('sales.shipping.shiprocket.pin_check', '1');
    setRatesConfig('sales.shipping.shiprocket.pickup_pin', '411001');

    setRatesConfig('sales.carriers.shiprocket.active', '1');
    setRatesConfig('sales.carriers.shiprocket.title', 'Shiprocket');
    setRatesConfig('sales.carriers.shiprocket.backup_price', '120');

    app()->instance(ShiprocketApi::class, $this->api = new RatesTestApi);
});

it('offers standard and express at the price shiprocket charges', function () {
    ratesCart($this);

    $arrives = fn (int $days) => trans('shiprocket::app.checkout.arrives', ['date' => now()->addDays($days)->format('j M')]);

    expect(shiprocketRates())->toBe([
        'shiprocket_standard_c1' => [90.0, 'Standard delivery', $arrives(5)],
        'shiprocket_express_c2' => [150.0, 'Express delivery', $arrives(2)],
    ]);
});

it('prices cash on delivery with its fee', function () {
    ratesCart($this, 'cashondelivery');

    expect(shiprocketRates())->toMatchArray([
        'shiprocket_standard_c1' => [140.0, 'Standard delivery (incl. cash on delivery fee)', trans('shiprocket::app.checkout.arrives', ['date' => now()->addDays(5)->format('j M')])],
    ])->and(shiprocketRates()['shiprocket_express_c2'][0])->toBe(200.0);
});

it('adds gst when the rates exclude it, rounding up to the rupee', function () {
    setRatesConfig('sales.carriers.shiprocket.add_gst', '1');

    ratesCart($this);

    expect(array_map(fn ($rate) => $rate[0], shiprocketRates()))->toBe([
        'shiprocket_standard_c1' => 107.0,
        'shiprocket_express_c2' => 177.0,
    ]);
});

it('offers the backup price when shiprocket cannot answer', function () {
    Log::spy();

    $this->api->fail = true;

    ratesCart($this);

    expect(shiprocketRates())->toBe([
        'shiprocket_backup' => [120.0, 'Standard delivery', trans('shiprocket::app.checkout.tracked')],
    ]);
});

it('offers nothing while it cannot be used', function (string $how) {
    match ($how) {
        'method off' => setRatesConfig('sales.carriers.shiprocket.active', '0'),
        'shiprocket off' => setRatesConfig('sales.shipping.shiprocket.active', '0'),
        'no API user' => config(['shiprocket.password' => null]),
        'no backup price' => setRatesConfig('sales.carriers.shiprocket.backup_price', null),
    };

    ratesCart($this);

    expect(shiprocketRates())->toBe([])
        ->and(Shipping::collectRates()['shippingMethods'])->toHaveKey('free');
})->with(['method off', 'shiprocket off', 'no API user', 'no backup price']);

it('offers nothing outside india', function () {
    $cart = ratesCart($this);

    CartAddress::where('cart_id', $cart->id)->update(['country' => 'PK', 'postcode' => '54000']);

    cart()->setCart($cart->refresh());

    expect(shiprocketRates())->toBe([]);
});

it('re-prices delivery when the customer switches to cash on delivery and back', function () {
    $cart = ratesCart($this);

    chooseDelivery('shiprocket_standard_c1');

    expect((float) $cart->refresh()->shipping_amount)->toBe(90.0);

    choosePayment('cashondelivery');

    expect($cart->refresh()->shipping_method)->toBe('shiprocket_standard_c1')
        ->and((float) $cart->shipping_amount)->toBe(140.0);

    choosePayment('razorpay');

    expect((float) $cart->refresh()->shipping_amount)->toBe(90.0);
});

it('keeps express with cash on delivery when a fast courier collects cash', function () {
    $cart = ratesCart($this);

    chooseDelivery('shiprocket_express_c2');

    choosePayment('cashondelivery');

    expect($cart->refresh()->shipping_method)->toBe('shiprocket_express_c2')
        ->and((float) $cart->shipping_amount)->toBe(200.0);
});

it('falls back to standard when no fast courier collects cash', function () {
    $this->api->cashCouriers = [1];

    $cart = ratesCart($this);

    chooseDelivery('shiprocket_express_c2');

    choosePayment('cashondelivery');

    expect($cart->refresh()->shipping_method)->toBe('shiprocket_standard_c1')
        ->and((float) $cart->shipping_amount)->toBe(140.0);
});

it('tells the customer what paying cash adds before they choose it', function () {
    ratesCart($this);

    $methods = collect(
        postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'shiprocket_standard_c1'])
            ->assertOk()
            ->json('payment_methods')
    );

    expect($methods->firstWhere('method', 'cashondelivery')['description'])
        ->toEndWith('. '.trans('shiprocket::app.checkout.cod-fee', ['fee' => core()->currency(50)]));
});

it('says nothing about a fee for other delivery methods', function () {
    ratesCart($this);

    $methods = collect(
        postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'free_free'])
            ->assertOk()
            ->json('payment_methods')
    );

    expect($methods->firstWhere('method', 'cashondelivery')['description'] ?? '')
        ->not->toContain(trans('shiprocket::app.checkout.cod-fee', ['fee' => core()->currency(50)]));
});

it('refuses to place an order at a delivery price shiprocket no longer charges', function () {
    $cart = ratesCart($this, 'cashondelivery');

    chooseDelivery('shiprocket_standard_c1');

    $this->api->raise = 10;

    Cache::flush();

    $orders = Order::count();

    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertStatus(500)
        ->assertJsonPath('message', trans('shiprocket::app.checkout.price-changed', ['price' => core()->currency(150)]));

    expect(Order::count())->toBe($orders)
        ->and((float) $cart->refresh()->shipping_amount)->toBe(150.0);

    // Placed at the new price once the customer has seen it.
    postJson(route('shop.checkout.onepage.orders.store'))->assertOk();

    expect((float) Order::latest('id')->first()->shipping_amount)->toBe(150.0);
});

it('does not open the payment popup at a stale delivery price', function () {
    $this->configureRazorpay();

    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_rates_'.uniqid()])]);

    $cart = ratesCart($this);

    chooseDelivery('shiprocket_standard_c1');

    $this->api->raise = 10;

    Cache::flush();

    get(route('razorpay.redirect'))
        ->assertRedirect(route('shop.checkout.onepage.index'))
        ->assertSessionHas('error', trans('shiprocket::app.checkout.price-changed', ['price' => core()->currency(100)]));

    expect(RazorpayPayment::where('cart_id', $cart->id)->exists())->toBeFalse();
});
