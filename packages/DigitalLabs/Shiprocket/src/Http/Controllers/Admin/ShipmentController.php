<?php

namespace DigitalLabs\Shiprocket\Http\Controllers\Admin;

use DigitalLabs\Admin\Http\Controllers\Controller;
use DigitalLabs\Sales\Contracts\Order;
use DigitalLabs\Sales\Models\Order as OrderModel;
use DigitalLabs\Sales\Repositories\OrderRepository;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Jobs\BookShipment;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Services\ShipmentBooker;
use DigitalLabs\Shiprocket\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * Admin actions on an order's Shiprocket parcel.
 */
class ShipmentController extends Controller
{
    /**
     * A parcel in one of these states can be (re-)booked.
     */
    protected const BOOKABLE = [
        ShiprocketShipment::STATUS_PENDING,
        ShiprocketShipment::STATUS_FAILED,
        ShiprocketShipment::STATUS_CANCELLED,
    ];

    protected const CANCELLABLE = [
        ShiprocketShipment::STATUS_BOOKED,
        ShiprocketShipment::STATUS_PICKUP_SCHEDULED,
    ];

    public function __construct(protected OrderRepository $orderRepository) {}

    /**
     * Whether the order's parcel may be booked now: not booked yet (or failed or cancelled)
     * and something is left to ship. A cancelled booking keeps its store shipment, which
     * re-booking reuses, so that order has nothing left to ship and is still allowed.
     */
    public static function canBook(Order $order, ?ShiprocketShipment $shipment): bool
    {
        if ($shipment && ! in_array($shipment->status, self::BOOKABLE, true)) {
            return false;
        }

        if (in_array($order->status, [OrderModel::STATUS_CANCELED, OrderModel::STATUS_CLOSED, OrderModel::STATUS_FRAUD], true)) {
            return false;
        }

        return $order->canShip()
            || ($shipment?->store_shipment_id && $shipment->status !== ShiprocketShipment::STATUS_PENDING);
    }

    public static function canCancel(?ShiprocketShipment $shipment): bool
    {
        return $shipment && in_array($shipment->status, self::CANCELLABLE, true);
    }

    public function ship(int $id): RedirectResponse
    {
        return $this->book($id);
    }

    public function retry(int $id): RedirectResponse
    {
        return $this->book($id);
    }

    public function cancel(int $id, ShipmentBooker $booker): RedirectResponse
    {
        abort_unless(Settings::enabled(), 404);

        $order = $this->orderRepository->findOrFail($id);

        $shipment = $this->shipmentFor($order);

        abort_unless($shipment, 404);

        if ($shipment->status === ShiprocketShipment::STATUS_CANCELLED) {
            return $this->back($order, 'success', trans('shiprocket::app.admin.cancelled'));
        }

        if ($shipment->isPickedUp()) {
            return $this->back($order, 'error', trans('shiprocket::app.cancel.blocked'));
        }

        abort_unless(self::canCancel($shipment), 404);

        try {
            $booker->cancel($shipment);
        } catch (ShiprocketException $exception) {
            return $this->back($order, 'error', trans('shiprocket::app.cancel.failed', ['reason' => $exception->getMessage()]));
        }

        return $this->back($order, 'success', trans('shiprocket::app.admin.cancelled'));
    }

    /**
     * Send the admin to the parcel's label, asking Shiprocket for it first when booking
     * could not get one.
     */
    public function label(int $id, ShipmentBooker $booker): RedirectResponse
    {
        $order = $this->orderRepository->findOrFail($id);

        $shipment = $this->shipmentFor($order);

        abort_unless($shipment && self::hasLabel($shipment), 404);

        try {
            $url = $booker->label($shipment);
        } catch (ShiprocketException $exception) {
            return $this->back($order, 'error', trans('shiprocket::app.admin.reason', ['reason' => $exception->getMessage()]));
        }

        return redirect()->away($url);
    }

    /**
     * Whether the parcel has a label, or can get one: the courier is booked and the
     * booking was not cancelled (a cancelled AWB's label must not end up on a parcel).
     */
    public static function hasLabel(?ShiprocketShipment $shipment): bool
    {
        return $shipment
            && $shipment->status !== ShiprocketShipment::STATUS_CANCELLED
            && (filled($shipment->label_url) || (filled($shipment->awb_code) && $shipment->shipment_id));
    }

    protected function book(int $id): RedirectResponse
    {
        abort_unless(Settings::enabled(), 404);

        $order = $this->orderRepository->findOrFail($id);

        if (! self::canBook($order, $this->shipmentFor($order))) {
            return $this->back($order, 'error', trans('shiprocket::app.admin.cannot-ship'));
        }

        // Booked by hand: the weight guards are the admin's call (the panel asks first). Sent
        // for the current booking generation, so after a cancel this books the parcel again.
        $generation = (int) $this->shipmentFor($order)?->generation;

        BookShipment::dispatch($order->id, manual: true, generation: $generation);

        return $this->back($order, 'success', trans('shiprocket::app.admin.queued'));
    }

    protected function shipmentFor(Order $order): ?ShiprocketShipment
    {
        return ShiprocketShipment::query()->where('order_id', $order->id)->first();
    }

    protected function back(Order $order, string $type, string $message): RedirectResponse
    {
        session()->flash($type, $message);

        return redirect()->route('admin.sales.orders.view', $order->id);
    }
}
