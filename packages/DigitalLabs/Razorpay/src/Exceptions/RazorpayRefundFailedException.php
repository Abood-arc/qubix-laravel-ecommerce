<?php

namespace DigitalLabs\Razorpay\Exceptions;

use Illuminate\Http\RedirectResponse;
use Throwable;

class RazorpayRefundFailedException extends RazorpayException
{
    /**
     * What the admin is told; the generic message when nothing more specific is known.
     */
    protected ?string $adminMessage = null;

    /**
     * Razorpay pays refunds from the account balance, and refused this one while the
     * balance was too low (its own reply only says "invalid request sent").
     */
    public static function lowBalance(int $balancePaise, int $amountPaise, ?Throwable $previous = null): static
    {
        $exception = new static("Razorpay balance {$balancePaise} paise is below the refund of {$amountPaise} paise", 0, $previous);

        $exception->adminMessage = trans('razorpay::app.errors.admin-refund-low-balance', [
            'balance' => core()->formatPrice($balancePaise / 100, 'INR'),
            'amount' => core()->formatPrice($amountPaise / 100, 'INR'),
        ]);

        return $exception;
    }

    /**
     * Show the admin a message instead of the generic 500 page.
     */
    public function render(): RedirectResponse
    {
        session()->flash('error', $this->adminMessage ?? trans('razorpay::app.errors.admin-refund-failed'));

        return redirect()->back();
    }
}
