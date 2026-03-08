<?php

namespace DigitalLabs\Customer\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class CustomerGroupRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Customer\Contracts\CustomerGroup';
    }
}
