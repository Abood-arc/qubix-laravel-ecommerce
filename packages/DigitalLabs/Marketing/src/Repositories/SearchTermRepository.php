<?php

namespace DigitalLabs\Marketing\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class SearchTermRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Marketing\Contracts\SearchTerm';
    }
}
