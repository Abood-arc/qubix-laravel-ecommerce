<?php

namespace DigitalLabs\CMS\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\CMS\Models\Page::class,
        \DigitalLabs\CMS\Models\PageTranslation::class,
    ];
}
