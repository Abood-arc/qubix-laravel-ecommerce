<?php

namespace DigitalLabs\BookingProduct\Repositories;

use DigitalLabs\BookingProduct\Contracts\BookingProductTableSlot;
use DigitalLabs\Core\Eloquent\Repository;

class BookingProductTableSlotRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return BookingProductTableSlot::class;
    }
}
