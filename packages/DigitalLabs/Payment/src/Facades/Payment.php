<?php

namespace DigitalLabs\Payment\Facades;

use Illuminate\Support\Facades\Facade;
use DigitalLabs\Payment\Payment as BasePayment;

class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return BasePayment::class;
    }
}
