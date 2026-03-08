<?php

namespace DigitalLabs\CatalogRule\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'promotions.catalog_rule.create.after' => [
            'DigitalLabs\CatalogRule\Listeners\CatalogRule@afterUpdateCreate',
        ],

        'promotions.catalog_rule.update.after' => [
            'DigitalLabs\CatalogRule\Listeners\CatalogRule@afterUpdateCreate',
        ],

        'promotions.catalog_rule.update.before' => [
            'DigitalLabs\CatalogRule\Listeners\CatalogRule@beforeUpdate',
        ],

        'promotions.catalog_rule.delete.before' => [
            'DigitalLabs\CatalogRule\Listeners\CatalogRule@beforeDelete',
        ],

        'catalog.product.update.after' => [
            'DigitalLabs\CatalogRule\Listeners\Product@afterUpdate',
        ],
    ];
}
