<?php

namespace DigitalLabs\BookingProduct\Repositories;

use DigitalLabs\BookingProduct\Contracts\BookingProductDefaultSlot;
use DigitalLabs\Core\Eloquent\Repository;

class BookingProductDefaultSlotRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return BookingProductDefaultSlot::class;
    }
}
