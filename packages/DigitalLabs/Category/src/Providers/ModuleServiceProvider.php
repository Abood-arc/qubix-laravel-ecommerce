<?php

namespace DigitalLabs\Category\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Category\Models\Category::class,
        \DigitalLabs\Category\Models\CategoryTranslation::class,
    ];
}
