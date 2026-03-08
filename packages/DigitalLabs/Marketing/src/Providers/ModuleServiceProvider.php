<?php

namespace DigitalLabs\Marketing\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Marketing\Models\Campaign::class,
        \DigitalLabs\Marketing\Models\Event::class,
        \DigitalLabs\Marketing\Models\SearchSynonym::class,
        \DigitalLabs\Marketing\Models\SearchTerm::class,
        \DigitalLabs\Marketing\Models\Template::class,
        \DigitalLabs\Marketing\Models\URLRewrite::class,
    ];
}
