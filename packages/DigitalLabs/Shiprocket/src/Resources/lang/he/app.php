<?php

return [
    'system' => [
        'title' => 'Shiprocket',
        'info' => 'Book couriers, labels and pickups with Shiprocket. The API user email, password and webhook token are set on the server in .env (SHIPROCKET_EMAIL, SHIPROCKET_PASSWORD, SHIPROCKET_WEBHOOK_TOKEN).',
        'active' => 'Enabled',
        'pickup-location' => 'Pickup location name (exactly as in Shiprocket)',
        'pickup-pin' => 'Pickup PIN code',
        'length' => 'Default parcel length (cm, packed)',
        'breadth' => 'Default parcel width (cm, packed)',
        'height' => 'Default parcel thickness (cm, packed)',
        'auto-send' => 'Send prepaid orders automatically',
        'pin-check' => 'Check PIN codes at checkout',
        'max-weight' => 'Maximum weight for automatic booking (kg)',
        'carrier' => [
            'title' => 'Shiprocket (live courier prices)',
            'info' => 'Charges each customer what Shiprocket bills for their parcel: Standard (cheapest courier) and Express (fastest), with the cash on delivery fee when they pay cash. Needs Shiprocket enabled under Sales → Shipping → Shiprocket.',
            'method-title' => 'Title',
            'backup-price' => 'Backup price (₹ per order)',
            'backup-price-info' => 'Charged when Shiprocket cannot give a price at checkout (it is down or slow); Shiprocket then picks the courier.',
            'add-gst' => 'Add 18% GST to Shiprocket prices',
            'add-gst-info' => 'Turn on if Shiprocket bills more than its quoted price because GST is added on top.',
        ],
    ],

    'checkout' => [
        'not-deliverable' => 'Sorry, we can\'t deliver to PIN code :pin yet.',
        'cod-unavailable' => 'Cash on Delivery isn\'t available for PIN code :pin. Please pay online.',
        'standard' => 'Standard delivery',
        'express' => 'Express delivery',
        'standard-cod' => 'Standard delivery (incl. cash on delivery fee)',
        'express-cod' => 'Express delivery (incl. cash on delivery fee)',
        'backup' => 'Standard delivery',
        'arrives' => 'Arrives by :date',
        'tracked' => 'Tracked courier delivery',
        'cod-fee' => 'A cash on delivery fee of :fee is added to delivery.',
        'price-changed' => 'The delivery price changed to :price. Please check your order and place it again.',
    ],

    'admin' => [
        'panel-title' => 'Shiprocket',
        'status' => [
            'pending' => 'Pending',
            'booking' => 'Booking…',
            'booked' => 'Booked',
            'pickup_scheduled' => 'Pickup scheduled',
            'in_transit' => 'In transit',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'rto' => 'Returned to sender (RTO)',
            'cancelled' => 'Cancelled',
            'failed' => 'Failed',
        ],

        'courier' => 'Courier',
        'awb' => 'AWB',
        'track' => 'Track',
        'label' => 'Download label',
        'ship' => 'Ship with Shiprocket',
        'retry' => 'Retry',
        'cancel' => 'Cancel shipment',
        'confirm-heavy' => 'This parcel is outside the automatic weight limits (:weight kg). Book it anyway?',
        'confirm-cancel' => 'Cancel this Shiprocket shipment?',
        'not-sent' => 'Not sent to Shiprocket yet.',
        'reason' => 'Reason: :reason',
        'queued' => 'Sent to Shiprocket. This page updates once booking finishes.',
        'cancelled' => 'Shiprocket shipment cancelled. The store shipment record stays; re-booking will update it.',
        'cannot-ship' => 'This order can\'t be shipped (already shipped, cancelled or nothing left to ship).',
        'courier-swapped' => 'Booked with :courier instead of the courier the customer paid for: Shiprocket refused that one.',
    ],

    'cancel' => [
        'blocked' => 'This order\'s parcel has already been picked up by the courier, so it can\'t be cancelled here. Handle it as a return.',
        'failed' => 'Shiprocket could not cancel the shipment: :reason',
    ],

    'errors' => [
        'invalid-pin' => 'The delivery PIN code :pin is not a valid 6-digit Indian PIN code.',
        'invalid-phone' => 'The phone number must have 10 digits.',
        'unknown-state' => 'Unknown state :state for an Indian address.',
    ],

    'emails' => [
        'track' => 'Track your parcel',
        'alert' => [
            'subject' => 'Action needed: Shiprocket for order #:order',
            'booking_failed' => 'Booking with Shiprocket failed: :reason. Fix the cause and press Retry on the order.',
            'rto' => 'The parcel (AWB :awb) is being returned to you. Decide whether to restock the items or refund the customer.',
            'too_heavy' => 'The parcel weighs :weight kg, above the automatic limit, so it was not booked. Check the product weights, then book it from the order page.',
            'too_light' => 'The parcel weighs only :weight kg, so product weights are probably wrong. It was not booked.',
            'cancel_failed' => 'Shiprocket could not cancel shipment AWB :awb: :reason. Cancel it in the Shiprocket dashboard.',
        ],
    ],
];
