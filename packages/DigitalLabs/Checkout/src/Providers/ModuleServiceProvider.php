<?php

namespace DigitalLabs\Checkout\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Checkout\Models\Cart::class,
        \DigitalLabs\Checkout\Models\CartAddress::class,
        \DigitalLabs\Checkout\Models\CartItem::class,
        \DigitalLabs\Checkout\Models\CartPayment::class,
        \DigitalLabs\Checkout\Models\CartShippingRate::class,
    ];
}
