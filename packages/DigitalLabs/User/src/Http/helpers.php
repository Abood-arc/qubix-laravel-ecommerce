<?php

use DigitalLabs\User\Facades\Bouncer as BouncerFacade;

if (! function_exists('bouncer')) {
    /**
     * Bouncer helper.
     *
     * @return \DigitalLabs\User\Bouncer
     */
    function bouncer()
    {
        return BouncerFacade::getFacadeRoot();
    }
}
