<?php

namespace DigitalLabs\Checkout\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        'DigitalLabs\Checkout\Listeners\CustomerEventsHandler',
    ];
}
