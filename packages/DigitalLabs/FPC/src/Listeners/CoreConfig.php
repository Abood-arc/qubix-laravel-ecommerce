<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class CoreConfig
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * After core configuration update.
     *
     * @return void
     */
    public function afterUpdate()
    {
        $this->cacheClearer->clearOnce();
    }
}
