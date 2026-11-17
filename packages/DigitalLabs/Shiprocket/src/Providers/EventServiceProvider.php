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
    ];
}
