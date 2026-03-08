<?php

namespace DigitalLabs\User\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class RoleRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\User\Contracts\Role';
    }
}
