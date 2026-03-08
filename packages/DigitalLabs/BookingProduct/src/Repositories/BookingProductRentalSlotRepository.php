<?php

namespace DigitalLabs\BookingProduct\Repositories;

use DigitalLabs\BookingProduct\Contracts\BookingProductRentalSlot;
use DigitalLabs\Core\Eloquent\Repository;

class BookingProductRentalSlotRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return BookingProductRentalSlot::class;
    }
}
