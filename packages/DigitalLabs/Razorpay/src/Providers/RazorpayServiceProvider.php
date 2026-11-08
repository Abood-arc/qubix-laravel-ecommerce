<?php

namespace DigitalLabs\Razorpay\Providers;

use Illuminate\Support\ServiceProvider;

class RazorpayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/paymentmethods.php', 'payment_methods');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/system.php', 'core');

        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/Database/Migrations');

        $this->loadRoutesFrom(dirname(__DIR__).'/Http/routes.php');

        $this->loadTranslationsFrom(dirname(__DIR__).'/Resources/lang', 'razorpay');

        $this->loadViewsFrom(dirname(__DIR__).'/Resources/views', 'razorpay');
    }
}
