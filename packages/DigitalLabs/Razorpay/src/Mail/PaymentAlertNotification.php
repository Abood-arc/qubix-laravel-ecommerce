<?php

namespace DigitalLabs\Razorpay\Mail;

use DigitalLabs\Admin\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentAlertNotification extends Mailable
{
    public function __construct(
        public string $paymentReference,
        public string $formattedAmount,
        public string $problem,
        public array $details = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address(core()->getAdminEmailDetails()['email'], core()->getAdminEmailDetails()['name'])],
            subject: trans('razorpay::app.emails.alert.subject', ['payment' => $this->paymentReference]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'razorpay::emails.alert');
    }
}
