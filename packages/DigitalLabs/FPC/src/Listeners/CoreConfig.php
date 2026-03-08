<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class CoreConfig
{
    /**
     * After core configuration update.
     *
     * @return void
     */
    public function afterUpdate()
    {
        ResponseCache::clear();
    }
}
