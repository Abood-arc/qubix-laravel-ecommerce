<?php

namespace DigitalLabs\Attribute\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Attribute\Models\Attribute::class,
        \DigitalLabs\Attribute\Models\AttributeFamily::class,
        \DigitalLabs\Attribute\Models\AttributeGroup::class,
        \DigitalLabs\Attribute\Models\AttributeOption::class,
        \DigitalLabs\Attribute\Models\AttributeOptionTranslation::class,
        \DigitalLabs\Attribute\Models\AttributeTranslation::class,
    ];
}
