<?php

use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Sales\Models\OrderAddress;
use DigitalLabs\Sales\Models\OrderItem;
use DigitalLabs\Sales\Models\OrderPayment;
use DigitalLabs\Shiprocket\Services\ParcelBuilder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Webkul\Faker\Helpers\Customer as CustomerFaker;

const SHIPROCKET_CHECK_API = 'https://apiv2.shiprocket.in/v1/external/';

/**
 * The calls a booking makes that cost money or book a courier: the check never makes them.
 */
const SHIPROCKET_CHECK_BOOKING_PATHS = ['courier/assign/awb', 'courier/generate/pickup', 'manifests/generate', 'courier/generate/label'];

function shiprocketCheckConfig(string $code, mixed $value): void
{
    CoreConfig::query()->where('code', $code)->get()->each->delete();

    CoreConfig::create(['code' => $code, 'value' => $value, 'channel_code' => core()->getRequestedChannelCode()]);
}

/**
 * A recorded Shiprocket: an account with two pickup locations; PIN 560001 has no courier,
 * 700001 no courier that collects cash; orders/show answers with $recorded, then cancelled.
 */
function fakeShiprocketCheckApi(array $overrides = [], array $recorded = []): void
{
    Http::preventStrayRequests();

    Http::fake([
        SHIPROCKET_CHECK_API.'auth/login' => Http::response(['token' => 'tok-check']),
        SHIPROCKET_CHECK_API.'settings/company/pickup' => Http::response(['data' => ['shipping_address' => [
            ['id' => 1, 'pickup_location' => 'Pune Warehouse', 'pin_code' => '411001'],
            ['id' => 2, 'pickup_location' => 'Home', 'pin_code' => '110001'],
        ]]]),
        SHIPROCKET_CHECK_API.'courier/serviceability/*' => function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if ($query['delivery_postcode'] === '560001') {
                return Http::response(['status' => 404, 'message' => 'No courier serviceable for this pincode'], 404);
            }

            $cod = $query['delivery_postcode'] === '700001' ? 0 : (int) $query['cod'];

            return Http::response(['status' => 200, 'data' => ['available_courier_companies' => [
                ['courier_name' => 'Delhivery Surface', 'cod' => $cod],
            ]]]);
        },
        SHIPROCKET_CHECK_API.'orders/create/adhoc' => Http::response(['order_id' => 9001, 'shipment_id' => 9101, 'status' => 'NEW']),
        SHIPROCKET_CHECK_API.'orders/show/9001' => Http::sequence()
            ->push(['data' => $recorded + [
                'id' => 9001,
                'status' => 'NEW',
                'payment_method' => 'cod',
                'total' => 1950,
                'net_total' => '1950.00',
                'discount' => 0,
                'tax' => 0,
                'cod' => 1,
                'others' => ['shipping_charges' => '100'],
                'products' => [['name' => 'Leather Tote', 'quantity' => 2, 'selling_price' => 925, 'discount' => 0]],
            ]])
            ->push(['data' => ['id' => 9001, 'status' => 'CANCELED']]),
        SHIPROCKET_CHECK_API.'orders/cancel' => Http::response('', 204),
        ...$overrides,
    ]);
}

/**
 * A cash on delivery order: 2 × 1000 with a 150 discount and 100 shipping (grand total 1950).
 */
function shiprocketCheckOrder(): Order
{
    $customer = (new CustomerFaker)->factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'sub_total' => 2000,
        'tax_amount' => 0,
        'shipping_amount' => 100,
        'shipping_tax_amount' => 0,
        'shipping_discount_amount' => 0,
        'discount_amount' => 150,
        'grand_total' => 1950,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'type' => 'simple',
        'name' => 'Leather Tote',
        'sku' => 'TOTE-1',
        'weight' => 500,
        'qty_ordered' => 2,
        'price' => 1000,
        'total' => 2000,
        'tax_percent' => 0,
        'tax_amount' => 0,
        'discount_amount' => 150,
    ]);

    foreach ([OrderAddress::ADDRESS_TYPE_BILLING, OrderAddress::ADDRESS_TYPE_SHIPPING] as $type) {
        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'address_type' => $type,
            'first_name' => 'Asha',
            'last_name' => 'Rao',
            'address' => '12 MG Road',
            'city' => 'Pune',
            'state' => 'MH',
            'postcode' => '411001',
            'country' => 'IN',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
        ]);
    }

    OrderPayment::factory()->create(['order_id' => $order->id, 'method' => 'cashondelivery']);

    return $order->fresh();
}

/**
 * Run the check and return its exit code and everything it printed.
 *
 * @return array{0: int, 1: string}
 */
function runShiprocketCheck(array $options = []): array
{
    $code = Artisan::call('shiprocket:check', $options);

    return [$code, Artisan::output()];
}

function shiprocketCheckSent(string $path): int
{
    return Http::recorded(fn (Request $request) => str_starts_with($request->url(), SHIPROCKET_CHECK_API.$path))->count();
}

beforeEach(function () {
    config([
        'shiprocket.email' => 'api@example.com',
        'shiprocket.password' => 'secret-password',
        'shiprocket.fake' => false,
    ]);

    Cache::forget('shiprocket:token:'.sha1('api@example.com'));

    // Switched off in admin: the check only needs the API user in .env.
    shiprocketCheckConfig('sales.shipping.shiprocket.active', '0');
    shiprocketCheckConfig('sales.shipping.shiprocket.pickup_location', 'Pune Warehouse');
    shiprocketCheckConfig('sales.shipping.shiprocket.pickup_pin', '411001');
    shiprocketCheckConfig('sales.shipping.shiprocket.length', '30');
    shiprocketCheckConfig('sales.shipping.shiprocket.breadth', '25');
    shiprocketCheckConfig('sales.shipping.shiprocket.height', '10');
    shiprocketCheckConfig('general.general.locale_options.weight_unit', 'g');
});

it('checks the api user, the pickup location and serviceability while shiprocket is off', function () {
    // A cached token would hide a changed password: the check always logs in afresh.
    Cache::put('shiprocket:token:'.sha1('api@example.com'), 'old-token', now()->addDay());

    fakeShiprocketCheckApi();

    [$code, $output] = runShiprocketCheck();

    expect($code)->toBe(0)
        ->and($output)->toContain('API user OK (api@example.com)')
        ->toContain('Pune Warehouse')
        ->toContain('Home')
        ->toContain('Pickup location "Pune Warehouse" in the settings: found in Shiprocket.')
        ->toContain('Pickup PIN 411001 in the settings matches that location.')
        ->toContain('Serviceability from 411001 (0.5 kg)')
        ->and($output)->toMatch('/110001\s*\|\s*yes\s*\|\s*yes/')
        ->toMatch('/400001\s*\|\s*yes\s*\|\s*yes/')
        ->toMatch('/560001\s*\|\s*no\s*\|\s*no/')
        ->toMatch('/700001\s*\|\s*yes\s*\|\s*no/')
        // Never the password or the token.
        ->not->toContain('secret-password')
        ->not->toContain('tok-check');

    expect(shiprocketCheckSent('auth/login'))->toBe(1)
        ->and(shiprocketCheckSent('courier/serviceability/'))->toBe(8)
        ->and(shiprocketCheckSent('orders'))->toBe(0);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'courier/serviceability/')
        && str_contains($request->url(), 'pickup_postcode=411001')
        && str_contains($request->url(), 'weight=0.5'));
});

it('checks only the PINs asked for', function () {
    fakeShiprocketCheckApi();

    [$code, $output] = runShiprocketCheck(['--pin' => ['122001', '12345']]);

    expect($output)->toMatch('/122001\s*\|\s*yes\s*\|\s*yes/')
        ->toMatch('/12345\s*\|\s*not a 6-digit PIN/')
        ->not->toMatch('/400001\s*\|/')
        ->and(shiprocketCheckSent('courier/serviceability/'))->toBe(2)
        ->and($code)->toBe(0);
});

it('says when the pickup location in the settings is not exactly one of the account\'s', function () {
    shiprocketCheckConfig('sales.shipping.shiprocket.pickup_location', 'pune warehouse');

    fakeShiprocketCheckApi();

    [$code, $output] = runShiprocketCheck();

    expect($code)->toBe(1)
        ->and($output)->toContain('Pickup location "pune warehouse" in the settings is not in Shiprocket')
        ->toContain('Shiprocket has "Pune Warehouse"');
});

it('says when the pickup PIN differs from the location\'s', function () {
    shiprocketCheckConfig('sales.shipping.shiprocket.pickup_pin', '411002');

    fakeShiprocketCheckApi();

    [$code, $output] = runShiprocketCheck();

    expect($code)->toBe(1)
        ->and($output)->toContain('Pickup PIN 411002 in the settings differs from that location\'s PIN 411001 in Shiprocket.');
});

it('shows shiprocket\'s answer when the login is refused and checks nothing else', function () {
    fakeShiprocketCheckApi([
        SHIPROCKET_CHECK_API.'auth/login' => Http::response(['message' => 'Invalid email and password combination', 'status_code' => 400], 400),
    ]);

    [$code, $output] = runShiprocketCheck();

    expect($code)->toBe(1)
        ->and($output)->toContain('Login failed: Invalid email and password combination')
        ->not->toContain('secret-password')
        ->and(Http::recorded())->toHaveCount(1);
});

it('needs the api user in .env', function () {
    config(['shiprocket.password' => '']);

    fakeShiprocketCheckApi();

    [$code, $output] = runShiprocketCheck();

    expect($code)->toBe(1)
        ->and($output)->toContain('SHIPROCKET_EMAIL and SHIPROCKET_PASSWORD must both be set in .env')
        ->and(Http::recorded())->toHaveCount(0);
});

it('prints the payload of a store order as JSON and calls nothing', function () {
    Http::preventStrayRequests();
    Http::fake();

    $order = shiprocketCheckOrder();

    [$code, $output] = runShiprocketCheck(['--order' => $order->increment_id, '--payload' => true]);

    $payload = json_decode($output, true);

    expect($code)->toBe(0)
        ->and($output)->toContain("\n    \"order_id\"")
        ->and($payload)->toEqual(app(ParcelBuilder::class)->build($order))
        ->and($payload['sub_total'] + $payload['shipping_charges'])->toEqual(1950);

    Http::assertNothingSent();
});

it('creates a test order, reads back what shiprocket recorded and cancels it, never booking a courier', function () {
    $this->travelTo(now()->setDateTime(2026, 11, 18, 10, 30, 15));

    fakeShiprocketCheckApi();

    $order = shiprocketCheckOrder();

    [$code, $output] = runShiprocketCheck(['--order' => $order->increment_id, '--create-test-order' => true, '--force' => true]);

    $channelOrderId = 'CHECK-'.$order->increment_id.'-20261118103015';

    expect($code)->toBe(0)
        ->and($output)->toContain('Created Shiprocket order 9001 (shipment 9101) as '.$channelOrderId)
        ->toMatch('/Store grand_total\s*\|\s*1950/')
        ->toMatch('/Sent sub_total\s*\|\s*1850/')
        ->toMatch('/Sent shipping_charges\s*\|\s*100/')
        ->toMatch('/total\s*\|\s*1950/')
        ->toMatch('/payment_method\s*\|\s*cod/')
        ->toMatch('/others.shipping_charges\s*\|\s*100/')
        ->toMatch('/sub_total\s*\|\s*\(not returned\)/')
        ->toMatch('/Leather Tote\s*\|\s*2\s*\|\s*925/')
        ->toContain('Shiprocket\'s total 1950 equals the amount sent (sub_total + shipping_charges) 1950.')
        ->toContain('Cancelled test order '.$channelOrderId.' (Shiprocket order 9001); Shiprocket now shows it as CANCELED.');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'orders/create/adhoc')
        && $request['order_id'] === $channelOrderId
        && $request['payment_method'] === 'COD'
        && $request['sub_total'] == 1850);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'orders/cancel') && $request->data() === ['ids' => [9001]]);

    // In this order: create, read back, cancel, read the status.
    $calls = Http::recorded(fn (Request $request) => str_contains($request->url(), 'orders/'))
        ->map(fn ($pair) => str_replace(SHIPROCKET_CHECK_API, '', $pair[0]->url()))
        ->values()
        ->all();

    expect($calls)->toBe(['orders/create/adhoc', 'orders/show/9001', 'orders/cancel', 'orders/show/9001']);

    foreach (SHIPROCKET_CHECK_BOOKING_PATHS as $path) {
        expect(shiprocketCheckSent($path))->toBe(0);
    }
});

it('cancels the test order even when it cannot be read back', function () {
    fakeShiprocketCheckApi([
        SHIPROCKET_CHECK_API.'orders/show/9001' => Http::response(['message' => 'Order ID not found', 'status_code' => 400], 400),
    ]);

    $order = shiprocketCheckOrder();

    [$code, $output] = runShiprocketCheck(['--order' => $order->increment_id, '--create-test-order' => true, '--force' => true]);

    expect($code)->toBe(1)
        ->and($output)->toContain('Could not read the test order back: Order ID not found')
        ->toContain('Cancelled test order CHECK-'.$order->increment_id)
        ->and(shiprocketCheckSent('orders/cancel'))->toBe(1);
});

it('says loudly when the test order could not be cancelled', function () {
    fakeShiprocketCheckApi([
        SHIPROCKET_CHECK_API.'orders/cancel' => Http::response(['message' => 'Cannot cancel order'], 400),
    ]);

    $order = shiprocketCheckOrder();

    [$code, $output] = runShiprocketCheck(['--order' => $order->increment_id, '--create-test-order' => true, '--force' => true]);

    expect($code)->toBe(1)
        ->and($output)->toContain('CANCEL FAILED for Shiprocket order 9001')
        ->toContain('cancel it by hand in the Shiprocket panel');
});

it('asks before creating the test order and creates nothing when the answer is no', function () {
    $this->travelTo(now()->setDateTime(2026, 11, 18, 10, 30, 15));

    fakeShiprocketCheckApi();

    $order = shiprocketCheckOrder();

    $this->artisan('shiprocket:check', ['--order' => $order->increment_id, '--create-test-order' => true])
        ->expectsConfirmation(
            'Create test order CHECK-'.$order->increment_id.'-20261118103015 in Shiprocket? It is cancelled straight after; no courier is booked.',
            'no'
        )
        ->expectsOutputToContain('No test order created.')
        ->assertExitCode(1);

    expect(shiprocketCheckSent('orders'))->toBe(0);
});

it('refuses to create anything for an order without --create-test-order', function () {
    fakeShiprocketCheckApi();

    $order = shiprocketCheckOrder();

    [$code, $output] = runShiprocketCheck(['--order' => $order->increment_id]);

    expect($code)->toBe(1)
        ->and($output)->toContain('--order needs --payload (print the payload) or --create-test-order')
        ->and(Http::recorded())->toHaveCount(0);
});

it('says when the store order does not exist', function () {
    fakeShiprocketCheckApi();

    [$code, $output] = runShiprocketCheck(['--order' => 'NOPE-1', '--payload' => true]);

    expect($code)->toBe(1)
        ->and($output)->toContain('No store order NOPE-1.');
});

it('says FAKE MODE and checks nothing against the fake client', function () {
    config(['shiprocket.fake' => true]);

    Http::preventStrayRequests();
    Http::fake();

    [$code, $output] = runShiprocketCheck();

    expect($code)->toBe(1)
        ->and($output)->toContain('FAKE MODE')
        ->not->toContain('API user OK');

    Http::assertNothingSent();
});
