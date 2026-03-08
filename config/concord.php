<?php

return [

    /**
     * The path of the convention file.
     */
    'convention' => DigitalLabs\Core\CoreConvention::class,

    /**
     * Example:
     *
     * VendorA\ModuleX\Providers\ModuleServiceProvider::class,
     * VendorB\ModuleY\Providers\ModuleServiceProvider::class,
     */
    'modules' => [
        \DigitalLabs\Admin\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Attribute\Providers\ModuleServiceProvider::class,
        \DigitalLabs\BookingProduct\Providers\ModuleServiceProvider::class,
        \DigitalLabs\CMS\Providers\ModuleServiceProvider::class,
        \DigitalLabs\CartRule\Providers\ModuleServiceProvider::class,
        \DigitalLabs\CatalogRule\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Category\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Checkout\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Core\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Customer\Providers\ModuleServiceProvider::class,
        \DigitalLabs\DataGrid\Providers\ModuleServiceProvider::class,
        \DigitalLabs\DataTransfer\Providers\ModuleServiceProvider::class,
        \DigitalLabs\GDPR\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Inventory\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Marketing\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Notification\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Payment\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Paypal\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Product\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Rule\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Sales\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Shipping\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Shop\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Sitemap\Providers\ModuleServiceProvider::class,
        \DigitalLabs\SocialLogin\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Tax\Providers\ModuleServiceProvider::class,
        \DigitalLabs\Theme\Providers\ModuleServiceProvider::class,
        \DigitalLabs\User\Providers\ModuleServiceProvider::class,
    ],

];
