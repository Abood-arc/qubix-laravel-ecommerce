<?php

use DigitalLabs\Checkout\Models\CartItem;
use DigitalLabs\Product\Models\ProductInventoryIndex;
use DigitalLabs\Product\Repositories\ProductRepository;
use DigitalLabs\Razorpay\Mail\PaymentAlertNotification;
use DigitalLabs\Razorpay\Mail\PaymentRefundedNotification;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Razorpay\Services\FinalizeResult;
use DigitalLabs\Razorpay\Services\PaymentFinalizer;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Sales\Repositories\InvoiceRepository;
use DigitalLabs\Sales\Repositories\OrderRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->configureRazorpay();

    Mail::fake();

    $this->cart = $this->createRazorpayCart();

    $this->payment = $this->createRazorpayPayment($this->cart);

    $this->finalizer = fn () => app(PaymentFinalizer::class);
});

function refundRequests(): int
{
    return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/refund'))->count();
}

it('creates one processing, invoiced order and closes the cart', function () {
    $this->fakeRazorpayApi($this->payment);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    $order = Order::where('cart_id', $this->cart->id)->firstOrFail();

    expect($result->status)->toBe(FinalizeResult::ORDERED)
        ->and($result->order->id)->toBe($order->id)
        ->and($order->status)->toBe('processing')
        ->and($order->invoices()->count())->toBe(1)
        ->and($order->payment->additional['razorpay_order_id'])->toBe($this->payment->razorpay_order_id)
        ->and($this->payment->fresh()->order_id)->toBe($order->id)
        ->and($this->payment->fresh()->status)->toBe(RazorpayPayment::STATUS_PAID)
        ->and($this->cart->fresh()->is_active)->toBeFalsy();

    $this->assertDatabaseHas('order_transactions', ['order_id' => $order->id, 'transaction_id' => 'pay_test', 'payment_method' => 'razorpay']);
});

it('returns the same order when called twice', function () {
    $this->fakeRazorpayApi($this->payment);

    $first = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    $second = ($this->finalizer)()->finalize($this->payment->fresh(), 'pay_test');

    expect($second->status)->toBe(FinalizeResult::ORDERED)
        ->and($second->order->id)->toBe($first->order->id)
        ->and(Order::where('cart_id', $this->cart->id)->count())->toBe(1);
});

it('captures an authorized payment', function () {
    $this->fakeRazorpayApi($this->payment, 'authorized');

    ($this->finalizer)()->finalize($this->payment, 'pay_test');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/capture')
        && $request['amount'] === $this->payment->amount);
});

it('follows the frozen copy when the cart changes during payment', function () {
    $this->fakeRazorpayApi($this->payment);

    $item = CartItem::where('cart_id', $this->cart->id)->firstOrFail();

    $frozenName = $item->name;

    $item->update(['name' => 'Changed in another tab']);

    $this->cart->update(['grand_total' => 99999]);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::ORDERED)
        ->and($result->order->items()->first()->name)->toBe($frozenName)
        ->and(refundRequests())->toBe(0);
});

it('rejects a payment that does not match the row', function (array $remote) {
    Http::fake(['api.razorpay.com/v1/payments/*' => Http::response(array_merge([
        'id' => 'pay_test',
        'order_id' => $this->payment->razorpay_order_id,
        'amount' => $this->payment->amount,
        'currency' => 'INR',
        'status' => 'captured',
    ], $remote))]);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::REJECTED)
        ->and(Order::where('cart_id', $this->cart->id)->exists())->toBeFalse();
})->with([
    'other order' => [['order_id' => 'order_other']],
    'other amount' => [['amount' => 1]],
    'other currency' => [['currency' => 'USD']],
    'not paid' => [['status' => 'failed']],
]);

it('returns pending when razorpay cannot be reached', function () {
    Http::fake(['api.razorpay.com/*' => Http::response([], 500)]);

    expect(($this->finalizer)()->finalize($this->payment, 'pay_test')->status)->toBe(FinalizeResult::PENDING);
});

it('returns pending when the cart lock stays busy', function () {
    $this->fakeRazorpayApi($this->payment);

    $lock = Cache::lock('razorpay:cart:'.$this->cart->id, 60);

    $lock->get();

    expect(($this->finalizer)()->finalize($this->payment, 'pay_test')->status)->toBe(FinalizeResult::PENDING);

    $lock->release();
});

it('refunds when another order already exists for the cart', function () {
    $this->fakeRazorpayApi($this->payment);

    $existing = Order::factory()->create(['cart_id' => $this->cart->id]);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    $payment = $this->payment->fresh();

    expect($result->status)->toBe(FinalizeResult::REFUNDED)
        ->and($payment->status)->toBe(RazorpayPayment::STATUS_REFUNDED)
        ->and($payment->refund_reason)->toBe('already_ordered')
        ->and($payment->razorpay_refund_id)->toBe('rfnd_test')
        ->and(($this->finalizer)()->customerMessage($payment))->toContain((string) $existing->increment_id);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/refund')
        && $request->hasHeader('X-Refund-Idempotency', 'auto-pay_test')
        && $request['amount'] === $this->payment->amount);

    Mail::assertQueued(PaymentRefundedNotification::class);
});

it('adopts its own order after a crash instead of refunding', function () {
    $this->fakeRazorpayApi($this->payment);

    $order = app(OrderRepository::class)->create($this->payment->snapshot);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::ORDERED)
        ->and($result->order->id)->toBe($order->id)
        ->and($order->fresh()->status)->toBe('processing')
        ->and($order->invoices()->count())->toBe(1)
        ->and(refundRequests())->toBe(0);
});

it('refunds after losing a race on the unique index', function () {
    $this->fakeRazorpayApi($this->payment);

    $repository = Mockery::mock(app(OrderRepository::class));

    $repository->shouldReceive('create')->once()->andReturnUsing(function (array $data) {
        Order::factory()->create(['cart_id' => $data['cart_id']]);

        throw new UniqueConstraintViolationException('mysql', 'insert', [], new Exception('duplicate'));
    });

    app()->instance(OrderRepository::class, $repository);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::REFUNDED)
        ->and($this->payment->fresh()->refund_reason)->toBe('already_ordered');
});

it('refunds when the item has sold out', function () {
    $this->fakeRazorpayApi($this->payment);

    $productId = $this->payment->snapshot['items'][array_key_first($this->payment->snapshot['items'])]['product_id'];

    ProductInventoryIndex::where('product_id', $productId)->update(['qty' => 0]);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::REFUNDED)
        ->and($this->payment->fresh()->refund_reason)->toBe('out_of_stock')
        ->and(Order::where('cart_id', $this->cart->id)->exists())->toBeFalse();
});

it('refunds when the order cannot be saved', function () {
    $this->fakeRazorpayApi($this->payment);

    $repository = Mockery::mock(app(OrderRepository::class));

    $repository->shouldReceive('create')->once()->andThrow(new RuntimeException('boom'));

    app()->instance(OrderRepository::class, $repository);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::REFUNDED)
        ->and($this->payment->fresh()->refund_reason)->toBe('order_failed');
});

it('never creates an order for or re-refunds a payment whose refund has started', function (string $status) {
    $this->fakeRazorpayApi($this->payment);

    $this->payment->update([
        'status' => $status,
        'refund_reason' => 'out_of_stock',
        'razorpay_payment_id' => 'pay_test',
    ]);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::REFUNDED)
        ->and($this->payment->fresh()->status)->toBe($status)
        ->and(Order::where('cart_id', $this->cart->id)->exists())->toBeFalse()
        ->and(refundRequests())->toBe(0);
})->with([RazorpayPayment::STATUS_REFUNDING, RazorpayPayment::STATUS_REFUNDED, RazorpayPayment::STATUS_REFUND_FAILED]);

it('alerts the admin when the refund fails', function () {
    $this->fakeRazorpayApi($this->payment, refundStatus: 400);

    Order::factory()->create(['cart_id' => $this->cart->id]);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::REFUNDED)
        ->and($this->payment->fresh()->status)->toBe(RazorpayPayment::STATUS_REFUND_FAILED);

    Mail::assertQueued(PaymentAlertNotification::class);

    Mail::assertNotQueued(PaymentRefundedNotification::class);
});

it('keeps a paid order and alerts the admin when the invoice fails', function () {
    $this->fakeRazorpayApi($this->payment);

    $this->partialMock(InvoiceRepository::class, fn ($mock) => $mock->shouldReceive('create')->once()->andThrow(new RuntimeException('boom')));

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::ORDERED)
        ->and($result->order->status)->toBe('processing')
        ->and(Order::where('cart_id', $this->cart->id)->firstOrFail()->status)->toBe('processing')
        ->and(refundRequests())->toBe(0);

    Mail::assertQueued(PaymentAlertNotification::class);
});

it('refunds when the stock check throws', function () {
    $this->fakeRazorpayApi($this->payment);

    $this->partialMock(ProductRepository::class, fn ($mock) => $mock->shouldReceive('find')->andThrow(new RuntimeException('boom')));

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::REFUNDED)
        ->and($this->payment->fresh()->refund_reason)->toBe('order_failed')
        ->and(Order::where('cart_id', $this->cart->id)->exists())->toBeFalse();
});

it('adopts its own saved order when order creation fails afterwards', function () {
    $this->fakeRazorpayApi($this->payment);

    $real = app(OrderRepository::class);

    $repository = Mockery::mock($real);

    $repository->shouldReceive('create')->once()->andReturnUsing(function (array $data) use ($real) {
        $real->create($data);

        throw new RuntimeException('boom');
    });

    app()->instance(OrderRepository::class, $repository);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::ORDERED)
        ->and($result->order->status)->toBe('processing')
        ->and($this->payment->fresh()->status)->toBe(RazorpayPayment::STATUS_PAID)
        ->and(Order::where('cart_id', $this->cart->id)->count())->toBe(1)
        ->and(refundRequests())->toBe(0);
});

it('retries a refund that stopped halfway once it is stale', function () {
    $seenWhileRefunding = null;

    $paymentId = $this->payment->id;

    Http::fake([
        'api.razorpay.com/v1/payments/*/refund' => function () use (&$seenWhileRefunding, $paymentId) {
            $seenWhileRefunding = RazorpayPayment::find($paymentId)->updated_at;

            return Http::response(['id' => 'rfnd_test', 'status' => 'processed']);
        },
    ]);

    $this->payment->update([
        'status' => RazorpayPayment::STATUS_REFUNDING,
        'refund_reason' => 'out_of_stock',
        'razorpay_payment_id' => 'pay_test',
    ]);

    DB::table('razorpay_payments')->where('id', $paymentId)->update(['updated_at' => now()->subMinutes(6)]);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::REFUNDED)
        ->and($result->refundStarted)->toBeTrue()
        ->and($seenWhileRefunding->gt(now()->subMinute()))->toBeTrue()
        ->and($this->payment->fresh()->status)->toBe(RazorpayPayment::STATUS_REFUNDED)
        ->and(refundRequests())->toBe(1);

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Refund-Idempotency', 'auto-pay_test'));
});

it('leaves a recent refund alone', function () {
    $this->fakeRazorpayApi($this->payment);

    $this->payment->update([
        'status' => RazorpayPayment::STATUS_REFUNDING,
        'refund_reason' => 'out_of_stock',
        'razorpay_payment_id' => 'pay_test',
    ]);

    DB::table('razorpay_payments')->where('id', $this->payment->id)->update(['updated_at' => now()->subMinutes(4)]);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::REFUNDED)
        ->and($result->refundStarted)->toBeFalse()
        ->and($this->payment->fresh()->status)->toBe(RazorpayPayment::STATUS_REFUNDING)
        ->and(refundRequests())->toBe(0);
});

it('invoices a linked order that a crash left without an invoice', function () {
    $this->fakeRazorpayApi($this->payment);

    $order = app(OrderRepository::class)->create($this->payment->snapshot);

    $this->payment->update([
        'order_id' => $order->id,
        'razorpay_payment_id' => 'pay_test',
        'status' => RazorpayPayment::STATUS_PAID,
    ]);

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($result->status)->toBe(FinalizeResult::ORDERED)
        ->and($order->fresh()->status)->toBe('processing')
        ->and($order->invoices()->count())->toBe(1)
        ->and(refundRequests())->toBe(0);

    $this->assertDatabaseHas('order_transactions', ['order_id' => $order->id, 'transaction_id' => 'pay_test']);
});

it('leaves an order that has moved on unchanged when called again', function () {
    $this->fakeRazorpayApi($this->payment);

    $order = ($this->finalizer)()->finalize($this->payment, 'pay_test')->order;

    DB::table('orders')->where('id', $order->id)->update(['status' => 'completed']);

    $result = ($this->finalizer)()->finalize($this->payment->fresh(), 'pay_test');

    expect($result->status)->toBe(FinalizeResult::ORDERED)
        ->and($order->fresh()->status)->toBe('completed')
        ->and($order->invoices()->count())->toBe(1)
        ->and(DB::table('order_transactions')->where('order_id', $order->id)->count())->toBe(1);
});

it('marks a refund that failed later and alerts the admin', function () {
    $this->payment->update(['status' => RazorpayPayment::STATUS_REFUNDED, 'razorpay_payment_id' => 'pay_test']);

    ($this->finalizer)()->recordRefundFailure('pay_test');

    expect($this->payment->fresh()->status)->toBe(RazorpayPayment::STATUS_REFUND_FAILED);

    Mail::assertQueued(PaymentAlertNotification::class, 1);
});

it('alerts without changing the row when an admin refund fails later', function () {
    $this->payment->update(['status' => RazorpayPayment::STATUS_PAID, 'razorpay_payment_id' => 'pay_test']);

    ($this->finalizer)()->recordRefundFailure('pay_test');

    expect($this->payment->fresh()->status)->toBe(RazorpayPayment::STATUS_PAID);

    Mail::assertQueued(PaymentAlertNotification::class, 1);
});

it('does not alert twice or for an unknown payment when a refund fails later', function () {
    $this->payment->update(['status' => RazorpayPayment::STATUS_REFUND_FAILED, 'razorpay_payment_id' => 'pay_test']);

    ($this->finalizer)()->recordRefundFailure('pay_test');

    ($this->finalizer)()->recordRefundFailure('pay_unknown');

    Mail::assertNothingQueued();
});

it('renders both mails', function () {
    $refunded = (new PaymentRefundedNotification('buyer@example.com', 'A Buyer', 'We are returning your payment.', 'Rs 10.00'))->render();

    $alert = (new PaymentAlertNotification('pay_1', 'Rs 10.00', 'invoice_failed'))->render();

    expect($refunded)->toContain('We are returning your payment.')
        ->and($refunded)->toContain(trans('razorpay::app.emails.refunded.timing'))
        ->and($alert)->toContain('pay_1')
        ->and($alert)->toContain(trans('razorpay::app.emails.alert.invoice_failed'));
});

it('keeps an order pending while it is being invoiced so a crash can be repaired', function () {
    $this->fakeRazorpayApi($this->payment);

    $seen = new ArrayObject;

    $this->partialMock(InvoiceRepository::class, fn ($mock) => $mock->shouldReceive('create')->once()->andReturnUsing(function (array $data) use ($seen) {
        $seen['status'] = Order::findOrFail($data['order_id'])->status;

        throw new RuntimeException('boom');
    }));

    $result = ($this->finalizer)()->finalize($this->payment, 'pay_test');

    expect($seen['status'])->toBe('pending')
        ->and($result->order->status)->toBe('processing');
});
