<?php

namespace DigitalLabs\CatalogRule\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class CatalogRuleProductPriceRepository extends Repository
{
    /**
     * Specify Model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\CatalogRule\Contracts\CatalogRuleProductPrice';
    }
}
