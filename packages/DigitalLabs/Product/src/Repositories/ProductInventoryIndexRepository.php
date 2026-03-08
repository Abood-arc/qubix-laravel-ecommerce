<?php

namespace DigitalLabs\Product\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class ProductInventoryIndexRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Product\Contracts\ProductInventoryIndex';
    }
}
