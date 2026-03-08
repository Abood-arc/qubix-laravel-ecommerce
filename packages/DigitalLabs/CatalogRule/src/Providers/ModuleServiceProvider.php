<?php

namespace DigitalLabs\CatalogRule\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\CatalogRule\Models\CatalogRule::class,
        \DigitalLabs\CatalogRule\Models\CatalogRuleProduct::class,
        \DigitalLabs\CatalogRule\Models\CatalogRuleProductPrice::class,
    ];
}
