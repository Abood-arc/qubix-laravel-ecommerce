<?php

namespace DigitalLabs\Marketing\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class EventRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Marketing\Contracts\Event';
    }
}
