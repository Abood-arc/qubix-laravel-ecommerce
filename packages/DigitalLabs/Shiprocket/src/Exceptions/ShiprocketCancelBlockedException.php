<?php

namespace DigitalLabs\Shiprocket\Exceptions;

use Illuminate\Http\RedirectResponse;

/**
 * An order cancel was stopped because of its Shiprocket parcel.
 */
class ShiprocketCancelBlockedException extends ShiprocketException
{
    /**
     * Show the admin the reason instead of the generic error page.
     */
    public function render(): RedirectResponse
    {
        session()->flash('error', $this->getMessage());

        return redirect()->back();
    }
}
