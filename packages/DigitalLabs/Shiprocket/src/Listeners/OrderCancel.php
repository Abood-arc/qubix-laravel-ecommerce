<?php

namespace DigitalLabs\Shiprocket\Listeners;

use DigitalLabs\Shiprocket\Exceptions\ShiprocketCancelBlockedException;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Services\ShipmentBooker;

/**
 * Cancels the Shiprocket booking together with the order, or stops the order cancel once
 * the courier has the parcel or Shiprocket would not cancel it.
 *
 * Listens to `sales.order.cancel.before`, which core fires before it changes anything, so
 * throwing here leaves the order as it was. The exception renders as a flash error.
 */
class OrderCancel
{
    public function __construct(protected ShipmentBooker $booker) {}

    /**
     * @param  \DigitalLabs\Sales\Contracts\Order  $order
     *
     * @throws ShiprocketCancelBlockedException
     */
    public function handle($order): void
    {
        $shipment = ShiprocketShipment::where('order_id', $order->id)->first();

        if (! $shipment) {
            return;
        }

        if ($shipment->isPickedUp()) {
            throw new ShiprocketCancelBlockedException(trans('shiprocket::app.cancel.blocked'));
        }

        if (! $this->hasLiveBooking($shipment)) {
            return;
        }

        try {
            $this->booker->cancel($shipment);
        } catch (ShiprocketCancelBlockedException $exception) {
            throw $exception;
        } catch (ShiprocketException $exception) {
            throw new ShiprocketCancelBlockedException(
                trans('shiprocket::app.cancel.failed', ['reason' => $exception->getMessage()]),
                0,
                $exception,
            );
        }
    }

    /**
     * A Shiprocket order exists and is not cancelled: booked or scheduled for pickup, or a
     * booking that stopped part way (it may already hold a courier).
     */
    protected function hasLiveBooking(ShiprocketShipment $shipment): bool
    {
        return $shipment->shiprocket_order_id && in_array($shipment->status, [
            ShiprocketShipment::STATUS_BOOKING,
            ShiprocketShipment::STATUS_BOOKED,
            ShiprocketShipment::STATUS_PICKUP_SCHEDULED,
            ShiprocketShipment::STATUS_FAILED,
        ], true);
    }
}
