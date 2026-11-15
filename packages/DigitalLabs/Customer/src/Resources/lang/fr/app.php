<?php

return [
    'validations' => [
        'captcha' => [
            'captcha' => 'Quelque chose s\'est mal passé ! Veuillez réessayer.',
            'required' => 'Veuillez sélectionner CAPTCHA',
        ],

        'vat-id' => [
            'invalid-format' => 'Format de TVA invalide',
            'invalid-gstin-format' => 'Format de GSTIN invalide. Exemple : 27AAPFU0939F1ZV',
        ],
    ],
];
