<?php

namespace DigitalLabs\Customer\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Customer\Models\CompareItem::class,
        \DigitalLabs\Customer\Models\Customer::class,
        \DigitalLabs\Customer\Models\CustomerAddress::class,
        \DigitalLabs\Customer\Models\CustomerGroup::class,
        \DigitalLabs\Customer\Models\CustomerNote::class,
        \DigitalLabs\Customer\Models\Wishlist::class,
    ];
}
