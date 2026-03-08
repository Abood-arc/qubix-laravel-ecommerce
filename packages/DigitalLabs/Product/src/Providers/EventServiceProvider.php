<?php

namespace DigitalLabs\Product\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'catalog.product.create.after' => [
            'DigitalLabs\Product\Listeners\Product@afterCreate',
        ],
        'catalog.product.update.after' => [
            'DigitalLabs\Product\Listeners\Product@afterUpdate',
        ],
        'catalog.product.delete.before' => [
            'DigitalLabs\Product\Listeners\Product@beforeDelete',
        ],
        'checkout.order.save.after' => [
            'DigitalLabs\Product\Listeners\Order@afterCancelOrCreate',
        ],
        'sales.order.cancel.after' => [
            'DigitalLabs\Product\Listeners\Order@afterCancelOrCreate',
        ],
        'sales.refund.save.after' => [
            'DigitalLabs\Product\Listeners\Refund@afterCreate',
        ],
    ];
}
