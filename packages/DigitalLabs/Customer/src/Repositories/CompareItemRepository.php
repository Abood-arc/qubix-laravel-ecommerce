<?php

namespace DigitalLabs\Customer\Repositories;

use DigitalLabs\Core\Eloquent\Repository;
use DigitalLabs\Customer\Contracts\CompareItem;

class CompareItemRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return CompareItem::class;
    }
}
