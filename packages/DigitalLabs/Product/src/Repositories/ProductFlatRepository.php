<?php

namespace DigitalLabs\Product\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class ProductFlatRepository extends Repository
{
    /**
     * Specify model.
     */
    public function model(): string
    {
        return 'DigitalLabs\Product\Contracts\ProductFlat';
    }
}
