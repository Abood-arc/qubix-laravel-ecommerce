<?php

namespace DigitalLabs\Razorpay\Exceptions;

use Illuminate\Http\RedirectResponse;

class RazorpayRefundFailedException extends RazorpayException
{
    /**
     * Show the admin a message instead of the generic 500 page.
     */
    public function render(): RedirectResponse
    {
        session()->flash('error', trans('razorpay::app.errors.admin-refund-failed'));

        return redirect()->back();
    }
}
