<?php

namespace DigitalLabs\Tax\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Tax\Models\TaxCategory::class,
        \DigitalLabs\Tax\Models\TaxMap::class,
        \DigitalLabs\Tax\Models\TaxRate::class,
    ];
}
