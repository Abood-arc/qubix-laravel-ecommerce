<?php

namespace DigitalLabs\Marketing\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        /**
         * Product Events
         */
        'catalog.product.update.before' => [
            'DigitalLabs\Marketing\Listeners\Product@beforeUpdate',
        ],

        'catalog.product.delete.before' => [
            'DigitalLabs\Marketing\Listeners\Product@beforeDelete',
        ],

        /**
         * Category Events
         */
        'catalog.category.create.after' => [
            'DigitalLabs\Marketing\Listeners\Category@afterCreate',
        ],

        'catalog.category.update.before' => [
            'DigitalLabs\Marketing\Listeners\Category@beforeUpdate',
        ],

        'catalog.category.delete.before' => [
            'DigitalLabs\Marketing\Listeners\Category@beforeDelete',
        ],

        /**
         * CMS Page Events
         */
        'cms.page.create.after' => [
            'DigitalLabs\Marketing\Listeners\Page@afterCreate',
        ],

        'cms.page.update.before' => [
            'DigitalLabs\Marketing\Listeners\Page@beforeUpdate',
        ],

        'cms.page.delete.before' => [
            'DigitalLabs\Marketing\Listeners\Page@beforeDelete',
        ],
    ];
}
