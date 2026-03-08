<?php

namespace DigitalLabs\Core\Facades;

use Illuminate\Support\Facades\Facade;
use DigitalLabs\Core\SystemConfig as BaseSystemConfig;

class SystemConfig extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return BaseSystemConfig::class;
    }
}
