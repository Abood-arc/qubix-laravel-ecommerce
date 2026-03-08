<?php

namespace DigitalLabs\Attribute\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class AttributeGroupRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\Attribute\Contracts\AttributeGroup';
    }
}
