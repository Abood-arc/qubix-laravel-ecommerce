<?php

use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Inventory\Models\InventorySource;
use DigitalLabs\Product\Models\ProductInventory;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Sales\Models\OrderAddress;
use DigitalLabs\Sales\Models\OrderItem;
use DigitalLabs\Sales\Models\OrderPayment;
use DigitalLabs\Sales\Models\Shipment;
use DigitalLabs\Sales\Repositories\InvoiceRepository;
use DigitalLabs\Sales\Repositories\ShipmentRepository;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketUnknownOutcomeException;
use DigitalLabs\Shiprocket\Jobs\BookShipment;
use DigitalLabs\Shiprocket\Mail\AdminAlert;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use DigitalLabs\Shiprocket\Services\ShipmentBooker;
use DigitalLabs\Shop\Mail\Order\ShippedNotification;
use DigitalLabs\User\Models\Admin;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Webkul\Faker\Helpers\Customer as CustomerFaker;
use Webkul\Faker\Helpers\Product as ProductFaker;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * The deterministic fake, recording every call. A hook replaces one call: it gets the real
 * call as a closure, so it can refuse before Shiprocket acts, or let it act and then lose
 * the answer.
 */
function bookingApi(): object
{
    $api = new class extends FakeShiprocketClient
    {
        public array $calls = [];

        /** @var array<string, Closure[]> */
        public array $hooks = [
            'createOrder' => [], 'findOrderByChannelOrderId' => [], 'assignAwb' => [],
            'requestPickup' => [], 'generateManifest' => [], 'generateLabel' => [], 'cancelOrder' => [],
        ];

        public function on(string $method, Closure $hook): static
        {
            $this->hooks[$method][] = $hook;

            return $this;
        }

        public function count(string $method): int
        {
            return collect($this->calls)->where(0, $method)->count();
        }

        public function args(string $method): array
        {
            return collect($this->calls)->where(0, $method)->pluck(1)->all();
        }

        public function createOrder(array $payload): array
        {
            return $this->record(__FUNCTION__, $payload, fn () => parent::createOrder($payload));
        }

        public function findOrderByChannelOrderId(string $channelOrderId): ?array
        {
            return $this->record(__FUNCTION__, $channelOrderId, fn () => parent::findOrderByChannelOrderId($channelOrderId));
        }

        public function assignAwb(int $shipmentId): array
        {
            return $this->record(__FUNCTION__, $shipmentId, fn () => parent::assignAwb($shipmentId));
        }

        public function requestPickup(int $shipmentId): void
        {
            $this->record(__FUNCTION__, $shipmentId, fn () => parent::requestPickup($shipmentId));
        }

        public function generateManifest(int $shipmentId): ?string
        {
            return $this->record(__FUNCTION__, $shipmentId, fn () => parent::generateManifest($shipmentId));
        }

        public function generateLabel(int $shipmentId): string
        {
            return $this->record(__FUNCTION__, $shipmentId, fn () => parent::generateLabel($shipmentId));
        }

        public function cancelOrder(int $shiprocketOrderId): void
        {
            $this->record(__FUNCTION__, $shiprocketOrderId, fn () => parent::cancelOrder($shiprocketOrderId));
        }

        protected function record(string $method, mixed $argument, Closure $call): mixed
        {
            $this->calls[] = [$method, $argument];

            $hook = array_shift($this->hooks[$method]);

            return $hook ? $hook($call) : $call();
        }
    };

    app()->instance(ShiprocketApi::class, $api);

    return $api;
}

function bookingConfig(string $code, mixed $value): void
{
    CoreConfig::query()->where('code', $code)->get()->each->delete();

    CoreConfig::create(['code' => $code, 'value' => $value, 'channel_code' => 'default']);
}

/**
 * A real order for one stocked product: weight in grams, an Indian address and the
 * payment method. A prepaid order is invoiced (paid) the way Razorpay does it.
 */
function bookingOrder(string $method = 'razorpay', array $item = [], int $stock = 10): Order
{
    $product = (new ProductFaker)->getSimpleProductFactory()->create();

    ProductInventory::query()->where('product_id', $product->id)->update(['qty' => $stock]);

    $customer = (new CustomerFaker)->factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_first_name' => $customer->first_name,
        'customer_last_name' => $customer->last_name,
        'status' => 'pending',
        'sub_total' => 1000,
        'base_sub_total' => 1000,
        'grand_total' => 1000,
        'base_grand_total' => 1000,
        'sub_total_invoiced' => 0,
        'base_sub_total_invoiced' => 0,
        'grand_total_invoiced' => 0,
        'base_grand_total_invoiced' => 0,
        'grand_total_refunded' => 0,
        'sub_total_refunded' => 0,
        'base_sub_total_refunded' => 0,
        'base_currency_code' => 'INR',
        'channel_currency_code' => 'INR',
        'order_currency_code' => 'INR',
    ]);

    OrderItem::factory()->create(array_merge([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'type' => 'simple',
        'name' => 'Leather Tote',
        'weight' => 500,
        'qty_ordered' => 1,
        'price' => 1000,
        'base_price' => 1000,
        'total' => 1000,
        'base_total' => 1000,
        'tax_amount' => 0,
        'base_tax_amount' => 0,
        'discount_amount' => 0,
        'base_discount_amount' => 0,
    ], $item));

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

    OrderPayment::factory()->create(['order_id' => $order->id, 'method' => $method]);

    if ($method !== 'cashondelivery') {
        $order = $order->fresh();

        app(InvoiceRepository::class)->create([
            'order_id' => $order->id,
            'invoice' => ['items' => $order->items->mapWithKeys(fn ($item) => [$item->id => $item->qty_ordered])->all()],
        ]);
    }

    return $order->fresh();
}

function booker(): ShipmentBooker
{
    return app(ShipmentBooker::class);
}

beforeEach(function () {
    Mail::fake();

    bookingConfig('general.general.locale_options.weight_unit', 'g');
    bookingConfig('sales.shipping.shiprocket.pickup_location', 'Pune Warehouse');
    bookingConfig('sales.shipping.shiprocket.length', '30');
    bookingConfig('sales.shipping.shiprocket.breadth', '25');
    bookingConfig('sales.shipping.shiprocket.height', '10');
    bookingConfig('emails.general.notifications.emails.general.notifications.new_shipment', 1);
});

it('books a paid order, ships it in the store and completes it', function () {
    $api = bookingApi();
    $order = bookingOrder();

    expect($order->status)->toBe(Order::STATUS_PROCESSING);

    $row = booker()->book($order);

    $shipment = Shipment::where('order_id', $order->id)->sole();

    expect($row->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->channel_order_id->toBe((string) $order->increment_id)
        ->shiprocket_order_id->toBe(100001)
        ->shipment_id->toBe(200001)
        ->awb_code->toBe('FAKE200001')
        ->courier_name->toBe('Fake Express')
        ->label_url->toBe('https://example.test/labels/200001.pdf')
        ->store_shipment_id->toBe($shipment->id)
        ->pickup_scheduled_at->not->toBeNull()
        ->failure_reason->toBeNull();

    expect($shipment)
        ->carrier_title->toBe('Shiprocket – Fake Express')
        ->track_number->toBe('FAKE200001')
        ->inventory_source_id->toBe(1)
        ->total_qty->toEqual(1);

    expect($api->args('createOrder')[0])
        ->order_id->toBe((string) $order->increment_id)
        ->payment_method->toBe('Prepaid')
        ->weight->toBe(0.5);

    // Nothing could exist in Shiprocket before the first try, so there is no lookup.
    expect($api->count('findOrderByChannelOrderId'))->toBe(0)
        ->and($api->count('requestPickup'))->toBe(1)
        ->and($order->fresh()->status)->toBe(Order::STATUS_COMPLETED)
        ->and($order->items->first()->fresh()->qty_shipped)->toEqual(1);

    Mail::assertQueued(ShippedNotification::class, 1);
    Mail::assertNotQueued(AdminAlert::class);
});

it('books cash on delivery as COD and leaves the order processing until delivery', function () {
    $api = bookingApi();
    $order = bookingOrder('cashondelivery');

    booker()->book($order, manual: true);

    expect($api->args('createOrder')[0]['payment_method'])->toBe('COD')
        ->and(ShiprocketShipment::where('order_id', $order->id)->value('status'))->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PROCESSING);
});

it('books only once however often it is asked', function () {
    $api = bookingApi();
    $order = bookingOrder();

    booker()->book($order);
    booker()->book($order);
    booker()->book($order->fresh(), manual: true);

    expect(collect($api->calls)->pluck(0)->all())
        ->toBe(['createOrder', 'assignAwb', 'requestPickup', 'generateManifest', 'generateLabel'])
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1);
});

it('resumes after a lost answer by finding the order shiprocket created', function () {
    $api = bookingApi()->on('createOrder', function (Closure $call) {
        $call();

        throw new ShiprocketUnknownOutcomeException('Shiprocket error (no response)');
    });
    $order = bookingOrder();

    expect(fn () => booker()->book($order))->toThrow(ShiprocketUnknownOutcomeException::class);

    // The number sent is stored before Shiprocket is asked, so the retry looks for that one.
    expect(ShiprocketShipment::where('order_id', $order->id)->first())
        ->status->toBe(ShiprocketShipment::STATUS_BOOKING)
        ->channel_order_id->toBe((string) $order->increment_id)
        ->shiprocket_order_id->toBeNull();

    $row = booker()->book($order);

    expect($api->count('createOrder'))->toBe(1)
        ->and($api->args('findOrderByChannelOrderId'))->toBe([(string) $order->increment_id])
        ->and($row->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->and($row->shiprocket_order_id)->toBe(100001)
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1);
});

it('resumes after a lost courier answer without creating the order again', function () {
    $api = bookingApi()->on('assignAwb', function (Closure $call) {
        $call();

        throw new ShiprocketUnknownOutcomeException('Shiprocket error (HTTP 504)');
    });
    $order = bookingOrder();

    expect(fn () => booker()->book($order))->toThrow(ShiprocketUnknownOutcomeException::class);

    expect(ShiprocketShipment::where('order_id', $order->id)->first())
        ->status->toBe(ShiprocketShipment::STATUS_BOOKING)
        ->shipment_id->toBe(200001)
        ->awb_code->toBeNull();

    // The client returns an AWB that is already assigned as success.
    $row = booker()->book($order);

    expect($api->count('createOrder'))->toBe(1)
        ->and($api->count('findOrderByChannelOrderId'))->toBe(0)
        ->and($api->count('assignAwb'))->toBe(2)
        ->and($row->awb_code)->toBe('FAKE200001')
        ->and($row->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED);
});

it('resumes a booking that has its courier but no store shipment yet', function () {
    $api = bookingApi()->on('requestPickup', fn () => throw new ShiprocketUnknownOutcomeException('Shiprocket error (HTTP 502)'));
    $order = bookingOrder();

    expect(fn () => booker()->book($order))->toThrow(ShiprocketUnknownOutcomeException::class);

    expect(ShiprocketShipment::where('order_id', $order->id)->value('status'))->toBe(ShiprocketShipment::STATUS_BOOKED)
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(0);

    $row = booker()->book($order);

    expect($row->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->and($api->count('assignAwb'))->toBe(1)
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1);
});

it('ships the order even when shiprocket has no label yet', function (Throwable $failure) {
    Log::spy();

    $api = bookingApi()->on('generateLabel', fn () => throw $failure);
    $order = bookingOrder();

    $row = booker()->book($order);

    // The courier and pickup are booked and the customer is told; the label is fetched
    // when the admin asks for it.
    expect($row->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->label_url->toBeNull()
        ->failure_reason->toBeNull()
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->status)->toBe(Order::STATUS_COMPLETED);

    Log::shouldHaveReceived('warning')->once();

    Mail::assertQueued(ShippedNotification::class, 1);
    Mail::assertNotQueued(AdminAlert::class);

    // Asked again, nothing is booked twice.
    booker()->book($order->fresh());

    expect($api->count('assignAwb'))->toBe(1)
        ->and($api->count('generateLabel'))->toBe(1);
})->with([
    'refused' => [fn () => new ShiprocketException('Label not ready')],
    'no answer' => [fn () => new ShiprocketUnknownOutcomeException('Shiprocket error (HTTP 502)')],
]);

it('generates the manifest right after the pickup request', function () {
    Log::spy();

    $api = bookingApi();
    $order = bookingOrder();

    $row = booker()->book($order);

    expect($api->args('generateManifest'))->toBe([$row->shipment_id]);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message, $context = []) => str_contains($message, 'manifest')
            && ($context['manifest_url'] ?? null) === 'https://example.test/manifests/200001.pdf')
        ->once();
});

it('ships the order even when shiprocket generates no manifest', function (Throwable $failure) {
    Log::spy();

    $api = bookingApi()->on('generateManifest', fn () => throw $failure);
    $order = bookingOrder();

    $row = booker()->book($order);

    // The manifest is paperwork for the courier's pickup: the booking, the store Shipment,
    // the customer's email and the label all go ahead without it.
    expect($row->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->failure_reason->toBeNull()
        ->label_url->toBe('https://example.test/labels/200001.pdf')
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->status)->toBe(Order::STATUS_COMPLETED);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains($message, 'manifest'))
        ->once();

    Mail::assertQueued(ShippedNotification::class, 1);
    Mail::assertNotQueued(AdminAlert::class);

    booker()->book($order->fresh());

    expect($api->count('generateManifest'))->toBe(1)
        ->and($api->count('assignAwb'))->toBe(1);
})->with([
    'refused' => [fn () => new ShiprocketException('Manifest not generated')],
    'no answer' => [fn () => new ShiprocketUnknownOutcomeException('Shiprocket error (HTTP 502)')],
]);

it('fails the booking loudly when the parcel size setting is too small', function () {
    bookingConfig('sales.shipping.shiprocket.height', '0.5');

    $api = bookingApi();
    $order = bookingOrder();

    $row = booker()->book($order);

    expect($row->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_FAILED)
        ->failure_reason->toContain('must be more than 0.5 cm')
        ->and($api->calls)->toBe([]);

    Mail::assertQueued(AdminAlert::class, fn (AdminAlert $mail) => $mail->problem === 'booking_failed');
});

it('marks a refusal failed with shiprocket\'s reason and emails the admin', function () {
    bookingApi()->on('createOrder', fn () => throw new ShiprocketException('Wrong Pickup location entered.'));
    $order = bookingOrder();

    $row = booker()->book($order);

    expect($row->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_FAILED)
        ->failure_reason->toBe('Wrong Pickup location entered.');

    expect(Shipment::where('order_id', $order->id)->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PROCESSING);

    Mail::assertQueued(AdminAlert::class, fn (AdminAlert $mail) => $mail->problem === 'booking_failed'
        && $mail->details === ['reason' => 'Wrong Pickup location entered.']
        && $mail->order->id === $order->id);
});

it('retries a failed booking from where it stopped', function () {
    $api = bookingApi()->on('assignAwb', fn () => throw new ShiprocketException('Insufficient wallet balance'));
    $order = bookingOrder();

    expect(booker()->book($order)->status)->toBe(ShiprocketShipment::STATUS_FAILED);

    $row = booker()->book($order);

    // The Shiprocket order the first try made is reused, not created twice.
    expect($row->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->and($row->failure_reason)->toBeNull()
        ->and($api->count('createOrder'))->toBe(1)
        ->and($api->count('assignAwb'))->toBe(2);
});

it('refuses an address shiprocket cannot use without calling shiprocket', function () {
    $api = bookingApi();
    $order = bookingOrder();
    $order->shipping_address->update(['postcode' => '12345']);

    $row = booker()->book($order->fresh());

    expect($row->status)->toBe(ShiprocketShipment::STATUS_FAILED)
        ->and($row->failure_reason)->toContain('12345')
        ->and($api->calls)->toBe([]);

    Mail::assertQueued(AdminAlert::class, fn (AdminAlert $mail) => $mail->problem === 'booking_failed');
});

it('does not book a parcel outside the weight limits automatically', function (float $grams, string $problem) {
    $api = bookingApi();
    $order = bookingOrder(item: ['weight' => $grams]);

    $row = booker()->book($order);

    expect($row->status)->toBe(ShiprocketShipment::STATUS_FAILED)
        ->and($row->failure_reason)->toBe(trans('shiprocket::app.emails.alert.'.$problem, ['weight' => $grams / 1000]))
        ->and($api->calls)->toBe([]);

    Mail::assertQueued(AdminAlert::class, fn (AdminAlert $mail) => $mail->problem === $problem
        && $mail->details === ['weight' => $grams / 1000]);

    // The admin's button books it anyway.
    expect(booker()->book($order, manual: true)->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED);
})->with([
    'over the 10 kg default' => [10500, 'too_heavy'],
    'under 10 g' => [5, 'too_light'],
]);

it('uses the max weight setting', function () {
    bookingConfig('sales.shipping.shiprocket.max_weight', '0.4');
    bookingApi();

    expect(booker()->book(bookingOrder())->failure_reason)
        ->toBe(trans('shiprocket::app.emails.alert.too_heavy', ['weight' => 0.5]));
});

it('does not book an order that was already shipped another way', function () {
    $api = bookingApi();
    $order = bookingOrder();

    app(ShipmentRepository::class)->create([
        'order_id' => $order->id,
        'shipment' => [
            'source' => 1,
            'carrier_title' => 'Hand delivery',
            'track_number' => '',
            'items' => [$order->items->first()->id => [1 => 1]],
        ],
    ]);

    $row = booker()->book($order->fresh());

    expect($row->status)->toBe(ShiprocketShipment::STATUS_FAILED)
        ->and($row->failure_reason)->toBe(trans('shiprocket::app.admin.cannot-ship'))
        ->and($api->calls)->toBe([]);
});

it('does not book a courier when the order is shipped by hand while shiprocket answers', function () {
    $order = bookingOrder();

    $api = bookingApi()->on('createOrder', function (Closure $call) use ($order) {
        app(ShipmentRepository::class)->create([
            'order_id' => $order->id,
            'shipment' => [
                'source' => 1,
                'carrier_title' => 'Hand delivery',
                'track_number' => '',
                'items' => [$order->items->first()->id => [1 => 1]],
            ],
        ]);

        return $call();
    });

    $row = booker()->book($order);

    expect($row->status)->toBe(ShiprocketShipment::STATUS_FAILED)
        ->and($api->count('assignAwb'))->toBe(0)
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1);
});

it('books again after a cancel with a new order number and updates the same store shipment', function () {
    $api = bookingApi();
    $order = bookingOrder('cashondelivery');

    $first = booker()->book($order, manual: true);
    $shipment = Shipment::where('order_id', $order->id)->sole();

    booker()->cancel($first);

    expect($first->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_CANCELLED)
        ->generation->toBe(1)
        ->and($api->args('cancelOrder'))->toBe([100001])
        ->and($shipment->fresh()->track_number)->toBe('FAKE200001');

    $second = booker()->book($order->fresh(), manual: true, generation: 1);

    expect($second->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->channel_order_id->toBe($order->increment_id.'-2')
        ->shiprocket_order_id->toBe(100002)
        ->awb_code->toBe('FAKE200002')
        ->store_shipment_id->toBe($shipment->id);

    // Every item already counts as shipped (the store Shipment stays), yet the same parcel
    // is sent again: same items, units, weight and COD amounts, under the new number.
    [$firstPayload, $secondPayload] = $api->args('createOrder');

    expect($secondPayload['order_id'])->toBe($order->increment_id.'-2')
        ->and(Arr::except($secondPayload, 'order_id'))->toBe(Arr::except($firstPayload, 'order_id'))
        ->and($secondPayload['weight'])->toBe(0.5)
        ->and($secondPayload['order_items'][0]['units'])->toBe(1)
        ->and($order->items->first()->fresh()->qty_shipped)->toEqual(1);

    expect(Shipment::where('order_id', $order->id)->sole())
        ->id->toBe($shipment->id)
        ->track_number->toBe('FAKE200002')
        ->carrier_title->toBe('Shiprocket – Fake Express');

    // The customer is told about the new AWB too.
    Mail::assertQueued(ShippedNotification::class, 2);

    booker()->cancel($second->fresh());

    expect(booker()->book($order->fresh(), manual: true, generation: 2)->channel_order_id)->toBe($order->increment_id.'-3')
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1);
});

it('never books again from a retry left over from before a cancel', function () {
    bookingConfig('sales.shipping.shiprocket.active', 1);
    config(['shiprocket.email' => 'api@example.com', 'shiprocket.password' => 'secret']);

    $api = bookingApi()->on('requestPickup', fn () => throw new ShiprocketUnknownOutcomeException('Shiprocket error (no response)'));
    $order = bookingOrder('cashondelivery');

    // The admin's Ship: the courier is booked, then the pickup answer is lost, so the queue
    // will run this job again later.
    $job = new BookShipment($order->id, manual: true, generation: 0);

    expect(fn () => app()->call([$job, 'handle']))->toThrow(ShiprocketUnknownOutcomeException::class);

    $row = ShiprocketShipment::where('order_id', $order->id)->sole();

    expect($row->status)->toBe(ShiprocketShipment::STATUS_BOOKED);

    // Before the retry runs, the admin cancels the parcel.
    booker()->cancel($row);

    expect($row->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_CANCELLED)
        ->generation->toBe(1);

    app()->call([$job, 'handle']);

    // A call that does not say which booking it is for never starts a new one either.
    booker()->book($order->fresh(), manual: true);

    expect($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_CANCELLED)
        ->and($api->count('createOrder'))->toBe(1)
        ->and($api->count('assignAwb'))->toBe(1)
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(0);

    // A new Ship after the cancel is for the new booking.
    Queue::fake();

    actingAs(Admin::factory()->create(), 'admin');

    post(route('admin.shiprocket.ship', $order->id))
        ->assertSessionHas('success', trans('shiprocket::app.admin.queued'));

    $fresh = null;

    Queue::assertPushed(BookShipment::class, function (BookShipment $pushed) use (&$fresh) {
        $fresh = $pushed;

        return $pushed->manual && $pushed->generation === 1;
    });

    // Its first try loses Shiprocket's answer, so it is still running when the old job's
    // last try gives up: that must not touch the new booking.
    $api->on('createOrder', function (Closure $call) {
        $call();

        throw new ShiprocketUnknownOutcomeException('Shiprocket error (no response)');
    });

    expect(fn () => app()->call([$fresh, 'handle']))->toThrow(ShiprocketUnknownOutcomeException::class);

    $job->failed(new ShiprocketUnknownOutcomeException('Shiprocket error (no response)'));

    expect($row->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_BOOKING)
        ->generation->toBe(1);

    app()->call([$fresh, 'handle']);

    expect($row->fresh())
        ->status->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->channel_order_id->toBe($order->increment_id.'-2')
        ->and($api->count('createOrder'))->toBe(2)
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1);
});

it('lets only one booking of an order run at a time', function () {
    Sleep::fake(syncWithCarbon: true);

    $order = bookingOrder();
    $parallel = null;

    // A second worker asks for the same order while the first waits for Shiprocket.
    $api = bookingApi()->on('createOrder', function (Closure $call) use ($order, &$parallel) {
        $parallel = booker()->attempt($order);

        return $call();
    });

    $row = booker()->book($order);

    expect($parallel)->toBeNull()
        ->and($row->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->and($api->count('createOrder'))->toBe(1)
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1);
});

it('chooses the inventory source that holds the stock', function () {
    bookingApi();
    $order = bookingOrder(stock: 0);

    $warehouse = InventorySource::factory()->create(['status' => 1]);
    $order->channel->inventory_sources()->attach($warehouse->id);

    ProductInventory::create([
        'product_id' => $order->items->first()->product_id,
        'inventory_source_id' => $warehouse->id,
        'vendor_id' => 0,
        'qty' => 5,
    ]);

    booker()->book($order);

    expect(Shipment::where('order_id', $order->id)->sole()->inventory_source_id)->toBe($warehouse->id)
        ->and(ProductInventory::where('inventory_source_id', $warehouse->id)->value('qty'))->toBe(4);
});

it('books in the background and gives up after the last unanswered try', function () {
    bookingConfig('sales.shipping.shiprocket.active', 1);
    config(['shiprocket.email' => 'api@example.com', 'shiprocket.password' => 'secret']);

    $order = bookingOrder();

    bookingApi()->on('createOrder', fn () => throw new ShiprocketUnknownOutcomeException('Shiprocket error (no response)'));

    $job = new BookShipment($order->id);

    expect($job->maxExceptions)->toBe(3)
        ->and($job->backoff())->toBe([60, 300])
        ->and(fn () => app()->call([$job, 'handle']))->toThrow(ShiprocketUnknownOutcomeException::class);

    $job->failed(new ShiprocketUnknownOutcomeException('Shiprocket error (no response)'));

    expect(ShiprocketShipment::where('order_id', $order->id)->first())
        ->status->toBe(ShiprocketShipment::STATUS_FAILED)
        ->failure_reason->toBe('Shiprocket error (no response)');

    Mail::assertQueued(AdminAlert::class, fn (AdminAlert $mail) => $mail->problem === 'booking_failed');

    app()->call([new BookShipment($order->id, manual: true), 'handle']);

    expect(ShiprocketShipment::where('order_id', $order->id)->value('status'))->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED);
});

it('stops a try before the queue hands the job out again, and keeps trying for half an hour', function () {
    $this->freezeTime();

    $job = new BookShipment(1);

    // The production worker hands a job out again after 90 s (retry_after).
    expect($job->timeout)->toBe(85)
        ->and($job->retryUntil()->getTimestamp())->toBe(now()->addMinutes(30)->getTimestamp())
        // The queue ignores $tries once retryUntil() is set; failed tries are counted instead.
        ->and(property_exists($job, 'tries'))->toBeFalse()
        ->and($job->maxExceptions)->toBe(3);

    // Queued with its deadline, so a busy order's releases never exhaust it.
    $payload = json_decode((fn () => $this->createPayload($job, 'default'))->call(app('queue')->connection('sync')), true);

    expect($payload['retryUntil'])->toBe(now()->addMinutes(30)->getTimestamp())
        ->and($payload['maxExceptions'])->toBe(3)
        ->and($payload['timeout'])->toBe(85);
});

it('puts the job back while another booking of the order runs', function () {
    Sleep::fake(syncWithCarbon: true);
    bookingConfig('sales.shipping.shiprocket.active', 1);
    config(['shiprocket.email' => 'api@example.com', 'shiprocket.password' => 'secret']);

    $api = bookingApi();
    $order = bookingOrder();

    $lock = Cache::lock('shiprocket:book:'.$order->id, 180);
    $lock->get();

    $job = (new BookShipment($order->id))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertReleased(BookShipment::BUSY_RETRY_SECONDS);

    expect($api->calls)->toBe([]);

    $lock->release();
});

it('does nothing in the background while shiprocket is switched off', function () {
    $api = bookingApi();
    $order = bookingOrder();

    app()->call([new BookShipment($order->id), 'handle']);

    expect($api->calls)->toBe([])
        ->and(ShiprocketShipment::where('order_id', $order->id)->exists())->toBeFalse();
});
