<?php

namespace DigitalLabs\MagicAI\Facades;

use Illuminate\Support\Facades\Facade;
use DigitalLabs\MagicAI\MagicAI as BaseMagicAI;

class MagicAI extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return BaseMagicAI::class;
    }
}
