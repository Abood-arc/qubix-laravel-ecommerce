<?php

return [
    'simple' => [
        'key' => 'simple',
        'name' => 'product::app.type.simple',
        'class' => 'DigitalLabs\Product\Type\Simple',
        'sort' => 1,
    ],

    'booking' => [
        'key' => 'booking',
        'name' => 'product::app.type.booking',
        'class' => 'DigitalLabs\Product\Type\Booking',
        'sort' => 2,
    ],

    'configurable' => [
        'key' => 'configurable',
        'name' => 'product::app.type.configurable',
        'class' => 'DigitalLabs\Product\Type\Configurable',
        'sort' => 3,
    ],

    'virtual' => [
        'key' => 'virtual',
        'name' => 'product::app.type.virtual',
        'class' => 'DigitalLabs\Product\Type\Virtual',
        'sort' => 4,
    ],

    'grouped' => [
        'key' => 'grouped',
        'name' => 'product::app.type.grouped',
        'class' => 'DigitalLabs\Product\Type\Grouped',
        'sort' => 5,
    ],

    'downloadable' => [
        'key' => 'downloadable',
        'name' => 'product::app.type.downloadable',
        'class' => 'DigitalLabs\Product\Type\Downloadable',
        'sort' => 6,
    ],

    'bundle' => [
        'key' => 'bundle',
        'name' => 'product::app.type.bundle',
        'class' => 'DigitalLabs\Product\Type\Bundle',
        'sort' => 7,
    ],
];
