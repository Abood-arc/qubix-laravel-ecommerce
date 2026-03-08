<?php

namespace DigitalLabs\Product\Repositories;

class ProductImageRepository extends ProductMediaRepository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Product\Contracts\ProductImage';
    }
}
