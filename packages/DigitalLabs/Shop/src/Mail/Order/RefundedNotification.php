<?php

namespace DigitalLabs\Shop\Mail\Order;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use DigitalLabs\Sales\Contracts\Refund;
use DigitalLabs\Shop\Mail\Mailable;

class RefundedNotification extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public Refund $refund) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: [
                new Address(
                    $this->refund->order->customer_email,
                    $this->refund->order->customer_full_name
                ),
            ],
            subject: trans('shop::app.emails.orders.refunded.subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'shop::emails.orders.refunded',
        );
    }
}
