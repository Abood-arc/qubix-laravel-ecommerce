<?php

namespace DigitalLabs\Shiprocket\Mail;

use DigitalLabs\Admin\Mail\Mailable;
use DigitalLabs\Sales\Contracts\Order;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the store admin that a Shiprocket parcel needs a person.
 *
 * Problems: booking_failed, rto, too_heavy, too_light, cancel_failed.
 */
class AdminAlert extends Mailable
{
    public function __construct(
        public Order $order,
        public string $problem,
        public array $details = [],
    ) {}

    public function envelope(): Envelope
    {
        $admin = core()->getAdminEmailDetails();

        return new Envelope(
            to: [new Address($admin['email'], $admin['name'])],
            subject: trans('shiprocket::app.emails.alert.subject', ['order' => $this->order->increment_id]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'shiprocket::emails.admin-alert');
    }
}
