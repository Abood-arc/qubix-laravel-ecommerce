<?php

namespace DigitalLabs\Product\Repositories;

class ProductVideoRepository extends ProductMediaRepository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Product\Contracts\ProductVideo';
    }
}
