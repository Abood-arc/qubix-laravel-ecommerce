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
     * A booking is running, or stopped part way (it may already hold a Shiprocket order and
     * a courier), or a Shiprocket order exists and is not cancelled.
     *
     * A `booking` row always goes through the booker's cancel, Shiprocket order id or not:
     * the cancel waits for the order's booking lock, so it can never slip past a booking
     * that is about to book a courier. If that booking is still running after the wait,
     * the order cancel is stopped with `cancel.failed` ("could not cancel: Booking…"), not
     * `cancel.blocked`, which tells the admin the courier has the parcel; trying again a
     * moment later succeeds.
     */
    protected function hasLiveBooking(ShiprocketShipment $shipment): bool
    {
        if ($shipment->status === ShiprocketShipment::STATUS_BOOKING) {
            return true;
        }

        return $shipment->shiprocket_order_id && in_array($shipment->status, [
            ShiprocketShipment::STATUS_BOOKED,
            ShiprocketShipment::STATUS_PICKUP_SCHEDULED,
            ShiprocketShipment::STATUS_FAILED,
        ], true);
    }
}
