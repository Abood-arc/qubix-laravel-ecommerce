<?php

namespace DigitalLabs\Theme\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use DigitalLabs\Theme\Console\Commands\SyncHomeProductCarouselCategoryCommand;
use DigitalLabs\Theme\ThemeViewFinder;
use DigitalLabs\Theme\ViewRenderEventManager;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        include __DIR__.'/../Http/helpers.php';

        $this->app->singleton('view.finder', function ($app) {
            return new ThemeViewFinder(
                $app['files'],
                $app['config']['view.paths'],
                null
            );
        });

        $this->app->singleton(ViewRenderEventManager::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Blade::directive('qubixVite', function ($expression) {
            return "<?php echo themes()->setQubixVite({$expression})->toHtml(); ?>";
        });

        $this->commands([
            SyncHomeProductCarouselCategoryCommand::class,
        ]);
    }
}
