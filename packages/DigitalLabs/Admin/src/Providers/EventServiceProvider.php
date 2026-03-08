<?php

namespace DigitalLabs\Admin\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use DigitalLabs\Admin\Listeners\Admin;
use DigitalLabs\Admin\Listeners\Customer;
use DigitalLabs\Admin\Listeners\GDPR;
use DigitalLabs\Admin\Listeners\Invoice;
use DigitalLabs\Admin\Listeners\Order;
use DigitalLabs\Admin\Listeners\Refund;
use DigitalLabs\Admin\Listeners\Shipment;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'customer.create.after' => [
            [Customer::class, 'afterCreated'],
        ],

        'customer.gdpr-request.create.after' => [
            [GDPR::class, 'afterGdprRequestCreated'],
        ],

        'customer.gdpr-request.update.after' => [
            [GDPR::class, 'afterGdprRequestUpdated'],
        ],

        'admin.password.update.after' => [
            [Admin::class, 'afterPasswordUpdated'],
        ],

        'checkout.order.save.after' => [
            [Order::class, 'afterCreated'],
        ],

        'sales.order.cancel.after' => [
            [Order::class, 'afterCanceled'],
        ],

        'sales.invoice.save.after' => [
            [Invoice::class, 'afterCreated'],
        ],

        'sales.shipment.save.after' => [
            [Shipment::class, 'afterCreated'],
        ],

        'sales.refund.save.after' => [
            [Refund::class, 'afterCreated'],
        ],
    ];
}
