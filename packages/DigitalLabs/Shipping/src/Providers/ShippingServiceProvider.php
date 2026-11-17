<?php

namespace DigitalLabs\Shipping\Providers;

use DigitalLabs\Shipping\Contracts\DeliveryCheck;
use DigitalLabs\Shipping\DeliveryCheck\AlwaysDeliverable;
use Illuminate\Support\ServiceProvider;

class ShippingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        include __DIR__.'/../Http/helpers.php';

        $this->registerConfig();

        // Every address is deliverable unless a shipping integration rebinds this.
        $this->app->bindIf(DeliveryCheck::class, AlwaysDeliverable::class);
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/carriers.php', 'carriers'
        );
    }
}
