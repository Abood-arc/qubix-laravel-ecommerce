<?php

namespace DigitalLabs\Shiprocket\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array
     */
    protected $listen = [
        'sales.invoice.save.after' => [
            [\DigitalLabs\Shiprocket\Listeners\AutoSend::class, 'handle'],
        ],
        'sales.order.cancel.before' => [
            [\DigitalLabs\Shiprocket\Listeners\OrderCancel::class, 'handle'],
        ],
        'checkout.cart.payment.save.after' => [
            [\DigitalLabs\Shiprocket\Listeners\RepriceShipping::class, 'handle'],
        ],
        'checkout.cart.shipping_method.resolve' => [
            [\DigitalLabs\Shiprocket\Listeners\ResolveShippingMethod::class, 'handle'],
        ],
    ];
}
