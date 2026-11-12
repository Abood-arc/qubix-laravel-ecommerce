<?php

return [
    'validations' => [
        'captcha' => [
            'captcha' => 'Bir şeyler yanlış gitti! Lütfen tekrar deneyin.',
            'required' => 'Lütfen CAPTCHA\'yı seçin',
        ],

        'vat-id' => [
            'invalid-format' => 'Geçersiz KDV Formatı',
            'invalid-gstin-format' => 'Geçersiz GSTIN biçimi. Örnek: 27AAPFU0939F1ZV',
        ],
    ],
];
