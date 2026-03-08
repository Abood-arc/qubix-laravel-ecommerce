<?php

namespace DigitalLabs\Theme\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Define the models
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Theme\Models\ThemeCustomization::class,
        \DigitalLabs\Theme\Models\ThemeCustomizationTranslation::class,
    ];
}
