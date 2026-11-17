<?php

namespace DigitalLabs\Shiprocket\Listeners;

/**
 * Sends a paid-online order to Shiprocket once it is fully invoiced (Task 6).
 */
class AutoSend
{
    /**
     * @param  \DigitalLabs\Sales\Contracts\Invoice  $invoice
     */
    public function handle($invoice): void {}
}
