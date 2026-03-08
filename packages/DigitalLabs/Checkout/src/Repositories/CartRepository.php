<?php

namespace DigitalLabs\Checkout\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class CartRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\Checkout\Contracts\Cart';
    }
}
