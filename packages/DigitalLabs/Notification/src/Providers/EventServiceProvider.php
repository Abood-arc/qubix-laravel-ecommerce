<?php

namespace DigitalLabs\Notification\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen('checkout.order.save.after', 'DigitalLabs\Notification\Listeners\Order@createOrder');

        Event::listen('sales.order.update-status.after', 'DigitalLabs\Notification\Listeners\Order@updateOrder');
    }
}
