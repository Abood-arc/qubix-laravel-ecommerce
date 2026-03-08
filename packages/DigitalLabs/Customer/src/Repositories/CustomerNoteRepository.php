<?php

namespace DigitalLabs\Customer\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class CustomerNoteRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\Customer\Contracts\CustomerNote';
    }
}
