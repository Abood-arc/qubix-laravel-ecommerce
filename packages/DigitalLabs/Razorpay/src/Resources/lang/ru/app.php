<?php

return [
    'system' => [
        'title' => 'Razorpay',
        'info' => 'Accept UPI, cards and netbanking through Razorpay.',
        'key-id' => 'Key ID',
        'key-secret' => 'Key Secret',
        'webhook-secret' => 'Webhook Secret',
        'test-customer-email' => 'Test Customer Email',
        'test-customer-email-info' => 'While set, only this logged-in customer can use Razorpay. Clear it to open Razorpay to everyone.',
    ],

    'redirect' => [
        'title' => 'Complete your payment',
        'opening' => 'Opening the secure payment window...',
        'total' => 'Total',
        'description' => 'Order payment',
        'confirming-title' => 'Payment received',
        'confirming' => "Payment received, we're confirming your order. You will get a confirmation email shortly, so you can safely close this page.",
        'continue' => 'Continue shopping',
    ],

    'errors' => [
        'unavailable' => "This payment method isn't available, please choose another.",
        'gateway' => 'We could not start the payment. Please try again or choose another payment method.',
        'cancelled' => 'Payment cancelled. Your cart is still here.',
        'payment-invalid' => 'We could not verify this payment. If money was deducted, your order will be confirmed or the money returned shortly.',
        'admin-refund-failed' => 'Razorpay could not process this refund, so nothing was changed. Check the Razorpay dashboard and balance, then try again.',
    ],

    'refund' => [
        'already_ordered' => "You already placed order #:order with :method, so we're returning your payment.",
        'out_of_stock' => "An item sold out while you were paying, so we're returning your payment.",
        'order_failed' => "We couldn't complete your order, so we're returning your payment.",
    ],

    'emails' => [
        'refunded' => [
            'subject' => 'Your payment is being returned',
            'title' => 'Your payment is being returned',
            'amount' => 'Amount: :amount',
            'timing' => 'Refunds usually reach your account within 5 to 7 working days.',
        ],

        'alert' => [
            'subject' => 'Action needed: Razorpay payment :payment',
            'title' => 'A Razorpay payment needs attention',
            'payment' => 'Payment: :payment',
            'amount' => 'Amount: :amount',
            'refund_failed' => 'The automatic refund failed. Refund this payment by hand in the Razorpay dashboard, and check the account balance.',
            'invoice_failed' => 'The order was created and paid, but its invoice could not be created. Create the invoice from the admin panel.',
        ],
    ],
];
