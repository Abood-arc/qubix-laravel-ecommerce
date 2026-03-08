<?php

use DigitalLabs\Payment\Facades\Payment;

if (! function_exists('payment')) {
    /**
     * Payment helper.
     *
     * @return \DigitalLabs\Payment\Payment
     */
    function payment()
    {
        return Payment::getFacadeRoot();
    }
}
