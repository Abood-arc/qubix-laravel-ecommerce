<?php

namespace DigitalLabs\Shiprocket\Http\Controllers\Admin;

use DigitalLabs\Admin\Http\Controllers\Controller;

/**
 * Admin actions on an order's Shiprocket parcel (Task 7).
 */
class ShipmentController extends Controller
{
    public function ship(int $id)
    {
        abort(501);
    }

    public function retry(int $id)
    {
        abort(501);
    }

    public function cancel(int $id)
    {
        abort(501);
    }

    public function label(int $id)
    {
        abort(501);
    }
}
