<?php

namespace DigitalLabs\User\Facades;

use Illuminate\Support\Facades\Facade;
use DigitalLabs\User\Bouncer as BaseBouncer;

class Bouncer extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return BaseBouncer::class;
    }
}
