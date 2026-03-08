<?php

namespace DigitalLabs\Product\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class ProductPriceIndexRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Product\Contracts\ProductPriceIndex';
    }
}
