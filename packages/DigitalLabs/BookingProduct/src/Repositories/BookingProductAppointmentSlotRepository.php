<?php

namespace DigitalLabs\BookingProduct\Repositories;

use DigitalLabs\BookingProduct\Contracts\BookingProductAppointmentSlot;
use DigitalLabs\Core\Eloquent\Repository;

class BookingProductAppointmentSlotRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return BookingProductAppointmentSlot::class;
    }
}
