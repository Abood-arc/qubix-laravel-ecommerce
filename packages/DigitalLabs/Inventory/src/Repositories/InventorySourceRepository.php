<?php

namespace DigitalLabs\Inventory\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class InventorySourceRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Inventory\Contracts\InventorySource';
    }
}
