<?php

namespace DigitalLabs\Shiprocket\Providers;

use DigitalLabs\Shipping\Contracts\DeliveryCheck as DeliveryCheckContract;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Payment\CashOnDelivery;
use DigitalLabs\Shiprocket\Services\DeliveryCheck;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use DigitalLabs\Shiprocket\Services\ShiprocketClient;
use DigitalLabs\Shiprocket\Support\Settings;
use Illuminate\Support\Facades\Event;
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

        $this->registerViewHooks();
    }

    /**
     * The admin order panel and the customer's "Track your parcel" links.
     */
    protected function registerViewHooks(): void
    {
        Event::listen('qubix.admin.sales.order.right_component.before', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('shiprocket::admin.order-panel');
        });

        Event::listen('qubix.shop.emails.orders.shipped.track_number.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('shiprocket::shop.tracking-link-email');
        });

        Event::listen('qubix.shop.customers.account.orders.view.shipments.track_number.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('shiprocket::shop.tracking-link');
        });
    }
}
