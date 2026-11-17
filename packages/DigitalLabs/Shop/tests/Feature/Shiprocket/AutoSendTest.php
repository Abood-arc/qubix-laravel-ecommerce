<?php

use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Product\Models\ProductInventory;
use DigitalLabs\Sales\Models\Invoice;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Sales\Models\OrderAddress;
use DigitalLabs\Sales\Models\OrderItem;
use DigitalLabs\Sales\Models\OrderPayment;
use DigitalLabs\Sales\Repositories\InvoiceRepository;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Jobs\BookShipment;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Webkul\Faker\Helpers\Customer as CustomerFaker;
use Webkul\Faker\Helpers\Product as ProductFaker;

function autoSendConfig(string $code, mixed $value): void
{
    CoreConfig::query()->where('code', $code)->get()->each->delete();

    CoreConfig::create(['code' => $code, 'value' => $value, 'channel_code' => 'default']);
}

/**
 * A placed, not yet invoiced order for one stocked product.
 */
function autoSendOrder(string $method = 'razorpay', int $qty = 1, string $type = 'simple'): Order
{
    $product = (new ProductFaker)->getSimpleProductFactory()->create();

    ProductInventory::query()->where('product_id', $product->id)->update(['qty' => 10]);

    $customer = (new CustomerFaker)->factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'status' => 'pending',
        'sub_total' => 1000 * $qty,
        'grand_total' => 1000 * $qty,
        'sub_total_invoiced' => 0,
        'grand_total_invoiced' => 0,
        'base_grand_total_invoiced' => 0,
        'base_sub_total_invoiced' => 0,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'type' => $type,
        'name' => 'Leather Tote',
        'weight' => 500,
        'qty_ordered' => $qty,
        'price' => 1000,
        'base_price' => 1000,
        'total' => 1000 * $qty,
        'base_total' => 1000 * $qty,
        'tax_amount' => 0,
        'base_tax_amount' => 0,
        'discount_amount' => 0,
        'base_discount_amount' => 0,
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

    OrderPayment::factory()->create(['order_id' => $order->id, 'method' => $method]);

    return $order->fresh();
}

function autoSendInvoice(Order $order, ?int $qty = null, ?string $state = null): Invoice
{
    $item = $order->fresh()->items->first();

    return app(InvoiceRepository::class)->create([
        'order_id' => $order->id,
        'invoice' => ['items' => [$item->id => $qty ?? $item->qty_ordered]],
    ], $state);
}

beforeEach(function () {
    Mail::fake();

    autoSendConfig('general.general.locale_options.weight_unit', 'g');
    autoSendConfig('sales.shipping.shiprocket.active', 1);
    autoSendConfig('sales.shipping.shiprocket.auto_send', 1);
    config(['shiprocket.email' => 'api@example.com', 'shiprocket.password' => 'secret']);
});

it('queues the booking of a fully invoiced and paid razorpay order, after the commit', function () {
    Queue::fake();

    $order = autoSendOrder();

    autoSendInvoice($order);

    // QueueFake records a job straight away, so the after-commit part is checked on the job
    // itself here, and for real (sync queue, real transaction) in the next test.
    Queue::assertPushed(BookShipment::class, fn (BookShipment $job) => $job->orderId === $order->id
        && $job->manual === false
        && $job->generation === 0
        && $job->afterCommit === true);
});

it('sends the job for the current booking of a pending row', function () {
    Queue::fake();

    $order = autoSendOrder();

    ShiprocketShipment::create(['order_id' => $order->id, 'generation' => 2]);

    autoSendInvoice($order);

    Queue::assertPushed(BookShipment::class, fn (BookShipment $job) => $job->generation === 2);
});

it('does not send a paid order with nothing to ship', function () {
    Queue::fake();

    $order = autoSendOrder(type: 'virtual');

    autoSendInvoice($order);

    Queue::assertNotPushed(BookShipment::class);

    expect(ShiprocketShipment::where('order_id', $order->id)->exists())->toBeFalse();
});

it('books only once the surrounding transaction commits, and never when it rolls back', function () {
    // The suite's sync queue runs the job in-process, and honours afterCommit: the job runs
    // when the transaction around the invoice commits (the test's own wrapping transaction
    // does not count).
    $api = new class extends FakeShiprocketClient
    {
        public int $created = 0;

        public function createOrder(array $payload): array
        {
            $this->created++;

            return parent::createOrder($payload);
        }
    };

    app()->instance(ShiprocketApi::class, $api);

    $rolledBack = autoSendOrder();

    try {
        DB::transaction(function () use ($rolledBack) {
            autoSendInvoice($rolledBack);

            throw new RuntimeException('payment record failed');
        });
    } catch (RuntimeException) {
    }

    expect($api->created)->toBe(0)
        ->and($rolledBack->invoices()->count())->toBe(0);

    $order = autoSendOrder();

    DB::transaction(function () use ($order, $api) {
        autoSendInvoice($order);

        expect($api->created)->toBe(0)
            ->and(ShiprocketShipment::where('order_id', $order->id)->exists())->toBeFalse();
    });

    expect($api->created)->toBe(1)
        ->and(ShiprocketShipment::where('order_id', $order->id)->value('status'))->toBe(ShiprocketShipment::STATUS_PICKUP_SCHEDULED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_COMPLETED);
});

it('does not send an order paid another way', function (string $method) {
    Queue::fake();

    autoSendInvoice(autoSendOrder($method));

    Queue::assertNotPushed(BookShipment::class);
})->with(['moneytransfer', 'cashondelivery']);

it('does not send an order whose invoice is not paid', function () {
    Queue::fake();

    autoSendInvoice(autoSendOrder(), state: Invoice::STATUS_PENDING);

    Queue::assertNotPushed(BookShipment::class);
});

it('waits until the order is fully invoiced and every invoice is paid', function () {
    Queue::fake();

    $order = autoSendOrder(qty: 2);

    autoSendInvoice($order, qty: 1, state: Invoice::STATUS_PENDING);

    autoSendInvoice($order, qty: 1);

    // Fully invoiced now, but the first invoice is still unpaid.
    Queue::assertNotPushed(BookShipment::class);

    $order->invoices()->update(['state' => Invoice::STATUS_PAID]);

    $partly = autoSendOrder(qty: 2);

    autoSendInvoice($partly, qty: 1);

    Queue::assertNotPushed(BookShipment::class);

    autoSendInvoice($partly, qty: 1);

    Queue::assertPushed(BookShipment::class, fn (BookShipment $job) => $job->orderId === $partly->id);
});

it('does nothing while shiprocket or auto-send is off', function (string $setting) {
    Queue::fake();

    if ($setting === 'credentials') {
        config(['shiprocket.password' => '']);
    } else {
        autoSendConfig('sales.shipping.shiprocket.'.$setting, 0);
    }

    autoSendInvoice(autoSendOrder());

    Queue::assertNotPushed(BookShipment::class);
})->with(['active', 'auto_send', 'credentials']);

it('never sends an order a second time', function (string $status) {
    Queue::fake();

    $order = autoSendOrder();

    ShiprocketShipment::create(['order_id' => $order->id, 'status' => $status]);

    autoSendInvoice($order);

    Queue::assertNotPushed(BookShipment::class);
})->with([
    ShiprocketShipment::STATUS_BOOKING,
    ShiprocketShipment::STATUS_FAILED,
    ShiprocketShipment::STATUS_CANCELLED,
    ShiprocketShipment::STATUS_PICKUP_SCHEDULED,
]);

it('sends an order whose row is still pending', function () {
    Queue::fake();

    $order = autoSendOrder();

    ShiprocketShipment::create(['order_id' => $order->id]);

    autoSendInvoice($order);

    Queue::assertPushed(BookShipment::class);
});
