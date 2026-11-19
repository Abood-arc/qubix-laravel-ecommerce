<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Product\Models\ProductInventoryProxy;
use DigitalLabs\Sales\Contracts\Order;
use DigitalLabs\Sales\Contracts\Shipment;
use DigitalLabs\Sales\Models\OrderProxy;
use DigitalLabs\Sales\Repositories\ShipmentRepository;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketCancelBlockedException;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketUnknownOutcomeException;
use DigitalLabs\Shiprocket\Mail\AdminAlert;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Support\RateCode;
use DigitalLabs\Shiprocket\Support\Settings;
use DigitalLabs\Shop\Listeners\Shipment as ShipmentEmail;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Books one order with Shiprocket: order, courier (AWB) and pickup, then the store Shipment
 * that tells the customer, then (best effort) the manifest Shiprocket asks for after a
 * pickup request, and the label.
 *
 * Every step is saved on the order's row as soon as Shiprocket confirms it, so a booking
 * that stopped half way (timeout, worker restart) resumes where it stopped instead of
 * booking a second parcel. One booking per order runs at a time.
 *
 * Every cancel starts a new booking generation (the row's `generation`). A booking job
 * carries the generation it was sent for, and a job left over from before a cancel does
 * nothing: only a new request (an admin's Ship after the cancel) books the parcel again.
 */
class ShipmentBooker
{
    /**
     * How long one booking may hold the order. Longer than the worst booking (the job is
     * stopped after 85 s, see BookShipment) and than the queue's 90 s retry_after, so a job
     * the queue hands out again while the first run is still busy waits instead of booking
     * in parallel.
     */
    protected const LOCK_SECONDS = 180;

    /**
     * How long a second booking (or a cancel) waits for a running one before giving up.
     */
    protected const LOCK_WAIT_SECONDS = 3;

    /**
     * Below this the product weights are almost certainly missing (10 g).
     */
    protected const MIN_WEIGHT_KG = 0.01;

    protected const CARRIER_PREFIX = 'Shiprocket – ';

    public function __construct(
        protected ShiprocketApi $api,
        protected ParcelBuilder $parcels,
        protected ShipmentRepository $shipmentRepository,
    ) {}

    /**
     * Book the order, or resume its booking. Returns the row unchanged when another booking
     * of the same order is running.
     *
     * Shiprocket refusals and order problems leave the row `failed` with the reason and
     * email the admin. An unknown outcome leaves it `booking` and is rethrown, so the
     * caller can try again later.
     *
     * A request for an earlier booking generation (a retry queued before a cancel) leaves
     * the row as it is, and so does a request without a generation once the parcel was
     * cancelled: booking again after a cancel needs the current generation.
     *
     * @param  bool  $manual  an admin asked for it: the weight limits do not apply
     * @param  int|null  $generation  the booking generation the request was made for
     *
     * @throws ShiprocketUnknownOutcomeException
     */
    public function book(Order $order, bool $manual = false, ?int $generation = null): ShiprocketShipment
    {
        return $this->attempt($order, $manual, $generation)
            ?? ShiprocketShipment::firstOrNew(['order_id' => $order->id]);
    }

    /**
     * Like book(), but null when another booking of the same order holds the lock.
     *
     * @throws ShiprocketUnknownOutcomeException
     */
    public function attempt(Order $order, bool $manual = false, ?int $generation = null): ?ShiprocketShipment
    {
        try {
            return $this->lock($order->id)
                ->block(static::LOCK_WAIT_SECONDS, fn () => $this->bookLocked($order->id, $manual, $generation));
        } catch (LockTimeoutException) {
            return null;
        }
    }

    /**
     * Cancel the Shiprocket order of a parcel the courier has not picked up yet. The store
     * Shipment stays (core cannot delete one); booking again updates it.
     *
     * @throws ShiprocketCancelBlockedException when the courier already has the parcel
     * @throws ShiprocketException when Shiprocket did not cancel it (the admin is emailed)
     */
    public function cancel(ShiprocketShipment $shipment): void
    {
        try {
            $this->lock($shipment->order_id)
                ->block(static::LOCK_WAIT_SECONDS, fn () => $this->cancelLocked($shipment));
        } catch (LockTimeoutException) {
            throw new ShiprocketException(trans('shiprocket::app.admin.status.booking'));
        }
    }

    /**
     * Give up on a booking that kept ending without a clear answer (the queue's last try).
     * A job of an earlier generation gives up on nothing: the booking now running is not its own.
     */
    public function markFailed(int $orderId, string $reason, ?int $generation = null): void
    {
        $row = ShiprocketShipment::where('order_id', $orderId)->first();

        if (
            ! $row
            || ($generation !== null && $generation !== $row->generation)
            || $row->status === ShiprocketShipment::STATUS_CANCELLED
            || $this->isDone($row)
        ) {
            return;
        }

        $this->fail($row, 'booking_failed', $reason);
    }

    /**
     * Fetch and store the label of a booked parcel that has none yet, and return its URL.
     *
     * @throws ShiprocketException when Shiprocket has no label for it (yet)
     */
    public function label(ShiprocketShipment $row): string
    {
        if (! $row->label_url) {
            if (! $row->shipment_id || ! $row->awb_code) {
                throw new ShiprocketException(trans('shiprocket::app.admin.cannot-ship'));
            }

            $row->update(['label_url' => $this->api->generateLabel($row->shipment_id)]);
        }

        return $row->label_url;
    }

    protected function lock(int $orderId): Lock
    {
        return Cache::lock('shiprocket:book:'.$orderId, static::LOCK_SECONDS);
    }

    protected function bookLocked(int $orderId, bool $manual, ?int $generation): ShiprocketShipment
    {
        $row = ShiprocketShipment::firstOrCreate(['order_id' => $orderId]);

        if (! $this->isCurrent($row, $generation)) {
            return $row;
        }

        if ($this->isDone($row)) {
            return $row;
        }

        $order = $this->freshOrder($orderId);

        $storeShipment = $this->storeShipment($row, $order);

        try {
            $parcelOrder = $this->parcelOrder($order, $storeShipment);

            $this->ensureShippable($order, $parcelOrder, $storeShipment);

            $sourceId = $storeShipment ? null : $this->inventorySourceId($parcelOrder);

            $weight = $this->parcels->weightKg($parcelOrder);

            if (! $manual && $problem = $this->weightProblem($weight)) {
                $details = ['weight' => $weight];

                return $this->fail($row, $problem, trans('shiprocket::app.emails.alert.'.$problem, $details), $details);
            }

            $payload = $this->parcels->build($parcelOrder);

            $mayExistAlready = $this->startBooking($row, $order);

            $payload['order_id'] = $row->channel_order_id;

            $this->createShiprocketOrder($row, $payload, $mayExistAlready);

            $this->assignCourier($row, $orderId, $storeShipment);

            $this->api->requestPickup($row->shipment_id);

            $this->recordStoreShipment($row, $orderId, $storeShipment, $sourceId);
        } catch (ShiprocketUnknownOutcomeException $exception) {
            throw $exception;
        } catch (ShiprocketException $exception) {
            return $this->fail($row, 'booking_failed', $exception->getMessage());
        }

        $this->tryManifest($row);

        $this->tryLabel($row);

        return $row;
    }

    /**
     * Whether a request for $generation may work on the row now (see book()). A request
     * that may not is logged and left alone.
     */
    protected function isCurrent(ShiprocketShipment $row, ?int $generation): bool
    {
        $stale = $generation !== null && $generation !== $row->generation;

        if (! $stale && ($generation !== null || $row->status !== ShiprocketShipment::STATUS_CANCELLED)) {
            return true;
        }

        Log::info('Shiprocket booking request skipped: it is not for the current booking of the order.', [
            'order_id' => $row->order_id,
            'status' => $row->status,
            'generation' => $row->generation,
            'requested_generation' => $generation,
        ]);

        return false;
    }

    /**
     * Shiprocket's pickup docs: "You will have to call the 'Generate Manifest' API after the
     * successful response of this API." It runs once the store Shipment is saved, so it can
     * never hold the booking up; when Shiprocket refuses or does not answer, the reason is
     * logged and the manifest can be made in Shiprocket's panel.
     */
    protected function tryManifest(ShiprocketShipment $row): void
    {
        $context = ['order_id' => $row->order_id, 'shipment_id' => $row->shipment_id];

        try {
            $url = $this->api->generateManifest($row->shipment_id);
        } catch (ShiprocketException $exception) {
            Log::warning('Shiprocket manifest not generated; generate it in the Shiprocket panel before the pickup.', [
                ...$context,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        Log::info('Shiprocket manifest generated.', [...$context, 'manifest_url' => $url]);
    }

    /**
     * The label is not needed to ship: courier, pickup and customer email are done. When
     * Shiprocket has none yet the row stays without one, and the admin's "Download label"
     * asks again.
     */
    protected function tryLabel(ShiprocketShipment $row): void
    {
        try {
            $this->label($row);
        } catch (ShiprocketException $exception) {
            Log::warning('Shiprocket label not generated; it is fetched when the admin downloads it.', [
                'order_id' => $row->order_id,
                'shipment_id' => $row->shipment_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Every step is done (pickup and store Shipment), or the parcel has moved on since. A
     * `booked` row still needs its pickup and store Shipment; cancelled and failed
     * bookings may be booked again.
     */
    protected function isDone(ShiprocketShipment $row): bool
    {
        return ! $row->isTerminal()
            && ShiprocketShipment::rank($row->status) >= ShiprocketShipment::rank(ShiprocketShipment::STATUS_PICKUP_SCHEDULED);
    }

    protected function freshOrder(int $orderId): Order
    {
        return OrderProxy::modelClass()::query()->findOrFail($orderId);
    }

    /**
     * The store Shipment an earlier (now cancelled) booking created, when booking again.
     */
    protected function storeShipment(ShiprocketShipment $row, Order $order): ?Shipment
    {
        return $row->store_shipment_id
            ? $order->shipments()->find($row->store_shipment_id)
            : null;
    }

    /**
     * The order the parcel is built from. Booking again after a cancel ships what the
     * earlier store Shipment holds, so its quantities are taken back out of the top-level
     * items' `qty_shipped` (all that ParcelBuilder and canShip() read) on a separate copy
     * of the order that is never saved.
     */
    protected function parcelOrder(Order $order, ?Shipment $storeShipment): Order
    {
        if (! $storeShipment) {
            return $order;
        }

        $copy = $this->freshOrder($order->id);

        $shippedHere = $storeShipment->items->groupBy('order_item_id')->map->sum('qty');

        foreach ($copy->items as $item) {
            $item->qty_shipped = max(0, $item->qty_shipped - ($shippedHere[$item->id] ?? 0));
        }

        return $copy;
    }

    /**
     * Only an order with something left to ship, and without a store Shipment made some
     * other way, is booked. Booking again after a cancel keeps its own Shipment.
     *
     * @throws ShiprocketException
     */
    protected function ensureShippable(Order $order, Order $parcelOrder, ?Shipment $storeShipment): void
    {
        $otherShipments = $order->shipments()
            ->when($storeShipment, fn ($query) => $query->whereKeyNot($storeShipment->id))
            ->exists();

        if ($otherShipments || ! $parcelOrder->canShip()) {
            throw new ShiprocketException(trans('shiprocket::app.admin.cannot-ship'));
        }
    }

    /**
     * The same check against the order as it is now: it may have been shipped by hand or
     * cancelled while Shiprocket was answering.
     *
     * @throws ShiprocketException
     */
    protected function ensureShippableNow(int $orderId, ?Shipment $storeShipment): void
    {
        $order = $this->freshOrder($orderId);

        $storeShipment = $storeShipment ? $order->shipments()->find($storeShipment->id) : null;

        $this->ensureShippable($order, $this->parcelOrder($order, $storeShipment), $storeShipment);
    }

    /**
     * The admin alert for a parcel outside the automatic weight limits, if it is.
     */
    protected function weightProblem(float $weightKg): ?string
    {
        return match (true) {
            $weightKg > Settings::maxWeightKg() => 'too_heavy',
            $weightKg < self::MIN_WEIGHT_KG => 'too_light',
            default => null,
        };
    }

    /**
     * Mark the row as booking and choose the order number sent to Shiprocket.
     *
     * The number is saved before Shiprocket is asked, so a resumed booking looks for the
     * same one. Booking again after a cancel starts a new Shiprocket order with a new
     * number (`{increment id}-2`, `-3`, …): Shiprocket may refuse a reused one, and its
     * search never returns a cancelled order.
     *
     * @return bool whether an earlier try may have created the Shiprocket order already
     */
    protected function startBooking(ShiprocketShipment $row, Order $order): bool
    {
        $mayExistAlready = in_array($row->status, [
            ShiprocketShipment::STATUS_BOOKING,
            ShiprocketShipment::STATUS_FAILED,
        ], true);

        if ($row->status === ShiprocketShipment::STATUS_CANCELLED) {
            $row->fill([
                'shiprocket_order_id' => null,
                'shipment_id' => null,
                'awb_code' => null,
                'courier_name' => null,
                'label_url' => null,
                'pickup_scheduled_at' => null,
                'channel_order_id' => $row->channel_order_id
                    ? $this->nextChannelOrderId($order, $row->channel_order_id)
                    : null,
            ]);
        }

        $row->fill([
            'status' => ShiprocketShipment::STATUS_BOOKING,
            'failure_reason' => null,
            'channel_order_id' => $row->channel_order_id ?: (string) $order->increment_id,
        ])->save();

        return $mayExistAlready;
    }

    protected function nextChannelOrderId(Order $order, string $previous): string
    {
        $base = (string) $order->increment_id;

        $number = preg_match('/^'.preg_quote($base, '/').'-(\d+)$/', $previous, $matches)
            ? (int) $matches[1] + 1
            : 2;

        return $base.'-'.$number;
    }

    /**
     * Create the Shiprocket order, or adopt the one an earlier try created before its
     * answer was lost.
     */
    protected function createShiprocketOrder(ShiprocketShipment $row, array $payload, bool $mayExistAlready): void
    {
        if ($row->shiprocket_order_id) {
            return;
        }

        $ids = ($mayExistAlready ? $this->api->findOrderByChannelOrderId($row->channel_order_id) : null)
            ?? $this->api->createOrder($payload);

        $row->update(['shiprocket_order_id' => $ids['order_id'], 'shipment_id' => $ids['shipment_id']]);
    }

    /**
     * Book the courier. The order is checked once more first: the AWB is the step that
     * costs money.
     */
    protected function assignCourier(ShiprocketShipment $row, int $orderId, ?Shipment $storeShipment): void
    {
        if ($row->awb_code) {
            return;
        }

        $this->ensureShippableNow($orderId, $storeShipment);

        $paidFor = RateCode::parse(OrderProxy::modelClass()::whereKey($orderId)->value('shipping_method'))['courier_id'] ?? null;

        $swapped = false;

        try {
            $awb = $this->api->assignAwb($row->shipment_id, $paidFor);
        } catch (ShiprocketUnknownOutcomeException $exception) {
            // Maybe booked: the retry asks for the same courier again.
            throw $exception;
        } catch (ShiprocketException $exception) {
            if ($paidFor === null) {
                throw $exception;
            }

            // The courier stopped taking this parcel since checkout: ship it anyway, with the
            // courier Shiprocket picks, and keep a note for the admin.
            Log::warning('Shiprocket refused the courier the customer paid for; booking its own choice instead.', [
                'order_id' => $orderId,
                'courier_id' => $paidFor,
                'reason' => $exception->getMessage(),
            ]);

            $awb = $this->api->assignAwb($row->shipment_id);

            $swapped = true;
        }

        $row->update([
            'awb_code' => $awb['awb_code'],
            'courier_name' => $awb['courier_name'],
            'requested_courier_id' => $swapped ? $paidFor : null,
            'status' => ShiprocketShipment::STATUS_BOOKED,
        ]);
    }

    /**
     * Create the store Shipment (the customer's "shipped" email, and the order becomes
     * Completed once it is also fully invoiced), or point the earlier one at the new
     * courier and AWB and email the customer again.
     */
    protected function recordStoreShipment(ShiprocketShipment $row, int $orderId, ?Shipment $storeShipment, ?int $sourceId): void
    {
        $carrierTitle = self::CARRIER_PREFIX.$row->courier_name;

        $rebooking = $storeShipment !== null;

        $storeShipment = DB::transaction(function () use ($row, $orderId, $storeShipment, $sourceId, $carrierTitle) {
            $this->ensureShippableNow($orderId, $storeShipment);

            if ($storeShipment) {
                $storeShipment->update(['carrier_title' => $carrierTitle, 'track_number' => $row->awb_code]);
            } else {
                $storeShipment = $this->shipmentRepository->create([
                    'order_id' => $orderId,
                    'shipment' => [
                        'source' => $sourceId,
                        'carrier_title' => $carrierTitle,
                        'track_number' => $row->awb_code,
                        'items' => $this->shipmentItems($this->freshOrder($orderId), $sourceId),
                    ],
                ]);
            }

            $row->update([
                'status' => ShiprocketShipment::STATUS_PICKUP_SCHEDULED,
                'pickup_scheduled_at' => now(),
                'store_shipment_id' => $storeShipment->id,
            ]);

            return $storeShipment;
        });

        // A new Shipment emails the customer through core's own listener; send that email again.
        if ($rebooking) {
            app(ShipmentEmail::class)->afterCreated($storeShipment->fresh());
        }
    }

    /**
     * Every top-level item with something left to ship, in the shape the admin's
     * "Create shipment" form posts: [order item id => [source id => qty]].
     */
    protected function shipmentItems(Order $order, int $sourceId): array
    {
        return $order->items
            ->filter(fn ($item) => $item->qty_to_ship > 0)
            ->mapWithKeys(fn ($item) => [$item->id => [$sourceId => $item->qty_to_ship]])
            ->all();
    }

    /**
     * The inventory source the parcel leaves from: among the order channel's active
     * sources, the first that has enough stock of every item, else the first that stocks
     * every item at all, else the channel's first source.
     *
     * @throws ShiprocketException when the channel has no inventory source
     */
    protected function inventorySourceId(Order $order): int
    {
        $sources = $order->channel?->inventory_sources()->orderBy('id')->get() ?? collect();

        $active = $sources->where('status', 1)->values();

        $candidates = $active->isNotEmpty() ? $active : $sources;

        if ($candidates->isEmpty()) {
            throw new ShiprocketException(trans('shiprocket::app.admin.cannot-ship'));
        }

        $needed = $this->stockNeeded($order);

        $stock = ProductInventoryProxy::modelClass()::query()
            ->whereIn('inventory_source_id', $candidates->pluck('id'))
            ->whereIn('product_id', $needed->keys())
            ->where('vendor_id', 0)
            ->get()
            ->groupBy('inventory_source_id');

        $holds = fn ($source, bool $enough) => $needed->every(function ($qty, $productId) use ($stock, $source, $enough) {
            $rows = ($stock[$source->id] ?? collect())->where('product_id', $productId);

            return $rows->isNotEmpty() && (! $enough || $rows->sum('qty') >= $qty);
        });

        $source = $candidates->first(fn ($source) => $holds($source, true))
            ?? $candidates->first(fn ($source) => $holds($source, false))
            ?? $candidates->first();

        return $source->id;
    }

    /**
     * Quantity to take from stock per product id: a configurable or bundle takes it from
     * its child products, like core's shipment does.
     */
    protected function stockNeeded(Order $order): Collection
    {
        $needed = collect();

        foreach ($order->items as $item) {
            if ($item->qty_to_ship <= 0) {
                continue;
            }

            $lines = $item->getTypeInstance()->isComposite()
                ? $item->children->map(fn ($child) => [
                    $child->product_id,
                    $item->qty_ordered ? $child->qty_ordered / $item->qty_ordered * $item->qty_to_ship : $item->qty_to_ship,
                ])
                : collect([[$item->product_id, $item->qty_to_ship]]);

            foreach ($lines as [$productId, $qty]) {
                if ($productId) {
                    $needed[$productId] = ($needed[$productId] ?? 0) + $qty;
                }
            }
        }

        return $needed;
    }

    protected function cancelLocked(ShiprocketShipment $shipment): void
    {
        $shipment->refresh();

        if ($shipment->status === ShiprocketShipment::STATUS_CANCELLED) {
            return;
        }

        if ($shipment->isPickedUp()) {
            throw new ShiprocketCancelBlockedException(trans('shiprocket::app.cancel.blocked'));
        }

        if ($shipment->shiprocket_order_id) {
            try {
                $this->api->cancelOrder($shipment->shiprocket_order_id);
            } catch (ShiprocketException $exception) {
                $this->alert($shipment, 'cancel_failed', [
                    'awb' => $shipment->awb_code ?: '-',
                    'reason' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }

        // A new generation: no booking job queued before this cancel can book the parcel again.
        $shipment->update([
            'status' => ShiprocketShipment::STATUS_CANCELLED,
            'generation' => $shipment->generation + 1,
        ]);
    }

    protected function fail(ShiprocketShipment $row, string $problem, string $reason, array $details = []): ShiprocketShipment
    {
        $row->update([
            'status' => ShiprocketShipment::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);

        $this->alert($row, $problem, $details ?: ['reason' => $reason]);

        return $row;
    }

    protected function alert(ShiprocketShipment $row, string $problem, array $details): void
    {
        try {
            Mail::queue(new AdminAlert($this->freshOrder($row->order_id), $problem, $details));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
