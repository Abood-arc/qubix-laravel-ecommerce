<?php

namespace DigitalLabs\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use DigitalLabs\Theme\ViewRenderEventManager;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        include __DIR__.'/../Http/helpers.php';

        $this->registerCommands();

        $this->registerOverrides();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'core');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'core');

        Event::listen('qubix.shop.layout.body.after', static function (ViewRenderEventManager $viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('core::blade.tracer.style');
        });

        Event::listen('qubix.admin.layout.head', static function (ViewRenderEventManager $viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('core::blade.tracer.style');
        });

        // The queue worker is one long-lived process: re-read settings for every job, so a
        // change saved in admin reaches queued mails and imports without a worker restart.
        Event::listen(JobProcessing::class, static fn () => system_config()->flushCoreConfig());

        // Same for anything that serves several requests in one process (the test client, Octane).
        Event::listen(RequestHandled::class, static fn () => system_config()->flushCoreConfig());

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('invoice:cron')->dailyAt('3:00');
        });

        $this->app->register(EventServiceProvider::class);
        $this->app->register(ImageServiceProvider::class);
        $this->app->register(VisitorServiceProvider::class);
    }

    /**
     * Register the console commands of this package.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \DigitalLabs\Core\Console\Commands\QubixVersion::class,
                \DigitalLabs\Core\Console\Commands\ExchangeRateUpdate::class,
                \DigitalLabs\Core\Console\Commands\InvoiceOverdueCron::class,
                \DigitalLabs\Core\Console\Commands\TranslationsChecker::class,
            ]);
        }
    }

    /**
     * Register the overrides.
     */
    protected function registerOverrides(): void
    {
        $this->app->extend(
            \Illuminate\Foundation\Console\UpCommand::class,
            fn () => new \DigitalLabs\Core\Console\Commands\UpCommand
        );

        $this->app->extend(
            \Illuminate\Foundation\Console\DownCommand::class,
            fn () => new \DigitalLabs\Core\Console\Commands\DownCommand
        );

        // A request that only reads the session doesn't save it back (see Session\SkipUnchangedWrites).
        $this->app->singleton('session', fn ($app) => new \DigitalLabs\Core\Session\SessionManager($app));

        $this->app->bind(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \DigitalLabs\Core\Exceptions\Handler::class
        );

        $this->app->bind(
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            fn ($app) => new \DigitalLabs\Core\Http\Middleware\PreventRequestsDuringMaintenance($app)
        );

        $this->app->singleton(
            \Elastic\Elasticsearch\Client::class,
            fn () => \DigitalLabs\Core\Facades\ElasticSearch::getFacadeApplication()->connection()
        );

        $this->app->singleton(
            'blade.compiler',
            fn ($app) => new \DigitalLabs\Core\View\Compilers\BladeCompiler($app['files'], $app['config']['view.compiled'])
        );
    }
}
