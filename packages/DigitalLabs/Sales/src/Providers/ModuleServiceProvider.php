<?php

namespace DigitalLabs\Sales\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Sales\Models\DownloadableLinkPurchased::class,
        \DigitalLabs\Sales\Models\Invoice::class,
        \DigitalLabs\Sales\Models\InvoiceItem::class,
        \DigitalLabs\Sales\Models\Order::class,
        \DigitalLabs\Sales\Models\OrderAddress::class,
        \DigitalLabs\Sales\Models\OrderComment::class,
        \DigitalLabs\Sales\Models\OrderItem::class,
        \DigitalLabs\Sales\Models\OrderPayment::class,
        \DigitalLabs\Sales\Models\OrderTransaction::class,
        \DigitalLabs\Sales\Models\Refund::class,
        \DigitalLabs\Sales\Models\RefundItem::class,
        \DigitalLabs\Sales\Models\Shipment::class,
        \DigitalLabs\Sales\Models\ShipmentItem::class,
    ];
}
