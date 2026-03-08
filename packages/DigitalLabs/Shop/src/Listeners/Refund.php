<?php

namespace DigitalLabs\Shop\Listeners;

use DigitalLabs\Shop\Mail\Order\RefundedNotification;

class Refund extends Base
{
    /**
     * After order is created
     *
     * @param  \DigitalLabs\Sale\Contracts\Refund  $refund
     * @return void
     */
    public function afterCreated($refund)
    {
        try {
            if (! core()->getConfigData('emails.general.notifications.emails.general.notifications.new_refund')) {
                return;
            }

            $this->prepareMail($refund, new RefundedNotification($refund));

            $refund->query()->update(['email_sent' => 1]);
        } catch (\Exception $e) {
            report($e);
        }
    }
}
