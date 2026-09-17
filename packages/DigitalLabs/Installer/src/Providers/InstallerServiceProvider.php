<?php

namespace DigitalLabs\Installer\Providers;

use DigitalLabs\Installer\Console\Commands\Installer as InstallerCommand;
use DigitalLabs\Installer\Console\Commands\Provision as ProvisionCommand;
use DigitalLabs\Installer\Console\Commands\ProvisionAdmin as ProvisionAdminCommand;
use DigitalLabs\Installer\Console\Commands\SeedStarterStore as SeedStarterStoreCommand;
use DigitalLabs\Installer\Http\Middleware\CanInstall;
use DigitalLabs\Installer\Http\Middleware\Locale;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InstallerServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $router->middlewareGroup('install', [CanInstall::class]);

        $router->aliasMiddleware('installer_locale', Locale::class);

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'installer');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'installer');

        Event::listen('qubix.installed', 'DigitalLabs\Installer\Listeners\Installer@installed');
    }

    /**
     * Register the Installer Commands of this package.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallerCommand::class,
                ProvisionCommand::class,
                ProvisionAdminCommand::class,
                SeedStarterStoreCommand::class,
            ]);
        }
    }
}
