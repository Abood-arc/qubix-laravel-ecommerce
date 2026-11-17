<?php

namespace DigitalLabs\Shiprocket\Providers;

use DigitalLabs\Shipping\Contracts\DeliveryCheck as DeliveryCheckContract;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Payment\CashOnDelivery;
use DigitalLabs\Shiprocket\Services\DeliveryCheck;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use DigitalLabs\Shiprocket\Services\ShiprocketClient;
use DigitalLabs\Shiprocket\Support\Settings;
use Illuminate\Support\ServiceProvider;

class ShiprocketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/system.php', 'core');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/shiprocket.php', 'shiprocket');

        $this->app->bind(ShiprocketApi::class, fn ($app) => $app->make(
            Settings::fake() ? FakeShiprocketClient::class : ShiprocketClient::class
        ));

        $this->app->bind(DeliveryCheckContract::class, DeliveryCheck::class);

        $this->app->register(EventServiceProvider::class);
    }

    public function boot(): void
    {
        // Always our subclass: it behaves exactly like core COD while Shiprocket is off,
        // so the admin setting is never read from the database at boot.
        config(['payment_methods.cashondelivery.class' => CashOnDelivery::class]);

        $this->loadMigrationsFrom(dirname(__DIR__).'/Database/Migrations');

        $this->loadRoutesFrom(dirname(__DIR__).'/Http/routes.php');

        $this->loadTranslationsFrom(dirname(__DIR__).'/Resources/lang', 'shiprocket');

        $this->loadViewsFrom(dirname(__DIR__).'/Resources/views', 'shiprocket');

        // Task 7 registers the admin order panel on 'qubix.admin.sales.order.right_component.before' here.
    }
}
