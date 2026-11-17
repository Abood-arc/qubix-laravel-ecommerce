<?php

use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Product\Models\ProductInventory;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Sales\Models\OrderAddress;
use DigitalLabs\Sales\Models\OrderItem;
use DigitalLabs\Sales\Models\OrderPayment;
use DigitalLabs\Sales\Models\Shipment;
use DigitalLabs\Sales\Repositories\OrderRepository;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketCancelBlockedException;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Mail\AdminAlert;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use DigitalLabs\Shiprocket\Services\ShipmentBooker;
use DigitalLabs\User\Models\Admin;
use Illuminate\Support\Facades\Mail;
use Webkul\Faker\Helpers\Customer as CustomerFaker;
use Webkul\Faker\Helpers\Product as ProductFaker;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * The deterministic fake, recording cancels and creates, and refusing a cancel on request.
 */
function cancelApi(?string $refuseCancelWith = null): object
{
    $api = new class extends FakeShiprocketClient
    {
        public ?string $refuseCancelWith = null;

        public array $cancelled = [];

        public int $created = 0;

        public function createOrder(array $payload): array
        {
            $this->created++;

            return parent::createOrder($payload);
        }

        public function cancelOrder(int $shiprocketOrderId): void
        {
            if ($this->refuseCancelWith) {
                throw new ShiprocketException($this->refuseCancelWith);
            }

            $this->cancelled[] = $shiprocketOrderId;

            parent::cancelOrder($shiprocketOrderId);
        }
    };

    $api->refuseCancelWith = $refuseCancelWith;

    app()->instance(ShiprocketApi::class, $api);

    return $api;
}

/**
 * A cash on delivery order (cancellable: nothing invoiced) for one stocked product.
 */
function cancellableOrder(): Order
{
    $product = (new ProductFaker)->getSimpleProductFactory()->create();

    ProductInventory::query()->where('product_id', $product->id)->update(['qty' => 10]);

    $customer = (new CustomerFaker)->factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'status' => 'pending',
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'type' => 'simple',
        'name' => 'Leather Tote',
        'weight' => 500,
        'qty_ordered' => 1,
        'price' => 1000,
        'total' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    foreach ([OrderAddress::ADDRESS_TYPE_BILLING, OrderAddress::ADDRESS_TYPE_SHIPPING] as $type) {
        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'address_type' => $type,
            'address' => '12 MG Road',
            'city' => 'Pune',
            'state' => 'MH',
            'postcode' => '411001',
            'country' => 'IN',
            'phone' => '9876543210',
        ]);
    }

    OrderPayment::factory()->create(['order_id' => $order->id, 'method' => 'cashondelivery']);

    return $order->fresh();
}

function bookedCancelOrder(): array
{
    $order = cancellableOrder();

    $row = app(ShipmentBooker::class)->book($order, manual: true);

    expect($row->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED);

    return [$order->fresh(), $row];
}

function cancelAsAdmin(Order $order)
{
    actingAs(Admin::factory()->create(), 'admin');

    return post(route('admin.sales.orders.cancel', $order->id));
}

beforeEach(function () {
    Mail::fake();

    CoreConfig::query()->where('code', 'general.general.locale_options.weight_unit')->get()->each->delete();
    CoreConfig::create(['code' => 'general.general.locale_options.weight_unit', 'value' => 'g', 'channel_code' => 'default']);
});

it('cancels the shiprocket booking with the order before pickup', function () {
    $api = cancelApi();
    [$order, $row] = bookedCancelOrder();

    cancelAsAdmin($order)
        ->assertRedirect(route('admin.sales.orders.view', $order->id))
        ->assertSessionHas('success');

    expect($api->cancelled)->toBe([$row->shiprocket_order_id])
        ->and($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_CANCELLED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_CANCELED)
        // Core cannot delete a Shipment; booking again would update this one.
        ->and(Shipment::where('order_id', $order->id)->count())->toBe(1);
});

it('keeps the order when shiprocket will not cancel, and tells the admin', function () {
    cancelApi(refuseCancelWith: 'Order cannot be cancelled after pickup is generated');
    [$order, $row] = bookedCancelOrder();

    cancelAsAdmin($order)
        ->assertRedirect()
        ->assertSessionHas('error', trans('shiprocket::app.cancel.failed', [
            'reason' => 'Order cannot be cancelled after pickup is generated',
        ]));

    expect($order->fresh()->status)->toBe(Order::STATUS_PROCESSING)
        ->and($order->items->first()->fresh()->qty_canceled)->toEqual(0)
        ->and($row->fresh()->status)->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED);

    Mail::assertQueued(AdminAlert::class, fn (AdminAlert $mail) => $mail->problem === 'cancel_failed'
        && $mail->details === ['awb' => $row->awb_code, 'reason' => 'Order cannot be cancelled after pickup is generated']);
});

it('refuses to cancel once the courier has the parcel', function (string $status) {
    $api = cancelApi();
    [$order, $row] = bookedCancelOrder();

    $row->update(['status' => $status]);

    // Core fires the event before it changes anything, so the throw leaves the order as it was.
    expect(fn () => app(OrderRepository::class)->cancel($order))
        ->toThrow(ShiprocketCancelBlockedException::class, trans('shiprocket::app.cancel.blocked'));

    expect($order->fresh()->status)->toBe(Order::STATUS_PROCESSING)
        ->and($order->items->first()->fresh()->qty_canceled)->toEqual(0)
        ->and($api->cancelled)->toBe([]);

    cancelAsAdmin($order)
        ->assertRedirect()
        ->assertSessionHas('error', trans('shiprocket::app.cancel.blocked'));

    expect($order->fresh()->status)->toBe(Order::STATUS_PROCESSING);
})->with([
    ShiprocketShipment::STATUS_IN_TRANSIT,
    ShiprocketShipment::STATUS_OUT_FOR_DELIVERY,
    ShiprocketShipment::STATUS_DELIVERED,
    ShiprocketShipment::STATUS_RTO,
]);

it('cancels an order that was never booked without asking shiprocket', function (?string $status) {
    $api = cancelApi();
    $order = cancellableOrder();

    if ($status) {
        ShiprocketShipment::create(['order_id' => $order->id, 'status' => $status]);
    }

    expect(app(OrderRepository::class)->cancel($order))->toBeTrue()
        ->and($order->fresh()->status)->toBe(Order::STATUS_CANCELED)
        ->and($api->cancelled)->toBe([]);
})->with([
    'no row' => [null],
    'pending' => [ShiprocketShipment::STATUS_PENDING],
    'cancelled' => [ShiprocketShipment::STATUS_CANCELLED],
]);

it('also cancels a shiprocket order whose booking stopped part way', function (string $status) {
    $api = cancelApi();
    $order = cancellableOrder();

    ShiprocketShipment::create([
        'order_id' => $order->id,
        'status' => $status,
        'channel_order_id' => (string) $order->increment_id,
        'shiprocket_order_id' => 555,
        'shipment_id' => 777,
    ]);

    expect(app(OrderRepository::class)->cancel($order))->toBeTrue()
        ->and($api->cancelled)->toBe([555])
        ->and(ShiprocketShipment::where('order_id', $order->id)->value('status'))->toBe(ShiprocketShipment::STATUS_CANCELLED);
})->with([ShiprocketShipment::STATUS_BOOKING, ShiprocketShipment::STATUS_FAILED]);

it('does not book a cancelled order again', function () {
    $api = cancelApi();
    [$order] = bookedCancelOrder();

    app(OrderRepository::class)->cancel($order);

    $row = app(ShipmentBooker::class)->book($order->fresh(), manual: true);

    expect($row->status)->toBe(ShiprocketShipment::STATUS_FAILED)
        ->and($row->failure_reason)->toBe(trans('shiprocket::app.admin.cannot-ship'))
        ->and($api->created)->toBe(1);
});
