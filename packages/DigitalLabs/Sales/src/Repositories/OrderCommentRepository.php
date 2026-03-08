<?php

namespace DigitalLabs\Sales\Repositories;

use DigitalLabs\Core\Eloquent\Repository;

class OrderCommentRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return 'DigitalLabs\Sales\Contracts\OrderComment';
    }
}
