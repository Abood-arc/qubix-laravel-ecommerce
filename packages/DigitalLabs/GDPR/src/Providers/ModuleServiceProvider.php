<?php

namespace DigitalLabs\GDPR\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\GDPR\Models\GDPRDataRequest::class,
    ];
}
