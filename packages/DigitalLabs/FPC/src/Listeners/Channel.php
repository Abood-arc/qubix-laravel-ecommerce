<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class Channel
{
    /**
     * After channel update.
     *
     * @param  \DigitalLabs\Core\Contracts\Channel  $channel
     * @return void
     */
    public function afterUpdate($channel)
    {
        ResponseCache::clear();
    }
}
