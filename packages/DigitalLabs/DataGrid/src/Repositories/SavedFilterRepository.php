<?php

namespace DigitalLabs\DataGrid\Repositories;

use DigitalLabs\Core\Eloquent\Repository;
use DigitalLabs\DataGrid\Contracts\SavedFilter;

class SavedFilterRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return SavedFilter::class;
    }
}
