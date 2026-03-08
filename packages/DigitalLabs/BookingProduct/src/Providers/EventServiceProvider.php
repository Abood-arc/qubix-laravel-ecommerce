<?php

namespace DigitalLabs\BookingProduct\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'checkout.order.save.after' => [
            'DigitalLabs\BookingProduct\Listeners\Order@afterPlaceOrder',
        ],
    ];
}
