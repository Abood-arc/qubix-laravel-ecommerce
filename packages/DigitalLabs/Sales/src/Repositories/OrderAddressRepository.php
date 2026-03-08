<?php

namespace DigitalLabs\Sales\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

/**
 * Order Address Repository
 *
 * @author    Jitendra Singh <jitendra@digitalLabs.com>
 * @copyright 2018 DigitalLabs Software Pvt Ltd (http://www.digitalLabs.com)
 */
class OrderAddressRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\Sales\Contracts\OrderAddress';
    }
}
