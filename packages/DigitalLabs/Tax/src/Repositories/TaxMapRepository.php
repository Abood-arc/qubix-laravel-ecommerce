<?php

namespace DigitalLabs\Tax\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class TaxMapRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\Tax\Contracts\TaxMap';
    }
}
