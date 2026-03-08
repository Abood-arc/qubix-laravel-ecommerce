<?php

namespace DigitalLabs\Core\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class CountryRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\Core\Contracts\Country';
    }
}
