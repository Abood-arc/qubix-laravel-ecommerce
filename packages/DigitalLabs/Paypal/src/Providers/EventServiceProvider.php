<?php

namespace DigitalLabs\Paypal\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use DigitalLabs\Theme\ViewRenderEventManager;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen('qubix.shop.layout.body.after', static function (ViewRenderEventManager $viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('paypal::checkout.onepage.paypal-smart-button');
        });

        Event::listen('sales.invoice.save.after', 'DigitalLabs\Paypal\Listeners\Transaction@saveTransaction');
    }
}
