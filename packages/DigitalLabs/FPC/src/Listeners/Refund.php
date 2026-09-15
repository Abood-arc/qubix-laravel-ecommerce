<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class Refund
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * After refund is created, product stock/availability may change.
     *
     * @param  \DigitalLabs\Sale\Contracts\Refund  $refund
     * @return void
     */
    public function afterCreate($refund)
    {
        $this->cacheClearer->clearOnce();
    }
}
