<?php

namespace DigitalLabs\Customer\Repositories;

use DigitalLabs\Core\Eloquent\Repository;
use DigitalLabs\Customer\Contracts\CustomerAddress;

class CustomerAddressRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return CustomerAddress::class;
    }
}
