<?php

namespace DigitalLabs\Shop\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use DigitalLabs\Core\Http\Middleware\PreventRequestsDuringMaintenance;
use DigitalLabs\Shop\Http\Middleware\AuthenticateCustomer;
use DigitalLabs\Shop\Http\Middleware\CacheResponse;
use DigitalLabs\Shop\Http\Middleware\Currency;
use DigitalLabs\Shop\Http\Middleware\Locale;
use DigitalLabs\Shop\Http\Middleware\Theme;

class ShopServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();
    }

    /**
     * Bootstrap services.
     */
    public function boot(Router $router): void
    {
        $router->middlewareGroup('shop', [
            Theme::class,
            Locale::class,
            Currency::class,
        ]);

        $router->aliasMiddleware('theme', Theme::class);
        $router->aliasMiddleware('locale', Locale::class);
        $router->aliasMiddleware('currency', Currency::class);
        $router->aliasMiddleware('cache.response', CacheResponse::class);
        $router->aliasMiddleware('customer', AuthenticateCustomer::class);

        Route::middleware(['web', 'shop', PreventRequestsDuringMaintenance::class])->group(__DIR__.'/../Routes/web.php');
        Route::middleware(['web', 'shop', PreventRequestsDuringMaintenance::class])->group(__DIR__.'/../Routes/api.php');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'shop');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'shop');

        Paginator::defaultView('shop::partials.pagination');
        Paginator::defaultSimpleView('shop::partials.pagination');

        Blade::anonymousComponentPath(__DIR__.'/../Resources/views/components', 'shop');

        $this->app->register(EventServiceProvider::class);

        // The compare tray of the Swipe and Photo deck product cards; the view decides whether it shows.
        Event::listen('qubix.shop.layout.content.after', static function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('shop::components.products.card.compare-tray');
        });
    }

    /**
     * Register package config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/menu.php',
            'menu.customer'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/home.php',
            'shop.home'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/compare.php',
            'shop.compare'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/fonts.php',
            'shop.fonts'
        );
    }
}
