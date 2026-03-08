<?php

namespace DigitalLabs\Sales\Repositories;

use DigitalLabs\Core\Eloquent\Repository;
use DigitalLabs\Sales\Contracts\OrderTransaction;

class OrderTransactionRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return OrderTransaction::class;
    }
}
