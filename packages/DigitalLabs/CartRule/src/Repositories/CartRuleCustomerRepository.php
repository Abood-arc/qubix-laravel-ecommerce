<?php

namespace DigitalLabs\CartRule\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class CartRuleCustomerRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\CartRule\Contracts\CartRuleCustomer';
    }
}
