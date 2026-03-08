<?php

return [
    'cashondelivery' => [
        'code' => 'cashondelivery',
        'title' => 'Cash On Delivery',
        'description' => 'Cash On Delivery',
        'class' => 'DigitalLabs\Payment\Payment\CashOnDelivery',
        'active' => true,
        'sort' => 1,
    ],

    'moneytransfer' => [
        'code' => 'moneytransfer',
        'title' => 'Money Transfer',
        'description' => 'Money Transfer',
        'class' => 'DigitalLabs\Payment\Payment\MoneyTransfer',
        'active' => true,
        'sort' => 2,
    ],
];
