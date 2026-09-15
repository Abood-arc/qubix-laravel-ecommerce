<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class Order
{
    /**
     * After order is created or cancelled, product stock/availability may change.
     *
     * @param  \DigitalLabs\Sale\Contracts\Order  $order
     * @return void
     */
    public function afterCancelOrCreate($order)
    {
        ResponseCache::clear();
    }
}
