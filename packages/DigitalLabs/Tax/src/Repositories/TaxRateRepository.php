<?php

namespace DigitalLabs\Tax\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class TaxRateRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Tax\Contracts\TaxRate';
    }
}
