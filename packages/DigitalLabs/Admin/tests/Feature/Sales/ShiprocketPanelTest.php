<?php

use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Jobs\BookShipment;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Services\ShipmentBooker;
use DigitalLabs\Shiprocket\Support\Settings;
use DigitalLabs\User\Models\Admin as AdminModel;
use DigitalLabs\User\Models\Role;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

// The Shop suite's Shiprocket order fixtures (one-item orders, store shipment, Shiprocket row).
require_once __DIR__.'/../../../../Shop/tests/Feature/Shiprocket/fixtures/delivery.php';

function shiprocketPanelAdmin(array $permissions): AdminModel
{
    $role = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return AdminModel::factory()->create(['role_id' => $role->id]);
}

function viewShiprocketPanel(Order $order)
{
    return get(route('admin.sales.orders.view', $order->id))->assertOk();
}

beforeEach(function () {
    enableShiprocketForDelivery();

    shiprocketDeliveryConfig('sales.shipping.shiprocket.max_weight', '10');

    shiprocketDeliveryConfig('general.general.locale_options.weight_unit', 'kgs');
});

it('hides the panel while shiprocket is off', function () {
    shiprocketDeliveryConfig('sales.shipping.shiprocket.active', '0');

    $this->loginAsAdmin();

    viewShiprocketPanel(shiprocketDeliveryOrder(shipped: false))
        ->assertDontSee('id="shiprocket-panel"', false)
        ->assertDontSeeText(trans('shiprocket::app.admin.ship'));
});

it('offers to ship an order that is not sent yet', function () {
    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder(shipped: false);

    viewShiprocketPanel($order)
        ->assertSee('id="shiprocket-panel"', false)
        ->assertSeeText(trans('shiprocket::app.admin.not-sent'))
        ->assertSee(route('admin.shiprocket.ship', $order->id), false)
        ->assertSeeText(trans('shiprocket::app.admin.ship'))
        ->assertDontSee(route('admin.shiprocket.cancel', $order->id), false)
        ->assertDontSee(trans('shiprocket::app.admin.confirm-heavy', ['weight' => 1]));
});

it('asks before booking a parcel outside the weight limits', function () {
    shiprocketDeliveryConfig('sales.shipping.shiprocket.max_weight', '0.5');

    $this->loginAsAdmin();

    viewShiprocketPanel(shiprocketDeliveryOrder(shipped: false))
        ->assertSee(trans('shiprocket::app.admin.confirm-heavy', ['weight' => 1]));
});

it('shows the failure reason and a retry button', function () {
    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder(shipped: false);

    ShiprocketShipment::create([
        'order_id' => $order->id,
        'status' => ShiprocketShipment::STATUS_FAILED,
        'failure_reason' => 'Pickup location not found',
    ]);

    viewShiprocketPanel($order)
        ->assertSeeText(trans('shiprocket::app.admin.status.failed'))
        ->assertSeeText(trans('shiprocket::app.admin.reason', ['reason' => 'Pickup location not found']))
        ->assertSee(route('admin.shiprocket.retry', $order->id), false)
        ->assertDontSee(route('admin.shiprocket.ship', $order->id), false);
});

it('shows the courier, awb, tracking, label and cancel once booked', function () {
    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder();

    $row = shiprocketDeliveryRow($order);
    $row->update(['label_url' => 'https://labels.example.com/1.pdf']);

    viewShiprocketPanel($order)
        ->assertSeeText(trans('shiprocket::app.admin.status.pickup_scheduled'))
        ->assertSeeText('Delhivery')
        ->assertSeeText($row->awb_code)
        ->assertSee(Settings::trackingUrl($row->awb_code), false)
        ->assertSee(route('admin.shiprocket.label', $order->id), false)
        ->assertSee(route('admin.shiprocket.cancel', $order->id), false)
        ->assertSee(trans('shiprocket::app.admin.confirm-cancel'))
        ->assertDontSee(route('admin.shiprocket.ship', $order->id), false)
        ->assertDontSee(route('admin.shiprocket.retry', $order->id), false);
});

it('shows no buttons once the parcel is delivered', function () {
    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder();

    shiprocketDeliveryRow($order, ShiprocketShipment::STATUS_DELIVERED);

    viewShiprocketPanel($order)
        ->assertSeeText(trans('shiprocket::app.admin.status.delivered'))
        ->assertDontSee(route('admin.shiprocket.ship', $order->id), false)
        ->assertDontSee(route('admin.shiprocket.retry', $order->id), false)
        ->assertDontSee(route('admin.shiprocket.cancel', $order->id), false);
});

it('hides the buttons from an admin who cannot create shipments', function () {
    $this->loginAsAdmin(shiprocketPanelAdmin(['sales', 'sales.orders', 'sales.orders.view']));

    $order = shiprocketDeliveryOrder(shipped: false);

    viewShiprocketPanel($order)
        ->assertSee('id="shiprocket-panel"', false)
        ->assertDontSee(route('admin.shiprocket.ship', $order->id), false);
});

it('queues a manual booking and says so', function () {
    Queue::fake();

    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder(shipped: false);

    post(route('admin.shiprocket.ship', $order->id))
        ->assertRedirect(route('admin.sales.orders.view', $order->id))
        ->assertSessionHas('success', trans('shiprocket::app.admin.queued'));

    Queue::assertPushed(BookShipment::class, fn (BookShipment $job) => $job->orderId === $order->id && $job->manual === true);
});

it('retries a failed booking', function () {
    Queue::fake();

    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder(shipped: false);

    ShiprocketShipment::create(['order_id' => $order->id, 'status' => ShiprocketShipment::STATUS_FAILED]);

    post(route('admin.shiprocket.retry', $order->id))
        ->assertRedirect(route('admin.sales.orders.view', $order->id))
        ->assertSessionHas('success', trans('shiprocket::app.admin.queued'));

    Queue::assertPushed(BookShipment::class, fn (BookShipment $job) => $job->orderId === $order->id && $job->manual === true);
});

it('re-books a cancelled parcel whose store shipment stays', function () {
    Queue::fake();

    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder();

    shiprocketDeliveryRow($order, ShiprocketShipment::STATUS_CANCELLED);

    post(route('admin.shiprocket.ship', $order->id))
        ->assertSessionHas('success', trans('shiprocket::app.admin.queued'));

    Queue::assertPushed(BookShipment::class);
});

it('refuses to book a parcel that is booked, in flight or has nothing to ship', function (string $route, Closure $arrange) {
    Queue::fake();

    $this->loginAsAdmin();

    $order = $arrange();

    post(route($route, $order->id))
        ->assertRedirect(route('admin.sales.orders.view', $order->id))
        ->assertSessionHas('error', trans('shiprocket::app.admin.cannot-ship'));

    Queue::assertNotPushed(BookShipment::class);
})->with([
    'booked' => ['admin.shiprocket.ship', function () {
        $order = shiprocketDeliveryOrder();
        shiprocketDeliveryRow($order);

        return $order;
    }],
    'booking in flight' => ['admin.shiprocket.retry', function () {
        $order = shiprocketDeliveryOrder(shipped: false);
        ShiprocketShipment::create(['order_id' => $order->id, 'status' => ShiprocketShipment::STATUS_BOOKING]);

        return $order;
    }],
    'shipped by hand' => ['admin.shiprocket.ship', fn () => shiprocketDeliveryOrder()],
    'closed order' => ['admin.shiprocket.ship', function () {
        $order = shiprocketDeliveryOrder(shipped: false);
        $order->update(['status' => Order::STATUS_CLOSED]);

        return $order;
    }],
]);

it('is not there while shiprocket is off', function () {
    Queue::fake();

    shiprocketDeliveryConfig('sales.shipping.shiprocket.active', '0');

    $this->loginAsAdmin();

    post(route('admin.shiprocket.ship', shiprocketDeliveryOrder(shipped: false)->id))->assertNotFound();

    Queue::assertNotPushed(BookShipment::class);
});

it('cancels a booked parcel', function () {
    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder();
    $row = shiprocketDeliveryRow($order);

    $booker = Mockery::mock(ShipmentBooker::class);
    $booker->shouldReceive('cancel')->once()->withArgs(fn ($given) => $given->is($row));
    app()->instance(ShipmentBooker::class, $booker);

    post(route('admin.shiprocket.cancel', $order->id))
        ->assertRedirect(route('admin.sales.orders.view', $order->id))
        ->assertSessionHas('success', trans('shiprocket::app.admin.cancelled'));
});

it('shows why a cancel failed', function () {
    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder();
    shiprocketDeliveryRow($order);

    $booker = Mockery::mock(ShipmentBooker::class);
    $booker->shouldReceive('cancel')->once()->andThrow(new ShiprocketException('Shipment already picked up'));
    app()->instance(ShipmentBooker::class, $booker);

    post(route('admin.shiprocket.cancel', $order->id))
        ->assertRedirect(route('admin.sales.orders.view', $order->id))
        ->assertSessionHas('error', trans('shiprocket::app.cancel.failed', ['reason' => 'Shipment already picked up']));
});

it('does not cancel a parcel the courier already has', function () {
    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder();
    shiprocketDeliveryRow($order, ShiprocketShipment::STATUS_IN_TRANSIT);

    $booker = Mockery::mock(ShipmentBooker::class);
    $booker->shouldNotReceive('cancel');
    app()->instance(ShipmentBooker::class, $booker);

    post(route('admin.shiprocket.cancel', $order->id))
        ->assertSessionHas('error', trans('shiprocket::app.cancel.blocked'));
});

it('sends the admin to the label, or 404 when there is none', function () {
    $this->loginAsAdmin();

    $order = shiprocketDeliveryOrder();

    get(route('admin.shiprocket.label', $order->id))->assertNotFound();

    $row = shiprocketDeliveryRow($order);

    get(route('admin.shiprocket.label', $order->id))->assertNotFound();

    $row->update(['label_url' => 'https://labels.example.com/1.pdf']);

    get(route('admin.shiprocket.label', $order->id))->assertRedirect('https://labels.example.com/1.pdf');
});

it('lets only admins who may create shipments ship, retry or cancel', function (string $route) {
    Queue::fake();

    $order = shiprocketDeliveryOrder(shipped: false);

    $this->loginAsAdmin(shiprocketPanelAdmin(['sales', 'sales.orders', 'sales.orders.view', 'sales.shipments', 'sales.shipments.view']));

    post(route($route, $order->id))->assertUnauthorized();

    Queue::assertNotPushed(BookShipment::class);

    $this->loginAsAdmin(shiprocketPanelAdmin(['sales', 'sales.shipments', 'sales.shipments.create']));

    expect(post(route($route, $order->id))->status())->not->toBeIn([401, 403]);
})->with(['admin.shiprocket.ship', 'admin.shiprocket.retry', 'admin.shiprocket.cancel']);

it('lets only admins who may view shipments download the label', function () {
    $order = shiprocketDeliveryOrder();
    shiprocketDeliveryRow($order)->update(['label_url' => 'https://labels.example.com/1.pdf']);

    $this->loginAsAdmin(shiprocketPanelAdmin(['sales', 'sales.orders', 'sales.orders.view']));

    get(route('admin.shiprocket.label', $order->id))->assertUnauthorized();

    $this->loginAsAdmin(shiprocketPanelAdmin(['sales', 'sales.shipments', 'sales.shipments.view']));

    get(route('admin.shiprocket.label', $order->id))->assertRedirect('https://labels.example.com/1.pdf');
});
