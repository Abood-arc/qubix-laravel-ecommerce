<?php

namespace DigitalLabs\DataTransfer\Repositories;

use DigitalLabs\Core\Eloquent\Repository;
use DigitalLabs\DataTransfer\Contracts\Import;

class ImportRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return Import::class;
    }
}
