<?php

return [
    [
        'key' => 'sales.payment_methods.razorpay',
        'name' => 'razorpay::app.system.title',
        'info' => 'razorpay::app.system.info',
        'sort' => 5,
        'fields' => [
            [
                'name' => 'title',
                'title' => 'admin::app.configuration.index.sales.payment-methods.title',
                'type' => 'text',
                'depends' => 'active:1',
                'validation' => 'required_if:active,1',
                'channel_based' => true,
                'locale_based' => true,
            ], [
                'name' => 'description',
                'title' => 'admin::app.configuration.index.sales.payment-methods.description',
                'type' => 'textarea',
                'channel_based' => true,
                'locale_based' => true,
            ], [
                'name' => 'image',
                'title' => 'admin::app.configuration.index.sales.payment-methods.logo',
                'type' => 'image',
                'info' => 'admin::app.configuration.index.sales.payment-methods.logo-information',
                'channel_based' => false,
                'locale_based' => false,
                'validation' => 'mimes:bmp,jpeg,jpg,png,webp',
            ], [
                'name' => 'test_customer_email',
                'title' => 'razorpay::app.system.test-customer-email',
                'info' => 'razorpay::app.system.test-customer-email-info',
                'type' => 'text',
                'channel_based' => true,
                'locale_based' => false,
            ], [
                'name' => 'active',
                'title' => 'admin::app.configuration.index.sales.payment-methods.status',
                'type' => 'boolean',
                'channel_based' => true,
                'locale_based' => false,
            ], [
                'name' => 'sort',
                'title' => 'admin::app.configuration.index.sales.payment-methods.sort-order',
                'type' => 'select',
                'options' => [
                    ['title' => '0', 'value' => 0],
                    ['title' => '1', 'value' => 1],
                    ['title' => '2', 'value' => 2],
                    ['title' => '3', 'value' => 3],
                    ['title' => '4', 'value' => 4],
                ],
            ],
        ],
    ],
];
