<?php

namespace DigitalLabs\FPC\Providers;

use DigitalLabs\FPC\Support\CacheClearer;
use Illuminate\Support\ServiceProvider;

class FPCServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // scoped(), not singleton(): a persistent queue:work process (see
        // docker-compose.prod.yml's `queue` service) never rebuilds its
        // container between jobs, so a singleton's $cleared flag would stay
        // true forever after the first clearOnce() call in that process's
        // life — silently suppressing every later job's cache clear for as
        // long as the worker runs. Laravel's queue Worker calls
        // Container::forgetScopedInstances() between every job specifically
        // to reset bindings like this one; a singleton is invisible to that
        // mechanism, scoped() isn't. No FPC-tracked event is dispatched from
        // inside a queued job today, so this has no observable effect yet —
        // it closes the gap before something does.
        //
        // This doesn't replace the terminating()-based reset below: nothing
        // calls forgetScopedInstances() for an HTTP request or a console
        // command (only the queue Worker does), and Pest's simulated
        // requests share one container across multiple real kernel
        // round-trips within a test method — terminating() is what resets
        // this between those.
        $this->app->scoped(CacheClearer::class);
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
