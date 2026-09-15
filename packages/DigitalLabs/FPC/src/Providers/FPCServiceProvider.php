<?php

namespace DigitalLabs\FPC\Providers;

use Illuminate\Support\ServiceProvider;
use DigitalLabs\FPC\Support\CacheClearer;

class FPCServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CacheClearer::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->register(EventServiceProvider::class);

        $this->app->terminating(function () {
            $this->app->make(CacheClearer::class)->reset();
        });
    }
}
