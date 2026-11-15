<?php

namespace DigitalLabs\Razorpay\Mail;

use DigitalLabs\Shop\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentRefundedNotification extends Mailable
{
    public function __construct(
        public string $customerEmail,
        public string $customerName,
        public string $explanation,
        public string $formattedAmount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->customerEmail, $this->customerName)],
            subject: trans('razorpay::app.emails.refunded.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'razorpay::emails.refunded');
    }
}
