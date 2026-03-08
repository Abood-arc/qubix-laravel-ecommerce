<?php

namespace DigitalLabs\FPC\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'catalog.product.update.after' => [
            'DigitalLabs\FPC\Listeners\Product@afterUpdate',
        ],

        'catalog.product.delete.before' => [
            'DigitalLabs\FPC\Listeners\Product@beforeDelete',
        ],

        'catalog.category.update.after' => [
            'DigitalLabs\FPC\Listeners\Category@afterUpdate',
        ],

        'catalog.category.delete.before' => [
            'DigitalLabs\FPC\Listeners\Category@beforeDelete',
        ],

        'customer.review.update.after' => [
            'DigitalLabs\FPC\Listeners\Review@afterUpdate',
        ],

        'customer.review.delete.before' => [
            'DigitalLabs\FPC\Listeners\Review@beforeDelete',
        ],

        'checkout.order.save.after' => [
            'DigitalLabs\FPC\Listeners\Order@afterCancelOrCreate',
        ],

        'sales.order.cancel.after' => [
            'DigitalLabs\FPC\Listeners\Order@afterCancelOrCreate',
        ],

        'sales.refund.save.after' => [
            'DigitalLabs\FPC\Listeners\Refund@afterCreate',
        ],

        'cms.page.update.after' => [
            'DigitalLabs\FPC\Listeners\Page@afterUpdate',
        ],

        'cms.page.delete.before' => [
            'DigitalLabs\FPC\Listeners\Page@beforeDelete',
        ],

        'theme_customization.create.after' => [
            'DigitalLabs\FPC\Listeners\ThemeCustomization@afterCreate',
        ],

        'theme_customization.update.after' => [
            'DigitalLabs\FPC\Listeners\ThemeCustomization@afterUpdate',
        ],

        'theme_customization.delete.before' => [
            'DigitalLabs\FPC\Listeners\ThemeCustomization@beforeDelete',
        ],

        'core.channel.update.after' => [
            'DigitalLabs\FPC\Listeners\Channel@afterUpdate',
        ],

        'core.configuration.save.after' => [
            'DigitalLabs\FPC\Listeners\CoreConfig@afterUpdate',
        ],

        'marketing.search_seo.url_rewrites.update.after' => [
            'DigitalLabs\FPC\Listeners\URLRewrite@afterUpdate',
        ],

        'marketing.search_seo.url_rewrites.delete.before' => [
            'DigitalLabs\FPC\Listeners\URLRewrite@beforeDelete',
        ],
    ];
}
