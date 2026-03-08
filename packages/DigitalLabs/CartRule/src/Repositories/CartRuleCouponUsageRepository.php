<?php

namespace DigitalLabs\CartRule\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class CartRuleCouponUsageRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\CartRule\Contracts\CartRuleCouponUsage';
    }
}
