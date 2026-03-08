<?php

namespace DigitalLabs\SocialShare\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen('qubix.shop.products.view.compare.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('social_share::share');
        });
    }
}
