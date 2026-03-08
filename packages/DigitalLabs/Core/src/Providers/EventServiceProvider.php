<?php

namespace DigitalLabs\Core\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Prettus\Repository\Events\RepositoryEntityCreated' => [
            'DigitalLabs\Core\Listeners\CleanCacheRepository',
        ],
        'Prettus\Repository\Events\RepositoryEntityUpdated' => [
            'DigitalLabs\Core\Listeners\CleanCacheRepository',
        ],
        'Prettus\Repository\Events\RepositoryEntityDeleted' => [
            'DigitalLabs\Core\Listeners\CleanCacheRepository',
        ],
        'Spatie\ResponseCache\Events\ResponseCacheHit' => [
            'DigitalLabs\Core\Listeners\ResponseCacheHit',
        ],
    ];
}
