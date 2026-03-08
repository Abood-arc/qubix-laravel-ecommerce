<?php

namespace DigitalLabs\DataTransfer\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\DataTransfer\Models\Import::class,
        \DigitalLabs\DataTransfer\Models\ImportBatch::class,
    ];
}
