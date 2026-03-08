<?php

namespace DigitalLabs\Category\Providers;

use Illuminate\Support\ServiceProvider;
use DigitalLabs\Category\Models\CategoryProxy;
use DigitalLabs\Category\Observers\CategoryObserver;

class CategoryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        CategoryProxy::observe(CategoryObserver::class);
    }
}
