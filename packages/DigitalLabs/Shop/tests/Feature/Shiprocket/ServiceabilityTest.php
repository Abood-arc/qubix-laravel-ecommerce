<?php

use DigitalLabs\Checkout\Models\CartAddress;
use DigitalLabs\Checkout\Models\CartItem;
use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketUnknownOutcomeException;
use DigitalLabs\Shiprocket\Payment\CashOnDelivery;
use DigitalLabs\Shiprocket\Services\DeliveryCheck;
use DigitalLabs\Shiprocket\Services\ServiceabilityChecker;
use Illuminate\Support\Facades\Log;

/**
 * Save one admin setting, replacing any saved value (null removes it).
 */
function setShiprocketServiceabilityConfig(string $code, mixed $value): void
{
    CoreConfig::query()->where('code', $code)->get()->each->delete();

    if ($value !== null) {
        CoreConfig::create([
            'code' => $code,
            'value' => $value,
            'channel_code' => core()->getRequestedChannelCode(),
        ]);
    }
}

/**
 * A recording Shiprocket API. By default a PIN starting with 9 has no courier, and a PIN ending
 * in 0 has couriers but none that collects cash (so the COD question finds no courier at all).
 */
function bindShiprocketServiceabilityApi(): object
{
    $api = new class implements ShiprocketApi
    {
        /** @var array<int, array{pickup: string, pin: string, weight: float, cod: bool}> */
        public array $calls = [];

        public function checkLogin(): void
        {
            throw new LogicException('Not used');
        }

        /** Throws on the call with this COD flag (null = both), when set. */
        public ?Throwable $failure = null;

        public ?bool $failOnCod = null;

        public function checkServiceability(string $pickupPin, string $deliveryPin, float $weightKg, bool $cod): array
        {
            $this->calls[] = ['pickup' => $pickupPin, 'pin' => $deliveryPin, 'weight' => $weightKg, 'cod' => $cod];

            if ($this->failure && ($this->failOnCod === null || $this->failOnCod === $cod)) {
                throw $this->failure;
            }

            $couriers = ! str_starts_with($deliveryPin, '9') && ! ($cod && str_ends_with($deliveryPin, '0'));

            return ['deliverable' => $couriers, 'cod' => $couriers && ! str_ends_with($deliveryPin, '0')];
        }

        public function createOrder(array $payload): array
        {
            throw new LogicException('Not used');
        }

        public function findOrderByChannelOrderId(string $channelOrderId): ?array
        {
            throw new LogicException('Not used');
        }

        public function assignAwb(int $shipmentId): array
        {
            throw new LogicException('Not used');
        }

        public function requestPickup(int $shipmentId): void
        {
            throw new LogicException('Not used');
        }

        public function generateManifest(int $shipmentId): ?string
        {
            throw new LogicException('Not used');
        }

        public function listPickupLocations(): array
        {
            throw new LogicException('Not used');
        }

        public function getOrder(int $shiprocketOrderId): array
        {
            throw new LogicException('Not used');
        }

        public function generateLabel(int $shipmentId): string
        {
            throw new LogicException('Not used');
        }

        public function cancelOrder(int $shiprocketOrderId): void
        {
            throw new LogicException('Not used');
        }
    };

    app()->instance(ShiprocketApi::class, $api);

    return $api;
}

function shiprocketServiceabilityChecker(): ServiceabilityChecker
{
    return app(ServiceabilityChecker::class);
}

/**
 * Point the cart's shipping address at a country and PIN code.
 */
function shipShiprocketServiceabilityCartTo($cart, string $country, ?string $postcode): void
{
    CartAddress::where('cart_id', $cart->id)->update(['country' => $country, 'state' => 'MH', 'postcode' => $postcode]);

    cart()->setCart($cart->refresh());
}

function shiprocketCodFor($cart): CashOnDelivery
{
    $cod = new CashOnDelivery;

    cart()->setCart($cart->refresh());

    return $cod;
}

beforeEach(function () {
    config(['shiprocket.email' => 'api@example.test', 'shiprocket.password' => 'secret', 'shiprocket.fake' => false]);

    setShiprocketServiceabilityConfig('sales.shipping.shiprocket.active', '1');
    setShiprocketServiceabilityConfig('sales.shipping.shiprocket.pin_check', '1');
    setShiprocketServiceabilityConfig('sales.shipping.shiprocket.pickup_pin', '411001');

    $this->api = bindShiprocketServiceabilityApi();
});

it('asks once for delivery and once for cash on delivery, then answers from the cache', function () {
    expect(shiprocketServiceabilityChecker()->check('560001', 0.7))->toBe(['deliverable' => true, 'cod' => true]);

    expect($this->api->calls)->toBe([
        ['pickup' => '411001', 'pin' => '560001', 'weight' => 1.0, 'cod' => false],
        ['pickup' => '411001', 'pin' => '560001', 'weight' => 1.0, 'cod' => true],
    ]);

    // Same 0.5 kg slab: no new calls.
    expect(shiprocketServiceabilityChecker()->check('560001', 0.6))->toBe(['deliverable' => true, 'cod' => true])
        ->and($this->api->calls)->toHaveCount(2);
});

it('keys the cache on the pickup PIN, delivery PIN and weight slab', function () {
    $checker = shiprocketServiceabilityChecker();

    $checker->check('560001', 0.5);
    $checker->check('560001', 0.51);
    $checker->check('560002', 0.5);

    setShiprocketServiceabilityConfig('sales.shipping.shiprocket.pickup_pin', '110001');

    $checker->check('560001', 0.5);

    expect(collect($this->api->calls)->map(fn ($call) => [$call['pickup'], $call['pin'], $call['weight']])->unique()->values()->all())->toBe([
        ['411001', '560001', 0.5],
        ['411001', '560001', 1.0],
        ['411001', '560002', 0.5],
        ['110001', '560001', 0.5],
    ])->and($this->api->calls)->toHaveCount(8);
});

it('asks with half a kilo when the cart weighs nothing', function () {
    shiprocketServiceabilityChecker()->check('560001', 0.0);

    expect($this->api->calls[0]['weight'])->toBe(0.5);
});

it('keeps a PIN deliverable when no courier there collects cash', function () {
    expect(shiprocketServiceabilityChecker()->check('560010', 1.0))->toBe(['deliverable' => true, 'cod' => false])
        ->and(shiprocketServiceabilityChecker()->check('560010', 1.0))->toBe(['deliverable' => true, 'cod' => false])
        ->and($this->api->calls)->toHaveCount(2);
});

it('does not ask about cash on delivery where nothing delivers', function () {
    expect(shiprocketServiceabilityChecker()->check('999999', 1.0))->toBe(['deliverable' => false, 'cod' => false])
        ->and(shiprocketServiceabilityChecker()->check('999999', 1.0))->toBe(['deliverable' => false, 'cod' => false])
        ->and($this->api->calls)->toBe([['pickup' => '411001', 'pin' => '999999', 'weight' => 1.0, 'cod' => false]]);
});

it('fails open, logs, and asks again two minutes later when Shiprocket fails', function (Throwable $failure) {
    $this->freezeTime();

    $this->api->failure = $failure;

    Log::spy();

    expect(shiprocketServiceabilityChecker()->check('999999', 1.0))->toBe(['deliverable' => true, 'cod' => true])
        ->and($this->api->calls)->toHaveCount(1);

    Log::shouldHaveReceived('error')->once();

    $this->api->failure = null;

    // The next checkout steps do not wait for Shiprocket again.
    $this->travel(119)->seconds();

    expect(shiprocketServiceabilityChecker()->check('999999', 1.0))->toBe(['deliverable' => true, 'cod' => true])
        ->and($this->api->calls)->toHaveCount(1);

    $this->travel(2)->seconds();

    expect(shiprocketServiceabilityChecker()->check('999999', 1.0))->toBe(['deliverable' => false, 'cod' => false])
        ->and($this->api->calls)->toHaveCount(2);
})->with([
    'no answer' => [fn () => new ShiprocketUnknownOutcomeException('Shiprocket did not answer')],
    'refused' => [fn () => new ShiprocketException('Oops! Invalid Data.')],
]);

it('keeps the delivery answer, and the failed cash on delivery answer only briefly', function () {
    $this->freezeTime();

    $this->api->failure = new ShiprocketUnknownOutcomeException('Shiprocket did not answer');
    $this->api->failOnCod = true;

    Log::spy();

    expect(shiprocketServiceabilityChecker()->check('560010', 1.0))->toBe(['deliverable' => true, 'cod' => true]);

    Log::shouldHaveReceived('error')->once();

    $this->api->failure = null;

    expect(shiprocketServiceabilityChecker()->check('560010', 1.0))->toBe(['deliverable' => true, 'cod' => true])
        ->and(collect($this->api->calls)->pluck('cod')->all())->toBe([false, true]);

    $this->travel(121)->seconds();

    expect(shiprocketServiceabilityChecker()->check('560010', 1.0))->toBe(['deliverable' => true, 'cod' => false])
        ->and(collect($this->api->calls)->pluck('cod')->all())->toBe([false, true, true]);
});

it('weighs the cart in kilograms from the store weight unit', function (?string $unit, float $weight, float $kg) {
    setShiprocketServiceabilityConfig('general.general.locale_options.weight_unit', $unit);

    $cart = $this->createRazorpayCart();

    CartItem::where('cart_id', $cart->id)->update(['weight' => $weight, 'quantity' => 3]);

    expect(shiprocketServiceabilityChecker()->cartWeightKg($cart->refresh()))->toBe($kg);
})->with([
    'grams' => ['g', 250, 0.75],
    'kilograms' => ['kgs', 0.25, 0.75],
    'pounds' => ['lbs', 1, 1.361],
    'unknown unit counts as kg' => ['stone', 0.25, 0.75],
]);

/**
 * Turn the PIN check off one way or another.
 */
function switchShiprocketServiceabilityOff(string $how): void
{
    match ($how) {
        'disabled' => setShiprocketServiceabilityConfig('sales.shipping.shiprocket.active', '0'),
        'pin check off' => setShiprocketServiceabilityConfig('sales.shipping.shiprocket.pin_check', '0'),
        'no API user' => config(['shiprocket.password' => null]),
    };
}

it('lets every address through, without asking, while Shiprocket is off', function (string $how) {
    switchShiprocketServiceabilityOff($how);

    $cart = $this->createRazorpayCart();

    shipShiprocketServiceabilityCartTo($cart, 'IN', '999999');

    expect(app(DeliveryCheck::class)->check($cart->shipping_address, $cart))->toBeNull()
        ->and($this->api->calls)->toBe([]);
})->with(['disabled', 'pin check off', 'no API user']);

it('only checks Indian addresses with a postcode', function (string $country, ?string $postcode) {
    $cart = $this->createRazorpayCart();

    shipShiprocketServiceabilityCartTo($cart, $country, $postcode);

    expect(app(DeliveryCheck::class)->check($cart->shipping_address, $cart))->toBeNull()
        ->and($this->api->calls)->toBe([]);
})->with([
    'abroad' => ['AE', '999999'],
    'no postcode' => ['IN', null],
]);

it('refuses a PIN code that is not a valid Indian PIN', function () {
    $cart = $this->createRazorpayCart();

    shipShiprocketServiceabilityCartTo($cart, 'IN', '01234');

    expect(app(DeliveryCheck::class)->check($cart->shipping_address, $cart))
        ->toBe(trans('shiprocket::app.errors.invalid-pin', ['pin' => '01234']))
        ->and($this->api->calls)->toBe([]);
});

it('refuses a PIN code Shiprocket cannot deliver to and accepts one it can', function () {
    $cart = $this->createRazorpayCart();

    shipShiprocketServiceabilityCartTo($cart, 'in', '999 999');

    expect(app(DeliveryCheck::class)->check($cart->shipping_address, $cart))
        ->toBe(trans('shiprocket::app.checkout.not-deliverable', ['pin' => '999 999']))
        ->and($this->api->calls[0]['pin'])->toBe('999999');

    shipShiprocketServiceabilityCartTo($cart, 'IN', '560010');

    expect(app(DeliveryCheck::class)->check($cart->shipping_address, $cart))->toBeNull();
});

it('offers cash on delivery only where a courier collects cash', function () {
    $cart = $this->createRazorpayCart();

    shipShiprocketServiceabilityCartTo($cart, 'IN', '560010');

    $cod = shiprocketCodFor($cart);

    expect($cod->isAvailable())->toBeFalse()
        ->and($cod->getUnavailableReason())->toBe(trans('shiprocket::app.checkout.cod-unavailable', ['pin' => '560010']));

    shipShiprocketServiceabilityCartTo($cart, 'IN', '560001');

    $cod = shiprocketCodFor($cart);

    expect($cod->isAvailable())->toBeTrue()
        ->and($cod->getUnavailableReason())->toBeNull();
});

it('reads the billing address when the cart has no shipping address', function () {
    $cart = $this->createRazorpayCart();

    shipShiprocketServiceabilityCartTo($cart, 'IN', '560010');

    CartAddress::where('cart_id', $cart->id)->where('address_type', CartAddress::ADDRESS_TYPE_SHIPPING)->delete();

    expect(shiprocketCodFor($cart)->getUnavailableReason())
        ->toBe(trans('shiprocket::app.checkout.cod-unavailable', ['pin' => '560010']));
});

it('leaves cash on delivery to the core rules when the PIN check does not apply', function (string $case) {
    $cart = $this->createRazorpayCart();

    shipShiprocketServiceabilityCartTo($cart, 'IN', '560010');

    match ($case) {
        'abroad' => shipShiprocketServiceabilityCartTo($cart, 'AE', '560010'),
        'invalid PIN' => shipShiprocketServiceabilityCartTo($cart, 'IN', '056001'),
        default => switchShiprocketServiceabilityOff($case),
    };

    $cod = shiprocketCodFor($cart);

    expect($cod->isAvailable())->toBeTrue()
        ->and($cod->getUnavailableReason())->toBeNull()
        ->and($this->api->calls)->toBe([]);
})->with(['disabled', 'pin check off', 'no API user', 'abroad', 'invalid PIN']);

it('stays hidden without a reason when core cash on delivery is unavailable', function () {
    config(['payment_methods.cashondelivery.active' => false]);

    setShiprocketServiceabilityConfig('sales.payment_methods.cashondelivery.active', '0');

    $cart = $this->createRazorpayCart();

    shipShiprocketServiceabilityCartTo($cart, 'IN', '560010');

    $cod = shiprocketCodFor($cart);

    expect((bool) $cod->isAvailable())->toBeFalse()
        ->and($cod->getUnavailableReason())->toBeNull()
        ->and($this->api->calls)->toBe([]);
});
