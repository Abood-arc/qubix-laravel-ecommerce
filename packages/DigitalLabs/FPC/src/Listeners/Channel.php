<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class Channel
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * After channel update.
     *
     * @param  \DigitalLabs\Core\Contracts\Channel  $channel
     * @return void
     */
    public function afterUpdate($channel)
    {
        $this->cacheClearer->clearOnce();
    }
}
