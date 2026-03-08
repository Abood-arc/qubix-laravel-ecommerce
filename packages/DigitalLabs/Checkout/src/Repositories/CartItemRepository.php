<?php

namespace DigitalLabs\Checkout\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class CartItemRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\Checkout\Contracts\CartItem';
    }

    /**
     * @param  int  $cartItemId
     * @return int
     */
    public function getProduct($cartItemId)
    {
        return $this->model->find($cartItemId)->product->id;
    }
}
