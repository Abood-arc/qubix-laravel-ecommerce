<?php

namespace DigitalLabs\Product\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use DigitalLabs\Product\Console\Commands\Indexer;
use DigitalLabs\Product\Models\ProductProxy;
use DigitalLabs\Product\Observers\ProductObserver;

class ProductServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        include __DIR__.'/../Http/helpers.php';

        $this->registerConfig();

        $this->registerCommands();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'product');

        ProductProxy::observe(ProductObserver::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('indexer:index --type=price')->dailyAt('00:01');
        });

        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Register configuration.
     */
    public function registerConfig(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/product_types.php', 'product_types');
    }

    /**
     * Register the console commands of this package.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([Indexer::class]);
        }
    }
}
