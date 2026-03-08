<?php

namespace DigitalLabs\Admin\Listeners;

use DigitalLabs\Admin\Mail\Order\InventorySourceNotification;
use DigitalLabs\Admin\Mail\Order\ShippedNotification;
use DigitalLabs\Sales\Contracts\Shipment as ShipmentContract;

class Shipment extends Base
{
    /**
     * After order is created
     *
     * @return void
     */
    public function afterCreated(ShipmentContract $shipment)
    {
        try {
            if (core()->getConfigData('emails.general.notifications.emails.general.notifications.new_shipment_mail_to_admin')) {
                $this->prepareMail($shipment, new ShippedNotification($shipment));
            }

            if (core()->getConfigData('emails.general.notifications.emails.general.notifications.new_inventory_source')) {
                $this->prepareMail($shipment, new InventorySourceNotification($shipment));
            }
        } catch (\Exception $e) {
            report($e);
        }
    }
}
