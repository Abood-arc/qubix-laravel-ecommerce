<?php

namespace DigitalLabs\Shiprocket\Listeners;

/**
 * Cancels the Shiprocket booking with the order, or stops the cancel once picked up (Task 6).
 */
class OrderCancel
{
    /**
     * @param  \DigitalLabs\Sales\Contracts\Order  $order
     */
    public function handle($order): void {}
}
