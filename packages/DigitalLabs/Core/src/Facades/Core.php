<?php

namespace DigitalLabs\Core\Facades;

use Illuminate\Support\Facades\Facade;
use DigitalLabs\Core\Core as BaseCore;

class Core extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return BaseCore::class;
    }
}
