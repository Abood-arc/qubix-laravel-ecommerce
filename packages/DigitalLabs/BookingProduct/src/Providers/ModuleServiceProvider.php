<?php

namespace DigitalLabs\BookingProduct\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\BookingProduct\Models\BookingProduct::class,
        \DigitalLabs\BookingProduct\Models\BookingProductDefaultSlot::class,
        \DigitalLabs\BookingProduct\Models\BookingProductAppointmentSlot::class,
        \DigitalLabs\BookingProduct\Models\BookingProductEventTicket::class,
        \DigitalLabs\BookingProduct\Models\BookingProductEventTicketTranslation::class,
        \DigitalLabs\BookingProduct\Models\BookingProductRentalSlot::class,
        \DigitalLabs\BookingProduct\Models\BookingProductTableSlot::class,
        \DigitalLabs\BookingProduct\Models\Booking::class,
    ];
}
