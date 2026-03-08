<?php

namespace DigitalLabs\BookingProduct\Helpers;

class AppointmentSlot extends Booking
{
    /**
     * @param  \DigitalLabs\BookingProduct\Contracts\BookingProduct  $bookingProduct
     */
    public function haveSufficientQuantity(int $qty, $bookingProduct): bool
    {
        return true;
    }
}
