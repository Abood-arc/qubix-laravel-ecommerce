<?php

use DigitalLabs\Shipping\Facades\Shipping;

if (! function_exists('shipping')) {
    /**
     * Shipping helper.
     *
     * @return \DigitalLabs\Shipping\Shipping
     */
    function shipping()
    {
        return Shipping::getFacadeRoot();
    }
}
