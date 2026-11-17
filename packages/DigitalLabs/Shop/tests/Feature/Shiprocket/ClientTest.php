<?php

use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketUnknownOutcomeException;
use DigitalLabs\Shiprocket\Services\ShiprocketClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const SHIPROCKET_TEST_API = 'https://apiv2.shiprocket.in/v1/external/';

/**
 * Fake Shiprocket: the login always answers with a token, plus the given routes.
 */
function fakeShiprocketHttp(array $routes, mixed $login = null): void
{
    Http::preventStrayRequests();

    Http::fake([
        SHIPROCKET_TEST_API.'auth/login' => $login ?? Http::response(['token' => 'tok-1']),
        ...$routes,
    ]);
}

function shiprocketTokenKey(string $email = 'api@example.com'): string
{
    return 'shiprocket:token:'.sha1($email);
}

function shiprocketLoginCount(): int
{
    return Http::recorded(fn (Request $request) => $request->url() === SHIPROCKET_TEST_API.'auth/login')->count();
}

function catchShiprocketException(Closure $call): ?ShiprocketException
{
    try {
        $call();
    } catch (ShiprocketException $exception) {
        return $exception;
    }

    return null;
}

beforeEach(function () {
    config(['shiprocket.email' => 'api@example.com', 'shiprocket.password' => 'secret']);

    Cache::forget(shiprocketTokenKey());
});

it('logs in with the api user and sends the token as a bearer header', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders/create/adhoc' => Http::response(['order_id' => 11, 'shipment_id' => 22, 'status' => 'NEW']),
    ]);

    $ids = app(ShiprocketClient::class)->createOrder(['order_id' => '1001', 'pickup_location' => 'Home']);

    expect($ids)->toBe(['order_id' => 11, 'shipment_id' => 22])
        ->and(Cache::get(shiprocketTokenKey()))->toBe('tok-1');

    Http::assertSent(fn (Request $request) => $request->url() === SHIPROCKET_TEST_API.'auth/login'
        && $request->method() === 'POST'
        && $request['email'] === 'api@example.com'
        && $request['password'] === 'secret');

    Http::assertSent(fn (Request $request) => $request->url() === SHIPROCKET_TEST_API.'orders/create/adhoc'
        && $request->method() === 'POST'
        && $request['order_id'] === '1001'
        && $request['pickup_location'] === 'Home'
        && $request->hasHeader('Authorization', 'Bearer tok-1'));
});

it('logs in once and reuses the cached token', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders/cancel' => Http::response(['message' => 'ok']),
    ]);

    $client = app(ShiprocketClient::class);
    $client->cancelOrder(1);
    $client->cancelOrder(2);

    expect(shiprocketLoginCount())->toBe(1);
});

it('keeps one token per api user', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders/cancel' => Http::response([]),
    ]);

    app(ShiprocketClient::class)->cancelOrder(1);

    config(['shiprocket.email' => 'other@example.com']);

    app(ShiprocketClient::class)->cancelOrder(2);

    expect(shiprocketLoginCount())->toBe(2)
        ->and(Cache::has(shiprocketTokenKey()))->toBeTrue()
        ->and(Cache::has(shiprocketTokenKey('other@example.com')))->toBeTrue();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'auth/login') && $request['email'] === 'other@example.com');
});

it('logs in again once and retries when the token is rejected', function () {
    Cache::put(shiprocketTokenKey(), 'stale', now()->addDay());

    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/generate/label' => Http::sequence()
            ->push(['message' => 'Token has expired'], 401)
            ->push(['label_created' => 1, 'label_url' => 'https://labels.example/1.pdf']),
    ], Http::response(['token' => 'tok-2']));

    $url = app(ShiprocketClient::class)->generateLabel(5);

    expect($url)->toBe('https://labels.example/1.pdf')
        ->and(shiprocketLoginCount())->toBe(1)
        ->and(Cache::get(shiprocketTokenKey()))->toBe('tok-2');

    $labelCalls = Http::recorded(fn (Request $request) => str_ends_with($request->url(), 'courier/generate/label'))->values();

    expect($labelCalls)->toHaveCount(2)
        ->and($labelCalls[0][0]->hasHeader('Authorization', 'Bearer stale'))->toBeTrue()
        ->and($labelCalls[1][0]->hasHeader('Authorization', 'Bearer tok-2'))->toBeTrue();
});

it('gives up as a refusal when the new token is rejected too', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders/cancel' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    $exception = catchShiprocketException(fn () => app(ShiprocketClient::class)->cancelOrder(9));

    expect($exception)->not->toBeNull()
        ->and($exception)->not->toBeInstanceOf(ShiprocketUnknownOutcomeException::class)
        ->and($exception->getMessage())->toBe('Unauthenticated.')
        ->and(shiprocketLoginCount())->toBe(2)
        ->and(Http::recorded(fn (Request $request) => str_ends_with($request->url(), 'orders/cancel')))->toHaveCount(2);
});

it('refuses with shiprocket\'s message when the login is rejected', function () {
    fakeShiprocketHttp([], Http::response(['message' => 'Invalid email and password combination'], 400));

    $exception = catchShiprocketException(fn () => app(ShiprocketClient::class)->cancelOrder(9));

    expect($exception)->not->toBeInstanceOf(ShiprocketUnknownOutcomeException::class)
        ->and($exception->getMessage())->toBe('Invalid email and password combination')
        ->and(shiprocketLoginCount())->toBe(1);
});

it('reports an unknown outcome when shiprocket cannot be reached or fails on its side', function (Closure $reply) {
    fakeShiprocketHttp([SHIPROCKET_TEST_API.'orders/create/adhoc' => $reply]);

    expect(fn () => app(ShiprocketClient::class)->createOrder(['order_id' => '1001']))
        ->toThrow(ShiprocketUnknownOutcomeException::class);
})->with([
    'timeout' => [fn () => fn () => throw new ConnectionException('Operation timed out')],
    'server error' => [fn () => fn () => Http::response(['message' => 'Bad gateway'], 502)],
    'rate limited' => [fn () => fn () => Http::response(['message' => 'Too Many Attempts.'], 429)],
]);

it('reports an unknown outcome when the login cannot be reached', function () {
    fakeShiprocketHttp([], fn () => throw new ConnectionException('Operation timed out'));

    expect(fn () => app(ShiprocketClient::class)->cancelOrder(1))
        ->toThrow(ShiprocketUnknownOutcomeException::class);
});

it('refuses with shiprocket\'s message on a client error', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders/create/adhoc' => Http::response(['message' => 'Wrong Pickup location entered.', 'status_code' => 422], 422),
    ]);

    $exception = catchShiprocketException(fn () => app(ShiprocketClient::class)->createOrder(['order_id' => '1001']));

    expect($exception)->not->toBeNull()
        ->and($exception)->not->toBeInstanceOf(ShiprocketUnknownOutcomeException::class)
        ->and($exception->getMessage())->toBe('Wrong Pickup location entered.');
});

it('flattens shiprocket\'s validation errors into the refusal message', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders/create/adhoc' => Http::response([
            'errors' => [
                'billing_phone' => ['The billing phone must be 10 digits.'],
                'order_items.0.sku' => ['The sku field is required.'],
            ],
        ], 422),
    ]);

    $exception = catchShiprocketException(fn () => app(ShiprocketClient::class)->createOrder(['order_id' => '1001']));

    expect($exception->getMessage())
        ->toBe('The billing phone must be 10 digits. The sku field is required.');
});

it('checks serviceability with cod and reads the courier list', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/serviceability/*' => Http::response(['status' => 200, 'data' => [
            'available_courier_companies' => [
                ['courier_name' => 'Prepaid Only', 'cod' => 0],
                ['courier_name' => 'Cod Courier', 'cod' => 1],
            ],
        ]]),
    ]);

    expect(app(ShiprocketClient::class)->checkServiceability('400001', '560001', 0.5, true))
        ->toBe(['deliverable' => true, 'cod' => true]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === SHIPROCKET_TEST_API.'courier/serviceability/?pickup_postcode=400001&delivery_postcode=560001&weight=0.5&cod=1');
});

it('is deliverable without cod when no courier collects cash', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/serviceability/*' => Http::response(['data' => [
            'available_courier_companies' => [['courier_name' => 'Prepaid Only', 'cod' => 0]],
        ]]),
    ]);

    expect(app(ShiprocketClient::class)->checkServiceability('400001', '560001', 1.0, false))
        ->toBe(['deliverable' => true, 'cod' => false]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && str_ends_with($request->url(), '&cod=0'));
});

it('is not deliverable when shiprocket has no courier for the pin', function (Closure $reply) {
    fakeShiprocketHttp([SHIPROCKET_TEST_API.'courier/serviceability/*' => $reply]);

    expect(app(ShiprocketClient::class)->checkServiceability('400001', '799999', 0.5, true))
        ->toBe(['deliverable' => false, 'cod' => false]);
})->with([
    'empty list' => [fn () => fn () => Http::response(['data' => ['available_courier_companies' => []]])],
    'no data' => [fn () => fn () => Http::response(['status' => 404, 'message' => 'No courier available'])],
    '404' => [fn () => fn () => Http::response(['message' => 'Delivery postcode not serviceable', 'status' => 404], 404)],
    '422 message' => [fn () => fn () => Http::response(['message' => 'No courier serviceable'], 422)],
]);

it('refuses a serviceability request shiprocket finds invalid', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/serviceability/*' => Http::response([
            'message' => 'Oops! Invalid Data.',
            'errors' => ['weight' => ['The weight field is required.']],
        ], 422),
    ]);

    $exception = catchShiprocketException(fn () => app(ShiprocketClient::class)->checkServiceability('400001', '560001', 0, true));

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())->toBe('Oops! Invalid Data. The weight field is required.');
});

it('finds an order by its store order number', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders?search=1001' => Http::response(['data' => [
            ['id' => 70, 'channel_order_id' => '10010', 'shipments' => [['id' => 80]]],
            ['id' => 71, 'channel_order_id' => '1001', 'shipments' => [['id' => 81], ['id' => 82]]],
        ]]),
    ]);

    expect(app(ShiprocketClient::class)->findOrderByChannelOrderId('1001'))
        ->toBe(['order_id' => 71, 'shipment_id' => 81]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === SHIPROCKET_TEST_API.'orders?search=1001');
});

it('skips cancelled orders with the same store order number', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders?search=1001' => Http::response(['data' => [
            ['id' => 70, 'channel_order_id' => '1001', 'status' => 'CANCELED', 'shipments' => [['id' => 80]]],
            ['id' => 71, 'channel_order_id' => '1001', 'status' => 'Cancelled', 'shipments' => [['id' => 81]]],
            ['id' => 72, 'channel_order_id' => '1001', 'status' => 'NEW', 'shipments' => [['id' => 82]]],
        ]]),
    ]);

    expect(app(ShiprocketClient::class)->findOrderByChannelOrderId('1001'))
        ->toBe(['order_id' => 72, 'shipment_id' => 82]);
});

it('finds nothing when the only order with that number is cancelled', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders?search=1001' => Http::response(['data' => [
            ['id' => 70, 'channel_order_id' => '1001', 'status' => 'canceled', 'shipments' => [['id' => 80]]],
        ]]),
    ]);

    expect(app(ShiprocketClient::class)->findOrderByChannelOrderId('1001'))->toBeNull();
});

it('finds nothing when no order has that store order number', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders?search=1001' => Http::response(['data' => [
            ['id' => 70, 'channel_order_id' => '10010', 'shipments' => [['id' => 80]]],
        ]]),
    ]);

    expect(app(ShiprocketClient::class)->findOrderByChannelOrderId('1001'))->toBeNull();
});

it('assigns the recommended courier', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/assign/awb' => Http::response(['awb_assign_status' => 1, 'response' => ['data' => [
            'awb_code' => 'AWB123', 'courier_name' => 'Delhivery Surface', 'shipment_id' => 22,
        ]]]),
    ]);

    expect(app(ShiprocketClient::class)->assignAwb(22))
        ->toBe(['awb_code' => 'AWB123', 'courier_name' => 'Delhivery Surface']);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'courier/assign/awb')
        && $request->data() === ['shipment_id' => 22]);
});

it('reads back an awb that is already assigned', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/assign/awb' => Http::response(['message' => 'AWB is already assigned', 'status_code' => 400], 400),
        SHIPROCKET_TEST_API.'shipments/22' => Http::response(['data' => ['id' => 22, 'awb' => 'AWB123', 'courier' => 'Delhivery Surface']]),
    ]);

    expect(app(ShiprocketClient::class)->assignAwb(22))
        ->toBe(['awb_code' => 'AWB123', 'courier_name' => 'Delhivery Surface']);
});

it('refuses when shiprocket assigns no awb', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/assign/awb' => Http::response(['awb_assign_status' => 0, 'response' => ['data' => [
            'awb_assign_error' => 'Insufficient wallet balance',
        ]]]),
    ]);

    $exception = catchShiprocketException(fn () => app(ShiprocketClient::class)->assignAwb(22));

    expect($exception?->getMessage())->toBe('Insufficient wallet balance');
});

it('requests a pickup', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/generate/pickup' => Http::response(['pickup_status' => 1]),
    ]);

    app(ShiprocketClient::class)->requestPickup(22);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'courier/generate/pickup')
        && $request->data() === ['shipment_id' => [22]]);
});

it('treats an already scheduled pickup as done', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/generate/pickup' => Http::response(['message' => 'Already in Pickup Queue.', 'status_code' => 400], 400),
    ]);

    app(ShiprocketClient::class)->requestPickup(22);

    expect(Http::recorded(fn (Request $request) => str_ends_with($request->url(), 'courier/generate/pickup')))->toHaveCount(1);
});

it('refuses a pickup shiprocket rejects for another reason', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/generate/pickup' => Http::response(['message' => 'Pickup location is not verified'], 400),
    ]);

    app(ShiprocketClient::class)->requestPickup(22);
})->throws(ShiprocketException::class, 'Pickup location is not verified');

it('generates the label', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/generate/label' => Http::response(['label_created' => 1, 'label_url' => 'https://labels.example/22.pdf']),
    ]);

    expect(app(ShiprocketClient::class)->generateLabel(22))->toBe('https://labels.example/22.pdf');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'courier/generate/label')
        && $request->data() === ['shipment_id' => [22]]);
});

it('refuses when no label url comes back', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'courier/generate/label' => Http::response(['label_created' => 0, 'not_created' => [22]]),
    ]);

    app(ShiprocketClient::class)->generateLabel(22);
})->throws(ShiprocketException::class, 'Shiprocket error (HTTP 200)');

it('names the http status when a refusal has no message', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders/cancel' => Http::response('', 400),
    ]);

    app(ShiprocketClient::class)->cancelOrder(71);
})->throws(ShiprocketException::class, 'Shiprocket error (HTTP 400)');

it('cancels an order', function () {
    fakeShiprocketHttp([
        SHIPROCKET_TEST_API.'orders/cancel' => Http::response(['status' => 200, 'message' => 'Order cancelled successfully.']),
    ]);

    app(ShiprocketClient::class)->cancelOrder(71);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'orders/cancel')
        && $request->method() === 'POST'
        && $request->data() === ['ids' => [71]]);
});
