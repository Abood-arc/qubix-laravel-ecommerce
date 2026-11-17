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

    public function label(int $id): RedirectResponse
    {
        $order = $this->orderRepository->findOrFail($id);

        $url = $this->shipmentFor($order)?->label_url;

        abort_unless(filled($url), 404);

        return redirect()->away($url);
    }

    protected function book(int $id): RedirectResponse
    {
        abort_unless(Settings::enabled(), 404);

        $order = $this->orderRepository->findOrFail($id);

        if (! self::canBook($order, $this->shipmentFor($order))) {
            return $this->back($order, 'error', trans('shiprocket::app.admin.cannot-ship'));
        }

        // Booked by hand: the weight guards are the admin's call (the panel asks first).
        BookShipment::dispatch($order->id, manual: true);

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
